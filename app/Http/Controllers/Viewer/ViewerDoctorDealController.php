<?php

namespace App\Http\Controllers\Viewer;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\DoctorDeal;
use App\Models\Zone;
use App\Models\ZoneExpense;
use App\Services\AuditService;
use App\Services\DoctorCommissionService;
use Illuminate\Http\Request;

class ViewerDoctorDealController extends Controller
{
    private function getFilteredDealsQuery(Request $request)
    {
        $query = DoctorDeal::with(['doctor', 'pharmacists', 'drugs']);

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->whereHas('doctor', function ($d) use ($searchTerm) {
                    $d->where('name', 'like', '%'.$searchTerm.'%');
                })
                    ->orWhereHas('pharmacists', function ($p) use ($searchTerm) {
                        $p->where('name', 'like', '%'.$searchTerm.'%');
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('zone_id')) {
            $query->whereHas('pharmacists.center.zones', function ($q) use ($request) {
                $q->where('zones.id', $request->zone_id);
            });
        }

        return $query;
    }

    /**
     * دالة مساعدة لحساب الإحصائيات بناءً على الفلتر الحالي
     */
    private function calculateDealStats($query)
    {
        $stats = (clone $query)->selectRaw('
            SUM(target_amount) as total_target,
            SUM(achieved_amount) as total_achieved,
            SUM(paid_amount) as total_paid,
            SUM(CASE WHEN target_amount > 0 THEN commission_amount ELSE (achieved_amount * commission_percentage / 100) END) as total_commission
        ')->first();

        $total_target = $stats->total_target ?? 0;
        $total_achieved = $stats->total_achieved ?? 0;
        $total_paid = $stats->total_paid ?? 0;
        $total_commission = $stats->total_commission ?? 0;

        $total_remaining = max(0, $total_commission - $total_paid);

        return compact('total_target', 'total_achieved', 'total_commission', 'total_paid', 'total_remaining');
    }

    public function index(Request $request)
    {
        $query = $this->getFilteredDealsQuery($request);
        $query->where('is_archived', false);

        // جلب الإحصائيات بعد تطبيق الفلاتر وقبل التقسيم (Pagination)
        $stats = $this->calculateDealStats($query);

        if ($request->has('export') && $request->export == 'excel') {
            return $this->exportDeals($query->get(), $stats, 'active_deals', 'الاتفاقات الجارية');
        }

        $zones = Zone::where('line', 1)->get();
        $deals = $query->latest()->paginate(12);
        $deals->appends($request->all());

        return view('viewer.deals.index', compact('deals', 'zones', 'stats'));
    }

    public function archived(Request $request)
    {
        $query = $this->getFilteredDealsQuery($request);
        $query->where('is_archived', true);

        // جلب الإحصائيات لصفحة الأرشيف بنفس طريقة الفلترة
        $stats = $this->calculateDealStats($query);

        if ($request->has('export') && $request->export == 'excel') {
            return $this->exportDeals($query->get(), $stats, 'archived_deals', 'أرشيف الاتفاقات');
        }

        $zones = Zone::where('line', 1)->get();
        $deals = $query->latest()->paginate(12);
        $deals->appends($request->all());

        return view('viewer.deals.index', compact('deals', 'zones', 'stats'));
    }

    /**
     * دالة لتصدير الاتفاقات إلى ملف إكسيل (CSV)
     */
    private function exportDeals($deals, $stats, $filenamePrefix, $title)
    {
        $filename = $filenamePrefix.'_'.date('Y-m-d').'.csv';

        AuditService::log(
            action: 'export_csv',
            module: 'DoctorDeal',
            entity: null,
            description: "تصدير {$title} إلى ملف CSV بواسطة مستعرض"
        );

        $commissionService = app(DoctorCommissionService::class);

        $callback = function () use ($deals, $stats, $title, $commissionService) {
            $file = fopen('php://output', 'w');
            // دعم اللغة العربية
            fwrite($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, sanitize_csv_row(['تقرير', $title]));
            fputcsv($file, []);

            // إضافة ملخص الإحصائيات في بداية الملف
            fputcsv($file, sanitize_csv_row(['ملخص الإحصائيات بناءً على الفلتر المختار']));
            fputcsv($file, sanitize_csv_row(['إجمالي التارجت المطلوب', $stats['total_target']]));
            fputcsv($file, sanitize_csv_row(['إجمالي المبيعات المحققة', $stats['total_achieved']]));
            fputcsv($file, sanitize_csv_row(['إجمالي العمولة المستحقة', $stats['total_commission']]));
            fputcsv($file, sanitize_csv_row(['إجمالي المدفوع للأطباء', $stats['total_paid']]));
            fputcsv($file, sanitize_csv_row(['إجمالي العمولات المتبقية', $stats['total_remaining']]));
            fputcsv($file, []);

            // ترويسة الجدول
            fputcsv($file, sanitize_csv_row([
                'الطبيب', 'الصيدليات المشمولة', 'تاريخ البدء', 'حالة الاتفاق', 'التارجت المطلوب', 'المبيعات المحققة',
                'نسبة العمولة %', 'العمولة المستحقة', 'المدفوع', 'المتبقي', 'حالة الدفع',
            ]));

            foreach ($deals as $deal) {
                $statusText = match ((int) $deal->status) {
                    1 => 'خالص',
                    2 => 'آجل',
                    3 => 'جزئي',
                    default => '-'
                };

                $isOpenDeal = ($deal->target_amount <= 0);
                $commission = $commissionService->getDealCommission($deal);

                $remaining = max(0, $commission - $deal->paid_amount);
                $activeStatus = $deal->is_active ? 'ساري' : 'موقوف مؤقتاً';

                fputcsv($file, sanitize_csv_row([
                    $deal->doctor->name ?? '-',
                    $deal->pharmacists->pluck('name')->implode(' - '),
                    $deal->start_date,
                    $activeStatus,
                    $isOpenDeal ? 'مفتوح' : $deal->target_amount,
                    $deal->achieved_amount,
                    $deal->commission_percentage.'%',
                    $commission,
                    $deal->paid_amount,
                    $remaining,
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

    public function showInvoices(Request $request, DoctorDeal $deal)
    {
        $paidInvoicesQuery = $deal->invoices()
            ->with(['representative', 'medicalRepresentative', 'pharmacist', 'details.drug']);

        $dealDrugIds = $deal->drugs->pluck('id')->toArray();
        $isGeneralDeal = empty($dealDrugIds);

        $pharmacistIds = $deal->pharmacists->pluck('id')->toArray();

        $unpaidQuery = \App\Models\Invoice::with(['representative', 'pharmacist', 'details.drug'])
            ->whereHas('doctors', function ($q) use ($deal) {
                $q->where('doctors.id', $deal->doctor_id);
            })
            ->whereIn('pharmacist_id', $pharmacistIds)
            ->whereIn('status', [2, 3])
            ->whereDate('invoice_date', '>=', $deal->start_date)
            ->latest()
            ->get();

        $unpaidTotalContribution = 0;
        $unpaidInvoicesList = collect();

        foreach ($unpaidQuery as $inv) {
            $invContribution = 0;
            foreach ($inv->details as $detail) {
                if ($isGeneralDeal || in_array($detail->drug_id, $dealDrugIds)) {
                    $invContribution += $detail->unit_price * $detail->quantity;
                }
            }

            if ($invContribution > 0) {
                $inv->potential_contribution = $invContribution;
                $unpaidTotalContribution += $invContribution;
                $unpaidInvoicesList->push($inv);
            }
        }

        $potentialCommission = $unpaidTotalContribution * ($deal->commission_percentage / 100);

        if ($request->has('export') && $request->export == 'excel') {
            $paidInvoicesAll = $paidInvoicesQuery->latest()->get();

            return $this->exportDealInvoices($deal, $paidInvoicesAll, $unpaidInvoicesList, $unpaidTotalContribution, $potentialCommission);
        }

        $paidInvoices = $paidInvoicesQuery->latest()->paginate(15);

        return view('viewer.deals.invoices', compact(
            'deal',
            'paidInvoices',
            'unpaidInvoicesList',
            'unpaidTotalContribution',
            'potentialCommission',
            'dealDrugIds',
            'isGeneralDeal'
        ));
    }

    /**
     * دالة مساعدة لتصدير فواتير التارجت (المدفوعة والمنتظرة) لملف Excel
     */
    private function exportDealInvoices($deal, $paidInvoices, $unpaidInvoicesList, $unpaidTotalContribution, $potentialCommission)
    {
        $filename = 'deal_invoices_'.date('Y-m-d').'.csv';

        AuditService::log(
            action: 'export_csv',
            module: 'DoctorDeal',
            entity: $deal,
            description: "تصدير فواتير الاتفاق المالي رقم #{$deal->id} للطبيب (".($deal->doctor->name ?? '-').') إلى ملف CSV بواسطة مستعرض'
        );

        $callback = function () use ($deal, $paidInvoices, $unpaidInvoicesList, $unpaidTotalContribution, $potentialCommission) {
            $file = fopen('php://output', 'w');

            // إضافة BOM لدعم اللغة العربية في Excel
            fwrite($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, sanitize_csv_row(['تقرير فواتير التارجت']));
            fputcsv($file, sanitize_csv_row(['الطبيب:', $deal->doctor->name ?? '-']));
            fputcsv($file, sanitize_csv_row(['الهدف (Target):', $deal->target_amount, 'المحقق (تم تحصيله):', $deal->achieved_amount]));
            fputcsv($file, []);

            // الجدول الأول: الفواتير المكتملة
            fputcsv($file, sanitize_csv_row(['--- الفواتير المكتملة والمحصلة (تم احتسابها في التارجت) ---']));
            fputcsv($file, sanitize_csv_row(['رقم الفاتورة', 'التاريخ', 'الصيدلية', 'الأدوية', 'القيمة المحتسبة (للاتفاق)']));

            foreach ($paidInvoices as $invoice) {
                $drugsList = [];
                foreach ($invoice->details as $detail) {
                    $drugName = $detail->drug->name ?? 'صنف غير معروف';
                    $drugsList[] = "$drugName ({$detail->quantity})";
                }

                fputcsv($file, sanitize_csv_row([
                    $invoice->serial_number ?? $invoice->id,
                    $invoice->invoice_date,
                    $invoice->pharmacist->name ?? '-',
                    implode(' | ', $drugsList),
                    $invoice->pivot->contribution_amount ?? $invoice->final_total,
                ]));
            }

            fputcsv($file, []);

            // الجدول الثاني: الفواتير الآجلة والجزئية
            fputcsv($file, sanitize_csv_row(['--- الفواتير الآجلة والجزئية (المنتظرة) ---']));
            fputcsv($file, sanitize_csv_row(['إجمالي المبيعات المتوقعة للاتفاق:', $unpaidTotalContribution, 'عمولة الطبيب المنتظرة:', $potentialCommission]));
            fputcsv($file, []);

            fputcsv($file, sanitize_csv_row(['رقم الفاتورة', 'التاريخ', 'الصيدلية', 'الأدوية', 'حالة الفاتورة', 'المنتظر للاتفاق']));

            foreach ($unpaidInvoicesList as $invoice) {
                $statusText = match ((int) $invoice->status) {
                    2 => 'آجل',
                    3 => 'جزئي',
                    default => '-'
                };

                $drugsList = [];
                foreach ($invoice->details as $detail) {
                    $drugName = $detail->drug->name ?? 'صنف غير معروف';
                    $drugsList[] = "$drugName ({$detail->quantity})";
                }

                fputcsv($file, sanitize_csv_row([
                    $invoice->serial_number ?? $invoice->id,
                    $invoice->invoice_date,
                    $invoice->pharmacist->name ?? '-',
                    implode(' | ', $drugsList),
                    $statusText,
                    $invoice->potential_contribution,
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

    private function recordDealExpense(DoctorDeal $deal, $amount)
    {
        if ($amount <= 0) {
            return;
        }

        $doctor = Doctor::find($deal->doctor_id);
        if (! $doctor || ! $doctor->center_id) {
            return;
        }

        $line = 1;
        $firstDrug = $deal->drugs()->first();
        if ($firstDrug) {
            $line = $firstDrug->line;
        }

        $zone = Zone::where('line', $line)->whereHas('centers', function ($q) use ($doctor) {
            $q->where('centers.id', $doctor->center_id);
        })->first();

        if (! $zone) {
            $zone = Zone::whereHas('centers', function ($q) use ($doctor) {
                $q->where('centers.id', $doctor->center_id);
            })->first();
        }

        if ($zone) {
            ZoneExpense::create([
                'zone_id' => $zone->id,
                'amount' => $amount,
                'description' => 'دفعة من اتفاق مالي (عمولة) للطبيب: '.$doctor->name,
                'expense_date' => now()->format('Y-m-d'),
            ]);
        }
    }
}
