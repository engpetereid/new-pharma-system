<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Drug;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * السجل التاريخي لحركات المخازن (يُستخدم للمدير والمشاهد/المحاسب)
 */
class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        $prefix = $request->routeIs('viewer.*') ? 'viewer' : 'admin';

        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $query = $this->filteredQuery($request);

        if ($request->input('export') === 'excel') {
            return $this->export($query, $request);
        }

        $summary = (clone $query)->toBase()
            ->selectRaw("COUNT(*) as cnt,
                COALESCE(SUM(CASE WHEN direction = 'in' THEN quantity ELSE 0 END), 0) as total_in,
                COALESCE(SUM(CASE WHEN direction = 'out' THEN quantity ELSE 0 END), 0) as total_out,
                COALESCE(SUM(CASE WHEN type = 'supply' THEN quantity * COALESCE(unit_cost, 0) ELSE 0 END), 0) as supply_cost")
            ->first();

        $movements = $query->with(['warehouse:id,name,type', 'relatedWarehouse:id,name', 'drug:id,name,line', 'user:id,name'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.stock_movements.index', [
            'prefix' => $prefix,
            'movements' => $movements,
            'summary' => $summary,
            'warehouses' => Warehouse::orderBy('type')->orderBy('name')->get(['id', 'name', 'type']),
            'drugs' => Drug::orderBy('name')->get(['id', 'name']),
            'users' => User::whereIn('id', StockMovement::whereNotNull('user_id')->distinct()->pluck('user_id'))->get(['id', 'name']),
            'types' => StockMovement::TYPES,
        ]);
    }

    private function filteredQuery(Request $request)
    {
        $query = StockMovement::query();

        if ($ids = array_filter((array) $request->input('warehouse_id', []))) {
            $query->whereIn('warehouse_id', $ids);
        }
        if ($ids = array_filter((array) $request->input('drug_id', []))) {
            $query->whereIn('drug_id', $ids);
        }
        if ($types = array_filter((array) $request->input('type', []))) {
            $query->whereIn('type', $types);
        }
        if (in_array($request->input('direction'), ['in', 'out'], true)) {
            $query->where('direction', $request->direction);
        }
        if ($request->filled('line')) {
            $query->whereHas('drug', fn ($q) => $q->where('line', $request->line));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', Carbon::parse($request->from));
        }
        if ($request->filled('to')) {
            $to = Carbon::parse($request->to);
            // لو أُدخل تاريخ بدون وقت نشمل اليوم كاملاً
            if (! str_contains($request->to, 'T') && ! str_contains($request->to, ':')) {
                $to->endOfDay();
            }
            $query->where('created_at', '<=', $to);
        }
        if ($request->filled('q')) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $request->q).'%';
            $query->where(fn ($q) => $q->where('reference_label', 'like', $term)->orWhere('notes', 'like', $term));
        }

        return $query;
    }

    private function export($query, Request $request)
    {
        $filename = 'stock_movements_'.now()->format('Y-m-d_His').'.csv';

        AuditService::log(
            action: 'export_csv',
            module: 'StockMovement',
            description: 'تصدير سجل حركات المخازن إلى ملف Excel',
            newValues: ['filters' => $request->except(['export', 'page'])]
        );

        $query = $query->with(['warehouse:id,name', 'relatedWarehouse:id,name', 'drug:id,name,line', 'user:id,name'])
            ->orderByDesc('created_at')->orderByDesc('id');

        $callback = function () use ($query) {
            $file = fopen('php://output', 'w');
            fwrite($file, chr(0xEF).chr(0xBB).chr(0xBF)); // دعم العربية في Excel

            fputcsv($file, sanitize_csv_row([
                'التاريخ والوقت', 'المخزن', 'الصنف', 'Line', 'نوع الحركة', 'الاتجاه', 'الكمية',
                'الرصيد قبل', 'الرصيد بعد', 'سعر الإنتاج', 'المخزن المقابل', 'المرجع', 'ملاحظات', 'المستخدم',
            ]));

            $query->chunk(500, function ($rows) use ($file) {
                foreach ($rows as $m) {
                    fputcsv($file, sanitize_csv_row([
                        $m->created_at->format('Y-m-d H:i:s'),
                        $m->warehouse->name ?? '-',
                        $m->drug->name ?? '-',
                        $m->drug->line ?? '-',
                        $m->type_label,
                        $m->direction === 'in' ? 'وارد' : 'صادر',
                        $m->direction === 'in' ? $m->quantity : -$m->quantity,
                        $m->balance_before,
                        $m->balance_after,
                        $m->unit_cost ?? '-',
                        $m->relatedWarehouse->name ?? '-',
                        $m->reference_label ?? '-',
                        $m->notes ?? '-',
                        $m->user->name ?? '-',
                    ]));
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }
}
