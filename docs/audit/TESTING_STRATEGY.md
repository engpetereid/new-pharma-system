# Automated Testing & Quality Assurance Strategy
**Application:** Pharmaceutical Company (SAAS)  
**Test Framework:** PestPHP / PHPUnit 11  
**Date:** October 2026  

---

## 1. Testing Philosophy & Baseline

Testing an ERP system requires strict verification of financial invariants, concurrency boundaries, stock integrity, and authorization rules. Passing tests alone do not guarantee correctness unless the test assertions verify realistic domain outcomes.

### Current Baseline
- **Existing Test Files**: 17 files in `tests/`
- **Initial Status**: 48 passed, 6 failed (Auth & Example tests).
- **Core Domain Coverage**: Existing feature tests cover partial invoice returns, viewer access, and model audit logging.
- **Coverage Deficiencies**:
  - Zero tests for invoice editing and invoice deletion stock effects.
  - Zero tests for concurrent stock deduction under race conditions.
  - Zero tests for doctor deal calculations across percentage vs. fixed target deals.
  - Zero tests for CSV formula injection.
  - Zero tests verifying that deleting an invoice return does not corrupt financial balances.

---

## 2. Test Architecture & Directory Structure

```
tests/
├── Feature/
│   ├── AuditLogTest.php                     [Existing - Verified]
│   ├── Auth/                                [Existing - Needs baseline fixes]
│   ├── ConcurrencyTest.php                  [NEW - Concurrency & Race conditions]
│   ├── DoctorCommissionTest.php             [NEW - Unified deal mathematics]
│   ├── InvoiceIntegrityTest.php             [NEW - Invoice edit/delete & stock rules]
│   ├── InvoiceReturnTest.php                [Existing - Verified]
│   ├── SecurityHardeningTest.php            [NEW - CSV sanitization, RCE & Role safety]
│   └── ViewerAccessTest.php                 [Existing - Verified]
└── Unit/
    └── DoctorCommissionCalculationTest.php  [NEW - Pure financial unit tests]
```

---

## 3. Test Suites to Implement

### 3.1 Invoice & Inventory Integrity (`InvoiceIntegrityTest.php`)
- **TEST-01**: Invoices with active returns cannot be deleted.
- **TEST-02**: Deleting an un-returned invoice restores exact quantities to warehouse.
- **TEST-03**: Invoice detail updates prevent phantom stock duplication.
- **TEST-04**: Warehouse stock deductions correctly apply row-level locking.

### 3.2 Doctor Deal Consistency (`DoctorCommissionTest.php`)
- **TEST-05**: Fixed-target deal computes correct commission when achieved amount reaches target.
- **TEST-06**: Percentage-based deal computes correct commission proportional to achieved sales.
- **TEST-07**: Settling a deal from either Deals controller or Report controller creates the corresponding `ZoneExpense`.
- **TEST-08**: Inactive or archived deals do not accrue new invoice contributions.

### 3.3 Security & Role Safety (`SecurityHardeningTest.php`)
- **TEST-09**: CSV exports sanitize formula triggers (`=`, `+`, `-`, `@`).
- **TEST-10**: Administrators cannot demote themselves to non-admin roles.
- **TEST-11**: Public registration route is disabled or restricted.
- **TEST-12**: Sample PHP files in `public/assets/` do not exist.
- **TEST-13**: Data exports generate audit log entries.
