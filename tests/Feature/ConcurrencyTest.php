<?php

namespace Tests\Feature;

use App\Models\Drug;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected Warehouse $warehouse;

    protected Drug $drug;

    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = app(InventoryService::class);

        $this->warehouse = Warehouse::create([
            'name' => 'مخزن الأدوية المركزي',
            'type' => 'main',
        ]);

        $this->drug = Drug::create([
            'name' => 'أمبيسلين 500',
            'price' => 35.00,
            'line' => 1,
        ]);

        $this->warehouse->drugs()->attach($this->drug->id, ['quantity' => 50]);
    }

    public function test_inventory_service_deducts_stock_safely_within_transaction()
    {
        DB::beginTransaction();
        $newQty = $this->inventoryService->deductStock($this->warehouse, $this->drug->id, 20);
        DB::commit();

        $this->assertEquals(30, $newQty);

        $stock = DB::table('drug_warehouse')
            ->where('warehouse_id', $this->warehouse->id)
            ->where('drug_id', $this->drug->id)
            ->first();

        $this->assertEquals(30, (int) $stock->quantity);
    }

    public function test_inventory_service_prevents_stock_overdraw()
    {
        $this->expectException(ValidationException::class);

        // Current stock is 50, attempt to deduct 60
        $this->inventoryService->deductStock($this->warehouse, $this->drug->id, 60);
    }

    public function test_inventory_service_adds_stock_accurately()
    {
        DB::beginTransaction();
        $newQty = $this->inventoryService->addStock($this->warehouse, $this->drug->id, 25);
        DB::commit();

        $this->assertEquals(75, $newQty);

        $current = $this->inventoryService->getAvailableQuantity($this->warehouse, $this->drug->id);
        $this->assertEquals(75, $current);
    }

    public function test_inventory_service_attaches_new_drug_if_not_present()
    {
        $newDrug = Drug::create([
            'name' => 'سيفوتاكس 1جم',
            'price' => 70.00,
            'line' => 1,
        ]);

        $newQty = $this->inventoryService->addStock($this->warehouse, $newDrug->id, 15);
        $this->assertEquals(15, $newQty);

        $stock = DB::table('drug_warehouse')
            ->where('warehouse_id', $this->warehouse->id)
            ->where('drug_id', $newDrug->id)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals(15, (int) $stock->quantity);
    }

    public function test_zero_or_negative_quantities_do_not_alter_stock()
    {
        $this->assertEquals(0, $this->inventoryService->deductStock($this->warehouse, $this->drug->id, 0));
        $this->assertEquals(0, $this->inventoryService->deductStock($this->warehouse, $this->drug->id, -5));
        $this->assertEquals(0, $this->inventoryService->addStock($this->warehouse, $this->drug->id, 0));
        $this->assertEquals(0, $this->inventoryService->addStock($this->warehouse, $this->drug->id, -10));

        $this->assertEquals(50, $this->inventoryService->getAvailableQuantity($this->warehouse, $this->drug->id));
    }
}
