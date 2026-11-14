<?php

namespace App\Http\Controllers\Viewer;

use App\Http\Controllers\Controller;
use App\Models\Drug;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ViewerDrugController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // عرض كل الأدوية مع أرصدتها وأسعارها
        $drugs = Drug::filteredInventory($request);

        return view('viewer.drugs.index', compact('drugs'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Drug $drug)
    {
        // جلب جميع المناطق لقائمة الفلتر
        $zones = \App\Models\Zone::all();

        // 1. بناء الاستعلام مع تطبيق فلاتر (التاريخ، المنطقة، حالة الدفع) على الفاتورة المرتبطة
        $query = \App\Models\InvoiceDetail::where('drug_id', $drug->id)
            ->whereHas('invoice', function ($invoiceQuery) use ($request) {
                if ($request->filled('start_date')) {
                    $invoiceQuery->whereDate('invoice_date', '>=', $request->start_date);
                }
                if ($request->filled('end_date')) {
                    $invoiceQuery->whereDate('invoice_date', '<=', $request->end_date);
                }
                // تعديل لدعم تعدد المناطق
                if ($request->filled('zone_id')) {
                    $zoneIds = (array) $request->zone_id;
                    $invoiceQuery->whereHas('pharmacist.center.zones', function ($zoneQuery) use ($zoneIds) {
                        $zoneQuery->whereIn('zones.id', $zoneIds);
                    });
                }
                // تعديل لدعم تعدد حالات الدفع
                if ($request->filled('status')) {
                    $statuses = (array) $request->status;
                    $invoiceQuery->whereIn('status', $statuses);
                }
            });

        // 2. حساب الإحصائيات (تحسب بناءً على الفلتر المطبق)
        $totalQuantitySold = (clone $query)->sum('quantity');
        $totalRevenue = (clone $query)->sum('row_total');
        $invoicesCount = (clone $query)->count(); // عدد الفواتير التي ظهر فيها

        // 3. تصدير إكسيل
        if ($request->has('export') && $request->export == 'excel') {
            $exportData = (clone $query)->with(['invoice.pharmacist', 'invoice.representative'])->latest()->get();

            return $this->exportExcel($exportData, $drug, $totalQuantitySold, $totalRevenue);
        }

        // 4. جلب سجل المبيعات (التفاصيل) وعرضها
        $salesHistory = $query->with(['invoice.pharmacist', 'invoice.representative'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('viewer.drugs.show', compact(
            'drug',
            'salesHistory',
            'totalQuantitySold',
            'totalRevenue',
            'invoicesCount',
            'zones'
        ));
    }

    /**
     * دالة مساعدة لتصدير التقرير
     */
    private function exportExcel($data, $drug, $totalQty, $totalRev)
    {
        $filename = "drug_report_{$drug->id}_".date('Y-m-d').'.csv';

        AuditService::log(
            action: 'export_csv',
            module: 'Drug',
            entity: $drug,
            description: "تصدير تقرير مبيعات الصنف ({$drug->name}) إلى ملف CSV بواسطة مستعرض"
        );

        $callback = function () use ($data, $drug, $totalQty, $totalRev) {
            $file = fopen('php://output', 'w');

            // إضافة BOM لدعم اللغة العربية في Excel
            fwrite($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, sanitize_csv_row(['تقرير صنف:', $drug->name]));
            fputcsv($file, sanitize_csv_row(['إجمالي الكمية المباعة:', $totalQty]));
            fputcsv($file, sanitize_csv_row(['إجمالي الإيرادات:', number_format($totalRev, 2).' ج.م']));
            fputcsv($file, []);

            // ترويسة الجدول
            fputcsv($file, sanitize_csv_row(['رقم الفاتورة', 'التاريخ', 'الصيدلية', 'المندوب', 'الكمية', 'سعر البيع', 'الإجمالي', 'حالة الفاتورة']));

            foreach ($data as $detail) {
                $statusText = match ((int) ($detail->invoice->status ?? 0)) {
                    1 => 'مدفوع',
                    2 => 'آجل',
                    3 => 'جزئي',
                    default => '-'
                };

                fputcsv($file, sanitize_csv_row([
                    $detail->invoice->serial_number ?? $detail->invoice_id,
                    $detail->invoice->invoice_date ?? '-',
                    $detail->invoice->pharmacist->name ?? '-',
                    $detail->invoice->representative->name ?? '-',
                    $detail->quantity,
                    $detail->unit_price,
                    $detail->row_total,
                    $statusText,
                ]));
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }
}
