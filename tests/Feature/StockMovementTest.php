<?php

namespace Tests\Feature;

use App\Models\Drug;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Warehouse $main;

    protected Warehouse $sub;

    protected Drug $drug;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->main = Warehouse::create(['name' => 'رئيسي', 'type' => 'main']);
        $this->sub = Warehouse::create(['name' => 'فرعي', 'type' => 'sub', 'parent_id' => $this->main->id]);
        $this->drug = Drug::create(['name' => 'باراسيتامول', 'price' => 100, 'production_price' => 40, 'line' => 1]);
    }

    public function test_main_supply_requires_production_price()
    {
        $this->actingAs($this->admin)
            ->post(route('admin.warehouses.stock.store', $this->main), [
                'items' => [['drug_id' => $this->drug->id, 'quantity' => 10]],
            ])
            ->assertSessionHasErrors('items.0.production_price');

        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_supply_transfer_and_return_are_logged_with_balances()
    {
        $this->actingAs($this->admin);

        $this->post(route('admin.warehouses.stock.store', $this->main), [
            'items' => [['drug_id' => $this->drug->id, 'quantity' => 50, 'production_price' => 45]],
        ])->assertRedirect();

        $this->post(route('admin.warehouses.stock.store', $this->sub), [
            'items' => [['drug_id' => $this->drug->id, 'quantity' => 20]],
        ])->assertRedirect();

        $this->post(route('admin.warehouses.stock.return.process', $this->sub), [
            'drug_id' => $this->drug->id, 'quantity' => 5,
        ])->assertRedirect();

        $rows = StockMovement::orderBy('id')->get(['warehouse_id', 'type', 'direction', 'quantity', 'balance_before', 'balance_after', 'unit_cost'])
            ->map(fn ($m) => [$m->warehouse_id, $m->type, $m->direction, $m->quantity, $m->balance_before, $m->balance_after])
            ->all();

        $this->assertSame([
            [$this->main->id, 'supply', 'in', 50, 0, 50],
            [$this->main->id, 'transfer_out', 'out', 20, 50, 30],
            [$this->sub->id, 'transfer_in', 'in', 20, 0, 20],
            [$this->sub->id, 'return_to_parent_out', 'out', 5, 20, 15],
            [$this->main->id, 'return_to_parent_in', 'in', 5, 30, 35],
        ], $rows);

        $this->assertEquals(45, (float) StockMovement::where('type', 'supply')->value('unit_cost'));
        $this->assertEquals(45, (float) $this->drug->fresh()->production_price);
    }

    public function test_log_page_filters_and_exports()
    {
        $this->actingAs($this->admin)->post(route('admin.warehouses.stock.store', $this->main), [
            'items' => [['drug_id' => $this->drug->id, 'quantity' => 7, 'production_price' => 40]],
        ]);

        $this->get(route('admin.stock-movements.index', ['warehouse_id' => [$this->main->id], 'direction' => 'in']))
            ->assertOk()
            ->assertSee($this->drug->name);

        $this->get(route('admin.stock-movements.index', ['direction' => 'out']))
            ->assertOk()
            ->assertSee('لا توجد حركات مطابقة');

        $response = $this->get(route('admin.stock-movements.index', ['export' => 'excel']));
        $response->assertOk();
        $this->assertStringContainsString($this->drug->name, $response->streamedContent());
    }

    public function test_drugs_index_shows_stock_and_prices()
    {
        $this->main->drugs()->attach($this->drug->id, ['quantity' => 12]);

        $this->actingAs($this->admin)->get(route('admin.drugs.index'))
            ->assertOk()
            ->assertSee($this->drug->name)
            ->assertSee('60.00'); // ربح الوحدة = 100 - 40
    }
}
