<?php

namespace Tests\Feature;

use App\Exceptions\RedemptionException;
use App\Models\Customer;
use App\Models\CustomerPointWallet;
use App\Models\OrgNode;
use App\Models\PointLot;
use App\Models\ShopMonthlyAllowance;
use App\Models\ShopPackage;
use App\Models\ShopSubscription;
use App\Models\StockBalance;
use App\Services\RedemptionService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ทดสอบด่านป้องกัน over-pay ของระบบแลกแต้ม
 *
 * ต้องกันได้ทั้ง 2 ด้าน
 *   1) แต้มของลูกค้าไม่พอ
 *   2) วงเงินรายเดือนของร้านไม่พอ
 * และการแลกสินค้าต้องอ้างล็อตได้เสมอ
 */
class RedemptionGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    /** สร้างร้านพร้อมสมาชิกและวงเงินรายเดือน */
    private function makeShop(int $monthlyLimit = 10000, string $subStatus = 'active'): array
    {
        $agent = OrgNode::where('level_id', 4)->firstOrFail();

        $shop = OrgNode::create([
            'parent_id' => $agent->id,
            'level_id'  => 5,
            'code'      => 'SH-T' . random_int(1000, 9999),
            'name'      => 'ร้านทดสอบ',
            'status'    => 'active',
        ]);

        $package = ShopPackage::where('code', 'PKG-SILVER')->firstOrFail();

        $sub = ShopSubscription::create([
            'code'                => 'SUB-T' . random_int(1000, 9999),
            'shop_node_id'        => $shop->id,
            'package_id'          => $package->id,
            'recruiter_node_id'   => $agent->id,
            'monthly_point_limit' => $monthlyLimit,
            'price_paid'          => $package->price,
            'commission_amount'   => $package->commissionFor(),
            'starts_on'           => now()->subDay(),
            'ends_on'             => now()->addYear(),
            'status'              => $subStatus,
            'paid_at'             => now(),
        ]);

        $allowance = ShopMonthlyAllowance::create([
            'subscription_id'  => $sub->id,
            'shop_node_id'     => $shop->id,
            'period_ym'        => now()->format('Y-m'),
            'limit_points'     => $monthlyLimit,
            'remaining_points' => $monthlyLimit,
        ]);

        return [$shop, $sub, $allowance];
    }

    /** สร้างลูกค้าที่มีแต้มจากร้านผู้ออกแต้ม */
    private function makeCustomer(OrgNode $issuer, int $points): array
    {
        $customer = Customer::create([
            'phone'  => '08' . random_int(10000000, 99999999),
            'name'   => 'ลูกค้าทดสอบ',
            'status' => 'active',
        ]);

        $wallet = CustomerPointWallet::create([
            'customer_id'     => $customer->id,
            'issuer_node_id'  => $issuer->id,
            'balance'         => $points,
            'lifetime_earned' => $points,
        ]);

        PointLot::create([
            'wallet_id'   => $wallet->id,
            'points_in'   => $points,
            'points_left' => $points,
            'earned_at'   => now(),
            'expires_at'  => now()->addMonths(12),
            'source_type' => 'scan',
        ]);

        return [$customer, $wallet];
    }

    private function issuer(): OrgNode
    {
        return OrgNode::where('level_id', 5)->firstOrFail();
    }

    private function service(): RedemptionService
    {
        return app(RedemptionService::class);
    }

    /** สร้างสินค้าและล็อตสำหรับทดสอบ (ไม่พึ่ง id ตายตัว) */
    private function makeProductLot(): array
    {
        $productId = DB::table('products')->value('id');

        if (! $productId) {
            $productId = DB::table('products')->insertGetId([
                'sku'        => 'SKU-T' . random_int(1000, 9999),
                'name'       => 'สินค้าทดสอบ',
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $lotId = DB::table('product_lots')->insertGetId([
            'product_id'   => $productId,
            'lot_no'       => 'LOT-' . random_int(1000, 9999),
            'expiry_date'  => now()->addMonths(6)->toDateString(),
            'qty_produced' => 100,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return [$productId, $lotId];
    }

    public function test_แลกแต้มสำเร็จเมื่อแต้มและวงเงินพอ(): void
    {
        [$shop] = $this->makeShop(10000);
        $issuer = $this->issuer();
        [$customer, $wallet] = $this->makeCustomer($issuer, 1000);

        $redemption = $this->service()->redeem(
            $customer, $issuer, $shop, 800, 'ล้างรถ', 'service',
        );

        $this->assertSame(800, (int) $redemption->points_used);
        $this->assertSame('confirmed', $redemption->status);
        $this->assertEquals(200.0, (float) $redemption->cash_value);

        $this->assertSame(200, (int) $wallet->fresh()->balance);

        $alw = ShopMonthlyAllowance::where('shop_node_id', $shop->id)->first();
        $this->assertSame(800, (int) $alw->used_points);
        $this->assertSame(9200, (int) $alw->remaining_points);
    }

    public function test_แลกไม่ได้เมื่อแต้มลูกค้าไม่พอ(): void
    {
        [$shop] = $this->makeShop(10000);
        $issuer = $this->issuer();
        [$customer] = $this->makeCustomer($issuer, 500);

        $this->expectException(RedemptionException::class);

        try {
            $this->service()->redeem($customer, $issuer, $shop, 900, 'ล้างรถ', 'service');
        } finally {
            // วงเงินร้านต้องไม่ถูกแตะต้อง
            $alw = ShopMonthlyAllowance::where('shop_node_id', $shop->id)->first();
            $this->assertSame(0, (int) $alw->used_points);
        }
    }

    public function test_แลกไม่ได้เมื่อวงเงินร้านไม่พอ(): void
    {
        [$shop] = $this->makeShop(500);
        $issuer = $this->issuer();
        [$customer, $wallet] = $this->makeCustomer($issuer, 5000);

        $this->expectException(RedemptionException::class);

        try {
            $this->service()->redeem($customer, $issuer, $shop, 1000, 'ล้างรถ', 'service');
        } finally {
            $this->assertSame(5000, (int) $wallet->fresh()->balance);
        }
    }

    public function test_แลกไม่ได้เมื่อสมาชิกร้านหมดอายุ(): void
    {
        [$shop] = $this->makeShop(10000, 'expired');
        $issuer = $this->issuer();
        [$customer] = $this->makeCustomer($issuer, 5000);

        $this->expectException(RedemptionException::class);
        $this->service()->redeem($customer, $issuer, $shop, 500, 'ล้างรถ', 'service');
    }

    public function test_บันทึกความพยายามที่ล้มเหลวไว้เป็นหลักฐาน(): void
    {
        [$shop] = $this->makeShop(10000);
        $issuer = $this->issuer();
        [$customer] = $this->makeCustomer($issuer, 100);

        try {
            $this->service()->redeem($customer, $issuer, $shop, 900, 'ล้างรถ', 'service');
        } catch (RedemptionException) {
            // คาดว่าจะโยน
        }

        $this->assertDatabaseHas('redemption_attempts', [
            'customer_id' => $customer->id,
            'result'      => 'insufficient_customer_points',
        ]);
    }

    public function test_หักแต้มแบบ_fifo_ใช้ล็อตที่ใกล้หมดอายุก่อน(): void
    {
        [$shop] = $this->makeShop(10000);
        $issuer = $this->issuer();
        [$customer, $wallet] = $this->makeCustomer($issuer, 500);

        PointLot::create([
            'wallet_id'   => $wallet->id,
            'points_in'   => 500,
            'points_left' => 500,
            'earned_at'   => now(),
            'expires_at'  => now()->addMonths(24),
            'source_type' => 'scan',
        ]);
        $wallet->update(['balance' => 1000]);

        $this->service()->redeem($customer, $issuer, $shop, 600, 'ล้างรถ', 'service');

        $lots = PointLot::where('wallet_id', $wallet->id)->orderBy('expires_at')->get();

        $this->assertSame(0, (int) $lots[0]->points_left);
        $this->assertSame(400, (int) $lots[1]->points_left);
    }

    public function test_การแลกสินค้าต้องระบุรายการสินค้า(): void
    {
        [$shop] = $this->makeShop(10000);
        $issuer = $this->issuer();
        [$customer] = $this->makeCustomer($issuer, 5000);

        $this->expectException(RedemptionException::class);
        $this->service()->redeem($customer, $issuer, $shop, 500, 'ขนม', 'goods', []);
    }

    public function test_การแลกบริการต้องไม่มีรายการสินค้า(): void
    {
        [$shop] = $this->makeShop(10000);
        $issuer = $this->issuer();
        [$customer] = $this->makeCustomer($issuer, 5000);

        [$productId] = $this->makeProductLot();

        $this->expectException(RedemptionException::class);
        $this->service()->redeem(
            $customer, $issuer, $shop, 500, 'ล้างรถ', 'service',
            [['product_id' => $productId, 'lot_id' => null, 'qty' => 1]],
        );
    }

    public function test_แลกสินค้าแล้วตัดสต๊อกและบันทึกล็อต(): void
    {
        [$shop] = $this->makeShop(10000);
        $issuer = $this->issuer();
        [$customer] = $this->makeCustomer($issuer, 5000);

        [$productId, $lotId] = $this->makeProductLot();

        StockBalance::create([
            'org_node_id' => $shop->id,
            'product_id'  => $productId,
            'lot_id'      => $lotId,
            'qty_on_hand' => 10,
        ]);

        $redemption = $this->service()->redeem(
            $customer, $issuer, $shop, 400, 'ขนม 2 ชิ้น', 'goods',
            [['product_id' => $productId, 'lot_id' => $lotId, 'qty' => 2]],
        );

        $this->assertSame(
            8,
            (int) StockBalance::where('org_node_id', $shop->id)->first()->qty_on_hand,
        );

        $this->assertDatabaseHas('redemption_items', [
            'redemption_id' => $redemption->id,
            'lot_id'        => $lotId,
            'qty'           => 2,
        ]);
    }

    public function test_แลกสินค้าไม่ได้เมื่อสต๊อกไม่พอ(): void
    {
        [$shop] = $this->makeShop(10000);
        $issuer = $this->issuer();
        [$customer, $wallet] = $this->makeCustomer($issuer, 5000);

        [$productId, $lotId] = $this->makeProductLot();

        StockBalance::create([
            'org_node_id' => $shop->id,
            'product_id'  => $productId,
            'lot_id'      => $lotId,
            'qty_on_hand' => 1,
        ]);

        $this->expectException(RedemptionException::class);

        try {
            $this->service()->redeem(
                $customer, $issuer, $shop, 400, 'ขนม', 'goods',
                [['product_id' => $productId, 'lot_id' => $lotId, 'qty' => 5]],
            );
        } finally {
            // ต้อง rollback ทั้งหมด
            $this->assertSame(5000, (int) $wallet->fresh()->balance);
        }
    }
}
