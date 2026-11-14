<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Drug;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoiceReturn;
use App\Models\Pharmacist;
use App\Models\Province;
use App\Models\Representative;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Warehouse $warehouse;

    protected Zone $zone;

    protected Pharmacist $pharmacist;

    protected Drug $drug;

    protected Representative $rep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $province = Province::create(['name' => 'القاهرة']);
        $center = Center::create(['name' => 'مدينة نصر', 'province_id' => $province->id]);

        $this->rep = Representative::create(['name' => 'مندوب المبيعات', 'phone' => '01011111111']);

        $this->warehouse = Warehouse::create([
            'name' => 'المخزن الرئيسي',
            'type' => 'main',
        ]);

        $this->zone = Zone::create([
            'name' => 'منطقة شرق',
            'province_id' => $province->id,
            'line' => 1,
            'sales_representative_id' => $this->rep->id,
            'medical_representative_id' => $this->rep->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
        $this->zone->centers()->attach($center->id);

        $this->pharmacist = Pharmacist::create([
            'name' => 'صيدلية النور والشفاء',
            'phone' => '01222222222',
            'address' => 'شارع النزهة',
            'center_id' => $center->id,
        ]);

        $this->drug = Drug::create([
            'name' => 'كونجستال',
            'price' => 50.00,
            'line' => 1,
        ]);

        $this->warehouse->drugs()->attach($this->drug->id, ['quantity' => 100]);
    }

    public function test_cannot_edit_or_update_invoice_with_active_returns()
    {
        $this->actingAs($this->admin);

        $invoice = Invoice::create([
            'serial_number' => 'INV-TEST-001',
            'invoice_date' => now()->format('Y-m-d'),
            'line' => 1,
            'pharmacist_id' => $this->pharmacist->id,
            'representative_id' => $this->rep->id,
            'total_amount' => 500,
            'total_discount' => 50,
            'final_total' => 450,
            'paid_amount' => 0,
            'remaining_amount' => 450,
            'status' => 2,
        ]);

        $detail = InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'drug_id' => $this->drug->id,
            'quantity' => 10,
            'returned_quantity' => 2,
            'unit_price' => 50,
            'pharmacist_discount_percentage' => 10,
            'row_total' => 450,
        ]);

        // Create an active return for this invoice
        InvoiceReturn::create([
            'return_number' => 'RET-TEST-001',
            'invoice_id' => $invoice->id,
            'return_date' => now()->format('Y-m-d'),
            'total_return_public_price' => 100,
            'total_return_discount' => 10,
            'total_return_amount' => 90,
            'refund_amount' => 0,
            'created_by' => $this->admin->id,
        ]);

        $this->assertTrue($invoice->hasReturns());

        // Attempt to access edit page
        $editResponse = $this->get(route('admin.invoices.edit', $invoice));
        $editResponse->assertRedirect(route('admin.invoices.index'));
        $editResponse->assertSessionHas('error');

        // Attempt to update invoice
        $updateResponse = $this->put(route('admin.invoices.update', $invoice), [
            'serial_number' => 'INV-TEST-001',
            'invoice_date' => now()->format('Y-m-d'),
            'line' => 1,
            'pharmacist_id' => $this->pharmacist->id,
            'status' => 2,
            'items' => [
                ['drug_id' => $this->drug->id, 'quantity' => 5, 'pharmacist_discount_percentage' => 10],
            ],
        ]);

        $updateResponse->assertRedirect(route('admin.invoices.index'));
        $updateResponse->assertSessionHas('error');
    }

    public function test_cannot_delete_invoice_with_active_returns()
    {
        $this->actingAs($this->admin);

        $invoice = Invoice::create([
            'serial_number' => 'INV-TEST-002',
            'invoice_date' => now()->format('Y-m-d'),
            'line' => 1,
            'pharmacist_id' => $this->pharmacist->id,
            'representative_id' => $this->rep->id,
            'total_amount' => 500,
            'total_discount' => 50,
            'final_total' => 450,
            'paid_amount' => 0,
            'remaining_amount' => 450,
            'status' => 2,
        ]);

        InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'drug_id' => $this->drug->id,
            'quantity' => 10,
            'returned_quantity' => 2,
            'unit_price' => 50,
            'pharmacist_discount_percentage' => 10,
            'row_total' => 450,
        ]);

        InvoiceReturn::create([
            'return_number' => 'RET-TEST-002',
            'invoice_id' => $invoice->id,
            'return_date' => now()->format('Y-m-d'),
            'total_return_public_price' => 100,
            'total_return_discount' => 10,
            'total_return_amount' => 90,
            'refund_amount' => 0,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->delete(route('admin.invoices.destroy', $invoice));
        $response->assertRedirect(route('admin.invoices.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }

    public function test_deleting_regular_invoice_adds_back_stock_safely()
    {
        $this->actingAs($this->admin);

        // Initial warehouse stock is 100
        $invoice = Invoice::create([
            'serial_number' => 'INV-TEST-003',
            'invoice_date' => now()->format('Y-m-d'),
            'line' => 1,
            'pharmacist_id' => $this->pharmacist->id,
            'representative_id' => $this->rep->id,
            'total_amount' => 500,
            'total_discount' => 0,
            'final_total' => 500,
            'paid_amount' => 0,
            'remaining_amount' => 500,
            'status' => 2,
        ]);

        InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'drug_id' => $this->drug->id,
            'quantity' => 20,
            'returned_quantity' => 0,
            'unit_price' => 25,
            'pharmacist_discount_percentage' => 0,
            'row_total' => 500,
        ]);

        $response = $this->delete(route('admin.invoices.destroy', $invoice));
        $response->assertRedirect(route('admin.invoices.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);

        // Stock should have 20 added back: 100 + 20 = 120
        $stockRecord = $this->warehouse->drugs()->where('drug_id', $this->drug->id)->first();
        $this->assertEquals(120, (int) $stockRecord->pivot->quantity);
    }

    public function test_dashboard_discount_is_not_multiplied_by_invoice_details_count()
    {
        $this->actingAs($this->admin);

        $drug2 = Drug::create([
            'name' => 'أوجمنتين',
            'price' => 80.00,
            'line' => 1,
        ]);

        // Create an invoice with 2 items and a total discount of 100 EGP (out of 200 EGP total_amount = 50% discount)
        $invoice = Invoice::create([
            'serial_number' => 'INV-DASH-001',
            'invoice_date' => now()->format('Y-m-d'),
            'line' => 1,
            'pharmacist_id' => $this->pharmacist->id,
            'representative_id' => $this->rep->id,
            'total_amount' => 200,
            'total_discount' => 100, // 50% discount
            'final_total' => 100,
            'paid_amount' => 100,
            'remaining_amount' => 0,
            'status' => 1,
        ]);

        InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'drug_id' => $this->drug->id,
            'quantity' => 2,
            'unit_price' => 50,
            'pharmacist_discount_percentage' => 50,
            'row_total' => 50,
        ]);

        InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'drug_id' => $drug2->id,
            'quantity' => 1,
            'unit_price' => 100,
            'pharmacist_discount_percentage' => 50,
            'row_total' => 50,
        ]);

        $response = $this->get(route('admin.dashboard'));
        $response->assertStatus(200);

        // Verify riskyZones in view data
        $riskyZones = $response->viewData('riskyZones');
        $zoneData = $riskyZones->firstWhere('id', $this->zone->id);

        $this->assertNotNull($zoneData);
        // Public price must be 200, and discount must be exactly 100 (not 200!)
        // Risk ratio = (discount + expenses) / public_price * 100 = 100 / 200 * 100 = 50.0%
        $this->assertEquals(50.0, $zoneData['ratio']);
        $this->assertEquals(100.0, $zoneData['risk_amount']);
    }
}
