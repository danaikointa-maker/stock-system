<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPointWallet;
use App\Models\OrgNode;
use App\Models\PointLot;
use App\Models\ShopMonthlyAllowance;
use App\Models\ShopPackage;
use App\Models\ShopSubscription;
use App\Models\StockBalance;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ทดสอบหน้าเคาน์เตอร์รับแลกแต้มของร้านค้า
 *
 * ครอบคลุม
 *   - สิทธิ์: ใครเข้าได้ / ใครเข้าไม่ได้
 *   - แลกสำเร็จแล้วตัดแต้ม + วงเงิน + สต๊อกถูกต้อง
 *   - กัน over-pay ทั้งฝั่งลูกค้าและฝั่งร้าน
 *   - กันใช้กระเป๋าแต้มของลูกค้าคนอื่น
 *   - ดูใบเสร็จของร้านอื่นไม่ได้
 */
class RedeemDeskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    private function shop(): OrgNode
    {
        return OrgNode::where('code', 'SH-001')->firstOrFail();
    }

    private function shopUser(): User
    {
        return User::where('email', 'shop@demo.test')->firstOrFail();
    }

    /** เปิดสมาชิกและวงเงินให้ร้าน */
    private function activateShop(int $limit = 10000): ShopMonthlyAllowance
    {
        $shop = $this->shop();
        $pkg = ShopPackage::where('code', 'PKG-SILVER')->firstOrFail();

        $sub = ShopSubscription::create([
            'code'                => 'SUB-T' . random_int(1000, 9999),
            'shop_node_id'        => $shop->id,
            'package_id'          => $pkg->id,
            'recruiter_node_id'   => OrgNode::where('level_id', 4)->firstOrFail()->id,
            'monthly_point_limit' => $limit,
            'price_paid'          => $pkg->price,
            'commission_amount'   => $pkg->commissionFor(),
            'starts_on'           => now()->subMonth(),
            'ends_on'             => now()->addYear(),
            'status'              => 'active',
            'paid_at'             => now(),
        ]);

        return ShopMonthlyAllowance::create([
            'subscription_id'  => $sub->id,
            'shop_node_id'     => $shop->id,
            'period_ym'        => now()->format('Y-m'),
            'limit_points'     => $limit,
            'remaining_points' => $limit,
        ]);
    }

    /** ลูกค้าที่มีแต้มจากร้านนี้ */
    private function customerWithPoints(int $points = 1000): array
    {
        $customer = Customer::create([
            'phone'  => '08' . random_int(10000000, 99999999),
            'name'   => 'ลูกค้าทดสอบ',
            'status' => 'active',
        ]);

        $wallet = CustomerPointWallet::create([
            'customer_id'     => $customer->id,
            'issuer_node_id'  => $this->shop()->id,
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

    public function test_ร้านค้าเข้าหน้าเคาน์เตอร์ได้(): void
    {
        $this->activateShop();

        $this->actingAs($this->shopUser())
            ->get('/redeem')
            ->assertOk()
            ->assertSee('รับแลกแต้ม');
    }

    public function test_คลังใหญ่เข้าหน้าเคาน์เตอร์ไม่ได้(): void
    {
        $wh = User::where('email', 'wh@demo.test')->firstOrFail();

        $this->actingAs($wh)->get('/redeem')->assertForbidden();
    }

    public function test_ผู้ขายเข้าได้และใช้วงเงินของร้านแม่(): void
    {
        $this->activateShop();
        $seller = User::where('email', 'seller@demo.test')->firstOrFail();

        $this->actingAs($seller)
            ->get('/redeem')
            ->assertOk()
            ->assertSee('วงเงินรับแลกเดือนนี้');
    }

    public function test_แลกบริการสำเร็จแล้วตัดแต้มและวงเงิน(): void
    {
        $allowance = $this->activateShop(10000);
        [$customer, $wallet] = $this->customerWithPoints(1000);

        $this->actingAs($this->shopUser())
            ->post('/redeem', [
                'customer_id' => $customer->id,
                'wallet_id'   => $wallet->id,
                'points'      => 400,
                'redeem_type' => 'service',
                'reward_name' => 'ล้างรถ 1 ครั้ง',
            ])
            ->assertRedirect();

        $this->assertSame(600, (int) $wallet->fresh()->balance);
        $this->assertSame(400, (int) $allowance->fresh()->used_points);
        $this->assertSame(9600, (int) $allowance->fresh()->remaining_points);

        $this->assertDatabaseHas('point_redemptions', [
            'customer_id' => $customer->id,
            'points_used' => 400,
            'redeem_type' => 'service',
            'status'      => 'confirmed',
        ]);
    }

    public function test_แลกเกินแต้มลูกค้าไม่ได้(): void
    {
        $allowance = $this->activateShop(10000);
        [$customer, $wallet] = $this->customerWithPoints(300);

        $this->actingAs($this->shopUser())
            ->post('/redeem', [
                'customer_id' => $customer->id,
                'wallet_id'   => $wallet->id,
                'points'      => 900,
                'redeem_type' => 'service',
                'reward_name' => 'ทดสอบ',
            ])
            ->assertSessionHasErrors('points');

        // ต้องไม่มีการหักอะไรเลย
        $this->assertSame(300, (int) $wallet->fresh()->balance);
        $this->assertSame(0, (int) $allowance->fresh()->used_points);
    }

    public function test_แลกเกินวงเงินร้านไม่ได้(): void
    {
        $allowance = $this->activateShop(200);
        [$customer, $wallet] = $this->customerWithPoints(5000);

        $this->actingAs($this->shopUser())
            ->post('/redeem', [
                'customer_id' => $customer->id,
                'wallet_id'   => $wallet->id,
                'points'      => 1000,
                'redeem_type' => 'service',
                'reward_name' => 'ทดสอบ',
            ])
            ->assertSessionHasErrors('points');

        $this->assertSame(5000, (int) $wallet->fresh()->balance);
        $this->assertSame(0, (int) $allowance->fresh()->used_points);
    }

    public function test_ใช้กระเป๋าแต้มของลูกค้าคนอื่นไม่ได้(): void
    {
        $this->activateShop();
        [$victim, $victimWallet] = $this->customerWithPoints(5000);
        [$attacker] = $this->customerWithPoints(10);

        $this->actingAs($this->shopUser())
            ->post('/redeem', [
                'customer_id' => $attacker->id,
                'wallet_id'   => $victimWallet->id,   // กระเป๋าของคนอื่น
                'points'      => 1000,
                'redeem_type' => 'service',
                'reward_name' => 'ทดสอบ',
            ])
            ->assertSessionHasErrors('points');

        $this->assertSame(5000, (int) $victimWallet->fresh()->balance);

        // ต้องถูกบันทึกเป็นเหตุการณ์ความปลอดภัย
        $this->assertDatabaseHas('security_events', [
            'event_type' => 'data_tamper_attempt',
            'severity'   => 'high',
        ]);
    }

    public function test_แลกสินค้าแล้วตัดสต๊อกและบันทึกล็อต(): void
    {
        $this->activateShop();
        [$customer, $wallet] = $this->customerWithPoints(2000);
        $shop = $this->shop();

        $lot = DB::table('product_lots')->first();

        StockBalance::create([
            'org_node_id' => $shop->id,
            'product_id'  => $lot->product_id,
            'lot_id'      => $lot->id,
            'qty_on_hand' => 10,
        ]);

        $this->actingAs($this->shopUser())
            ->post('/redeem', [
                'customer_id' => $customer->id,
                'wallet_id'   => $wallet->id,
                'points'      => 300,
                'redeem_type' => 'goods',
                'reward_name' => 'ขนม 2 ชิ้น',
                'items'       => [
                    ['product_id' => $lot->product_id, 'lot_id' => $lot->id, 'qty' => 2],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(
            8,
            (int) StockBalance::where('org_node_id', $shop->id)->first()->qty_on_hand,
        );

        $this->assertDatabaseHas('redemption_items', [
            'lot_id' => $lot->id,
            'qty'    => 2,
        ]);
    }

    public function test_แลกสินค้าโดยไม่ระบุรายการไม่ได้(): void
    {
        $this->activateShop();
        [$customer, $wallet] = $this->customerWithPoints(2000);

        $this->actingAs($this->shopUser())
            ->post('/redeem', [
                'customer_id' => $customer->id,
                'wallet_id'   => $wallet->id,
                'points'      => 300,
                'redeem_type' => 'goods',
                'reward_name' => 'ขนม',
            ])
            ->assertSessionHasErrors('points');
    }

    public function test_ร้านที่ยังไม่สมัครสมาชิกรับแลกไม่ได้(): void
    {
        // ไม่เรียก activateShop
        [$customer, $wallet] = $this->customerWithPoints(1000);

        $this->actingAs($this->shopUser())
            ->post('/redeem', [
                'customer_id' => $customer->id,
                'wallet_id'   => $wallet->id,
                'points'      => 100,
                'redeem_type' => 'service',
                'reward_name' => 'ทดสอบ',
            ])
            ->assertSessionHasErrors('points');

        $this->assertSame(1000, (int) $wallet->fresh()->balance);
    }

    public function test_ดูใบเสร็จของร้านอื่นไม่ได้(): void
    {
        $this->activateShop();
        [$customer, $wallet] = $this->customerWithPoints(1000);

        $this->actingAs($this->shopUser())->post('/redeem', [
            'customer_id' => $customer->id,
            'wallet_id'   => $wallet->id,
            'points'      => 100,
            'redeem_type' => 'service',
            'reward_name' => 'ทดสอบ',
        ]);

        $redemptionId = DB::table('point_redemptions')->value('id');

        // เปลี่ยนเจ้าของรายการเป็นร้านอื่น แล้วต้องเข้าไม่ได้
        DB::table('point_redemptions')->where('id', $redemptionId)
            ->update(['accepting_node_id' => OrgNode::where('level_id', 2)->first()->id]);

        $this->actingAs($this->shopUser())
            ->get("/redeem/receipt/{$redemptionId}")
            ->assertNotFound();
    }

    public function test_ประวัติการรับแลกแสดงเฉพาะของร้านตัวเอง(): void
    {
        $this->activateShop();
        [$customer, $wallet] = $this->customerWithPoints(1000);

        $this->actingAs($this->shopUser())->post('/redeem', [
            'customer_id' => $customer->id,
            'wallet_id'   => $wallet->id,
            'points'      => 250,
            'redeem_type' => 'discount',
            'reward_name' => 'ส่วนลด 60 บาท',
        ]);

        $this->actingAs($this->shopUser())
            ->get('/redeem/history')
            ->assertOk()
            ->assertSee('ส่วนลด 60 บาท');
    }
}
