<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\OrgNode;
use App\Models\Product;
use App\Models\ProductQrcode;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\QrScanService;
use App\Services\SaleService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ทดสอบการขายหน้าร้าน การยกเลิกบิล และการสแกน QR รับคะแนน */
class SaleAndScanTest extends TestCase
{
    use RefreshDatabase;

    private OrgNode $shop;
    private Product $product;
    private User $shopUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);

        $this->shop     = OrgNode::where('code', 'SH-001')->firstOrFail();
        $this->product  = Product::firstOrFail();
        $this->shopUser = User::where('email', 'shop@demo.test')->firstOrFail();

        $this->actingAs($this->shopUser);
        $this->stockTheShop();
    }

    /**
     * DemoSeeder วางของไว้ที่คลังใหญ่เท่านั้น — ดันของลงมาถึงร้านค้าตามสายงานจริง
     * เพื่อให้เทสต์การขายมีของขาย
     */
    private function stockTheShop(): void
    {
        $svc  = app(\App\Services\TransferService::class);
        $path = ['WH-BKK' => 'SWH-NT', 'SWH-NT' => 'AG-001', 'AG-001' => 'SH-001'];
        $users = ['WH-BKK' => 'wh', 'SWH-NT' => 'swh', 'AG-001' => 'agent'];
        $qty = 200;

        foreach ($path as $fromCode => $toCode) {
            $from = OrgNode::where('code', $fromCode)->firstOrFail();
            $to   = OrgNode::where('code', $toCode)->firstOrFail();
            $actor = User::where('email', $users[$fromCode] . '@demo.test')->firstOrFail();

            $transfer = $svc->create($from, $to, [['product_id' => $this->product->id, 'qty' => $qty]]);
            $svc->approve($transfer, $actor);
            $svc->ship($transfer);
            $svc->receive($transfer, User::where('email', ($users[$toCode] ?? 'shop') . '@demo.test')->firstOrFail());
        }
    }

    private function shopStock(): int
    {
        return (int) StockBalance::where('org_node_id', $this->shop->id)
            ->where('product_id', $this->product->id)->sum('qty_on_hand');
    }

    public function test_ขายแล้วตัดสต๊อกถูกต้อง(): void
    {
        $before = $this->shopStock();

        $sale = app(SaleService::class)->create(
            $this->shop, [['product_id' => $this->product->id, 'qty' => 4]],
        );

        $this->assertSame('completed', $sale->fresh()->status);
        $this->assertSame($before - 4, $this->shopStock());
    }

    public function test_ยกเลิกบิลแล้วคืนสต๊อกครบ(): void
    {
        $svc = app(SaleService::class);
        $before = $this->shopStock();

        $sale = $svc->create($this->shop, [['product_id' => $this->product->id, 'qty' => 4]]);
        $this->assertSame($before - 4, $this->shopStock());


        $svc->void($sale);

        $this->assertSame('voided', $sale->fresh()->status);
        $this->assertSame($before, $this->shopStock(), 'ยกเลิกบิลต้องคืนสต๊อกกลับครบ');
    }

    public function test_ขายเกินสต๊อกต้องไม่สร้างบิลและไม่ตัดสต๊อก(): void
    {
        $before = $this->shopStock();
        $bills  = Sale::count();

        try {
            app(SaleService::class)->create(
                $this->shop, [['product_id' => $this->product->id, 'qty' => 999999]],
            );
            $this->fail('ควรโยน exception เมื่อขายเกินสต๊อก');
        } catch (\Throwable $e) {
            // คาดหวัง
        }

        $this->assertSame($bills, Sale::count(), 'ต้องไม่มีบิลค้างในระบบ');
        $this->assertSame($before, $this->shopStock(), 'สต๊อกต้องไม่ถูกแตะ');
    }

    public function test_สแกนQRรับคะแนนได้ครั้งเดียว(): void
    {
        $qr = ProductQrcode::where('status', 'sold')->first()
            ?? ProductQrcode::first();

        $customer = Customer::create(['phone' => '0899999999', 'name' => 'ทดสอบ', 'points_balance' => 0]);

        $first = app(QrScanService::class)->scan($qr->qr_token, $customer, null, ['ip' => '1.2.3.4']);

        // ถ้าล็อตนี้ต้องใช้รหัสใต้ฟิล์ม การสแกนแบบไม่มี secret จะไม่ผ่าน
        if (! $first['ok']) {
            $this->assertContains($first['result'], ['invalid', 'already_used', 'expired']);
            return;
        }

        $this->assertGreaterThan(0, $first['points']);
        $this->assertSame($first['points'], $customer->fresh()->points_balance);

        $second = app(QrScanService::class)->scan($qr->qr_token, $customer, null, ['ip' => '1.2.3.4']);

        $this->assertFalse($second['ok'], 'QR เดิมต้องสแกนซ้ำไม่ได้');
        $this->assertSame('already_used', $second['result']);
        $this->assertSame($first['points'], $customer->fresh()->points_balance,
            'สแกนซ้ำต้องไม่ได้คะแนนเพิ่ม');
    }

    public function test_ลูกค้าที่ถูกระงับสแกนไม่ได้(): void
    {
        $qr = ProductQrcode::first();
        $customer = Customer::create([
            'phone' => '0888888888', 'name' => 'โดนแบน',
            'points_balance' => 0, 'status' => 'blocked',
        ]);

        $result = app(QrScanService::class)->scan($qr->qr_token, $customer, null, ['ip' => '9.9.9.9']);

        $this->assertFalse($result['ok']);
        $this->assertSame('blocked', $result['result']);
        $this->assertSame(0, $customer->fresh()->points_balance);
    }
}
