<?php

namespace Tests\Feature;

use App\Models\OrgNode;
use App\Models\ShopMonthlyAllowance;
use App\Models\ShopPackage;
use App\Models\ShopSubscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ทดสอบการสมัครสมาชิกร้านค้า
 *
 * เน้น
 *   - ตัวแทนสมัครให้ร้านนอกสายงานตัวเองไม่ได้
 *   - ร้านหนึ่งมีสมาชิกใช้งานพร้อมกันได้ใบเดียว
 *   - ค่าถูกล็อกไว้ตอนสมัคร แก้แพ็กเกจภายหลังไม่กระทบสัญญาเดิม
 *   - แพ็กเกจฟรีเปิดวงเงินทันที แพ็กเกจมีราคาต้องรอชำระ
 */
class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    private function agentUser(): User
    {
        return User::where('email', 'agent@demo.test')->firstOrFail();
    }

    private function adminUser(): User
    {
        return User::where('email', 'admin@demo.test')->firstOrFail();
    }

    private function shop(): OrgNode
    {
        return OrgNode::where('code', 'SH-001')->firstOrFail();
    }

    private function pkg(string $code = 'PKG-SILVER'): ShopPackage
    {
        return ShopPackage::where('code', $code)->firstOrFail();
    }

    public function test_ตัวแทนเข้าหน้าสมาชิกร้านได้(): void
    {
        $this->actingAs($this->agentUser())
            ->get('/subscriptions')
            ->assertOk()
            ->assertSee('สมาชิกร้านค้า');
    }

    public function test_ร้านค้าเข้าหน้าสมัครสมาชิกไม่ได้(): void
    {
        $shopUser = User::where('email', 'shop@demo.test')->firstOrFail();

        $this->actingAs($shopUser)->get('/subscriptions')->assertForbidden();
    }

    public function test_สมัครแพ็กเกจมีราคาแล้วรอชำระเงิน(): void
    {
        $this->actingAs($this->agentUser())
            ->post('/subscriptions', [
                'shop_node_id' => $this->shop()->id,
                'package_id'   => $this->pkg()->id,
            ])
            ->assertRedirect();

        $sub = ShopSubscription::firstOrFail();

        $this->assertSame('pending_payment', $sub->status);
        $this->assertSame(10000, (int) $sub->monthly_point_limit);
        // คอมมิชชั่น 12% ของ 5000 = 600
        $this->assertEquals(600.0, (float) $sub->commission_amount);

        // ยังไม่เปิดวงเงินจนกว่าจะชำระ
        $this->assertDatabaseCount('shop_monthly_allowances', 0);
    }

    public function test_แพ็กเกจฟรีเปิดวงเงินทันที(): void
    {
        $this->actingAs($this->agentUser())
            ->post('/subscriptions', [
                'shop_node_id' => $this->shop()->id,
                'package_id'   => $this->pkg('PKG-TRIAL')->id,
            ])
            ->assertRedirect();

        $sub = ShopSubscription::firstOrFail();

        $this->assertSame('active', $sub->status);
        $this->assertDatabaseHas('shop_monthly_allowances', [
            'shop_node_id'     => $this->shop()->id,
            'period_ym'        => now()->format('Y-m'),
            'remaining_points' => 1000,
        ]);
    }

    public function test_ยืนยันชำระเงินแล้วเปิดวงเงินให้ทันที(): void
    {
        $this->actingAs($this->agentUser())->post('/subscriptions', [
            'shop_node_id' => $this->shop()->id,
            'package_id'   => $this->pkg()->id,
        ]);

        $sub = ShopSubscription::firstOrFail();

        $this->actingAs($this->agentUser())
            ->patch("/subscriptions/{$sub->id}/pay", ['payment_ref' => 'PAY-001'])
            ->assertRedirect();

        $fresh = $sub->fresh();
        $this->assertSame('active', $fresh->status);
        $this->assertNotNull($fresh->paid_at);

        $alw = ShopMonthlyAllowance::where('shop_node_id', $this->shop()->id)->first();
        $this->assertNotNull($alw);
        $this->assertSame(10000, (int) $alw->remaining_points);
    }

    public function test_ร้านเดิมสมัครซ้ำไม่ได้(): void
    {
        $payload = [
            'shop_node_id' => $this->shop()->id,
            'package_id'   => $this->pkg()->id,
        ];

        $this->actingAs($this->agentUser())->post('/subscriptions', $payload);

        $this->actingAs($this->agentUser())
            ->post('/subscriptions', $payload)
            ->assertSessionHasErrors('shop_node_id');

        $this->assertDatabaseCount('shop_subscriptions', 1);
    }

    public function test_สมัครให้ร้านนอกสายงานตัวเองไม่ได้(): void
    {
        // สร้างสายงานอื่นที่ตัวแทนคนนี้มองไม่เห็น
        $otherWh = OrgNode::create([
            'parent_id' => OrgNode::where('level_id', 1)->first()->id,
            'level_id'  => 2, 'code' => 'WH-OTHER', 'name' => 'คลังอื่น', 'status' => 'active',
        ]);
        $otherSwh = OrgNode::create([
            'parent_id' => $otherWh->id, 'level_id' => 3,
            'code' => 'SWH-OTHER', 'name' => 'คลังย่อยอื่น', 'status' => 'active',
        ]);
        $otherAgent = OrgNode::create([
            'parent_id' => $otherSwh->id, 'level_id' => 4,
            'code' => 'AG-OTHER', 'name' => 'ตัวแทนอื่น', 'status' => 'active',
        ]);
        $otherShop = OrgNode::create([
            'parent_id' => $otherAgent->id, 'level_id' => 5,
            'code' => 'SH-OTHER', 'name' => 'ร้านนอกสาย', 'status' => 'active',
        ]);

        $this->actingAs($this->agentUser())
            ->post('/subscriptions', [
                'shop_node_id' => $otherShop->id,
                'package_id'   => $this->pkg()->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('shop_subscriptions', 0);

        $this->assertDatabaseHas('security_events', [
            'event_type' => 'permission_denied',
            'severity'   => 'high',
        ]);
    }

    public function test_สมัครให้หน่วยงานที่ไม่ใช่ร้านค้าไม่ได้(): void
    {
        $warehouse = OrgNode::where('code', 'WH-BKK')->firstOrFail();

        $this->actingAs($this->adminUser())
            ->post('/subscriptions', [
                'shop_node_id' => $warehouse->id,
                'package_id'   => $this->pkg()->id,
            ])
            ->assertSessionHasErrors('shop_node_id');
    }

    public function test_ค่าถูกล็อกไว้แม้แอดมินแก้แพ็กเกจภายหลัง(): void
    {
        $this->actingAs($this->agentUser())->post('/subscriptions', [
            'shop_node_id' => $this->shop()->id,
            'package_id'   => $this->pkg()->id,
        ]);

        $sub = ShopSubscription::firstOrFail();
        $originalLimit = (int) $sub->monthly_point_limit;

        // แอดมินขึ้นราคาและลดวงเงิน
        $this->actingAs($this->adminUser())->put("/admin/packages/{$this->pkg()->id}", [
            'code'                 => 'PKG-SILVER',
            'name'                 => 'แพ็กเกจเงิน (ปรับใหม่)',
            'duration_months'      => 12,
            'monthly_point_limit'  => 3000,
            'price'                => 9000,
            'agent_commission_pct' => 5,
        ]);

        // สัญญาเดิมต้องไม่เปลี่ยน
        $this->assertSame($originalLimit, (int) $sub->fresh()->monthly_point_limit);
        $this->assertEquals(5000.0, (float) $sub->fresh()->price_paid);
    }

    public function test_ยกเลิกสมาชิกต้องระบุเหตุผล(): void
    {
        $this->actingAs($this->agentUser())->post('/subscriptions', [
            'shop_node_id' => $this->shop()->id,
            'package_id'   => $this->pkg('PKG-TRIAL')->id,
        ]);
        $sub = ShopSubscription::firstOrFail();

        $this->actingAs($this->agentUser())
            ->patch("/subscriptions/{$sub->id}/cancel", [])
            ->assertSessionHasErrors('cancel_reason');

        $this->actingAs($this->agentUser())
            ->patch("/subscriptions/{$sub->id}/cancel", ['cancel_reason' => 'ร้านปิดกิจการ'])
            ->assertRedirect();

        $this->assertSame('cancelled', $sub->fresh()->status);
    }

    public function test_ต่ออายุแล้วได้ใบใหม่และใบเก่าหมดอายุ(): void
    {
        $this->actingAs($this->agentUser())->post('/subscriptions', [
            'shop_node_id' => $this->shop()->id,
            'package_id'   => $this->pkg('PKG-TRIAL')->id,
        ]);
        $old = ShopSubscription::firstOrFail();

        $this->actingAs($this->agentUser())
            ->patch("/subscriptions/{$old->id}/renew", [])
            ->assertRedirect();

        $this->assertSame('expired', $old->fresh()->status);
        $this->assertDatabaseCount('shop_subscriptions', 2);
    }

    public function test_รีเซตวงเงินรายเดือนเปิดให้เฉพาะร้านที่ยังใช้งานได้(): void
    {
        // ร้านที่สมาชิกใช้งานอยู่
        $this->actingAs($this->agentUser())->post('/subscriptions', [
            'shop_node_id' => $this->shop()->id,
            'package_id'   => $this->pkg('PKG-TRIAL')->id,
        ]);

        $next = now()->addMonth()->format('Y-m');
        $result = app(SubscriptionService::class)->resetMonthly($next);

        $this->assertSame(1, $result['opened']);
        $this->assertDatabaseHas('shop_monthly_allowances', [
            'shop_node_id' => $this->shop()->id,
            'period_ym'    => $next,
        ]);
    }

    public function test_รีเซตซ้ำไม่สร้างวงเงินซ้ำ(): void
    {
        $this->actingAs($this->agentUser())->post('/subscriptions', [
            'shop_node_id' => $this->shop()->id,
            'package_id'   => $this->pkg('PKG-TRIAL')->id,
        ]);

        $svc = app(SubscriptionService::class);
        $next = now()->addMonth()->format('Y-m');

        $svc->resetMonthly($next);
        $svc->resetMonthly($next);

        $this->assertSame(
            1,
            ShopMonthlyAllowance::where('shop_node_id', $this->shop()->id)
                ->where('period_ym', $next)->count(),
        );
    }

    public function test_ดูใบสมัครนอกสายงานไม่ได้(): void
    {
        $this->actingAs($this->adminUser())->post('/subscriptions', [
            'shop_node_id' => $this->shop()->id,
            'package_id'   => $this->pkg('PKG-TRIAL')->id,
        ]);
        $sub = ShopSubscription::firstOrFail();

        // ย้ายร้านไปสายอื่นที่ตัวแทนมองไม่เห็น
        $otherWh = OrgNode::create([
            'parent_id' => OrgNode::where('level_id', 1)->first()->id,
            'level_id'  => 2, 'code' => 'WH-X', 'name' => 'คลัง X', 'status' => 'active',
        ]);
        $sub->update(['shop_node_id' => $otherWh->id]);

        $this->actingAs($this->agentUser())
            ->get("/subscriptions/{$sub->id}")
            ->assertNotFound();
    }
}
