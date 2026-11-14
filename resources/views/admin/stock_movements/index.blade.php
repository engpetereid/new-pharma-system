@extends($prefix === 'viewer' ? 'layouts.viewer' : 'layouts.admin')

@section('title', 'سجل حركات المخازن')

@section('style')
    <style>
        .select2-container--default .select2-selection--multiple { border: 1px solid #ccd6e6; min-height: 40px; }
        .mv-table td, .mv-table th { vertical-align: middle; white-space: nowrap; }
        .qty-in { color: #28d094; font-weight: 700; }
        .qty-out { color: #ff4961; font-weight: 700; }
    </style>
@endsection

@section('content')
    <div class="app-content content">
        <div class="content-wrapper">
            <div class="content-header row mb-2">
                <div class="content-header-left col-md-6 col-12">
                    <h3 class="content-header-title"><i class="la la-history"></i> سجل حركات المخازن</h3>
                    <div class="row breadcrumbs-top">
                        <div class="breadcrumb-wrapper col-12">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route($prefix.'.dashboard') }}">الرئيسية</a></li>
                                <li class="breadcrumb-item"><a href="{{ route($prefix.'.warehouses.index') }}">المخازن</a></li>
                                <li class="breadcrumb-item active">سجل الحركات</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-body">

                {{-- الفلاتر --}}
                <div class="card box-shadow-1 border-top-info border-top-3 mb-2">
                    <div class="card-body py-2">
                        <form action="{{ route($prefix.'.stock-movements.index') }}" method="GET">
                            @if($errors->any())
                                <div class="alert alert-danger mb-1">{{ $errors->first() }}</div>
                            @endif
                            <div class="row align-items-end">
                                <div class="col-md-3 mb-1">
                                    <label class="text-muted font-small-3">من (تاريخ ووقت)</label>
                                    <input type="datetime-local" name="from" class="form-control" value="{{ request('from') }}">
                                </div>
                                <div class="col-md-3 mb-1">
                                    <label class="text-muted font-small-3">إلى (تاريخ ووقت)</label>
                                    <input type="datetime-local" name="to" class="form-control" value="{{ request('to') }}">
                                </div>
                                <div class="col-md-3 mb-1">
                                    <label class="text-muted font-small-3">المخزن</label>
                                    @php $selW = (array) request('warehouse_id', []); @endphp
                                    <select name="warehouse_id[]" class="form-control select2" multiple data-placeholder="-- كل المخازن --">
                                        @foreach($warehouses as $w)
                                            <option value="{{ $w->id }}" {{ in_array($w->id, $selW) ? 'selected' : '' }}>
                                                {{ $w->name }} ({{ $w->type == 'main' ? 'رئيسي' : 'فرعي' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-1">
                                    <label class="text-muted font-small-3">الصنف</label>
                                    @php $selD = (array) request('drug_id', []); @endphp
                                    <select name="drug_id[]" class="form-control select2" multiple data-placeholder="-- كل الأصناف --">
                                        @foreach($drugs as $d)
                                            <option value="{{ $d->id }}" {{ in_array($d->id, $selD) ? 'selected' : '' }}>{{ $d->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-1">
                                    <label class="text-muted font-small-3">نوع الحركة</label>
                                    @php $selT = (array) request('type', []); @endphp
                                    <select name="type[]" class="form-control select2" multiple data-placeholder="-- كل الأنواع --">
                                        @foreach($types as $key => $t)
                                            <option value="{{ $key }}" {{ in_array($key, $selT) ? 'selected' : '' }}>{{ $t[0] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-1">
                                    <label class="text-muted font-small-3">الاتجاه</label>
                                    <select name="direction" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="in" {{ request('direction') == 'in' ? 'selected' : '' }}>وارد (إضافة)</option>
                                        <option value="out" {{ request('direction') == 'out' ? 'selected' : '' }}>صادر (سحب)</option>
                                    </select>
                                </div>
                                <div class="col-md-1 mb-1">
                                    <label class="text-muted font-small-3">Line</label>
                                    <select name="line" class="form-control">
                                        <option value="">الكل</option>
                                        <option value="1" {{ request('line') == '1' ? 'selected' : '' }}>1</option>
                                        <option value="2" {{ request('line') == '2' ? 'selected' : '' }}>2</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-1">
                                    <label class="text-muted font-small-3">المستخدم</label>
                                    <select name="user_id" class="form-control">
                                        <option value="">الكل</option>
                                        @foreach($users as $u)
                                            <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-1">
                                    <label class="text-muted font-small-3">بحث بالمرجع / ملاحظات</label>
                                    <input type="text" name="q" class="form-control" value="{{ request('q') }}" placeholder="رقم فاتورة، مرتجع...">
                                </div>
                                <div class="col-md-2 mb-1 d-flex">
                                    <button type="submit" class="btn btn-primary flex-grow-1 mr-1"><i class="ft-filter"></i> تصفية</button>
                                    <button type="submit" name="export" value="excel" class="btn btn-success flex-grow-1 mr-1" title="تصدير Excel"><i class="la la-file-excel-o"></i></button>
                                    <a href="{{ route($prefix.'.stock-movements.index') }}" class="btn btn-outline-secondary" title="إلغاء الفلتر"><i class="ft-x"></i></a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- الملخص --}}
                <div class="row">
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="card"><div class="card-body py-2">
                            <span class="text-muted">عدد الحركات</span>
                            <h3 class="mb-0">{{ number_format($summary->cnt) }}</h3>
                        </div></div>
                    </div>
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="card"><div class="card-body py-2">
                            <span class="text-muted">إجمالي الوارد</span>
                            <h3 class="mb-0 qty-in">+{{ number_format($summary->total_in) }}</h3>
                        </div></div>
                    </div>
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="card"><div class="card-body py-2">
                            <span class="text-muted">إجمالي الصادر</span>
                            <h3 class="mb-0 qty-out">-{{ number_format($summary->total_out) }}</h3>
                        </div></div>
                    </div>
                    <div class="col-xl-3 col-md-6 col-12">
                        <div class="card"><div class="card-body py-2">
                            <span class="text-muted">تكلفة التوريدات (سعر الإنتاج)</span>
                            <h3 class="mb-0">{{ number_format($summary->supply_cost, 2) }} <small>ج.م</small></h3>
                        </div></div>
                    </div>
                </div>

                {{-- الجدول --}}
                <div class="card">
                    <div class="card-content">
                        <div class="card-body card-dashboard">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mv-table">
                                    <thead class="bg-primary white">
                                    <tr>
                                        <th>التاريخ والوقت</th>
                                        <th>المخزن</th>
                                        <th>الصنف</th>
                                        <th>نوع الحركة</th>
                                        <th class="text-center">الكمية</th>
                                        <th class="text-center">الرصيد قبل</th>
                                        <th class="text-center">الرصيد بعد</th>
                                        <th>سعر الإنتاج</th>
                                        <th>المخزن المقابل</th>
                                        <th>المرجع</th>
                                        <th>المستخدم</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($movements as $m)
                                        <tr>
                                            <td>
                                                {{ $m->created_at->format('Y-m-d') }}
                                                <small class="text-muted d-block">{{ $m->created_at->format('h:i:s A') }}</small>
                                            </td>
                                            <td>{{ $m->warehouse->name ?? '-' }}</td>
                                            <td class="text-bold-600">
                                                {{ $m->drug->name ?? '-' }}
                                                @if($m->drug)
                                                    <span class="badge badge-sm {{ $m->drug->line == 1 ? 'badge-info' : 'badge-warning' }}">L{{ $m->drug->line }}</span>
                                                @endif
                                            </td>
                                            <td><span class="badge badge-{{ $m->type_color }}">{{ $m->type_label }}</span></td>
                                            <td class="text-center {{ $m->direction == 'in' ? 'qty-in' : 'qty-out' }}">
                                                {{ $m->direction == 'in' ? '+' : '-' }}{{ number_format($m->quantity) }}
                                            </td>
                                            <td class="text-center">{{ number_format($m->balance_before) }}</td>
                                            <td class="text-center font-weight-bold">{{ number_format($m->balance_after) }}</td>
                                            <td>{{ $m->unit_cost !== null ? number_format($m->unit_cost, 2) : '-' }}</td>
                                            <td>{{ $m->relatedWarehouse->name ?? '-' }}</td>
                                            <td>
                                                {{ $m->reference_label ?? '-' }}
                                                @if($m->notes)
                                                    <small class="text-muted d-block" title="{{ $m->notes }}">{{ \Illuminate\Support\Str::limit($m->notes, 40) }}</small>
                                                @endif
                                            </td>
                                            <td>{{ $m->user->name ?? 'النظام' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="11" class="text-center text-muted py-3">لا توجد حركات مطابقة</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-center mt-2">{{ $movements->links() }}</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
