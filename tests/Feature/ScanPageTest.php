<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPointWallet;
use App\Models\OrgNode;
use App\Models\ProductQrcode;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * ทดสอบหน้าสแกนสำหรับลูกค้าปลายทาง
 *
 * ครอบคลุม
 *   - เข้าหน้าสแกนได้โดยไม่ต้องล็อกอิน
 *   - สแกนแล้วแต้มเข้ากระเป๋าของ "ร้านผู้ออกแต้ม" ถูกต้อง
 *   - สแกนซ้ำไม่ได้
 *   - ต้องยอมรับเงื่อนไขก่อน
 *   - บันทึกตำแหน่ง GPS ทั้งกรณีอนุญาตและปฏิเสธ
 *   - scan session token (กัน refresh/reuse)
 */
class ScanPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    /** เตรียม QR ที่พร้อมสแกน พร้อมกำหนดร้านผู้ออกแต้ม */
    private function readyQr(string $secret = 'TEST1234'): ProductQrcode
    {
        $shop = OrgNode::where('code', 'SH-001')->firstOrFail();

        $qr = ProductQrcode::whereNull('redeemed_at')->firstOrFail();
        $qr->status = 'sold';
        $qr->secret_hash = hash('sha256', $secret);
        $qr->issuer_node_id = $shop->id;
        $qr->activated_node_id = $shop->id;
        $qr->save();

        return $qr->fresh();
    }

    /**
     * เปิดหน้า scan form เพื่อสร้าง scan session token
     * คืนค่า scan_token ที่ต้องใช้กับ POST /scan
     *
     * @param  string|null  $qrToken  ถ้าระบุ จะเปิด /s/{token} แทน /scan
     */
    private function openScanForm(?string $qrToken = null): string
    {
        $url = $qrToken ? '/s/' . $qrToken : '/scan';
        $this->get($url)->assertOk();

        // ดึง scan_token จาก session (key: roamembers.scan_token)
        $scanToken = Session::get('roamembers.scan_token');
        $this->assertNotEmpty($scanToken, 'Session ต้องมี scan_token หลังเปิด form');

        return $scanToken;
    }

    public function test_เข้าหน้าสแกนได้โดยไม่ต้องล็อกอิน(): void
    {
        $this->get('/scan')
            ->assertOk()
            ->assertSee('RoaMembers')
            ->assertSee('เบอร์โทรศัพท์');
    }

    public function test_เปิดลิงก์จาก_qr_แล้วเห็นชื่อสินค้าและแต้ม(): void
    {
        $qr = $this->readyQr();

        $this->get('/s/' . $qr->qr_token)
            ->assertOk()
            ->assertSee('สแกนแล้วรับทันที', false);
    }

    public function test_สแกนสำเร็จแล้วแต้มเข้ากระเป๋าของร้านผู้ออกแต้ม(): void
    {
        $qr = $this->readyQr();
        $shop = OrgNode::where('code', 'SH-001')->firstOrFail();

        $scanToken = $this->openScanForm($qr->qr_token);

        $this->post('/scan', [
            '_scan_token' => $scanToken,
            'token'       => $qr->qr_token,
            'phone'       => '0812345678',
            'name'        => 'สมหญิง',
            'secret'      => 'TEST1234',
            'consent'     => '1',
            'geo_status'  => 'granted',
            'lat'         => 13.7563,
            'lng'         => 100.5018,
            'accuracy'    => 25,
        ])->assertRedirect(route('scan.result'));

        $customer = Customer::where('phone', '0812345678')->firstOrFail();

        // แต้มต้องเข้ากระเป๋าของร้าน ไม่ใช่คลัง
        $wallet = CustomerPointWallet::where('customer_id', $customer->id)
            ->where('issuer_node_id', $shop->id)
            ->first();

        $this->assertNotNull($wallet, 'ต้องมีกระเป๋าแต้มของร้านผู้ออก');
        $this->assertSame((int) $qr->points, (int) $wallet->balance);

        // ต้องมีล็อตแต้มพร้อมวันหมดอายุ
        $this->assertDatabaseHas('point_lots', [
            'wallet_id'   => $wallet->id,
            'points_in'   => $qr->points,
            'points_left' => $qr->points,
        ]);

        // QR ต้องถูกทำเครื่องหมายว่าใช้แล้ว
        $this->assertDatabaseHas('product_qrcodes', [
            'id'                      => $qr->id,
            'status'                  => 'redeemed',
            'redeemed_by_customer_id' => $customer->id,
        ]);
    }

    public function test_สแกน_qr_เดิมซ้ำไม่ได้(): void
    {
        $qr = $this->readyQr();

        // ── สแกนครั้งแรก ──────────────────────────────────────────
        $scanToken1 = $this->openScanForm($qr->qr_token);

        $this->post('/scan', [
            '_scan_token' => $scanToken1,
            'token'       => $qr->qr_token,
            'phone'       => '0812345678',
            'secret'      => 'TEST1234',
            'consent'     => '1',
        ])->assertRedirect(route('scan.result'));

        // ── สแกนครั้งที่สอง (เปิด form ใหม่) ────────────────────────
        $scanToken2 = $this->openScanForm($qr->qr_token);

        $this->post('/scan', [
            '_scan_token' => $scanToken2,
            'token'       => $qr->qr_token,
            'phone'       => '0812345678',
            'secret'      => 'TEST1234',
            'consent'     => '1',
        ])->assertRedirect(route('scan.result'));

        // ครั้งที่สองต้องถูกบันทึกว่าใช้ไปแล้ว
        $this->assertDatabaseHas('qr_scan_logs', [
            'raw_token' => $qr->qr_token,
            'result'    => 'already_used',
        ]);

        // แต้มต้องได้แค่ครั้งเดียว
        $customer = Customer::where('phone', '0812345678')->firstOrFail();
        $total = CustomerPointWallet::where('customer_id', $customer->id)->sum('balance');
        $this->assertSame((int) $qr->points, (int) $total);
    }

    public function test_ต้องยอมรับเงื่อนไขก่อนถึงจะสแกนได้(): void
    {
        $qr = $this->readyQr();
        $scanToken = $this->openScanForm($qr->qr_token);

        $this->post('/scan', [
            '_scan_token' => $scanToken,
            'token'       => $qr->qr_token,
            'phone'       => '0812345678',
            'secret'      => 'TEST1234',
            // ไม่ส่ง consent
        ])->assertSessionHasErrors('consent');

        $this->assertDatabaseMissing('customer_point_wallets', []);
    }

    public function test_รหัสใต้ฟิล์มผิดต้องไม่ได้แต้ม(): void
    {
        $qr = $this->readyQr();
        $scanToken = $this->openScanForm($qr->qr_token);

        $this->post('/scan', [
            '_scan_token' => $scanToken,
            'token'       => $qr->qr_token,
            'phone'       => '0812345678',
            'secret'      => 'WRONGCODE',
            'consent'     => '1',
        ])->assertRedirect(route('scan.result'));

        $this->assertDatabaseHas('qr_scan_logs', [
            'raw_token' => $qr->qr_token,
            'result'    => 'invalid',
        ]);

        // QR ต้องยังใช้ได้อยู่
        $this->assertDatabaseHas('product_qrcodes', [
            'id'     => $qr->id,
            'status' => 'sold',
        ]);
    }

    public function test_เบอร์โทรผิดรูปแบบต้องถูกปฏิเสธ(): void
    {
        $qr = $this->readyQr();
        $scanToken = $this->openScanForm($qr->qr_token);

        $this->post('/scan', [
            '_scan_token' => $scanToken,
            'token'       => $qr->qr_token,
            'phone'       => '12345',
            'consent'     => '1',
        ])->assertSessionHasErrors('phone');
    }

    public function test_บันทึกตำแหน่งแม้ผู้ใช้ปฏิเสธการแชร์(): void
    {
        $qr = $this->readyQr();
        $scanToken = $this->openScanForm($qr->qr_token);

        $this->post('/scan', [
            '_scan_token' => $scanToken,
            'token'       => $qr->qr_token,
            'phone'       => '0812345678',
            'secret'      => 'TEST1234',
            'consent'     => '1',
            'geo_status'  => 'denied',
        ])->assertRedirect(route('scan.result'));

        // ต้องบันทึกว่าถูกปฏิเสธ แต่ยังให้แต้มตามปกติ
        $this->assertDatabaseHas('scan_geo_logs', ['permission' => 'denied']);

        $customer = Customer::where('phone', '0812345678')->firstOrFail();
        $this->assertGreaterThan(
            0,
            CustomerPointWallet::where('customer_id', $customer->id)->sum('balance'),
        );
    }

    public function test_ดูกระเป๋าแต้มได้หลังยืนยันเบอร์(): void
    {
        $qr = $this->readyQr();
        $scanToken = $this->openScanForm($qr->qr_token);

        $this->post('/scan', [
            '_scan_token' => $scanToken,
            'token'       => $qr->qr_token,
            'phone'       => '0812345678',
            'name'        => 'สมหญิง',
            'secret'      => 'TEST1234',
            'consent'     => '1',
        ]);

        $this->get('/scan/wallet')
            ->assertOk()
            ->assertSee('แต้มสะสมทั้งหมด')
            ->assertSee('สมหญิง');
    }

    public function test_ยังไม่ยืนยันเบอร์เข้ากระเป๋าไม่ได้(): void
    {
        $this->get('/scan/wallet')->assertRedirect(route('scan.form'));
    }

    public function test_ดาวน์โหลดประวัติได้(): void
    {
        $qr = $this->readyQr();
        $scanToken = $this->openScanForm($qr->qr_token);

        $this->post('/scan', [
            '_scan_token' => $scanToken,
            'token'       => $qr->qr_token,
            'phone'       => '0812345678',
            'secret'      => 'TEST1234',
            'consent'     => '1',
        ]);

        $this->get('/scan/statement')
            ->assertOk()
            ->assertSee('ประวัติแต้มสะสม');
    }

    public function test_ออกจากระบบแล้วต้องยืนยันเบอร์ใหม่(): void
    {
        $qr = $this->readyQr();
        $scanToken = $this->openScanForm($qr->qr_token);

        $this->post('/scan', [
            '_scan_token' => $scanToken,
            'token'       => $qr->qr_token,
            'phone'       => '0812345678',
            'secret'      => 'TEST1234',
            'consent'     => '1',
        ]);

        $this->get('/scan/wallet')->assertOk();
        $this->post('/scan/forget')->assertRedirect(route('scan.form'));
        $this->get('/scan/wallet')->assertRedirect(route('scan.form'));
    }

    // ─── ทดสอบ scan session token ─────────────────────────────

    public function test_submit_โดยไม่มี_scan_token_ถูกปฏิเสธ(): void
    {
        $qr = $this->readyQr();

        // POST ตรงโดยไม่เปิด form ก่อน → ไม่มี scan_token ใน session
        $this->post('/scan', [
            'token'   => $qr->qr_token,
            'phone'   => '0812345678',
            'secret'  => 'TEST1234',
            'consent' => '1',
        ])->assertRedirect(route('scan.form'));
    }

    public function test_submit_ด้วย_scan_token_ปลอมถูกปฏิเสธ(): void
    {
        $qr = $this->readyQr();

        // เปิด form ก่อน
        $this->get('/scan')->assertOk();

        // ส่ง token ปลอม
        $this->post('/scan', [
            '_scan_token' => 'fake-token-12345',
            'token'       => $qr->qr_token,
            'phone'       => '0812345678',
            'secret'      => 'TEST1234',
            'consent'     => '1',
        ])->assertRedirect(route('scan.form'));
    }

    public function test_scan_token_ใช้ซ้ำไม่ได้(): void
    {
        $qr = $this->readyQr();
        $scanToken = $this->openScanForm($qr->qr_token);

        // ใช้ token เดิมส่ง 2 ครั้ง — ครั้งแรก OK
        $payload = [
            '_scan_token' => $scanToken,
            'token'       => $qr->qr_token,
            'phone'       => '0812345678',
            'secret'      => 'TEST1234',
            'consent'     => '1',
        ];

        $this->post('/scan', $payload)->assertRedirect(route('scan.result'));

        // ครั้งที่สอง — token เดิม → ต้องถูกปฏิเสธ (token ถูกลบจาก session แล้ว)
        $this->post('/scan', $payload)->assertRedirect(route('scan.form'));
    }
}
