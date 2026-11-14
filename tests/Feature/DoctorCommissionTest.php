<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Doctor;
use App\Models\DoctorDeal;
use App\Models\Drug;
use App\Models\Pharmacist;
use App\Models\Province;
use App\Models\Representative;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Models\ZoneExpense;
use App\Services\DoctorCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorCommissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Zone $zone;

    protected Center $center;

    protected Doctor $doctor;

    protected Pharmacist $pharmacist;

    protected Drug $drug;

    protected DoctorCommissionService $commissionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->commissionService = app(DoctorCommissionService::class);

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $province = Province::create(['name' => 'الجيزة']);
        $this->center = Center::create(['name' => 'الدقي', 'province_id' => $province->id]);

        $rep = Representative::create(['name' => 'مندوب الجيزة', 'phone' => '01111111111']);
        $warehouse = Warehouse::create(['name' => 'مخزن الجيزة', 'type' => 'sub']);

        $this->zone = Zone::create([
            'name' => 'منطقة الدقي',
            'province_id' => $province->id,
            'line' => 1,
            'sales_representative_id' => $rep->id,
            'medical_representative_id' => $rep->id,
            'warehouse_id' => $warehouse->id,
        ]);
        $this->zone->centers()->attach($this->center->id);

        $this->doctor = Doctor::create([
            'name' => 'د. حسام غالي',
            'speciality' => 'باطنة',
            'phone' => '01055555555',
            'address' => 'شارع مصدق - الدقي',
            'center_id' => $this->center->id,
        ]);

        $this->pharmacist = Pharmacist::create([
            'name' => 'صيدلية النصر',
            'phone' => '01233333333',
            'address' => 'ميدان التحرير',
            'center_id' => $this->center->id,
        ]);

        $this->drug = Drug::create([
            'name' => 'ميجافين',
            'price' => 40.00,
            'line' => 1,
        ]);
    }

    public function test_target_deal_commission_calculation()
    {
        // 1. Target deal not achieved
        $dealNotAchieved = DoctorDeal::create([
            'doctor_id' => $this->doctor->id,
            'start_date' => '2026-01-01',
            'target_amount' => 10000,
            'achieved_amount' => 8000,
            'commission_amount' => 1000,
            'commission_percentage' => 10,
            'status' => 2,
            'is_paid' => false,
            'is_active' => true,
        ]);

        $this->assertEquals(1000, $this->commissionService->getDealCommission($dealNotAchieved));

        // 2. Target deal achieved
        $dealAchieved = DoctorDeal::create([
            'doctor_id' => $this->doctor->id,
            'start_date' => '2026-01-01',
            'target_amount' => 10000,
            'achieved_amount' => 12000,
            'commission_amount' => 1000,
            'commission_percentage' => 10,
            'status' => 2,
            'is_paid' => false,
            'is_active' => true,
        ]);

        $this->assertEquals(1000, $this->commissionService->getDealCommission($dealAchieved));
    }

    public function test_open_percentage_deal_commission_calculation()
    {
        // Open deal with target = 0 and 15% commission
        $openDeal = DoctorDeal::create([
            'doctor_id' => $this->doctor->id,
            'start_date' => '2026-01-01',
            'target_amount' => 0,
            'achieved_amount' => 20000,
            'commission_amount' => 0,
            'commission_percentage' => 15,
            'status' => 2,
            'is_paid' => false,
            'is_active' => true,
        ]);

        // 15% of 20000 = 3000
        $this->assertEquals(3000.0, $this->commissionService->getDealCommission($openDeal));
    }

    public function test_marking_deal_as_paid_settles_and_creates_zone_expense()
    {
        $this->actingAs($this->admin);

        $deal = DoctorDeal::create([
            'doctor_id' => $this->doctor->id,
            'start_date' => '2026-01-01',
            'target_amount' => 5000,
            'achieved_amount' => 6000,
            'commission_amount' => 800,
            'commission_percentage' => 16,
            'status' => 2,
            'is_paid' => false,
            'paid_amount' => 300, // partially paid in advance
            'is_active' => true,
        ]);

        $deal->drugs()->attach($this->drug->id);

        $initialExpenseCount = ZoneExpense::where('zone_id', $this->zone->id)->count();

        $response = $this->post(route('admin.deals.pay', $deal));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $deal->refresh();
        $this->assertTrue((bool) $deal->is_paid);
        $this->assertEquals(1, $deal->status);
        $this->assertEquals(800, $deal->paid_amount);

        // Zone expense must be created for the remaining difference (800 - 300 = 500)
        $newExpenseCount = ZoneExpense::where('zone_id', $this->zone->id)->count();
        $this->assertEquals($initialExpenseCount + 1, $newExpenseCount);

        $expense = ZoneExpense::where('zone_id', $this->zone->id)->latest()->first();
        $this->assertEquals(500, (float) $expense->amount);
        $this->assertStringContainsString($this->doctor->name, $expense->description);
    }
}
