<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\OrgNode;
use App\Models\ProductQrcode;
use App\Services\PointEarningService;
use App\Services\QrScanService;
use App\Services\ScanGeoService;
use App\Services\SecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * หน้าสแกน QR สำหรับลูกค้าปลายทาง — เป็นหน้าสาธารณะ ไม่ต้องล็อกอินระบบหลังบ้าน
 *
 * เส้นทาง: QR บนซองสินค้าพิมพ์ลิงก์ /s/{token} ลูกค้าสแกนแล้วมาที่นี่
 *
 * การยืนยันตัวตน (เลือกอย่างใดอย่างหนึ่ง)
 *   - กรอกเบอร์โทร (บังคับ) + ชื่อ (ไม่บังคับ)
 *   - เข้าด้วย LINE หรือ Google = สมัครอัตโนมัติ
 *
 * ลูกค้าถูกจำไว้ใน session เพื่อไม่ต้องกรอกซ้ำทุกครั้ง
 */
class ScanController extends Controller
{
    private const SESSION_KEY = 'roamembers.customer_id';

    public function __construct(
        private QrScanService $scanner,
        private PointEarningService $earning,
        private ScanGeoService $geo,
        private SecurityService $security,
    ) {
    }

    /** หน้าแรกหลังสแกน QR */
    public function form(Request $request, ?string $token = null): View
    {
        $token = $token ?? $request->query('token');
        $customer = $this->currentCustomer($request);

        // ดูข้อมูลสินค้าให้ลูกค้าเห็นก่อนกดรับแต้ม
        $preview = null;

        if ($token) {
            $qr = ProductQrcode::with('product')->where('qr_token', $token)->first();

            if ($qr) {
                $preview = [
                    'product' => $qr->product?->name,
                    'points'  => (int) $qr->points,
                    'used'    => in_array($qr->status?->value ?? $qr->status, ['redeemed', 'void'], true),
                ];
            }
        }

        return view('scan.form', [
            'token'    => $token,
            'customer' => $customer,
            'preview'  => $preview,
            'wallets'  => $customer ? $this->earning->wallets($customer) : collect(),
        ]);
    }

    /** ยิงสแกนจริง */
    public function submit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token'      => ['required', 'string', 'max:120'],
            'phone'      => ['required', 'string', 'regex:/^0[0-9]{8,9}$/'],
            'secret'     => ['nullable', 'string', 'max:60'],
            'name'       => ['nullable', 'string', 'max:120'],
            'consent'    => ['accepted'],
            // ตำแหน่งที่เบราว์เซอร์ส่งมา (อาจไม่มีถ้าผู้ใช้ปฏิเสธ)
            'lat'        => ['nullable', 'numeric', 'between:-90,90'],
            'lng'        => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy'   => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'geo_status' => ['nullable', 'in:granted,denied,unavailable'],
        ], [
            'phone.regex'      => 'กรุณากรอกเบอร์โทรให้ถูกต้อง (ขึ้นต้น 0 ตามด้วยตัวเลข 9-10 หลัก)',
            'consent.accepted' => 'กรุณายอมรับเงื่อนไขก่อนรับแต้ม',
        ]);

        $customer = Customer::firstOrCreate(
            ['phone' => $data['phone']],
            [
                'name'           => ($data['name'] ?? '') ?: 'ลูกค้า ' . substr($data['phone'], -4),
                'points_balance' => 0,
                'status'         => 'active',
            ],
        );

        // เติมชื่อให้ถ้าเพิ่งกรอกมาทีหลัง
        if (! empty($data['name']) && str_starts_with((string) $customer->name, 'ลูกค้า ')) {
            $customer->update(['name' => $data['name']]);
        }

        $result = $this->scanner->scan(
            token: $data['token'],
            customer: $customer,
            secret: $data['secret'] ?? null,
            context: ['ip' => $request->ip(), 'user_agent' => $request->userAgent()],
        );

        // บันทึกตำแหน่งไม่ว่าสแกนจะสำเร็จหรือไม่ (ใช้ตรวจพฤติกรรมผิดปกติ)
        $this->geo->record(
            customer: $customer,
            scanLogId: $result['scan_log_id'] ?? null,
            lat: $data['lat'] ?? null,
            lng: $data['lng'] ?? null,
            accuracy: $data['accuracy'] ?? null,
            permission: $data['geo_status'] ?? 'denied',
            request: $request,
        );

        $request->session()->put(self::SESSION_KEY, $customer->id);

        return redirect()
            ->route('scan.result')
            ->with('scan_result', $result);
    }

    /** หน้าแสดงผลหลังสแกน */
    public function result(Request $request): View|RedirectResponse
    {
        $result = $request->session()->get('scan_result');

        if (! $result) {
            return redirect()->route('scan.form');
        }

        $customer = $this->currentCustomer($request);

        return view('scan.result', [
            'result'   => $result,
            'customer' => $customer,
            'wallets'  => $customer ? $this->earning->wallets($customer) : collect(),
            'total'    => $customer ? $this->earning->totalBalance($customer) : 0,
            'expiring' => $customer ? $this->earning->expiringSoon($customer, 30) : collect(),
        ]);
    }

    /** กระเป๋าแต้ม + ประวัติ */
    public function wallet(Request $request): View|RedirectResponse
    {
        $customer = $this->currentCustomer($request);

        if (! $customer) {
            return redirect()->route('scan.form')
                ->withErrors(['phone' => 'กรุณายืนยันเบอร์โทรก่อนดูกระเป๋าแต้ม']);
        }

        return view('scan.wallet', [
            'customer'    => $customer,
            'wallets'     => $this->earning->wallets($customer),
            'total'       => $this->earning->totalBalance($customer),
            'expiring'    => $this->earning->expiringSoon($customer, 30),
            'redemptions' => $customer->redemptions()
                ->with('shop')
                ->latest('redeemed_at')
                ->limit(20)
                ->get(),
            'scans'       => $customer->scanLogs()
                ->with('qrcode.product')
                ->where('result', 'success')
                ->latest('scanned_at')
                ->limit(20)
                ->get(),
        ]);
    }

    /**
     * ดาวน์โหลดประวัติเป็นไฟล์
     *
     * ใช้ HTML ที่สั่งพิมพ์เป็น PDF ได้จากเบราว์เซอร์
     * (ไม่ต้องลง package PDF เพิ่ม ลดพื้นที่และช่องโหว่)
     */
    public function statement(Request $request): View|RedirectResponse
    {
        $customer = $this->currentCustomer($request);

        if (! $customer) {
            return redirect()->route('scan.form');
        }

        return view('scan.statement', [
            'customer'    => $customer,
            'wallets'     => $this->earning->wallets($customer),
            'total'       => $this->earning->totalBalance($customer),
            'redemptions' => $customer->redemptions()->with('shop')->latest('redeemed_at')->limit(200)->get(),
            'scans'       => $customer->scanLogs()->with('qrcode.product')
                ->where('result', 'success')->latest('scanned_at')->limit(200)->get(),
            'printedAt'   => now(),
        ]);
    }

    /** ออกจากระบบ (ลืมเบอร์ที่จำไว้) */
    public function forget(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('scan.form')->with('status', 'ออกจากระบบเรียบร้อย');
    }

    /** ลูกค้าที่จำไว้ใน session */
    private function currentCustomer(Request $request): ?Customer
    {
        $id = $request->session()->get(self::SESSION_KEY);

        return $id ? Customer::find($id) : null;
    }
}
