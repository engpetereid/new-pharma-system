<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\Doctor;
use App\Models\DoctorDeal;
use App\Models\Drug;
use App\Models\GeneralExpense;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePayment;
use App\Models\InvoiceReturn;
use App\Models\InvoiceReturnItem;
use App\Models\Pharmacist;
use App\Models\Province;
use App\Models\Representative;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Models\ZoneExpense;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * بيانات تجريبية كاملة للنظام تغطي جميع الحالات (Edge cases) لكل الشاشات.
 *
 * التشغيل: php artisan migrate:fresh --seed
 *
 * الحسابات (كلمة المرور لكل الحسابات: 12345678):
 *  - peter@gmail.com          (admin)
 *  - admin2@pharma.test       (admin)
 *  - accountant@pharma.test   (accountant)
 *  - accountant2@pharma.test  (accountant)
 *  - viewer@pharma.test       (viewer)
 *  - viewer2@pharma.test      (viewer)
 *
 * المنطق المحاسبي مطابق لما في الكنترولرز:
 *  - خصم المخزون من مخزن المنطقة عند البيع وإعادته عند المرتجع.
 *  - حالة الفاتورة تُحسب من الدفعات (1 خالص / 2 آجل / 3 جزئي).
 *  - الفاتورة تُضاف لتارجت اتفاقات الأطباء فقط عند السداد الكامل.
 *  - المرتجع يعيد حساب الفاتورة ويسترد الفرق نقداً إن كان المدفوع أكبر من الصافي الجديد.
 *  - دفعات اتفاقات الأطباء تُسجل كمصروف على المنطقة.
 */
class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    private const PASSWORD = '12345678';

    private Carbon $today;

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, Center> */
    private array $centers = [];

    /** @var array<string, Warehouse> */
    private array $warehouses = [];

    /** @var array<string, Drug> */
    private array $drugs = [];

    /** @var array<string, Representative> */
    private array $reps = [];

    /** @var array<string, Zone> */
    private array $zones = [];

    /** @var array<string, Pharmacist[]> center name => pharmacists */
    private array $pharmacistsByCenter = [];

    /** @var array<string, Doctor[]> center name => doctors */
    private array $doctorsByCenter = [];

    /** @var array<string, array> deal key => meta */
    private array $deals = [];

    /** @var array<int, array<int, int>> [warehouseId][drugId] => qty */
    private array $stock = [];

    /** @var array<string, array{zone: Zone, warehouse_id: int}> "centerId-line" => route */
    private array $routes = [];

    private array $audit = [];

    private int $serial = 10000;

    /** @var Invoice[] */
    private array $invoices = [];

    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
        'Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1',
    ];

    private const IPS = ['197.34.12.8', '41.233.90.15', '156.205.44.71', '102.40.3.120', '192.168.1.15'];

    public function run(): void
    {
        if (Invoice::query()->exists()) {
            $this->command?->warn('توجد فواتير بالفعل في قاعدة البيانات. شغّل: php artisan migrate:fresh --seed');

            return;
        }

        mt_srand(2026);
        $this->today = Carbon::today();

        Model::unguard();

        DB::transaction(function () {
            $this->seedUsers();
            $this->seedGeography();
            $this->seedDrugs();
            $this->seedWarehouses();
            $this->seedRepresentatives();
            $this->seedZones();
            $this->seedPharmacistsAndDoctors();
            $this->seedStock();
            $this->seedDeals();
            $this->seedInvoices();
            $this->seedReturns();
            $this->finalizeDeals();
            $this->seedExpenses();
            $this->seedMiscAudit();
            $this->flushStock();
            $this->flushAudit();
        });

        Model::reguard();

        $this->printSummary();
    }

    // =====================================================================
    // Users
    // =====================================================================

    private function seedUsers(): void
    {
        $list = [
            'admin' => ['مدير النظام', 'peter@gmail.com', 'admin'],
            'admin2' => ['أحمد سمير', 'admin2@pharma.test', 'admin'],
            'accountant' => ['منى عادل', 'accountant@pharma.test', 'accountant'],
            'accountant2' => ['خالد يوسف', 'accountant2@pharma.test', 'accountant'],
            'viewer' => ['سارة محمود', 'viewer@pharma.test', 'viewer'],
            'viewer2' => ['محمد فتحي', 'viewer2@pharma.test', 'viewer'],
        ];

        $created = $this->today->copy()->subMonths(13);
        foreach ($list as $key => [$name, $email, $role]) {
            $this->users[$key] = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(self::PASSWORD),
                'role' => $role,
                // حالة: مستخدم لم يفعّل بريده
                'email_verified_at' => $key === 'viewer2' ? null : $created,
                'created_at' => $created,
                'updated_at' => $created,
            ]);
        }
    }

    // =====================================================================
    // Provinces & Centers
    // =====================================================================

    private function seedGeography(): void
    {
        $data = [
            'القاهرة' => ['مدينة نصر', 'مصر الجديدة', 'المعادي', 'شبرا'],
            'الجيزة' => ['الدقي', 'الهرم', '6 أكتوبر'],
            'الإسكندرية' => ['سيدي جابر', 'المنتزه', 'العجمي'], // العجمي: مركز بدون أي منطقة
            'الدقهلية' => ['المنصورة', 'طلخا', 'ميت غمر'],
            'الشرقية' => ['الزقازيق', 'بلبيس'],
            'أسوان' => [], // حالة: محافظة بدون مراكز
        ];

        foreach ($data as $provinceName => $centers) {
            $province = Province::create(['name' => $provinceName]);
            foreach ($centers as $centerName) {
                $this->centers[$centerName] = Center::create([
                    'name' => $centerName,
                    'province_id' => $province->id,
                ]);
            }
        }
    }

    // =====================================================================
    // Drugs
    // =====================================================================

    private function seedDrugs(): void
    {
        $line1 = [
            'Amoxicillin 500mg Caps' => 48, 'Augmentin 1g Tabs' => 165, 'Azithromycin 500mg Tabs' => 92,
            'Ciprofloxacin 500mg Tabs' => 58, 'Cefixime 400mg Caps' => 120, 'Omeprazole 20mg Caps' => 39,
            'Esomeprazole 40mg Tabs' => 96, 'Pantoprazole 40mg Vial' => 75, 'Paracetamol 500mg Tabs' => 18,
            'Ibuprofen 400mg Tabs' => 27, 'Diclofenac 75mg Amp' => 36, 'Domperidone 10mg Tabs' => 31,
            'Metronidazole 500mg Tabs' => 22, 'Cough Syrup 120ml' => 44, 'Vitamin C 1000mg Eff' => 55,
            // حالة: صنف موجود في المخزن الرئيسي فقط ولم يُنقل لأي مخزن فرعي
            'Ceftriaxone 1g Vial' => 64,
        ];
        $line2 = [
            'Metformin 850mg Tabs' => 35, 'Gliclazide 60mg MR Tabs' => 68, 'Insulin Glargine Pen' => 420,
            'Atorvastatin 20mg Tabs' => 110, 'Rosuvastatin 10mg Tabs' => 145, 'Amlodipine 5mg Tabs' => 42,
            'Bisoprolol 5mg Tabs' => 57, 'Valsartan 160mg Tabs' => 132, 'Clopidogrel 75mg Tabs' => 98,
            'Levothyroxine 100mcg Tabs' => 33, 'Vitamin D3 50000IU Caps' => 85, 'Omega-3 1000mg Caps' => 160,
            // حالة: صنف جديد بدون أي رصيد في أي مخزن وبدون مبيعات
            'Sitagliptin 100mg Tabs' => 310,
        ];

        $at = $this->today->copy()->subMonths(13);
        foreach ([1 => $line1, 2 => $line2] as $line => $items) {
            foreach ($items as $name => $price) {
                $this->drugs[$name] = Drug::create([
                    'name' => $name, 'price' => $price, 'line' => $line,
                    'created_at' => $at, 'updated_at' => $at,
                ]);
            }
        }
    }

    // =====================================================================
    // Warehouses
    // =====================================================================

    private function seedWarehouses(): void
    {
        $main = Warehouse::create(['name' => 'المخزن الرئيسي - القاهرة', 'type' => 'main', 'parent_id' => null]);
        $mainAlex = Warehouse::create(['name' => 'المخزن الرئيسي - الإسكندرية', 'type' => 'main', 'parent_id' => null]);

        $this->warehouses = [
            'main' => $main,
            'main_alex' => $mainAlex,
            'cairo' => Warehouse::create(['name' => 'مخزن القاهرة', 'type' => 'sub', 'parent_id' => $main->id]),
            'giza' => Warehouse::create(['name' => 'مخزن الجيزة', 'type' => 'sub', 'parent_id' => $main->id]),
            'alex' => Warehouse::create(['name' => 'مخزن الإسكندرية', 'type' => 'sub', 'parent_id' => $mainAlex->id]),
            'delta' => Warehouse::create(['name' => 'مخزن الدلتا', 'type' => 'sub', 'parent_id' => $main->id]),
            // حالة: مخزن فرعي غير مربوط بأي منطقة وبدون رصيد
            'reserve' => Warehouse::create(['name' => 'مخزن احتياطي (فارغ)', 'type' => 'sub', 'parent_id' => $main->id]),
        ];
    }

    // =====================================================================
    // Representatives
    // =====================================================================

    private function seedRepresentatives(): void
    {
        $list = [
            'cairo_sales1' => ['محمود عبد الله', '01001234567'],
            'cairo_sales2' => ['إسلام حسن', '01112345678'],
            'cairo_med1' => ['د. ريهام فؤاد', '01223456789'],
            'cairo_med2' => ['د. شريف نبيل', '01534567890'],
            'giza_sales' => ['عمرو صلاح', '01045678901'],
            'giza_med' => ['د. نهى جمال', '01156789012'],
            'alex_sales' => ['مصطفى الشاذلي', '01267890123'],
            'alex_med' => ['د. هبة رمضان', '01578901234'],
            'delta_sales1' => ['حسام الدين علي', '01089012345'],
            'delta_sales2' => ['وليد منصور', '01190123456'],
            'delta_med' => ['د. أميرة السيد', '01201234560'],
            // حالة: مندوب غير معين على أي منطقة
            'unassigned' => ['ياسر عبد الحميد', '01512345609'],
        ];

        foreach ($list as $key => [$name, $phone]) {
            $this->reps[$key] = Representative::create(['name' => $name, 'phone' => $phone, 'user_id' => null]);
        }
    }

    // =====================================================================
    // Zones
    // =====================================================================

    private function seedZones(): void
    {
        $province = fn (string $center) => $this->centers[$center]->province_id;
        $w = $this->warehouses;
        $r = $this->reps;

        $defs = [
            'cairo_east_l1' => ['القاهرة شرق - Line 1', 'مدينة نصر', 1, 'cairo', 'cairo_sales1', 'cairo_med1', ['مدينة نصر', 'مصر الجديدة']],
            'cairo_south_l1' => ['القاهرة جنوب - Line 1', 'المعادي', 1, 'cairo', 'cairo_sales2', 'cairo_med1', ['المعادي', 'شبرا']],
            'cairo_l2' => ['القاهرة - Line 2', 'مدينة نصر', 2, 'cairo', 'cairo_sales1', 'cairo_med2', ['مدينة نصر', 'مصر الجديدة', 'المعادي', 'شبرا']],
            'giza_l1' => ['الجيزة - Line 1', 'الدقي', 1, 'giza', 'giza_sales', 'giza_med', ['الدقي', 'الهرم', '6 أكتوبر']],
            // حالة: منطقة بدون مندوب طبي
            'giza_l2' => ['الجيزة - Line 2', 'الدقي', 2, 'giza', 'giza_sales', null, ['الدقي', 'الهرم']],
            'alex_l1' => ['الإسكندرية - Line 1', 'سيدي جابر', 1, 'alex', 'alex_sales', 'alex_med', ['سيدي جابر', 'المنتزه']],
            // حالة: منطقة غير مربوطة بمخزن (لا يمكن إصدار فواتير Line 2 لها)
            'alex_l2' => ['الإسكندرية - Line 2', 'سيدي جابر', 2, null, 'alex_sales', 'alex_med', ['سيدي جابر', 'المنتزه']],
            'dakahlia_l1' => ['الدقهلية - Line 1', 'المنصورة', 1, 'delta', 'delta_sales1', 'delta_med', ['المنصورة', 'طلخا', 'ميت غمر']],
            'dakahlia_l2' => ['الدقهلية - Line 2', 'المنصورة', 2, 'delta', 'delta_sales1', 'delta_med', ['المنصورة', 'طلخا']],
            'sharqia_l1' => ['الشرقية - Line 1', 'الزقازيق', 1, 'delta', 'delta_sales2', 'delta_med', ['الزقازيق', 'بلبيس']],
            // حالة: منطقة بدون أي مناديب (غير مكتملة الإعداد)
            'sharqia_l2' => ['الشرقية - Line 2', 'الزقازيق', 2, 'delta', null, null, ['الزقازيق']],
        ];

        $at = $this->today->copy()->subMonths(13);
        foreach ($defs as $key => [$name, $provCenter, $line, $wh, $sales, $med, $centers]) {
            $zone = Zone::create([
                'name' => $name,
                'province_id' => $province($provCenter),
                'line' => $line,
                'warehouse_id' => $wh ? $w[$wh]->id : null,
                'sales_representative_id' => $sales ? $r[$sales]->id : null,
                'medical_representative_id' => $med ? $r[$med]->id : null,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
            foreach ($centers as $c) {
                DB::table('center_zone')->insert([
                    'zone_id' => $zone->id, 'center_id' => $this->centers[$c]->id,
                    'created_at' => $at, 'updated_at' => $at,
                ]);
                // نفس منطق الكنترولر: أول منطقة مطابقة للمركز والخط
                $routeKey = $this->centers[$c]->id.'-'.$line;
                if (! isset($this->routes[$routeKey]) && $zone->warehouse_id && $zone->sales_representative_id) {
                    $this->routes[$routeKey] = ['zone' => $zone, 'warehouse_id' => $zone->warehouse_id];
                }
            }
            $this->zones[$key] = $zone;
        }
    }

    // =====================================================================
    // Pharmacists & Doctors
    // =====================================================================

    private function seedPharmacistsAndDoctors(): void
    {
        $pharmNames = [
            'الشفاء', 'النور', 'الحياة', 'الأمل', 'الرحمة', 'السلام', 'الهدى', 'الصفا', 'المروة', 'الإيمان',
            'د. هشام', 'د. منى', 'د. عادل', 'د. رشا', 'د. إيهاب', 'د. نادية', 'د. طارق', 'د. سامية', 'د. حازم', 'د. لبنى',
            'العزبي', 'سيف', 'مصر', 'الزهراء', 'الفردوس', 'الياسمين', 'الندى', 'البركة', 'الرضا', 'الفتح',
            'د. وائل', 'د. داليا', 'د. كريم', 'د. شيماء', 'د. باسم', 'د. إنجي', 'د. أيمن', 'د. غادة', 'د. ماهر', 'د. ريم',
            'الحرية', 'الوفاء', 'الصحة', 'الدواء', 'العاصمة', 'النيل', 'الجوهرة', 'الأندلس', 'القدس', 'المدينة',
        ];
        $streets = ['التحرير', 'الجمهورية', 'عباس العقاد', 'مكرم عبيد', 'الثورة', 'النصر', 'الجيش', 'سعد زغلول', 'بورسعيد', 'الهرم', 'فيصل', 'الجلاء'];
        $firstNames = ['أحمد', 'محمد', 'محمود', 'علي', 'حسن', 'إبراهيم', 'يوسف', 'عمر', 'خالد', 'مصطفى', 'هاني', 'سامح', 'ياسمين', 'مريم', 'نورهان', 'دينا', 'هالة', 'إيمان', 'رانيا', 'سلمى'];
        $lastNames = ['عبد الرحمن', 'الشريف', 'المصري', 'السيد', 'فاروق', 'عثمان', 'الجمال', 'البنا', 'سليمان', 'رشدي', 'عزت', 'النجار', 'زكي', 'حمدي', 'الحسيني'];
        $specialities = ['باطنة', 'أطفال', 'قلب وأوعية دموية', 'سكر وغدد صماء', 'أنف وأذن وحنجرة', 'جلدية', 'عظام', 'نساء وتوليد', 'صدر', 'مخ وأعصاب', 'ممارس عام', 'جهاز هضمي'];

        $pi = 0;
        $at = $this->today->copy()->subMonths(13);
        foreach ($this->centers as $centerName => $center) {
            // حالة: مركز بلبيس به صيدلية واحدة فقط، ومركز طلخا بدون أطباء
            $pharmCount = $centerName === 'بلبيس' ? 1 : (in_array($centerName, ['مدينة نصر', 'المنصورة', 'الدقي']) ? 4 : 3);
            $doctorCount = $centerName === 'طلخا' ? 0 : (in_array($centerName, ['مدينة نصر', 'المنصورة', 'الدقي', 'المعادي']) ? 3 : 2);

            for ($i = 0; $i < $pharmCount; $i++) {
                $name = 'صيدلية '.$pharmNames[$pi % count($pharmNames)];
                if ($pi >= count($pharmNames)) {
                    $name .= ' '.($pi + 1);
                }
                $pi++;
                $this->pharmacistsByCenter[$centerName][] = Pharmacist::create([
                    'name' => $name.' - '.$centerName,
                    'phone' => $this->phone(),
                    'address' => mt_rand(1, 250).' شارع '.$this->pick($streets).'، '.$centerName,
                    'center_id' => $center->id,
                    'created_at' => $at, 'updated_at' => $at,
                ]);
            }

            for ($i = 0; $i < $doctorCount; $i++) {
                $this->doctorsByCenter[$centerName][] = Doctor::create([
                    'name' => 'د. '.$this->pick($firstNames).' '.$this->pick($lastNames),
                    'phone' => $this->phone(),
                    'address' => 'عيادة '.mt_rand(1, 120).' شارع '.$this->pick($streets).'، '.$centerName,
                    'speciality' => $this->pick($specialities),
                    'center_id' => $center->id,
                    'created_at' => $at, 'updated_at' => $at,
                ]);
            }
        }
    }

    // =====================================================================
    // Stock (inflow to main warehouses + transfers to sub warehouses)
    // =====================================================================

    private function seedStock(): void
    {
        $date = $this->today->copy()->subMonths(12)->subDays(5);
        $admin = $this->users['admin'];

        foreach ($this->drugs as $name => $drug) {
            if ($name === 'Sitagliptin 100mg Tabs') {
                continue; // لا يوجد أي رصيد
            }
            foreach (['main', 'main_alex'] as $mainKey) {
                $qty = $mainKey === 'main' ? mt_rand(6000, 9000) : mt_rand(2000, 3000);
                $wh = $this->warehouses[$mainKey];
                $this->stock[$wh->id][$drug->id] = $qty;
                $this->log('stock_added', 'Warehouse', $wh, "توريد مخزون إلى ({$wh->name}): {$drug->name} (كمية +{$qty})", $date, $admin, [
                    'new_values' => ['warehouse' => $wh->name, 'drug_id' => $drug->id, 'drug_name' => $drug->name, 'quantity' => $qty, 'operation' => 'stock_inflow'],
                    'url' => '/admin/warehouses/'.$wh->id.'/stock',
                    'method' => 'POST',
                ]);
            }
        }

        // تحويل من المخزن الرئيسي للمخازن الفرعية حسب الخطوط التي تخدمها مناطقها
        $subLines = [];
        foreach ($this->zones as $zone) {
            if ($zone->warehouse_id) {
                $subLines[$zone->warehouse_id][$zone->line] = true;
            }
        }

        // حالات مخزون منخفض/نافد متعمدة: [warehouse key, drug name, qty]
        $lowStock = [
            ['cairo', 'Insulin Glargine Pen', 15],
            ['giza', 'Cefixime 400mg Caps', 12],
            ['delta', 'Rosuvastatin 10mg Tabs', 10],
            ['alex', 'Esomeprazole 40mg Tabs', 8],
        ];

        foreach (['cairo', 'giza', 'alex', 'delta'] as $subKey) {
            $sub = $this->warehouses[$subKey];
            $parent = $sub->parent_id;
            foreach ($this->drugs as $name => $drug) {
                if (in_array($name, ['Sitagliptin 100mg Tabs', 'Ceftriaxone 1g Vial'])) {
                    continue;
                }
                if (empty($subLines[$sub->id][$drug->line])) {
                    continue;
                }
                $qty = mt_rand(500, 1200);
                foreach ($lowStock as [$lk, $ld, $lq]) {
                    if ($lk === $subKey && $ld === $name) {
                        $qty = $lq;
                    }
                }
                $this->stock[$parent][$drug->id] -= $qty;
                $this->stock[$sub->id][$drug->id] = ($this->stock[$sub->id][$drug->id] ?? 0) + $qty;

                $this->log('stock_added', 'Warehouse', $sub, "نقل مخزون إلى ({$sub->name}) من المخزن الرئيسي: {$drug->name} (كمية +{$qty})", $date->copy()->addDays(2), $admin, [
                    'new_values' => ['warehouse' => $sub->name, 'drug_id' => $drug->id, 'drug_name' => $drug->name, 'quantity' => $qty, 'operation' => 'transfer_from_parent'],
                    'url' => '/admin/warehouses/'.$sub->id.'/stock',
                    'method' => 'POST',
                ]);
            }
        }

        // حالة: صرف/مرتجع مخزون من مخزن فرعي إلى المخزن الرئيسي
        $giza = $this->warehouses['giza'];
        $drug = $this->drugs['Paracetamol 500mg Tabs'];
        $this->stock[$giza->id][$drug->id] -= 100;
        $this->stock[$giza->parent_id][$drug->id] += 100;
        $this->log('stock_returned', 'Warehouse', $giza, "إرجاع مخزون من ({$giza->name}) إلى المخزن الرئيسي: {$drug->name} (كمية -100)", $date->copy()->addMonths(3), $admin, [
            'new_values' => ['warehouse' => $giza->name, 'drug_id' => $drug->id, 'quantity' => 100, 'operation' => 'return_to_parent'],
            'method' => 'POST',
        ]);
    }

    // =====================================================================
    // Doctor deals
    // =====================================================================

    private function seedDeals(): void
    {
        $t = $this->today;
        $d = fn (string $name) => $this->drugs[$name]->id;

        // key => [center, doctorIdx, pharmIdx[], drugs[], target, commission, pct, start, status, prepaid, activeUntil, label, targetedInvoices]
        $defs = [
            // هدف ثابت تم تجاوزه ولم يُسوّى بعد (مستحق للصرف)
            'achieved_unpaid' => ['مدينة نصر', 0, [0, 1], [], 50000, 5000, 10, $t->copy()->subMonths(10), 2, 0, null, 9],
            // نفس الطبيب له اتفاق ثاني (نسبة مفتوحة على أصناف Line 2 محددة)
            'same_doctor_open' => ['مدينة نصر', 0, [2], [$d('Atorvastatin 20mg Tabs'), $d('Rosuvastatin 10mg Tabs')], null, null, 5, $t->copy()->subMonths(5), 2, 0, null, 4],
            // أصناف محددة + دفعة مقدمة جزئية
            'drug_specific_partial' => ['المعادي', 0, [0], [$d('Augmentin 1g Tabs'), $d('Azithromycin 500mg Tabs'), $d('Cefixime 400mg Caps')], 30000, 3000, 10, $t->copy()->subMonths(8), 3, 1000, null, 6],
            // نسبة مفتوحة بدون تارجت على كل الأصناف
            'open_percentage' => ['الدقي', 0, [0, 1], [], null, null, 7, $t->copy()->subMonths(9), 2, 0, null, 6],
            // مدفوع بالكامل مقدماً (خالص من البداية)
            'prepaid_full' => ['مصر الجديدة', 0, [0], [], 20000, 2000, 10, $t->copy()->subMonths(7), 1, 2000, null, 4],
            // تارجت كبير ونسبة إنجاز ضعيفة
            'low_progress' => ['المنصورة', 0, [0, 1], [], 150000, 15000, 10, $t->copy()->subMonths(3), 2, 0, null, 2],
            // اتفاق موقوف (غير نشط) - الفواتير بعد الإيقاف لا تُحتسب
            'paused' => ['شبرا', 0, [0], [], 40000, 4000, 10, $t->copy()->subMonths(11), 2, 0, $t->copy()->subMonths(6), 5],
            // اتفاق مؤرشف بعد تسويته
            'archived' => ['الهرم', 0, [0], [], 25000, 2500, 10, $t->copy()->subMonths(12), 2, 0, $t->copy()->subMonths(7), 5],
            // اتفاق يبدأ في المستقبل (0% إنجاز)
            'future' => ['سيدي جابر', 0, [0], [], 30000, 3000, 10, $t->copy()->addDays(20), 2, 0, null, 1],
            // تارجت محقق وتمت تسويته (markAsPaid)
            'settled' => ['المنصورة', 1, [2], [$d('Metformin 850mg Tabs'), $d('Gliclazide 60mg MR Tabs'), $d('Insulin Glargine Pen')], 15000, 1800, 12, $t->copy()->subMonths(9), 2, 0, null, 6],
            // مدفوع مقدماً أكثر من المستحق -> الطبيب مدين
            'overpaid' => ['الزقازيق', 0, [0], [], null, null, 5, $t->copy()->subMonths(6), 3, 6000, null, 2],
            // اتفاق على عدة صيدليات بأصناف Line 2
            'multi_pharmacy' => ['مدينة نصر', 1, [0, 1, 2], [$d('Amlodipine 5mg Tabs'), $d('Valsartan 160mg Tabs'), $d('Bisoprolol 5mg Tabs'), $d('Clopidogrel 75mg Tabs')], 60000, 6000, 10, $t->copy()->subMonths(6), 3, 2500, null, 7],
        ];

        foreach ($defs as $key => [$center, $docIdx, $pharmIdx, $drugIds, $target, $commission, $pct, $start, $status, $prepaid, $activeUntil, $targeted]) {
            $doctor = $this->doctorsByCenter[$center][$docIdx];
            $pharmacists = array_map(fn ($i) => $this->pharmacistsByCenter[$center][$i], $pharmIdx);
            $createdAt = $start->copy()->min($t)->setTime(10, 30);

            $deal = DoctorDeal::create([
                'doctor_id' => $doctor->id,
                'target_amount' => $target,
                'achieved_amount' => 0,
                'commission_amount' => $commission,
                'commission_percentage' => $pct,
                'paid_amount' => $prepaid,
                'status' => $status,
                'is_paid' => $status == 1,
                'is_active' => true,
                'is_archived' => false,
                'start_date' => $start->toDateString(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            foreach ($pharmacists as $p) {
                DB::table('deal_pharmacist')->insert(['doctor_deal_id' => $deal->id, 'pharmacist_id' => $p->id]);
            }
            foreach ($drugIds as $drugId) {
                DB::table('deal_drug')->insert(['doctor_deal_id' => $deal->id, 'drug_id' => $drugId]);
            }

            $this->log('created', 'DoctorDeal', $deal, "تم إضافة اتفاق مالي جديد للطبيب: {$doctor->name}", $createdAt, $this->users['admin'], [
                'new_values' => ['doctor_id' => $doctor->id, 'target_amount' => $target, 'commission_amount' => $commission, 'commission_percentage' => $pct, 'status' => $status, 'paid_amount' => $prepaid],
                'url' => '/admin/deals',
                'method' => 'POST',
            ]);

            if ($prepaid > 0) {
                $this->recordDealExpense($deal, $doctor, $drugIds, $prepaid, $createdAt);
            }

            $this->deals[$key] = [
                'model' => $deal,
                'doctor' => $doctor,
                'center' => $center,
                'pharmacist_ids' => array_map(fn ($p) => $p->id, $pharmacists),
                'drug_ids' => $drugIds,
                'start' => $start->copy()->startOfDay(),
                'active_until' => $activeUntil?->copy()->endOfDay(),
                'targeted' => $targeted,
            ];
        }
    }

    // =====================================================================
    // Invoices & payments
    // =====================================================================

    private function seedInvoices(): void
    {
        $specs = [];

        // 1) فواتير عامة موزعة على آخر 12 شهر
        $validCombos = [];
        foreach ($this->pharmacistsByCenter as $center => $pharmacists) {
            foreach ($pharmacists as $p) {
                foreach ([1, 2] as $line) {
                    if (isset($this->routes[$p->center_id.'-'.$line])) {
                        $validCombos[] = [$p, $line, $center];
                    }
                }
            }
        }

        for ($m = 12; $m >= 0; $m--) {
            $monthStart = $this->today->copy()->subMonths($m)->startOfMonth();
            $monthEnd = $m === 0 ? $this->today->copy() : $monthStart->copy()->endOfMonth();
            // نمو تدريجي في المبيعات
            $count = mt_rand(18, 24) + (12 - $m);
            if ($m === 0) {
                $count = (int) ceil($count * $this->today->day / $this->today->daysInMonth) + 2;
            }
            for ($i = 0; $i < $count; $i++) {
                [$p, $line, $center] = $this->pick($validCombos);
                $date = $this->randomDate($monthStart, $monthEnd);
                $specs[] = [
                    'date' => $date,
                    'pharmacist' => $p,
                    'line' => $line,
                    'doctor_ids' => $this->randomDoctorsFor($center),
                    'plan' => $this->randomPlan($date),
                    'must_drugs' => [],
                ];
            }
        }

        // 2) فواتير موجهة لاتفاقات الأطباء لضمان حالات التارجت المختلفة
        foreach ($this->deals as $key => $deal) {
            $from = $deal['start']->copy();
            $to = $this->today->copy(); // فواتير بعد إيقاف الاتفاق لا تُحتسب
            if ($key === 'future') {
                // فاتورة قبل بداية الاتفاق -> لا تُحتسب
                $from = $this->today->copy()->subDays(25);
                $to = $this->today->copy()->subDays(2);
            }
            if ($from->gt($to)) {
                continue;
            }
            $lines = $deal['drug_ids'] ? [$this->drugLine($deal['drug_ids'][0])] : [1, 2];
            for ($i = 0; $i < $deal['targeted']; $i++) {
                $pid = $this->pick($deal['pharmacist_ids']);
                $p = $this->findPharmacist($deal['center'], $pid);
                $line = $this->pick($lines);
                if (! isset($this->routes[$p->center_id.'-'.$line])) {
                    $line = $line === 1 ? 2 : 1;
                    if (! isset($this->routes[$p->center_id.'-'.$line]) || $deal['drug_ids']) {
                        continue;
                    }
                }
                $date = $this->randomDate($from, $to);
                $doctorIds = [$deal['doctor']->id];
                // بعض الفواتير لعدة أطباء
                if ($this->chance(25)) {
                    foreach ($this->doctorsByCenter[$deal['center']] as $doc) {
                        if ($doc->id !== $deal['doctor']->id) {
                            $doctorIds[] = $doc->id;
                            break;
                        }
                    }
                }
                $specs[] = [
                    'date' => $date,
                    'pharmacist' => $p,
                    'line' => $line,
                    'doctor_ids' => $doctorIds,
                    'plan' => $key === 'low_progress' ? 'cash'
                        : ($this->chance(80) ? $this->pick(['cash', 'cash', 'deferred_then_full', 'partial_then_full']) : $this->pick(['deferred', 'partial'])),
                    'must_drugs' => $deal['drug_ids'],
                ];
            }
        }

        // 3) حالات خاصة
        $nasr = $this->pharmacistsByCenter['مدينة نصر'][3];
        $specs[] = ['date' => $this->today->copy()->subMonths(2), 'pharmacist' => $nasr, 'line' => 1, 'doctor_ids' => [], 'plan' => 'cash', 'must_drugs' => [], 'discount' => 100, 'notes' => 'عينات مجانية - خصم 100%'];
        $specs[] = ['date' => $this->today->copy()->subMonths(1)->subDays(3), 'pharmacist' => $nasr, 'line' => 2, 'doctor_ids' => [], 'plan' => 'deferred_then_full', 'must_drugs' => [], 'items' => 12, 'notes' => 'فاتورة كبيرة - طلبية شهرية'];
        $specs[] = ['date' => $this->today->copy()->subMonths(11), 'pharmacist' => $this->pharmacistsByCenter['الهرم'][1], 'line' => 1, 'doctor_ids' => [], 'plan' => 'deferred', 'must_drugs' => [], 'notes' => 'فاتورة آجلة متأخرة جداً - متابعة تحصيل'];
        $specs[] = ['date' => $this->today->copy(), 'pharmacist' => $this->pharmacistsByCenter['الدقي'][2], 'line' => 1, 'doctor_ids' => [], 'plan' => 'cash', 'must_drugs' => [], 'notes' => 'فاتورة اليوم'];
        $specs[] = ['date' => $this->today->copy(), 'pharmacist' => $this->pharmacistsByCenter['المعادي'][1], 'line' => 2, 'doctor_ids' => [], 'plan' => 'partial', 'must_drugs' => []];
        $specs[] = ['date' => $this->today->copy()->subMonths(4), 'pharmacist' => $this->pharmacistsByCenter['المنتزه'][0], 'line' => 1, 'doctor_ids' => [], 'plan' => 'many_payments', 'must_drugs' => [], 'notes' => 'سداد على أقساط متعددة'];
        $specs[] = ['date' => $this->today->copy()->subDays(40), 'pharmacist' => $this->pharmacistsByCenter['المعادي'][2], 'line' => 1, 'doctor_ids' => [], 'plan' => 'cash', 'must_drugs' => [$this->drugs['Cefixime 400mg Caps']->id], 'discount' => 0, 'items' => 1];

        usort($specs, fn ($a, $b) => $a['date'] <=> $b['date']);

        foreach ($specs as $spec) {
            $this->createInvoice($spec);
        }
    }

    private function createInvoice(array $spec): ?Invoice
    {
        /** @var Pharmacist $pharmacist */
        $pharmacist = $spec['pharmacist'];
        $line = $spec['line'];
        $route = $this->routes[$pharmacist->center_id.'-'.$line] ?? null;
        if (! $route) {
            return null;
        }
        $zone = $route['zone'];
        $whId = $route['warehouse_id'];
        $date = $spec['date']->copy()->setTime(mt_rand(9, 17), mt_rand(0, 59));
        $user = $this->pick([$this->users['admin'], $this->users['admin'], $this->users['admin2']]);

        // اختيار الأصناف المتاحة في مخزن المنطقة
        $available = array_filter($this->drugs, fn (Drug $d) => $d->line == $line && ($this->stock[$whId][$d->id] ?? 0) > 0);
        if (! $available) {
            return null;
        }
        $itemsCount = $spec['items'] ?? mt_rand(2, 6);
        $chosen = [];
        foreach ($spec['must_drugs'] as $drugId) {
            if (($this->stock[$whId][$drugId] ?? 0) > 0) {
                $chosen[$drugId] = true;
            }
        }
        $keys = array_keys($available);
        shuffle($keys);
        foreach ($keys as $name) {
            if (count($chosen) >= $itemsCount) {
                break;
            }
            $chosen[$available[$name]->id] = true;
        }

        $items = [];
        foreach (array_keys($chosen) as $drugId) {
            $drug = $this->drugById($drugId);
            $qty = min(mt_rand(5, 60), $this->stock[$whId][$drugId]);
            if ($drug->price > 300) {
                $qty = min(mt_rand(2, 15), $this->stock[$whId][$drugId]);
            }
            if ($qty <= 0) {
                continue;
            }
            $disc = $spec['discount'] ?? $this->pick([0, 0, 5, 10, 10, 15, 15, 20, 25]);
            $items[] = [$drug, $qty, $disc];
        }
        if (! $items) {
            return null;
        }

        $this->serial++;
        $invoice = Invoice::create([
            'serial_number' => (string) $this->serial,
            'invoice_date' => $date->toDateString(),
            'line' => $line,
            'pharmacist_id' => $pharmacist->id,
            'representative_id' => $zone->sales_representative_id,
            'medical_representative_id' => $zone->medical_representative_id,
            'status' => 2,
            'notes' => $spec['notes'] ?? ($this->chance(15) ? $this->pick(['تسليم مع المندوب', 'العميل طلب التوريد صباحاً', 'خصم خاص لفتح حساب', 'طلبية عاجلة']) : null),
            'total_amount' => 0, 'total_discount' => 0, 'final_total' => 0, 'paid_amount' => 0, 'remaining_amount' => 0,
            'created_at' => $date, 'updated_at' => $date,
        ]);

        foreach (array_unique($spec['doctor_ids']) as $doctorId) {
            DB::table('doctor_invoice')->insert(['invoice_id' => $invoice->id, 'doctor_id' => $doctorId, 'created_at' => $date, 'updated_at' => $date]);
        }

        $total = 0;
        $discount = 0;
        foreach ($items as [$drug, $qty, $disc]) {
            $this->stock[$whId][$drug->id] -= $qty;
            $rowBefore = $drug->price * $qty;
            $rowDisc = $rowBefore * ($disc / 100);
            InvoiceDetail::create([
                'invoice_id' => $invoice->id, 'drug_id' => $drug->id, 'quantity' => $qty, 'returned_quantity' => 0,
                'unit_price' => $drug->price, 'pharmacist_discount_percentage' => $disc,
                'row_total' => round($rowBefore - $rowDisc, 2),
                'created_at' => $date, 'updated_at' => $date,
            ]);
            $total += $rowBefore;
            $discount += $rowDisc;
        }
        $final = round($total - $discount, 2);
        $invoice->update(['total_amount' => round($total, 2), 'total_discount' => round($discount, 2), 'final_total' => $final, 'updated_at' => $date]);

        $plan = $spec['plan'];
        $initialPaid = match (true) {
            $plan === 'cash' => $final,
            in_array($plan, ['partial', 'partial_then_full']) => round($final * mt_rand(20, 70) / 100, 0),
            default => 0,
        };
        if ($initialPaid > 0) {
            $this->addPayment($invoice, $initialPaid, $date, 'دفعة مقدمة عند إنشاء الفاتورة', $user, false);
        }
        $status = $this->recalcInvoice($invoice, $date);

        $this->log('created', 'Invoice', $invoice, "تم إضافة فاتورة مبيعات جديد: فاتورة #{$invoice->serial_number}", $date, $user, [
            'new_values' => ['serial_number' => $invoice->serial_number, 'pharmacist_id' => $pharmacist->id, 'line' => $line, 'final_total' => $final, 'status' => $status],
            'url' => '/admin/invoices',
            'method' => 'POST',
        ]);

        if ($status == 1) {
            $this->applyDealImpact($invoice, $date);
        }

        // دفعات لاحقة حسب خطة السداد
        $schedule = match ($plan) {
            'deferred_then_full', 'partial_then_full' => [[1.0, mt_rand(10, 45)]],
            'deferred_then_partial' => [[mt_rand(30, 60) / 100, mt_rand(10, 40)]],
            'many_payments' => [[0.25, 15], [0.25, 35], [0.2, 60], [1.0, 95]],
            default => [],
        };
        foreach ($schedule as [$fraction, $days]) {
            $payDate = $date->copy()->addDays($days);
            if ($payDate->gt($this->today)) {
                $payDate = $this->today->copy()->setTime(12, 0);
                if ($payDate->lt($date)) {
                    break;
                }
            }
            $invoice->refresh();
            $remaining = (float) $invoice->remaining_amount;
            $amount = $fraction >= 1.0 ? $remaining : round((float) $invoice->final_total * $fraction, 0);
            $amount = min($amount, $remaining);
            if ($amount <= 0) {
                break;
            }
            $this->addPayment($invoice, $amount, $payDate, $this->pick(['تحصيل نقدي', 'تحويل بنكي', 'شيك', 'تحصيل مع المندوب', null]), $user, true);
        }

        $this->invoices[] = $invoice->fresh();

        return $invoice;
    }

    private function addPayment(Invoice $invoice, float $amount, Carbon $date, ?string $notes, User $user, bool $recalc): void
    {
        $wasFullyPaid = (int) $invoice->fresh()->status === 1;
        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id, 'amount' => round($amount, 2), 'payment_date' => $date->toDateString(),
            'notes' => $notes, 'created_at' => $date, 'updated_at' => $date,
        ]);

        if (! $recalc) {
            return;
        }

        $status = $this->recalcInvoice($invoice, $date);
        $this->log('payment_added', 'InvoicePayment', $payment, "تمت إضافة دفعة بقيمة ".number_format($amount, 2)." ج.م للفاتورة #{$invoice->serial_number}", $date, $user, [
            'new_values' => ['invoice_id' => $invoice->id, 'amount' => round($amount, 2), 'payment_date' => $date->toDateString()],
            'url' => '/admin/invoices/'.$invoice->id.'/payments',
            'method' => 'POST',
        ]);

        if (! $wasFullyPaid && $status == 1) {
            $this->applyDealImpact($invoice, $date);
        }
    }

    private function recalcInvoice(Invoice $invoice, Carbon $date): int
    {
        $invoice->refresh();
        $paid = round((float) $invoice->payments()->sum('amount'), 2);
        $remaining = round((float) $invoice->final_total - $paid, 2);
        $status = 2;
        if ($remaining <= 0) {
            $remaining = 0;
            $status = 1;
        } elseif ($paid > 0) {
            $status = 3;
        }
        $invoice->update(['paid_amount' => $paid, 'remaining_amount' => $remaining, 'status' => $status, 'updated_at' => $date]);

        return $status;
    }

    /**
     * مطابق لـ InvoiceController::processDoctorDealImpact('increment')
     */
    private function applyDealImpact(Invoice $invoice, Carbon $eventDate): void
    {
        $doctorIds = DB::table('doctor_invoice')->where('invoice_id', $invoice->id)->pluck('doctor_id')->all();
        if (! $doctorIds) {
            return;
        }
        $details = InvoiceDetail::where('invoice_id', $invoice->id)->get();
        $invoiceDate = Carbon::parse($invoice->invoice_date);

        foreach ($this->deals as $deal) {
            if (! $this->dealAccepts($deal, $invoice, $doctorIds, $invoiceDate, $eventDate)) {
                continue;
            }
            $contribution = $this->contribution($deal, $details);
            if ($contribution <= 0) {
                continue;
            }
            $dealId = $deal['model']->id;
            DB::table('doctor_deals')->where('id', $dealId)->increment('achieved_amount', $contribution);
            DB::table('doctor_deal_invoices')->insert([
                'doctor_deal_id' => $dealId, 'invoice_id' => $invoice->id, 'contribution_amount' => $contribution,
                'created_at' => $eventDate, 'updated_at' => $eventDate,
            ]);
        }
    }

    private function dealAccepts(array $deal, Invoice $invoice, array $doctorIds, Carbon $invoiceDate, Carbon $eventDate): bool
    {
        return in_array($deal['doctor']->id, $doctorIds)
            && in_array($invoice->pharmacist_id, $deal['pharmacist_ids'])
            && $deal['start']->lte($invoiceDate)
            && (! $deal['active_until'] || $eventDate->lte($deal['active_until']))
            && ! DB::table('doctor_deal_invoices')->where('doctor_deal_id', $deal['model']->id)->where('invoice_id', $invoice->id)->exists();
    }

    private function contribution(array $deal, $details): float
    {
        $sum = 0;
        foreach ($details as $detail) {
            if (! $deal['drug_ids'] || in_array($detail->drug_id, $deal['drug_ids'])) {
                $sum += $detail->quantity * $detail->unit_price;
            }
        }

        return round($sum, 2);
    }

    // =====================================================================
    // Returns
    // =====================================================================

    private function seedReturns(): void
    {
        $used = [];
        $find = function (callable $filter, ?array $pool = null) use (&$used): ?Invoice {
            foreach ($pool ?? $this->invoices as $inv) {
                if (isset($used[$inv->id])) {
                    continue;
                }
                $inv->refresh();
                if ($filter($inv)) {
                    $used[$inv->id] = true;

                    return $inv;
                }
            }

            return null;
        };
        $hasDoctors = fn (Invoice $i) => DB::table('doctor_invoice')->where('invoice_id', $i->id)->exists();
        $hasDeals = fn (Invoice $i) => DB::table('doctor_deal_invoices')->where('invoice_id', $i->id)->exists();
        $detailCount = fn (Invoice $i) => InvoiceDetail::where('invoice_id', $i->id)->count();
        $old = fn (Invoice $i) => Carbon::parse($i->invoice_date)->lt($this->today->copy()->subDays(30));

        // 1) مرتجع جزئي من فاتورة خالصة -> استرداد نقدي
        if ($inv = $find(fn ($i) => $i->status == 1 && $old($i) && ! $hasDoctors($i) && $detailCount($i) >= 3 && $i->final_total > 0)) {
            $this->processReturn($inv, 'partial_one', 'مرتجع جزئي - عبوات تالفة');
        }
        // 2) مرتجع كلي لفاتورة خالصة -> صافي الفاتورة صفر واسترداد كامل
        if ($inv = $find(fn ($i) => $i->status == 1 && $old($i) && ! $hasDoctors($i) && $i->final_total > 0)) {
            $this->processReturn($inv, 'full', 'مرتجع كلي - إلغاء الطلبية');
        }
        // 3) مرتجع من فاتورة آجلة -> يقلل المتبقي بدون استرداد
        if ($inv = $find(fn ($i) => $i->status == 2 && $detailCount($i) >= 2)) {
            $this->processReturn($inv, 'partial_one', 'مرتجع من فاتورة آجلة - أصناف قريبة الانتهاء');
        }
        // 4) مرتجع كبير من فاتورة جزئية يجعل المدفوع أكبر من الصافي -> استرداد وتصبح خالصة
        if ($inv = $find(fn ($i) => $i->status == 3 && $i->paid_amount > $i->final_total * 0.5 && $detailCount($i) >= 2)) {
            $this->processReturn($inv, 'most', 'مرتجع كبير من فاتورة مسدد جزء منها');
        }
        // 5) مرتجعين على نفس الفاتورة (RET-x-1, RET-x-2)
        if ($inv = $find(fn ($i) => $i->status == 1 && $old($i) && ! $hasDoctors($i) && $detailCount($i) >= 3)) {
            $this->processReturn($inv, 'partial_one', 'مرتجع أول');
            $this->processReturn($inv->fresh(), 'partial_one', 'مرتجع ثاني على نفس الفاتورة');
        }
        // 6) مرتجع على فاتورة داخلة في تارجت طبيب -> تقل مساهمة الاتفاق
        if ($inv = $find(fn ($i) => $i->status == 1 && $hasDeals($i) && $old($i))) {
            $this->processReturn($inv, 'partial_one', 'مرتجع على فاتورة مرتبطة باتفاق طبيب');
        }
        // 7) مرتجع صغير من فاتورة جزئية تظل جزئية
        if ($inv = $find(fn ($i) => $i->status == 3 && $i->paid_amount < $i->final_total * 0.4 && $detailCount($i) >= 2)) {
            $this->processReturn($inv, 'small', 'مرتجع بسيط');
        }
        // 8) مرتجع كلي على فاتورة آجلة -> تصبح خالصة بصافي صفر
        if ($inv = $find(fn ($i) => $i->status == 2 && ! $hasDoctors($i))) {
            $this->processReturn($inv, 'full', 'رفض استلام الطلبية بالكامل');
        }
        // 9) مرتجعات عشوائية إضافية
        $pool = $this->invoices;
        shuffle($pool);
        for ($k = 0; $k < 12; $k++) {
            if ($inv = $find(fn ($i) => $i->final_total > 0, $pool)) {
                $this->processReturn($inv, $this->pick(['partial_one', 'small']), $this->pick([null, 'مرتجع تالف', 'خطأ في الطلبية', 'قرب انتهاء الصلاحية']));
            }
        }
    }

    /**
     * مطابق لـ InvoiceReturnController::store
     */
    private function processReturn(Invoice $invoice, string $mode, ?string $notes): ?InvoiceReturn
    {
        $details = InvoiceDetail::where('invoice_id', $invoice->id)->where('quantity', '>', 0)->get();
        if ($details->isEmpty()) {
            return null;
        }

        $qtys = [];
        if ($mode === 'full') {
            foreach ($details as $d) {
                $qtys[$d->id] = $d->quantity;
            }
        } elseif ($mode === 'most') {
            foreach ($details as $d) {
                $qtys[$d->id] = (int) ceil($d->quantity * 0.8);
            }
        } else {
            $d = $this->pick($details->all());
            $qtys[$d->id] = $mode === 'small' ? 1 : max(1, (int) floor($d->quantity / 2));
        }

        $lastPayment = InvoicePayment::where('invoice_id', $invoice->id)->max('payment_date');
        $lastReturn = InvoiceReturn::where('invoice_id', $invoice->id)->max('return_date');
        $base = Carbon::parse(max(array_filter([$invoice->invoice_date, $lastPayment, $lastReturn ? Carbon::parse($lastReturn)->toDateString() : null])));
        $date = $base->copy()->addDays(mt_rand(1, 12))->setTime(mt_rand(9, 17), mt_rand(0, 59));
        if ($date->gt($this->today->copy()->endOfDay())) {
            $date = $this->today->copy()->setTime(15, 0);
        }

        $user = $this->pick([$this->users['admin'], $this->users['admin2']]);
        $route = $this->routes[$invoice->pharmacist->center_id.'-'.$invoice->line];
        $whId = $route['warehouse_id'];
        $returnNumber = 'RET-'.$invoice->serial_number.'-'.(InvoiceReturn::where('invoice_id', $invoice->id)->count() + 1);

        $ret = InvoiceReturn::create([
            'return_number' => $returnNumber, 'invoice_id' => $invoice->id, 'return_date' => $date->toDateString(),
            'total_return_public_price' => 0, 'total_return_discount' => 0, 'total_return_amount' => 0, 'refund_amount' => 0,
            'notes' => $notes, 'created_by' => $user->id, 'created_at' => $date, 'updated_at' => $date,
        ]);

        $totPublic = 0;
        $totDisc = 0;
        foreach ($details as $detail) {
            $qty = $qtys[$detail->id] ?? 0;
            if ($qty <= 0) {
                continue;
            }
            $this->stock[$whId][$detail->drug_id] = ($this->stock[$whId][$detail->drug_id] ?? 0) + $qty;

            $rowPublic = $detail->unit_price * $qty;
            $rowDisc = $rowPublic * ($detail->pharmacist_discount_percentage / 100);
            $totPublic += $rowPublic;
            $totDisc += $rowDisc;

            InvoiceReturnItem::create([
                'invoice_return_id' => $ret->id, 'invoice_detail_id' => $detail->id, 'drug_id' => $detail->drug_id,
                'quantity' => $qty, 'unit_price' => $detail->unit_price,
                'pharmacist_discount_percentage' => $detail->pharmacist_discount_percentage,
                'subtotal_public' => round($rowPublic, 2), 'discount_amount' => round($rowDisc, 2),
                'subtotal_net' => round($rowPublic - $rowDisc, 2),
                'created_at' => $date, 'updated_at' => $date,
            ]);

            $newQty = $detail->quantity - $qty;
            $detail->update([
                'quantity' => $newQty,
                'returned_quantity' => $detail->returned_quantity + $qty,
                'row_total' => round($newQty * $detail->unit_price * (1 - $detail->pharmacist_discount_percentage / 100), 2),
                'updated_at' => $date,
            ]);
        }
        $net = round($totPublic - $totDisc, 2);
        $ret->update(['total_return_public_price' => round($totPublic, 2), 'total_return_discount' => round($totDisc, 2), 'total_return_amount' => $net]);

        // إعادة حساب الفاتورة
        $newPublic = (float) InvoiceDetail::where('invoice_id', $invoice->id)->sum(DB::raw('quantity * unit_price'));
        $newDisc = (float) InvoiceDetail::where('invoice_id', $invoice->id)->sum(DB::raw('quantity * unit_price * pharmacist_discount_percentage / 100'));
        $newFinal = round($newPublic - $newDisc, 2);

        $paid = (float) InvoicePayment::where('invoice_id', $invoice->id)->sum('amount');
        $refund = 0;
        if ($paid > $newFinal) {
            $refund = round($paid - $newFinal, 2);
            $toDeduct = $refund;
            foreach (InvoicePayment::where('invoice_id', $invoice->id)->orderByDesc('id')->get() as $payment) {
                if ($toDeduct <= 0) {
                    break;
                }
                if ($payment->amount <= $toDeduct) {
                    $toDeduct = round($toDeduct - $payment->amount, 2);
                    $payment->delete();
                } else {
                    $payment->update([
                        'amount' => round($payment->amount - $toDeduct, 2),
                        'notes' => trim(($payment->notes ? $payment->notes.' | ' : '')."تم تعديل المبلغ بخصم {$toDeduct} ج.م لاسترداد نقدي لمرتجع {$returnNumber}"),
                        'updated_at' => $date,
                    ]);
                    $toDeduct = 0;
                }
            }
            $ret->update(['refund_amount' => $refund]);
        }

        $finalPaid = (float) InvoicePayment::where('invoice_id', $invoice->id)->sum('amount');
        $remaining = round(max(0, $newFinal - $finalPaid), 2);
        $status = 2;
        if ($newFinal <= 0 || $remaining <= 0) {
            $remaining = 0;
            $status = 1;
        } elseif ($finalPaid > 0) {
            $status = 3;
        }
        $invoice->update([
            'total_amount' => round($newPublic, 2), 'total_discount' => round($newDisc, 2), 'final_total' => $newFinal,
            'paid_amount' => $finalPaid, 'remaining_amount' => $remaining, 'status' => $status, 'updated_at' => $date,
        ]);

        $this->syncDealsAfterReturn($invoice->fresh(), $status, $date);

        $this->log('returned', 'InvoiceReturn', $ret, "تم تسجيل مرتجع مبيعات رقم ({$returnNumber}) للفاتورة #{$invoice->serial_number} بصافي قيمة ".number_format($net, 2).' ج.م واسترداد نقدي بقيمة '.number_format($refund, 2).' ج.م.', $date, $user, [
            'new_values' => ['return_number' => $returnNumber, 'invoice_id' => $invoice->id, 'invoice_serial' => $invoice->serial_number, 'returned_amount' => $net, 'refund_amount' => $refund, 'new_invoice_net' => $newFinal, 'new_paid' => $finalPaid, 'new_remaining' => $remaining],
            'url' => '/admin/invoices/'.$invoice->id.'/returns',
            'method' => 'POST',
        ]);

        return $ret;
    }

    /**
     * مطابق لـ InvoiceReturnController::syncDoctorDeals
     */
    private function syncDealsAfterReturn(Invoice $invoice, int $status, Carbon $date): void
    {
        $attached = DB::table('doctor_deal_invoices')->where('invoice_id', $invoice->id)->get();

        if ($status != 1) {
            foreach ($attached as $pivot) {
                DB::table('doctor_deals')->where('id', $pivot->doctor_deal_id)->decrement('achieved_amount', $pivot->contribution_amount);
            }
            DB::table('doctor_deal_invoices')->where('invoice_id', $invoice->id)->delete();

            return;
        }

        $details = InvoiceDetail::where('invoice_id', $invoice->id)->get();
        if ($attached->isNotEmpty()) {
            foreach ($attached as $pivot) {
                $deal = collect($this->deals)->first(fn ($d) => $d['model']->id == $pivot->doctor_deal_id);
                $new = $this->contribution($deal, $details);
                $diff = round($new - $pivot->contribution_amount, 2);
                if ($diff != 0) {
                    DB::table('doctor_deals')->where('id', $pivot->doctor_deal_id)->increment('achieved_amount', $diff);
                    DB::table('doctor_deal_invoices')->where('id', $pivot->id)->update(['contribution_amount' => $new, 'updated_at' => $date]);
                }
            }
        } else {
            $this->applyDealImpact($invoice, $date);
        }
    }

    // =====================================================================
    // Deal settlement / pause / archive
    // =====================================================================

    private function finalizeDeals(): void
    {
        $admin = $this->users['admin'];

        // تسوية الاتفاق المحقق (markAsPaid)
        $this->settleDeal('settled', $this->today->copy()->subDays(5)->setTime(13, 0));

        // تسوية ثم أرشفة
        $archivedAt = $this->deals['archived']['active_until']->copy()->setTime(11, 0);
        $this->settleDeal('archived', $archivedAt);
        $deal = $this->deals['archived']['model']->fresh();
        $deal->update(['is_archived' => true, 'is_active' => false, 'updated_at' => $archivedAt]);
        $this->log('deal_toggled', 'DoctorDeal', $deal, "تم أرشفة الاتفاق المالي رقم #{$deal->id}", $archivedAt, $admin, [
            'changes' => ['is_archived' => ['old' => false, 'new' => true], 'is_active' => ['old' => true, 'new' => false]],
            'url' => '/admin/deals/'.$deal->id.'/toggle-archive', 'method' => 'POST',
        ]);

        // إيقاف اتفاق
        $pausedAt = $this->deals['paused']['active_until']->copy()->setTime(16, 0);
        $deal = $this->deals['paused']['model']->fresh();
        $deal->update(['is_active' => false, 'updated_at' => $pausedAt]);
        $this->log('deal_toggled', 'DoctorDeal', $deal, "تم إيقاف الاتفاق المالي رقم #{$deal->id}", $pausedAt, $admin, [
            'changes' => ['is_active' => ['old' => true, 'new' => false]],
            'url' => '/admin/deals/'.$deal->id.'/toggle-active', 'method' => 'POST',
        ]);

        // دفعة جزئية إضافية (تعديل الاتفاق status 3) على اتفاق التارجت المحقق
        $deal = $this->deals['achieved_unpaid']['model']->fresh();
        $at = $this->today->copy()->subDays(20)->setTime(12, 0);
        $deal->update(['status' => 3, 'paid_amount' => 1500, 'updated_at' => $at]);
        $this->recordDealExpense($deal, $this->deals['achieved_unpaid']['doctor'], [], 1500, $at);
        $this->log('updated', 'DoctorDeal', $deal, "تم تعديل اتفاق مالي: {$this->deals['achieved_unpaid']['doctor']->name}", $at, $admin, [
            'old_values' => ['status' => 2, 'paid_amount' => '0.00'], 'new_values' => ['status' => 3, 'paid_amount' => 1500],
            'changes' => ['status' => ['old' => 2, 'new' => 3], 'paid_amount' => ['old' => '0.00', 'new' => 1500]],
            'url' => '/admin/deals/'.$deal->id, 'method' => 'PUT',
        ]);
    }

    /**
     * مطابق لـ DoctorCommissionService::settleDeal
     */
    private function settleDeal(string $key, Carbon $at): void
    {
        $meta = $this->deals[$key];
        $deal = $meta['model']->fresh();
        $commission = (float) $deal->target_amount > 0
            ? (float) $deal->commission_amount
            : round((float) $deal->achieved_amount * ((float) $deal->commission_percentage / 100), 2);
        $diff = max(0, round($commission - (float) $deal->paid_amount, 2));

        $deal->update(['is_paid' => true, 'status' => 1, 'paid_amount' => $commission, 'updated_at' => $at]);
        if ($diff > 0) {
            $this->recordDealExpense($deal, $meta['doctor'], $meta['drug_ids'], $diff, $at);
        }
        $this->log('deal_paid', 'DoctorDeal', $deal, "تمت تسوية الاتفاق المالي رقم #{$deal->id} للطبيب ({$meta['doctor']->name}) وصرف باقي العمولة ({$diff} ج.م) كمصروف منطقة.", $at, $this->users['admin'], [
            'url' => '/admin/deals/'.$deal->id.'/mark-paid', 'method' => 'POST',
        ]);
    }

    /**
     * مطابق لـ DoctorCommissionService::recordDealExpense
     */
    private function recordDealExpense(DoctorDeal $deal, Doctor $doctor, array $drugIds, float $amount, Carbon $at): void
    {
        $line = $drugIds ? $this->drugLine($drugIds[0]) : 1;
        $zone = Zone::where('line', $line)->whereHas('centers', fn ($q) => $q->where('centers.id', $doctor->center_id))->first()
            ?? Zone::whereHas('centers', fn ($q) => $q->where('centers.id', $doctor->center_id))->first();
        if (! $zone) {
            return;
        }
        ZoneExpense::create([
            'zone_id' => $zone->id, 'amount' => $amount,
            'description' => 'دفعة من اتفاق مالي (عمولة) للطبيب: '.$doctor->name,
            'expense_date' => $at->toDateString(), 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    // =====================================================================
    // Expenses
    // =====================================================================

    private function seedExpenses(): void
    {
        $zoneDescriptions = [
            ['بدل انتقالات المناديب', 1500, 4000],
            ['وقود سيارة التوزيع', 2000, 5000],
            ['رعاية مؤتمر طبي', 8000, 25000],
            ['عينات مجانية للأطباء', 1000, 6000],
            ['هدايا دعائية (أقلام ونوت)', 500, 2500],
            ['ضيافة يوم طبي', 1200, 3500],
            ['صيانة سيارة التوزيع', 800, 4500],
        ];
        $accountant = $this->users['accountant'];

        foreach ($this->zones as $key => $zone) {
            if ($key === 'sharqia_l2') {
                continue; // حالة: منطقة بدون أي مصروفات
            }
            for ($m = 11; $m >= 0; $m--) {
                $n = mt_rand(1, 3);
                for ($i = 0; $i < $n; $i++) {
                    [$desc, $min, $max] = $this->pick($zoneDescriptions);
                    $start = $this->today->copy()->subMonths($m)->startOfMonth();
                    $end = $m === 0 ? $this->today->copy() : $start->copy()->endOfMonth();
                    $date = $this->randomDate($start, $end)->setTime(14, 0);
                    $exp = ZoneExpense::create([
                        'zone_id' => $zone->id, 'amount' => mt_rand($min, $max), 'description' => $desc,
                        'expense_date' => $date->toDateString(), 'created_at' => $date, 'updated_at' => $date,
                    ]);
                    $this->log('created', 'ZoneExpense', $exp, "تم إضافة مصروف منطقة جديد: {$desc}", $date, $accountant, [
                        'new_values' => ['zone_id' => $zone->id, 'amount' => $exp->amount, 'description' => $desc],
                        'url' => '/admin/zones/'.$zone->id.'/expenses', 'method' => 'POST',
                    ]);
                }
            }
        }

        $monthly = [
            ['رواتب الموظفين', 85000, 98000],
            ['إيجار المقر الرئيسي', 25000, 25000],
            ['كهرباء ومياه', 2500, 4800],
            ['إنترنت واتصالات', 1200, 1800],
        ];
        $occasional = [
            ['صيانة أجهزة كمبيوتر', 1500, 6000],
            ['أدوات مكتبية', 400, 1500],
            ['مصاريف بنكية', 150, 900],
            ['حملة تسويق رقمي', 5000, 15000],
            ['تجديد تراخيص', 3000, 9000],
        ];
        for ($m = 11; $m >= 0; $m--) {
            $monthDate = $this->today->copy()->subMonths($m)->startOfMonth()->addDays(mt_rand(0, 4))->setTime(11, 0);
            if ($monthDate->gt($this->today)) {
                $monthDate = $this->today->copy()->setTime(11, 0);
            }
            $rows = $monthly;
            if ($this->chance(70)) {
                $rows[] = $this->pick($occasional);
            }
            foreach ($rows as [$desc, $min, $max]) {
                $date = $monthDate->copy()->addDays(mt_rand(0, 10))->min($this->today->copy()->setTime(11, 0));
                $exp = GeneralExpense::create([
                    'amount' => mt_rand($min, $max), 'description' => $desc, 'expense_date' => $date->toDateString(),
                    'created_at' => $date, 'updated_at' => $date,
                ]);
                $this->log('created', 'GeneralExpense', $exp, "تم إضافة مصروف عام جديد: {$desc}", $date, $accountant, [
                    'new_values' => ['amount' => $exp->amount, 'description' => $desc],
                    'url' => '/admin/general-expenses', 'method' => 'POST',
                ]);
            }
        }
        // حالة: مصروف استثنائي كبير
        $date = $this->today->copy()->subMonths(5)->setTime(10, 0);
        GeneralExpense::create(['amount' => 150000, 'description' => 'دفعة مقدم سيارة توزيع جديدة', 'expense_date' => $date->toDateString(), 'created_at' => $date, 'updated_at' => $date]);
    }

    // =====================================================================
    // Misc audit (auth, exports, updates, deletes, denied)
    // =====================================================================

    private function seedMiscAudit(): void
    {
        $authUsers = ['admin', 'admin2', 'accountant', 'viewer'];
        for ($day = 90; $day >= 0; $day--) {
            $date = $this->today->copy()->subDays($day);
            if ($date->isFriday()) {
                continue;
            }
            foreach ($authUsers as $key) {
                if (! $this->chance($key === 'admin' ? 90 : 55)) {
                    continue;
                }
                $user = $this->users[$key];
                $in = $date->copy()->setTime(mt_rand(8, 10), mt_rand(0, 59));
                $this->log('login_success', 'Auth', $user, "تسجيل دخول ناجح للمستخدم: {$user->name}", $in, $user, ['url' => '/login', 'method' => 'POST']);
                if ($this->chance(60)) {
                    $this->log('logout', 'Auth', $user, "تسجيل خروج للمستخدم: {$user->name}", $in->copy()->addHours(mt_rand(3, 8)), $user, ['url' => '/logout', 'method' => 'POST']);
                }
            }
            // محاولات دخول فاشلة
            if ($this->chance(12)) {
                $email = $this->pick(['peter@gmail.com', 'viewer@pharma.test', 'admin@pharma.com', 'test@test.com']);
                $this->log('login_failed', 'Auth', null, "محاولة تسجيل دخول فاشلة بالبريد: {$email}", $date->copy()->setTime(mt_rand(0, 23), mt_rand(0, 59)), null, [
                    'outcome' => 'failed', 'url' => '/login', 'method' => 'POST', 'new_values' => ['email' => $email],
                ]);
            }
        }

        $admin = $this->users['admin'];
        $viewer = $this->users['viewer'];

        // محاولة وصول مرفوضة
        $this->log('updated', 'Invoice', null, 'محاولة تعديل فاتورة من حساب بصلاحية عرض فقط - تم الرفض', $this->today->copy()->subDays(9)->setTime(13, 5), $viewer, [
            'outcome' => 'denied', 'url' => '/admin/invoices/1/edit', 'method' => 'GET',
        ]);

        // عمليات تصدير
        foreach (['Invoice' => '/admin/invoices/export', 'DoctorDeal' => '/admin/deals?export=1', 'InvoiceReturn' => '/admin/returns?export=1', 'Drug' => '/admin/drugs/export'] as $module => $url) {
            $this->log('exported', $module, null, 'تصدير بيانات ('.$module.') إلى ملف CSV', $this->today->copy()->subDays(mt_rand(1, 60))->setTime(15, 30), $this->pick([$admin, $viewer]), ['url' => $url, 'method' => 'GET']);
        }

        // تعديل سعر دواء (مع diff)
        $drug = $this->drugs['Paracetamol 500mg Tabs'];
        $this->log('updated', 'Drug', $drug, "تم تعديل دواء: {$drug->name}", $this->today->copy()->subMonths(6)->setTime(10, 0), $admin, [
            'old_values' => ['price' => '16.00'], 'new_values' => ['price' => '18.00'],
            'changes' => ['price' => ['old' => '16.00', 'new' => '18.00']],
            'url' => '/admin/drugs/'.$drug->id, 'method' => 'PUT',
        ]);

        // تعديل صلاحية مستخدم
        $u = $this->users['accountant2'];
        $this->log('updated', 'User', $u, "تم تعديل مستخدم: {$u->name}", $this->today->copy()->subMonths(3)->setTime(9, 45), $admin, [
            'old_values' => ['role' => 'viewer'], 'new_values' => ['role' => 'accountant'],
            'changes' => ['role' => ['old' => 'viewer', 'new' => 'accountant']],
            'url' => '/admin/users/'.$u->id, 'method' => 'PUT',
        ]);

        // حذف صيدلية (كيان لم يعد موجوداً)
        $this->audit[] = $this->auditRow('deleted', 'Pharmacist', Pharmacist::class, 9999, 'صيدلية مغلقة - تجريبي', 'تم حذف صيدلية: صيدلية مغلقة - تجريبي', $this->today->copy()->subMonths(2)->setTime(12, 0), $admin, [
            'old_values' => ['name' => 'صيدلية مغلقة - تجريبي', 'phone' => '01000000000'], 'url' => '/admin/pharmacists/9999', 'method' => 'DELETE',
        ]);

        // حذف دفعة
        $this->audit[] = $this->auditRow('payment_deleted', 'InvoicePayment', InvoicePayment::class, 99999, 'دفعة مسجلة بالخطأ', 'تم حذف دفعة مسجلة بالخطأ بقيمة 500.00 ج.م', $this->today->copy()->subDays(15)->setTime(14, 20), $admin, [
            'old_values' => ['amount' => 500], 'url' => '/admin/invoices/payments/99999', 'method' => 'DELETE',
        ]);
    }

    // =====================================================================
    // Flush buffered data
    // =====================================================================

    private function flushStock(): void
    {
        $rows = [];
        $at = $this->today->copy()->subMonths(12);
        foreach ($this->stock as $whId => $drugs) {
            foreach ($drugs as $drugId => $qty) {
                $rows[] = ['warehouse_id' => $whId, 'drug_id' => $drugId, 'quantity' => max(0, $qty), 'created_at' => $at, 'updated_at' => now()];
            }
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('drug_warehouse')->insert($chunk);
        }
    }

    private function flushAudit(): void
    {
        usort($this->audit, fn ($a, $b) => $a['created_at'] <=> $b['created_at']);
        foreach (array_chunk($this->audit, 300) as $chunk) {
            DB::table('audit_logs')->insert($chunk);
        }
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function log(string $action, string $module, ?Model $entity, string $description, Carbon $at, ?User $user, array $extra = []): void
    {
        $this->audit[] = $this->auditRow(
            $action,
            $module,
            $entity ? get_class($entity) : null,
            $entity?->getKey(),
            $entity ? AuditService::resolveEntityName($entity) : null,
            $description,
            $at,
            $user,
            $extra
        );
    }

    private function auditRow(string $action, string $module, ?string $entityType, $entityId, ?string $entityName, string $description, Carbon $at, ?User $user, array $extra): array
    {
        $json = fn ($v) => $v === null ? null : json_encode($v, JSON_UNESCAPED_UNICODE);

        return [
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'نظام / زائر',
            'user_role' => $user?->role ?? 'system',
            'action' => $action,
            'module' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_name' => $entityName,
            'description' => $description,
            'old_values' => $json($extra['old_values'] ?? null),
            'new_values' => $json($extra['new_values'] ?? null),
            'changes' => $json($extra['changes'] ?? null),
            'outcome' => $extra['outcome'] ?? 'success',
            'ip_address' => $this->pick(self::IPS),
            'user_agent' => $this->pick(self::USER_AGENTS),
            'url' => rtrim((string) config('app.url'), '/').($extra['url'] ?? '/admin'),
            'method' => $extra['method'] ?? 'GET',
            'created_at' => $at->copy(),
        ];
    }

    private function randomPlan(Carbon $date): string
    {
        $age = $date->diffInDays($this->today);
        if ($age < 20) {
            return $this->weighted(['cash' => 35, 'deferred' => 35, 'partial' => 20, 'deferred_then_full' => 10]);
        }

        return $this->weighted([
            'cash' => 40, 'deferred' => 10, 'deferred_then_full' => 22, 'deferred_then_partial' => 8,
            'partial_then_full' => 12, 'partial' => 6, 'many_payments' => 2,
        ]);
    }

    private function randomDoctorsFor(string $center): array
    {
        $doctors = $this->doctorsByCenter[$center] ?? [];
        if (! $doctors || $this->chance(40)) {
            return [];
        }
        shuffle($doctors);
        $n = $this->chance(20) && count($doctors) > 1 ? 2 : 1;

        return array_map(fn ($d) => $d->id, array_slice($doctors, 0, $n));
    }

    private function findPharmacist(string $center, int $id): Pharmacist
    {
        foreach ($this->pharmacistsByCenter[$center] as $p) {
            if ($p->id === $id) {
                return $p;
            }
        }
        throw new \RuntimeException("Pharmacist {$id} not found");
    }

    private function drugById(int $id): Drug
    {
        foreach ($this->drugs as $d) {
            if ($d->id === $id) {
                return $d;
            }
        }
        throw new \RuntimeException("Drug {$id} not found");
    }

    private function drugLine(int $id): int
    {
        return (int) $this->drugById($id)->line;
    }

    private function randomDate(Carbon $from, Carbon $to): Carbon
    {
        return Carbon::createFromTimestamp(mt_rand($from->timestamp, max($from->timestamp, $to->timestamp)))->startOfDay();
    }

    private function phone(): string
    {
        return $this->pick(['010', '011', '012', '015']).str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    private function pick(array $items)
    {
        return $items[array_rand($items)];
    }

    private function chance(int $percent): bool
    {
        return mt_rand(1, 100) <= $percent;
    }

    private function weighted(array $weights): string
    {
        $r = mt_rand(1, array_sum($weights));
        foreach ($weights as $key => $w) {
            $r -= $w;
            if ($r <= 0) {
                return $key;
            }
        }

        return array_key_first($weights);
    }

    private function printSummary(): void
    {
        $this->command?->info('تم إنشاء البيانات التجريبية بنجاح:');
        $this->command?->table(['الجدول', 'العدد'], collect([
            'users', 'provinces', 'centers', 'zones', 'warehouses', 'drugs', 'drug_warehouse', 'representatives',
            'pharmacists', 'doctors', 'doctor_deals', 'invoices', 'invoice_details', 'invoice_payments',
            'invoice_returns', 'invoice_return_items', 'doctor_deal_invoices', 'zone_expenses', 'general_expenses', 'audit_logs',
        ])->map(fn ($t) => [$t, DB::table($t)->count()])->all());
        $this->command?->line('كلمة المرور لكل الحسابات: '.self::PASSWORD);
    }
}
