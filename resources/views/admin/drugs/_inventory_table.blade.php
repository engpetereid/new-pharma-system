{{-- جدول كل الأدوية مع الأرصدة والأسعار. المتغيرات: $drugs, $prefix ('admin'|'viewer') --}}
@php
    $canManage = $prefix === 'admin';
    $grandStock = 0; $grandCostValue = 0; $grandSaleValue = 0;
@endphp

<form action="{{ route($prefix.'.drugs.index') }}" method="GET" class="mb-2">
    <div class="row align-items-end">
        <div class="col-md-4 mb-1">
            <label class="text-muted font-small-3">بحث باسم الدواء</label>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="اسم الدواء...">
        </div>
        <div class="col-md-2 mb-1">
            <label class="text-muted font-small-3">Line</label>
            <select name="line" class="form-control">
                <option value="">الكل</option>
                <option value="1" {{ request('line') === '1' ? 'selected' : '' }}>Line 1</option>
                <option value="2" {{ request('line') === '2' ? 'selected' : '' }}>Line 2</option>
            </select>
        </div>
        <div class="col-md-3 mb-1">
            <label class="text-muted font-small-3">حالة الرصيد</label>
            <select name="stock" class="form-control">
                <option value="">الكل</option>
                <option value="available" {{ request('stock') === 'available' ? 'selected' : '' }}>متوفر</option>
                <option value="empty" {{ request('stock') === 'empty' ? 'selected' : '' }}>نفد (رصيد صفر)</option>
            </select>
        </div>
        <div class="col-md-3 mb-1 d-flex">
            <button type="submit" class="btn btn-primary flex-grow-1 mr-1"><i class="ft-filter"></i> تصفية</button>
            <a href="{{ route($prefix.'.drugs.index') }}" class="btn btn-outline-secondary"><i class="ft-x"></i></a>
        </div>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-striped table-hover w-100">
        <thead class="bg-primary white">
        <tr>
            <th>#</th>
            <th>اسم الدواء</th>
            <th>Line</th>
            <th>سعر البيع</th>
            <th>سعر الإنتاج</th>
            <th>ربح الوحدة</th>
            <th class="text-center">رصيد المخازن الرئيسية</th>
            <th class="text-center">رصيد المخازن الفرعية</th>
            <th class="text-center">إجمالي الرصيد</th>
            <th>قيمة المخزون (تكلفة)</th>
            <th>الإجراءات</th>
        </tr>
        </thead>
        <tbody>
        @forelse($drugs as $index => $drug)
            @php
                $mainStock = (int) $drug->warehouses->where('type', 'main')->sum('pivot.quantity');
                $subStock = (int) $drug->warehouses->where('type', 'sub')->sum('pivot.quantity');
                $total = $mainStock + $subStock;
                $costValue = $drug->production_price !== null ? $total * (float) $drug->production_price : null;
                $grandStock += $total;
                $grandCostValue += $costValue ?? 0;
                $grandSaleValue += $total * (float) $drug->price;
                $profit = $drug->unit_profit;
            @endphp
            <tr>
                <td>{{ $index + 1 }}</td>
                @php
                    $stocked = $drug->warehouses->where('pivot.quantity', '>', 0);
                    $breakdown = $stocked->map(fn ($w) => $w->name.': '.number_format($w->pivot->quantity))->join("\n");
                @endphp
                <td class="text-bold-600" style="min-width: 180px;">
                    {{ $drug->name }}
                    @if($stocked->isNotEmpty())
                        <small class="d-block text-muted">في {{ $stocked->count() }} مخزن</small>
                    @endif
                </td>
                <td>
                    <span class="badge {{ $drug->line == 1 ? 'badge-info' : 'badge-warning' }}">Line {{ $drug->line }}</span>
                </td>
                <td class="text-success font-weight-bold">{{ number_format($drug->price, 2) }}</td>
                <td>
                    @if($drug->production_price !== null)
                        {{ number_format($drug->production_price, 2) }}
                    @else
                        <span class="badge badge-light text-danger">غير محدد</span>
                    @endif
                </td>
                <td>
                    @if($profit !== null)
                        <span class="{{ $profit >= 0 ? 'text-success' : 'text-danger' }} font-weight-bold">{{ number_format($profit, 2) }}</span>
                        @if((float) $drug->price > 0)
                            <small class="text-muted d-block">{{ number_format($profit / (float) $drug->price * 100, 1) }}%</small>
                        @endif
                    @else
                        -
                    @endif
                </td>
                <td class="text-center">{{ number_format($mainStock) }}</td>
                <td class="text-center">{{ number_format($subStock) }}</td>
                <td class="text-center">
                    <span class="badge {{ $total > 0 ? 'badge-success' : 'badge-danger' }} font-medium-1"
                          @if($breakdown) title="{{ $breakdown }}" style="cursor: help;" @endif>{{ number_format($total) }}</span>
                </td>
                <td>{{ $costValue !== null ? number_format($costValue, 2) : '-' }}</td>
                <td>
                    <div class="btn-group" role="group">
                        @if($canManage)
                            <a href="{{ route('admin.drugs.edit', $drug->id) }}" class="btn btn-sm btn-outline-primary box-shadow-2 mr-1" title="تعديل">
                                <i class="ft-edit"></i>
                            </a>
                        @endif
                        <a href="{{ route($prefix.'.drugs.show', $drug->id) }}" class="btn btn-sm btn-outline-primary box-shadow-2 mr-1" title="تقرير المبيعات">
                            <i class="ft-eye"></i>
                        </a>
                        <a href="{{ route($prefix.'.stock-movements.index', ['drug_id' => [$drug->id]]) }}" class="btn btn-sm btn-outline-info box-shadow-2 mr-1" title="سجل الحركات">
                            <i class="la la-history"></i>
                        </a>
                        @if($canManage)
                            <form action="{{ route('admin.drugs.destroy', $drug->id) }}" method="POST" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger box-shadow-2"
                                        onclick="return confirm('هل أنت متأكد من حذف هذا الدواء؟');" title="حذف">
                                    <i class="ft-trash"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="11" class="text-center text-muted py-3">لا يوجد أدوية مطابقة</td>
            </tr>
        @endforelse
        </tbody>
        @if($drugs->isNotEmpty())
            <tfoot>
            <tr class="font-weight-bold bg-light">
                <td colspan="8" class="text-right">الإجمالي ({{ $drugs->count() }} صنف) — قيمة المخزون بسعر البيع: {{ number_format($grandSaleValue, 2) }} ج.م</td>
                <td class="text-center">{{ number_format($grandStock) }}</td>
                <td>{{ number_format($grandCostValue, 2) }}</td>
                <td></td>
            </tr>
            </tfoot>
        @endif
    </table>
</div>
