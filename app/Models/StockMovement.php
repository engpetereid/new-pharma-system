<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'warehouse_id', 'drug_id', 'type', 'direction', 'quantity',
        'balance_before', 'balance_after', 'unit_cost', 'related_warehouse_id',
        'reference_type', 'reference_id', 'reference_label', 'notes', 'user_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    /** أنواع الحركات: النوع => [الاسم، لون الشارة] */
    public const TYPES = [
        'supply' => ['توريد (شراء/إنتاج)', 'success'],
        'withdrawal' => ['صرف / سحب', 'danger'],
        'transfer_in' => ['تحويل وارد من الرئيسي', 'info'],
        'transfer_out' => ['تحويل صادر لمخزن فرعي', 'warning'],
        'return_to_parent_in' => ['مرتجع وارد من مخزن فرعي', 'info'],
        'return_to_parent_out' => ['مرتجع صادر للمخزن الرئيسي', 'warning'],
        'sale' => ['بيع (فاتورة)', 'danger'],
        'invoice_reversal' => ['إلغاء/تعديل فاتورة', 'secondary'],
        'sales_return' => ['مرتجع مبيعات', 'primary'],
        'sales_return_reversal' => ['إلغاء/تعديل مرتجع', 'secondary'],
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function relatedWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'related_warehouse_id');
    }

    public function drug()
    {
        return $this->belongsTo(Drug::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }

    public function getTypeColorAttribute(): string
    {
        return self::TYPES[$this->type][1] ?? 'secondary';
    }
}
