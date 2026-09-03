<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPointWallet;
use App\Models\NotificationQueue;
use App\Models\OrgNode;
use App\Models\PointLot;
use App\Models\ShopMonthlyAllowance;
use App\Models\ShopPackage;
use App\Models\ShopSubscription;
use App\Models\SocialLink;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\RedemptionService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * ทดสอบระบบแจ้งเตือน LINE / Email
 *
 * เรื่องที่สำคัญที่สุด
 *   การแจ้งเตือนล้มเหลว "ต้องไม่" ทำให้ธุรกรรมหลักพัง
 *   เช่น LINE ล่ม -> ลูกค้าต้องยังแลกแต้มได้ตามปกติ
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        Mail::fake();
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
        $pkg = ShopPackage::where('code', 'PKG-SILVER')->firstOrFail();

        $sub = ShopSubscription::create([
            'code'                => 'SUB-N' . random_int(1000, 9999),
            'shop_node_id'        => $this->shop()->id,
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
            'shop_node_id'     => $this->shop()->id,
            'period_ym'        => now()->format('Y-m'),
            'limit_points'     => $limit,
            'remaining_points' => $limit,
        ]);
    }

    /** ลูกค้าที่ผูก LINE แล้ว */
    private function customerWithLine(int $points = 1000): array
    {
        $customer = Customer::create([
            'phone'  => '08' . random_int(10000000, 99999999),
            'name'   => 'ลูกค้าทดสอบ',
            'status' => 'active',
        ]);

        SocialLink::create([
            'owner_type'     => 'customer',
            'owner_id'       => $customer->id,
            'provider'       => 'line',
            'provider_uid'   => 'U' . bin2hex(random_bytes(8)),
            'display_name'   => 'ลูกค้า LINE',
            'notify_enabled' => true,
            'linked_at'      => now(),
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

    public function test_แลกแต้มแล้วเข้าคิวแจ้งเตือนลูกค้า(): void
    {
        $this->activateShop();
        [$customer, $wallet] = $this->customerWithLine();

        app(RedemptionService::class)->redeem(
            $customer, $this->shop(), $this->shop(), 400, 'ล้างรถ', 'service',
        );

        // ลูกค้าต้องได้รับแจ้งเตือนทาง LINE
        $this->assertDatabaseHas('notification_queue', [
            'channel'        => 'line',
            'recipient_type' => 'customer',
            'recipient_id'   => $customer->id,
            'template'       => 'redemption_confirmed',
            'status'         => 'pending',
        ]);
    }

    public function test_แลกแต้มแล้วแจ้งร้านด้วย(): void
    {
        $this->activateShop();
        [$customer] = $this->customerWithLine();

        app(RedemptionService::class)->redeem(
            $customer, $this->shop(), $this->shop(), 400, 'ล้างรถ', 'service',
        );

        // ผู้ใช้ของร้านต้องได้รับแจ้งทางอีเมล (บัญชีมีอีเมลอยู่แล้ว)
        $this->assertDatabaseHas('notification_queue', [
            'channel'        => 'email',
            'recipient_type' => 'user',
            'template'       => 'shop_redeem_received',
        ]);
    }

    public function test_ลูกค้าที่มีแค่เบอร์โทรยังไม่ถูกส่งอะไร(): void
    {
        $this->activateShop();

        // ลูกค้าที่ไม่ได้ผูก LINE
        $customer = Customer::create([
            'phone' => '0899999999', 'name' => 'ลูกค้าเบอร์อย่างเดียว', 'status' => 'active',
        ]);
        $wallet = CustomerPointWallet::create([
            'customer_id' => $customer->id, 'issuer_node_id' => $this->shop()->id,
            'balance' => 1000, 'lifetime_earned' => 1000,
        ]);
        PointLot::create([
            'wallet_id' => $wallet->id, 'points_in' => 1000, 'points_left' => 1000,
            'earned_at' => now(), 'expires_at' => now()->addMonths(12), 'source_type' => 'scan',
        ]);

        app(RedemptionService::class)->redeem(
            $customer, $this->shop(), $this->shop(), 400, 'ล้างรถ', 'service',
        );

        // ต้องไม่มีคิวส่งหาลูกค้าคนนี้
        $this->assertSame(
            0,
            NotificationQueue::where('recipient_type', 'customer')
                ->where('recipient_id', $customer->id)->count(),
        );
    }

    public function test_line_ล่มต้องไม่ทำให้แลกแต้มพัง(): void
    {
        config(['services.line.channel_access_token' => 'dummy-token']);
        Http::fake(['api.line.me/*' => Http::response('server error', 500)]);

        $this->activateShop();
        [$customer, $wallet] = $this->customerWithLine();

        // แลกแต้มต้องสำเร็จแม้ LINE จะล่ม
        $redemption = app(RedemptionService::class)->redeem(
            $customer, $this->shop(), $this->shop(), 400, 'ล้างรถ', 'service',
        );

        $this->assertSame('confirmed', $redemption->status);
        $this->assertSame(600, (int) $wallet->fresh()->balance);

        // ส่งแจ้งเตือนแล้วล้มเหลว แต่ยอดยังถูกต้อง
        app(NotificationService::class)->dispatchPending();

        $this->assertSame(600, (int) $wallet->fresh()->balance);
        $this->assertDatabaseHas('notification_queue', ['channel' => 'line', 'status' => 'pending']);
    }

    public function test_ส่ง_line_สำเร็จแล้วเปลี่ยนสถานะเป็น_sent(): void
    {
        config(['services.line.channel_access_token' => 'dummy-token']);
        Http::fake(['api.line.me/*' => Http::response(['ok' => true], 200)]);

        $this->activateShop();
        [$customer] = $this->customerWithLine();

        app(RedemptionService::class)->redeem(
            $customer, $this->shop(), $this->shop(), 400, 'ล้างรถ', 'service',
        );

        $result = app(NotificationService::class)->dispatchPending();

        $this->assertGreaterThan(0, $result['sent']);

        $this->assertDatabaseHas('notification_queue', [
            'channel' => 'line',
            'status'  => 'sent',
        ]);
    }

    public function test_ล้มเหลวครบจำนวนครั้งแล้วหยุดลองใหม่(): void
    {
        config(['services.line.channel_access_token' => 'dummy-token']);
        Http::fake(['api.line.me/*' => Http::response('error', 500)]);

        $row = NotificationQueue::create([
            'channel'      => 'line',
            'recipient_type' => 'customer',
            'destination'  => 'U123',
            'template'     => 'test',
            'subject'      => 'ทดสอบ',
            'body'         => 'ข้อความทดสอบ',
            'status'       => 'pending',
            'max_attempts' => 3,
        ]);

        $svc = app(NotificationService::class);

        // ลอง 3 รอบ
        for ($i = 0; $i < 3; $i++) {
            $svc->dispatchPending();
        }

        $fresh = $row->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertSame(3, (int) $fresh->attempts);

        // รอบที่ 4 ต้องไม่หยิบมาอีก
        $svc->dispatchPending();
        $this->assertSame(3, (int) $row->fresh()->attempts);
    }

    public function test_ยังไม่ตั้งค่า_token_ต้องไม่พังแต่บันทึกเหตุผล(): void
    {
        config(['services.line.channel_access_token' => null]);

        NotificationQueue::create([
            'channel' => 'line', 'recipient_type' => 'customer',
            'destination' => 'U123', 'template' => 'test',
            'subject' => 'ทดสอบ', 'body' => 'ข้อความ', 'status' => 'pending',
        ]);

        $result = app(NotificationService::class)->dispatchPending();

        $this->assertSame(1, $result['failed']);
        $this->assertStringContainsString(
            'LINE_CHANNEL_ACCESS_TOKEN',
            NotificationQueue::first()->error_message,
        );
    }

    public function test_ส่งอีเมลได้(): void
    {
        NotificationQueue::create([
            'channel' => 'email', 'recipient_type' => 'user',
            'destination' => 'test@example.com', 'template' => 'test',
            'subject' => 'หัวข้อทดสอบ', 'body' => 'เนื้อหาทดสอบ', 'status' => 'pending',
        ]);

        $result = app(NotificationService::class)->dispatchPending();

        $this->assertSame(1, $result['sent']);
        $this->assertDatabaseHas('notification_queue', ['status' => 'sent']);
    }

    public function test_ปิดรับแจ้งเตือนแล้วไม่เข้าคิว(): void
    {
        $this->activateShop();
        [$customer] = $this->customerWithLine();

        SocialLink::where('owner_type', 'customer')
            ->where('owner_id', $customer->id)
            ->update(['notify_enabled' => false]);

        app(RedemptionService::class)->redeem(
            $customer, $this->shop(), $this->shop(), 400, 'ล้างรถ', 'service',
        );

        $this->assertSame(
            0,
            NotificationQueue::where('recipient_type', 'customer')
                ->where('recipient_id', $customer->id)->count(),
        );
    }

    // ── หน้าตั้งค่าการแจ้งเตือน ────────────────────────────

    public function test_เข้าหน้าตั้งค่าแจ้งเตือนได้(): void
    {
        $this->actingAs($this->shopUser())
            ->get('/profile/notify')
            ->assertOk()
            ->assertSee('การแจ้งเตือน');
    }

    public function test_ผู้ใช้ระบบผูก_line_ได้หลายไอดี(): void
    {
        $user = $this->shopUser();

        // ผูก 3 ไอดี
        for ($i = 0; $i < 3; $i++) {
            SocialLink::create([
                'owner_type' => 'user', 'owner_id' => $user->id,
                'provider' => 'line', 'provider_uid' => 'U' . bin2hex(random_bytes(8)),
                'display_name' => "พนักงาน {$i}", 'notify_enabled' => true, 'linked_at' => now(),
            ]);
        }

        $this->assertSame(3, SocialLink::where('owner_type', 'user')
            ->where('owner_id', $user->id)->count());

        $this->actingAs($user)->get('/profile/notify')->assertOk()->assertSee('พนักงาน 0');
    }

    public function test_ผูกเกินจำนวนที่กำหนดไม่ได้(): void
    {
        $user = $this->shopUser();
        $user->update(['max_social_links' => 2]);

        for ($i = 0; $i < 2; $i++) {
            SocialLink::create([
                'owner_type' => 'user', 'owner_id' => $user->id,
                'provider' => 'line', 'provider_uid' => 'U' . bin2hex(random_bytes(8)),
                'display_name' => "พนักงาน {$i}", 'notify_enabled' => true, 'linked_at' => now(),
            ]);
        }

        $this->actingAs($user)
            ->post('/profile/notify/link')
            ->assertSessionHasErrors('link');
    }

    public function test_ปิดและลบการผูกของตัวเองได้(): void
    {
        $user = $this->shopUser();

        $link = SocialLink::create([
            'owner_type' => 'user', 'owner_id' => $user->id,
            'provider' => 'line', 'provider_uid' => 'U' . bin2hex(random_bytes(8)),
            'display_name' => 'ของฉัน', 'notify_enabled' => true, 'linked_at' => now(),
        ]);

        $this->actingAs($user)->patch("/profile/notify/{$link->id}/toggle")->assertRedirect();
        $this->assertFalse($link->fresh()->notify_enabled);

        $this->actingAs($user)->delete("/profile/notify/{$link->id}")->assertRedirect();
        $this->assertDatabaseMissing('social_links', ['id' => $link->id]);
    }

    public function test_แก้การผูกของคนอื่นไม่ได้(): void
    {
        $other = User::where('email', 'agent@demo.test')->firstOrFail();

        $link = SocialLink::create([
            'owner_type' => 'user', 'owner_id' => $other->id,
            'provider' => 'line', 'provider_uid' => 'U' . bin2hex(random_bytes(8)),
            'display_name' => 'ของคนอื่น', 'notify_enabled' => true, 'linked_at' => now(),
        ]);

        $this->actingAs($this->shopUser())
            ->delete("/profile/notify/{$link->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('social_links', ['id' => $link->id]);

        $this->assertDatabaseHas('security_events', [
            'event_type' => 'permission_denied',
            'severity'   => 'high',
        ]);
    }
}
