<?php

namespace Tests\Feature;

use App\Models\AdminAlert;
use App\Models\BlockedEntity;
use App\Models\SecurityEvent;
use App\Models\ShopPackage;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ทดสอบหน้าแอดมิน: แพ็กเกจ ค่าแต้ม และศูนย์ความปลอดภัย
 *
 * เน้นว่าเฉพาะเจ้าของระบบเท่านั้นที่เข้าถึงได้
 * และการเปลี่ยนค่าที่กระทบเงินต้องมีร่องรอยเสมอ
 */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@demo.test')->firstOrFail();
    }

    private function agent(): User
    {
        return User::where('email', 'agent@demo.test')->firstOrFail();
    }

    // ── แพ็กเกจ ────────────────────────────────────────────

    public function test_เจ้าของระบบเข้าหน้าแพ็กเกจได้(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/packages')
            ->assertOk()
            ->assertSee('แพ็กเกจสมาชิก');
    }

    public function test_ตัวแทนเข้าหน้าแพ็กเกจไม่ได้(): void
    {
        $this->actingAs($this->agent())->get('/admin/packages')->assertForbidden();
    }

    public function test_เพิ่มแพ็กเกจใหม่ได้(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/packages', [
                'code'                 => 'PKG-PLATINUM',
                'name'                 => 'แพ็กเกจแพลทินัม',
                'duration_months'      => 24,
                'monthly_point_limit'  => 50000,
                'price'                => 20000,
                'agent_commission_pct' => 18,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('shop_packages', [
            'code'                => 'PKG-PLATINUM',
            'monthly_point_limit' => 50000,
        ]);
    }

    public function test_รหัสแพ็กเกจซ้ำไม่ได้(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/packages', [
                'code'                 => 'PKG-SILVER',
                'name'                 => 'ซ้ำ',
                'duration_months'      => 12,
                'monthly_point_limit'  => 100,
                'price'                => 100,
                'agent_commission_pct' => 0,
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_รหัสแพ็กเกจต้องเป็นตัวพิมพ์ใหญ่และตัวเลข(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/packages', [
                'code'                 => 'pkg lower!',
                'name'                 => 'ทดสอบ',
                'duration_months'      => 12,
                'monthly_point_limit'  => 100,
                'price'                => 100,
                'agent_commission_pct' => 0,
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_ปิดและเปิดแพ็กเกจได้(): void
    {
        $pkg = ShopPackage::where('code', 'PKG-BRONZE')->firstOrFail();

        $this->actingAs($this->admin())
            ->patch("/admin/packages/{$pkg->id}/toggle")
            ->assertRedirect();

        $this->assertFalse($pkg->fresh()->is_active);
    }

    // ── ค่าของแต้ม ─────────────────────────────────────────

    public function test_เปลี่ยนค่าแต้มแล้วบันทึกประวัติ(): void
    {
        $this->actingAs($this->admin())
            ->patch('/admin/packages/point-value', [
                'point_value_baht' => 0.5,
                'reason'           => 'ปรับตามนโยบายใหม่',
            ])
            ->assertRedirect();

        $this->assertEquals(0.5, (float) SystemSetting::get('point_value_baht'));

        $this->assertDatabaseHas('point_value_history', [
            'old_value' => 0.25,
            'new_value' => 0.5,
            'reason'    => 'ปรับตามนโยบายใหม่',
        ]);

        // ต้องบันทึกเป็นเหตุการณ์ความปลอดภัยระดับสูง
        $this->assertDatabaseHas('security_events', [
            'event_type' => 'critical_setting_changed',
            'severity'   => 'high',
        ]);
    }

    public function test_เปลี่ยนค่าแต้มต้องระบุเหตุผล(): void
    {
        $this->actingAs($this->admin())
            ->patch('/admin/packages/point-value', ['point_value_baht' => 0.5])
            ->assertSessionHasErrors('reason');

        $this->assertEquals(0.25, (float) SystemSetting::get('point_value_baht'));
    }

    public function test_ตัวแทนเปลี่ยนค่าแต้มไม่ได้(): void
    {
        $this->actingAs($this->agent())
            ->patch('/admin/packages/point-value', [
                'point_value_baht' => 99,
                'reason'           => 'แอบแก้',
            ])
            ->assertForbidden();

        $this->assertEquals(0.25, (float) SystemSetting::get('point_value_baht'));
    }

    public function test_แก้ค่าแต้มผ่านฟอร์มตั้งค่าทั่วไปไม่ได้(): void
    {
        // ต้องบังคับให้ใช้ช่องทางที่บันทึกประวัติเท่านั้น
        $this->actingAs($this->admin())
            ->patch('/admin/packages/setting', [
                'skey'   => 'point_value_baht',
                'svalue' => '99',
            ])
            ->assertSessionHasErrors('svalue');

        $this->assertEquals(0.25, (float) SystemSetting::get('point_value_baht'));
    }

    public function test_แก้ค่าตั้งค่าทั่วไปได้และบันทึกร่องรอย(): void
    {
        $this->actingAs($this->admin())
            ->patch('/admin/packages/setting', [
                'skey'   => 'claim_min_points',
                'svalue' => '1000',
            ])
            ->assertRedirect();

        $this->assertSame(1000, SystemSetting::get('claim_min_points'));

        $this->assertDatabaseHas('security_events', [
            'event_type' => 'critical_setting_changed',
        ]);
    }

    // ── ศูนย์ความปลอดภัย ───────────────────────────────────

    public function test_เจ้าของระบบเข้าศูนย์ความปลอดภัยได้(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/security')
            ->assertOk()
            ->assertSee('ศูนย์ความปลอดภัย');
    }

    public function test_คนอื่นเข้าศูนย์ความปลอดภัยไม่ได้(): void
    {
        foreach (['agent', 'shop', 'wh'] as $who) {
            $user = User::where('email', "{$who}@demo.test")->first();

            if ($user) {
                $this->actingAs($user)->get('/admin/security')->assertForbidden();
            }
        }
    }

    public function test_ทุกหน้าย่อยของศูนย์ความปลอดภัยเปิดได้(): void
    {
        foreach (['/admin/security/events', '/admin/security/audits',
                  '/admin/security/logins', '/admin/security/errors'] as $url) {
            $this->actingAs($this->admin())->get($url)->assertOk();
        }
    }

    public function test_ทำเครื่องหมายว่าตรวจสอบเหตุการณ์แล้ว(): void
    {
        $event = SecurityEvent::create([
            'event_type' => 'test_event',
            'severity'   => 'high',
            'actor_type' => 'guest',
            'message'    => 'เหตุการณ์ทดสอบ',
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->patch("/admin/security/events/{$event->id}/review", [
                'review_note' => 'ตรวจแล้วไม่มีปัญหา',
            ])
            ->assertRedirect();

        $fresh = $event->fresh();
        $this->assertTrue($fresh->is_reviewed);
        $this->assertNotNull($fresh->reviewed_at);
    }

    public function test_ปิดงานแจ้งเตือนได้(): void
    {
        $alert = AdminAlert::create([
            'alert_type' => 'test',
            'severity'   => 'warning',
            'title'      => 'แจ้งเตือนทดสอบ',
            'status'     => 'new',
        ]);

        $this->actingAs($this->admin())
            ->patch("/admin/security/alerts/{$alert->id}", ['status' => 'resolved'])
            ->assertRedirect();

        $this->assertSame('resolved', $alert->fresh()->status);
    }

    public function test_ระงับและปลดระงับ_ip_ได้(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/security/block', [
                'entity_type'  => 'ip',
                'entity_value' => '203.0.113.9',
                'reason'       => 'ยิง request ผิดปกติ',
                'minutes'      => 60,
            ])
            ->assertRedirect();

        $blocked = BlockedEntity::where('entity_value', '203.0.113.9')->firstOrFail();
        $this->assertTrue($blocked->is_active);

        $this->actingAs($this->admin())
            ->patch("/admin/security/blocked/{$blocked->id}/unblock")
            ->assertRedirect();

        $this->assertFalse($blocked->fresh()->is_active);
    }

    public function test_ระงับต้องระบุเหตุผล(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/security/block', [
                'entity_type'  => 'ip',
                'entity_value' => '203.0.113.10',
            ])
            ->assertSessionHasErrors('reason');
    }
}
