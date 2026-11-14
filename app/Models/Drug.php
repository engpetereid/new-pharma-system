<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class Drug extends Model
{
    use Auditable;

    protected $table = 'drugs';
    protected $fillable = ['name', 'price', 'production_price', 'line'];

    protected $casts = [
        'price' => 'decimal:2',
        'production_price' => 'decimal:2',
    ];

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * كل الأدوية مع أرصدتها في المخازن، مع فلاتر البحث / الخط / حالة الرصيد
     */
    public static function filteredInventory(\Illuminate\Http\Request $request)
    {
        $query = static::with(['warehouses:id,name,type'])->orderBy('line')->orderBy('name');

        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.addcslashes($request->q, '%_\\').'%');
        }
        if (in_array($request->input('line'), ['1', '2'], true)) {
            $query->where('line', $request->line);
        }

        $drugs = $query->get();

        return match ($request->input('stock')) {
            'available' => $drugs->filter(fn ($d) => $d->total_stock > 0)->values(),
            'empty' => $drugs->filter(fn ($d) => $d->total_stock <= 0)->values(),
            default => $drugs,
        };
    }

    /** إجمالي الرصيد في كل المخازن (يتطلب تحميل علاقة warehouses) */
    public function getTotalStockAttribute(): int
    {
        return (int) $this->warehouses->sum('pivot.quantity');
    }

    /** هامش الربح للوحدة = سعر البيع - سعر الإنتاج */
    public function getUnitProfitAttribute(): ?float
    {
        return $this->production_price === null ? null : (float) $this->price - (float) $this->production_price;
    }

    public function warehouses()
    {
        return $this->belongsToMany(Warehouse::class, 'drug_warehouse')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function deals()
    {
        return $this->belongsToMany(DoctorDeal::class, 'deal_drug');
    }

    public function stockIn($warehouseId)
    {
        $record = $this->warehouses()->where('warehouse_id', $warehouseId)->first();
        return $record ? $record->pivot->quantity : 0;
    }
}
