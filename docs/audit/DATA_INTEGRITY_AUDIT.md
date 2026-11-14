# Database & Data Integrity Audit Report
**Application:** Pharmaceutical Company (SAAS)  
**Database Engine:** MySQL 8.0+ / InnoDB  
**Date:** October 2026  
**Auditor:** Principal Database Architect & Financial Systems Specialist  

---

## 1. Executive Summary

A thorough review of the database schema, relational integrity constraints, financial calculation routines, concurrency mechanics, and audit logging guarantees was performed. Critical data corruption risks were identified in the invoice editing lifecycle, return deletion process, inventory deductions, and dashboard risk aggregation queries.

---

## 2. Invariants & Calculation Integrity Findings

### 2.1 The Invoice Edit & Delete Cascade Trap (FIN-01 / DATA-01)
- **Entity**: `Invoice`, `InvoiceDetail`, `InvoiceReturn`, `InvoiceReturnItem`
- **Violated Invariant**:
  $$\text{Invoice Return Items must always reference valid Invoice Details}$$
  $$\text{Warehouse Stock Restored on Cancellation} = \text{Original Quantity} - \text{Previously Returned Quantity}$$
- **Root Cause**:
  In `InvoiceController@update`:
  ```php
  $invoice->details()->delete();
  ```
  In `2026_10_12_000001_create_invoice_returns_tables.php`:
  ```php
  $table->foreignId('invoice_detail_id')->constrained('invoice_details')->cascadeOnDelete();
  ```
  Calling `$invoice->details()->delete()` executes a database cascade that silently **erases all `invoice_return_items`** for any prior return against this invoice!
  Furthermore, `InvoiceController` adds the full detail quantity back to the warehouse:
  ```php
  $this->updateWarehouseStock($oldWarehouse, $detail->drug_id, $detail->quantity, 'add');
  ```
  If 4 out of 10 items were already returned to the warehouse, this adds 10 more items, inflating the warehouse by 4 phantom items!
- **Impact**: Permanent loss of return records, broken financial vouchers, and stock duplication.
- **Solution**:
  1. Enforce business rule: Invoices with active returns cannot be deleted or restructured via direct edit.
  2. In invoice `destroy()`, only add back unreturned quantities: `quantity - returned_quantity`.

---

### 2.2 Return Deletion Payment Falsification (FIN-02)
- **Entity**: `InvoiceReturn`, `InvoicePayment`, `Invoice`
- **Violated Invariant**:
  $$\sum \text{Payments} - \sum \text{Refunds} = \text{Net Cash Collected on Invoice}$$
- **Root Cause**:
  When a sales return is created on a paid invoice in `InvoiceReturnController@store`, it reduces or deletes `InvoicePayment` rows to prevent negative balances:
  ```php
  if ($payment->amount <= $toDeduct) {
      $payment->delete();
  } else {
      $payment->update(['amount' => $newAmount]);
  }
  ```
  When the return is subsequently deleted in `InvoiceReturnController@destroy`, the invoice amounts are recalculated, but the deleted/reduced `InvoicePayment` rows are **never restored**:
  ```php
  $finalPaidAmount = (float) $invoice->payments()->sum('amount');
  $newRemaining = round(max(0, $newInvoiceFinal - $finalPaidAmount), 2);
  ```
- **Impact**: The customer is suddenly shown as owing debt for money they already paid. Historical daily collection reports are permanently altered because historical payment rows were deleted.
- **Solution**:
  1. Financial returns and refunds must be immutable documents. Replace hard deletion with a formal "Return Cancellation" voucher that reverses the stock and posts an offsetting adjustment.
  2. Implement an explicit `InvoiceRefund` record instead of mutating historical `InvoicePayment` records.

---

### 2.3 Dashboard Discount Multiplication Bug (FIN-03)
- **Entity**: `DashboardController`, `ViewerDashboardController`, `Zone`
- **Violated Invariant**:
  $$\text{Zone Discount} = \sum_{\text{invoices}} \text{invoice.total\_discount}$$
- **Root Cause**:
  In `DashboardController@index` (line 41-57):
  ```php
  $invoiceTotals = DB::table('zones')
      ->join('center_zone', ...)
      ->join('pharmacists', ...)
      ->join('invoices', ...)
      ->join('invoice_details', 'invoice_details.invoice_id', '=', 'invoices.id')
      ->groupBy('zones.id')
      ->select(
          'zones.id as zone_id',
          DB::raw('SUM(invoice_details.unit_price * invoice_details.quantity) as public_price_total'),
          DB::raw('SUM(invoices.total_discount) as total_discount')
      )->get();
  ```
  Because `invoices` is joined to `invoice_details`, each invoice is duplicated by the number of line items it contains.
  If an invoice has 5 items and a 1,000 EGP discount, `SUM(invoices.total_discount)` produces **5,000 EGP**!
- **Impact**: Any zone with multi-item invoices has its discounts multiplied by N, falsely inflating risk ratios and incorrectly classifying safe zones as dangerous (>40%).
- **Solution**: Aggregate invoice discounts at the invoice level without joining `invoice_details`, or calculate total discount as `SUM(unit_price * quantity * pharmacist_discount_percentage / 100)` at the detail level.

---

### 2.4 Doctor Deal Triplicate Divergence (FIN-04)
- **Entity**: `DoctorDeal`, `Doctor`, `DoctorBalanceController`, `ReportController`
- **Violated Invariant**:
  $$\text{Doctor Deal Due} = \text{Commission Earned} - \text{Commission Paid}$$
- **Discrepancy**:
  - `DoctorDealController`: Hybrid calculation:
    `CASE WHEN target_amount > 0 THEN commission_amount ELSE (achieved_amount * commission_percentage / 100) END`
  - `DoctorBalanceController`: Percentage only:
    `SUM(achieved_amount * (commission_percentage / 100))` (Calculates 0 for fixed deals!)
  - `ReportController@showDoctor`: Fixed only:
    `sum('commission_amount')` (Calculates 0 for percentage deals!)
  - `ReportController@payDoctorCommission`: Updates `paid_amount` without recording a `ZoneExpense`.
- **Solution**: Consolidate deal mathematics into a shared, testable `DoctorCommissionService`.

---

## 3. Concurrency & Race Condition Analysis

### 3.1 Warehouse Stock Overselling
- **Risk**: High
- In `InvoiceController@processInvoiceItems`, `updateWarehouseStock`:
  ```php
  $stockRecord = $warehouse->drugs()->where('drug_id', $drugId)->first();
  $currentQty = $stockRecord ? $stockRecord->pivot->quantity : 0;
  if ($currentQty < $quantity) throw ...;
  $warehouse->drugs()->updateExistingPivot($drugId, ['quantity' => $currentQty - $quantity]);
  ```
  Neither the warehouse nor the pivot row is locked (`lockForUpdate`). Two concurrent requests for the last 5 units of a drug will both read `currentQty = 5`, both approve the sale, and deduct 5, resulting in 10 units sold and stock corrupted.
- **Fix**: Apply `lockForUpdate()` within the transaction:
  ```php
  $stock = DB::table('warehouse_drug')
      ->where('warehouse_id', $warehouse->id)
      ->where('drug_id', $drugId)
      ->lockForUpdate()
      ->first();
  ```

### 3.2 Return Voucher Numbering Collisions
- **Risk**: High under concurrent usage
- In `InvoiceReturn::generateReturnNumber()`:
  ```php
  while (static::where('return_number', $candidate)->exists()) {
      $nextSeq++;
      $candidate = "RET-{$prefix}-{$nextSeq}";
  }
  ```
  Two concurrent returns for the same invoice check existence simultaneously, both find `RET-INV-001-1` absent, and both attempt to insert `RET-INV-001-1`. The second request crashes with a MySQL 1062 Duplicate entry error.
- **Fix**: Implement an atomic sequence table or row-lock on the parent invoice during return creation.

---

## 4. Foreign Key Cascade Hazards (DATA-01)

In `2025_11_19_145529_create_invoices_table.php`:
```php
$table->foreignId('representative_id')->constrained()->onDelete('cascade');
$table->foreignId('pharmacist_id')->constrained()->onDelete('cascade');
```
If an operator deletes a Pharmacist (customer) or Representative (sales rep) who has retired or left the business, **all historical invoices, payments, and tax records are permanently deleted by MySQL**.
- **Fix**: Alter constraints to `restrictOnDelete()` and implement soft-deletes or an `is_active` status flag on master data.
