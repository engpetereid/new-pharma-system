<?php

namespace App\Services;

use App\Models\Drug;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * نقطة المرور الوحيدة لتعديل أرصدة المخازن.
 * كل تعديل يُسجَّل في stock_movements (الرصيد قبل/بعد، الكمية، المرجع، المستخدم).
 *
 * $context المدعوم:
 *  - reference        Model   المستند المرتبط (فاتورة، مرتجع...)
 *  - reference_label  string  نص المرجع المعروض (رقم الفاتورة...)
 *  - related_warehouse Warehouse|int  المخزن الآخر في عمليات التحويل
 *  - unit_cost        float   سعر الإنتاج للوحدة
 *  - notes            string
 */
class InventoryService
{
    /**
     * @throws ValidationException
     */
    public function deductStock(Warehouse $warehouse, int $drugId, int $quantity, string $type = 'sale', array $context = []): int
    {
        if ($quantity <= 0) {
            return 0;
        }

        // قفل السجل لمنع البيع الزائد عند التزامن
        $stock = DB::table('drug_warehouse')
            ->where('warehouse_id', $warehouse->id)
            ->where('drug_id', $drugId)
            ->lockForUpdate()
            ->first();

        $currentQty = $stock ? (int) $stock->quantity : 0;

        if ($currentQty < $quantity) {
            $drug = Drug::find($drugId);
            $drugName = $drug ? $drug->name : "ID: {$drugId}";
            throw ValidationException::withMessages([
                'items' => "الكمية غير متوفرة في مخزن ({$warehouse->name}) للصنف ({$drugName}). الرصيد المتاح: {$currentQty}، المطلوب: {$quantity}.",
            ]);
        }

        $newQty = $currentQty - $quantity;

        DB::table('drug_warehouse')
            ->where('warehouse_id', $warehouse->id)
            ->where('drug_id', $drugId)
            ->update(['quantity' => $newQty, 'updated_at' => now()]);

        $this->record($warehouse, $drugId, $type, 'out', $quantity, $currentQty, $newQty, $context);

        return $newQty;
    }

    public function addStock(Warehouse $warehouse, int $drugId, int $quantity, string $type = 'supply', array $context = []): int
    {
        if ($quantity <= 0) {
            return 0;
        }

        $stock = DB::table('drug_warehouse')
            ->where('warehouse_id', $warehouse->id)
            ->where('drug_id', $drugId)
            ->lockForUpdate()
            ->first();

        $currentQty = $stock ? (int) $stock->quantity : 0;
        $newQty = $currentQty + $quantity;

        if ($stock) {
            DB::table('drug_warehouse')
                ->where('warehouse_id', $warehouse->id)
                ->where('drug_id', $drugId)
                ->update(['quantity' => $newQty, 'updated_at' => now()]);
        } else {
            DB::table('drug_warehouse')->insert([
                'warehouse_id' => $warehouse->id,
                'drug_id' => $drugId,
                'quantity' => $newQty,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->record($warehouse, $drugId, $type, 'in', $quantity, $currentQty, $newQty, $context);

        return $newQty;
    }

    public function getAvailableQuantity(Warehouse $warehouse, int $drugId): int
    {
        $stock = DB::table('drug_warehouse')
            ->where('warehouse_id', $warehouse->id)
            ->where('drug_id', $drugId)
            ->first();

        return $stock ? (int) $stock->quantity : 0;
    }

    private function record(Warehouse $warehouse, int $drugId, string $type, string $direction, int $quantity, int $before, int $after, array $context): void
    {
        $reference = $context['reference'] ?? null;
        $related = $context['related_warehouse'] ?? null;

        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'drug_id' => $drugId,
            'type' => $type,
            'direction' => $direction,
            'quantity' => $quantity,
            'balance_before' => $before,
            'balance_after' => $after,
            'unit_cost' => $context['unit_cost'] ?? null,
            'related_warehouse_id' => $related instanceof Warehouse ? $related->id : $related,
            'reference_type' => $reference instanceof Model ? $reference->getMorphClass() : null,
            'reference_id' => $reference instanceof Model ? $reference->getKey() : null,
            'reference_label' => $context['reference_label'] ?? null,
            'notes' => $context['notes'] ?? null,
            'user_id' => auth()->id(),
        ]);
    }
}
