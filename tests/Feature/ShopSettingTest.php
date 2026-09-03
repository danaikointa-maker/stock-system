<?php

namespace Tests\Feature;

use App\Models\OrgNode;
use App\Models\ShopProfile;
use App\Models\ShopReward;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ทดสอบหน้าตั้งค่าร้านและหน้าร้านสาธารณะ
 *
 * เน้นเรื่องความปลอดภัยของการอัปโหลดไฟล์
 * และการกันไม่ให้ร้านหนึ่งไปแก้ข้อมูลของอีกร้าน
 */
class ShopSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        Storage::fake('public');
    }

    private function shopUser(): User
    {
        return User::where('email', 'shop@demo.test')->firstOrFail();
    }

    private function shop(): OrgNode
    {
        return OrgNode::where('code', 'SH-001')->firstOrFail();
    }

    /** ข้อมูลขั้นต่ำที่ฟอร์มต้องการ */
    private function baseData(array $override = []): array
    {
        return array_merge([
            'display_name'  => 'ร้านกาแฟบ้านสวน',
            'business_type' => 'cafe',
            'status'        => 'active',
        ], $override);
    }

    public function test_เจ้าของร้านเข้าหน้าตั้งค่าได้(): void
    {
        $this->actingAs($this->shopUser())
            ->get('/shop/settings')
            ->assertOk()
            ->assertSee('ตั้งค่าหน้าร้าน');
    }

    public function test_คลังใหญ่เข้าหน้าตั้งค่าร้านไม่ได้(): void
    {
        $wh = User::where('email', 'wh@demo.test')->firstOrFail();

        $this->actingAs($wh)->get('/shop/settings')->assertForbidden();
    }

    public function test_ผู้ขายตั้งค่าร้านไม่ได้(): void
    {
        $seller = User::where('email', 'seller@demo.test')->firstOrFail();

        $this->actingAs($seller)->get('/shop/settings')->assertForbidden();
    }

    public function test_บันทึกข้อมูลร้านและสร้าง_slug_อัตโนมัติ(): void
    {
        $this->actingAs($this->shopUser())
            ->put('/shop/settings', $this->baseData([
                'tagline' => 'กาแฟคั่วสดทุกวัน',
                'phone'   => '0812345678',
            ]))
            ->assertRedirect();

        $profile = ShopProfile::where('node_id', $this->shop()->id)->firstOrFail();

        $this->assertSame('ร้านกาแฟบ้านสวน', $profile->display_name);
        $this->assertSame('cafe', $profile->business_type);
        $this->assertNotEmpty($profile->slug);
    }

    public function test_slug_ไม่เปลี่ยนเมื่อแก้ชื่อร้านภายหลัง(): void
    {
        $user = $this->shopUser();

        $this->actingAs($user)->put('/shop/settings', $this->baseData());
        $slugBefore = ShopProfile::where('node_id', $this->shop()->id)->value('slug');

        $this->actingAs($user)->put('/shop/settings', $this->baseData([
            'display_name' => 'เปลี่ยนชื่อใหม่หมดเลย',
        ]));
        $slugAfter = ShopProfile::where('node_id', $this->shop()->id)->value('slug');

        // ลิงก์เดิมต้องยังใช้ได้
        $this->assertSame($slugBefore, $slugAfter);
    }

    public function test_อัปโหลดโลโก้ได้และตั้งชื่อไฟล์ใหม่(): void
    {
        $file = UploadedFile::fake()->image('mylogo.png', 300, 300);

        $this->actingAs($this->shopUser())
            ->put('/shop/settings', $this->baseData(['logo' => $file]))
            ->assertRedirect();

        $profile = ShopProfile::where('node_id', $this->shop()->id)->firstOrFail();

        $this->assertNotNull($profile->logo_path);
        Storage::disk('public')->assertExists($profile->logo_path);

        // ต้องไม่ใช้ชื่อไฟล์เดิมจากผู้ใช้ (กันไฟล์อันตราย/ชนกัน)
        $this->assertStringNotContainsString('mylogo', $profile->logo_path);
        $this->assertStringStartsWith('shops/', $profile->logo_path);
    }

    public function test_อัปโหลดไฟล์ที่ไม่ใช่รูปไม่ได้(): void
    {
        $file = UploadedFile::fake()->create('evil.php', 10, 'application/x-php');

        $this->actingAs($this->shopUser())
            ->put('/shop/settings', $this->baseData(['logo' => $file]))
            ->assertSessionHasErrors('logo');

        $this->assertNull(
            ShopProfile::where('node_id', $this->shop()->id)->value('logo_path'),
        );
    }

    public function test_รหัสสีผิดรูปแบบถูกปฏิเสธ(): void
    {
        $this->actingAs($this->shopUser())
            ->put('/shop/settings', $this->baseData(['color_primary' => 'red']))
            ->assertSessionHasErrors('color_primary');
    }

    public function test_เพิ่มของรางวัลได้(): void
    {
        $this->actingAs($this->shopUser())
            ->post('/shop/rewards', [
                'name'        => 'กาแฟเย็น แก้วใหญ่',
                'reward_type' => 'service',
                'points_cost' => 450,
                'cash_value'  => 60,
                'icon'        => '🥤',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('shop_rewards', [
            'shop_node_id' => $this->shop()->id,
            'name'         => 'กาแฟเย็น แก้วใหญ่',
            'points_cost'  => 450,
        ]);
    }

    public function test_ของรางวัลประเภทสินค้าต้องเลือกสินค้า(): void
    {
        $this->actingAs($this->shopUser())
            ->post('/shop/rewards', [
                'name'        => 'ขนม',
                'reward_type' => 'goods',
                'points_cost' => 200,
            ])
            ->assertSessionHasErrors('product_id');

        $this->assertDatabaseCount('shop_rewards', 0);
    }

    public function test_แก้ของรางวัลของร้านอื่นไม่ได้(): void
    {
        // ของรางวัลที่เป็นของคลังใหญ่ (คนละร้าน)
        $otherNode = OrgNode::where('code', 'WH-BKK')->firstOrFail();

        $reward = ShopReward::create([
            'shop_node_id' => $otherNode->id,
            'name'         => 'ของร้านอื่น',
            'reward_type'  => 'service',
            'points_cost'  => 100,
        ]);

        $this->actingAs($this->shopUser())
            ->patch("/shop/rewards/{$reward->id}/toggle")
            ->assertNotFound();

        // ต้องไม่ถูกแก้
        $this->assertTrue($reward->fresh()->is_active);

        // ต้องถูกบันทึกเป็นเหตุการณ์ความปลอดภัย
        $this->assertDatabaseHas('security_events', [
            'event_type' => 'permission_denied',
            'severity'   => 'high',
        ]);
    }

    public function test_ลบของรางวัลของตัวเองได้(): void
    {
        $reward = ShopReward::create([
            'shop_node_id' => $this->shop()->id,
            'name'         => 'ของฉัน',
            'reward_type'  => 'service',
            'points_cost'  => 100,
        ]);

        $this->actingAs($this->shopUser())
            ->delete("/shop/rewards/{$reward->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('shop_rewards', ['id' => $reward->id]);
    }

    public function test_หน้าร้านสาธารณะเปิดได้เมื่อเผยแพร่แล้ว(): void
    {
        $this->actingAs($this->shopUser())->put('/shop/settings', $this->baseData([
            'tagline' => 'กาแฟคั่วสดทุกวัน',
        ]));

        $slug = ShopProfile::where('node_id', $this->shop()->id)->value('slug');

        // ไม่ต้องล็อกอินก็เปิดได้
        $this->get("/r/{$slug}")
            ->assertOk()
            ->assertSee('ร้านกาแฟบ้านสวน')
            ->assertSee('กาแฟคั่วสดทุกวัน');
    }

    public function test_หน้าร้านที่ยังเป็นร่างเปิดไม่ได้(): void
    {
        $this->actingAs($this->shopUser())->put('/shop/settings', $this->baseData([
            'status' => 'draft',
        ]));

        $slug = ShopProfile::where('node_id', $this->shop()->id)->value('slug');

        $this->get("/r/{$slug}")->assertNotFound();
    }

    public function test_หน้าร้านแสดงเฉพาะของรางวัลที่เปิดใช้(): void
    {
        $this->actingAs($this->shopUser())->put('/shop/settings', $this->baseData());
        $slug = ShopProfile::where('node_id', $this->shop()->id)->value('slug');

        ShopReward::create([
            'shop_node_id' => $this->shop()->id,
            'name'         => 'รายการที่เปิดอยู่',
            'reward_type'  => 'service',
            'points_cost'  => 100,
            'is_active'    => true,
        ]);

        ShopReward::create([
            'shop_node_id' => $this->shop()->id,
            'name'         => 'รายการที่ปิดไว้',
            'reward_type'  => 'service',
            'points_cost'  => 200,
            'is_active'    => false,
        ]);

        $this->get("/r/{$slug}")
            ->assertOk()
            ->assertSee('รายการที่เปิดอยู่')
            ->assertDontSee('รายการที่ปิดไว้');
    }

    public function test_ดูตัวอย่างหน้าร้านได้แม้ยังไม่เผยแพร่(): void
    {
        $this->actingAs($this->shopUser())->put('/shop/settings', $this->baseData([
            'status' => 'draft',
        ]));

        $this->actingAs($this->shopUser())
            ->get('/shop/preview')
            ->assertOk()
            ->assertSee('กำลังดูตัวอย่าง');
    }

    // ─── QR ร้านค้า ──────────────────────────────────────────

    public function test_บันทึกข้อมูลร้านแล้วสร้าง_shop_qr_token_อัตโนมัติ(): void
    {
        $this->actingAs($this->shopUser())
            ->put('/shop/settings', $this->baseData())
            ->assertRedirect();

        $profile = ShopProfile::where('node_id', $this->shop()->id)->firstOrFail();

        // เมื่อเรียก ensureQrToken ต้องได้ token และ URL
        $token = $profile->ensureQrToken();
        $this->assertNotEmpty($token);
        $this->assertSame(24, strlen($token));

        // URL ต้องชี้ไป /shop-qr/{token}
        $this->assertStringContainsString("/shop-qr/{$token}", $profile->shopQrUrl());
    }

    public function test_หน้า_qr_ร้านเปิดได้โดยไม่ต้องล็อกอิน(): void
    {
        $this->actingAs($this->shopUser())->put('/shop/settings', $this->baseData());

        $profile = ShopProfile::where('node_id', $this->shop()->id)->firstOrFail();
        $token = $profile->ensureQrToken();

        // ไม่ต้อง login
        $this->get("/shop-qr/{$token}")
            ->assertOk()
            ->assertSee($profile->display_name)
            ->assertSee('ของรางวัลที่แลกได้');
    }

    public function test_หน้า_qr_ร้านที่ไม่เผยแพร่เปิดไม่ได้(): void
    {
        $this->actingAs($this->shopUser())->put('/shop/settings', $this->baseData([
            'status' => 'draft',
        ]));

        $profile = ShopProfile::where('node_id', $this->shop()->id)->firstOrFail();
        $token = $profile->ensureQrToken();

        // ต้องได้ 404 เพราะร้านยังไม่เผยแพร่
        $this->get("/shop-qr/{$token}")->assertNotFound();
    }

    public function test_token_ผิดเปิดหน้า_qr_ไม่ได้(): void
    {
        $this->actingAs($this->shopUser())->put('/shop/settings', $this->baseData());

        $this->get('/shop-qr/invalidtoken123456789012')->assertNotFound();
    }

    public function test_หน้า_qr_แสดงของรางวัลที่เปิดใช้เท่านั้น(): void
    {
        $this->actingAs($this->shopUser())->put('/shop/settings', $this->baseData());

        $profile = ShopProfile::where('node_id', $this->shop()->id)->firstOrFail();
        $token = $profile->ensureQrToken();

        ShopReward::create([
            'shop_node_id' => $this->shop()->id,
            'name'         => 'กาแฟฟรี',
            'reward_type'  => 'service',
            'points_cost'  => 100,
            'is_active'    => true,
        ]);

        ShopReward::create([
            'shop_node_id' => $this->shop()->id,
            'name'         => 'ของที่ปิดไว้',
            'reward_type'  => 'service',
            'points_cost'  => 500,
            'is_active'    => false,
        ]);

        $this->get("/shop-qr/{$token}")
            ->assertOk()
            ->assertSee('กาแฟฟรี')
            ->assertDontSee('ของที่ปิดไว้');
    }

    public function test_ดาวน์โหลด_qr_ร้านได้(): void
    {
        $this->actingAs($this->shopUser())->put('/shop/settings', $this->baseData());

        // สร้าง token ก่อน
        ShopProfile::where('node_id', $this->shop()->id)->firstOrFail()->ensureQrToken();

        $response = $this->actingAs($this->shopUser())
            ->get('/shop/qr-download');

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');

        // SVG ต้องมีข้อมูล URL ของ shop QR
        $this->assertStringContainsString('shop-qr', $response->getContent());
    }

    public function test_คนอื่นดาวน์โหลด_qr_ร้านไม่ได้(): void
    {
        $wh = User::where('email', 'wh@demo.test')->firstOrFail();

        $this->actingAs($wh)
            ->get('/shop/qr-download')
            ->assertForbidden();
    }

    public function test_กรอกเบอร์โทรเพื่อดูแต้มผ่าน_qr(): void
    {
        $this->actingAs($this->shopUser())->put('/shop/settings', $this->baseData());

        $profile = ShopProfile::where('node_id', $this->shop()->id)->firstOrFail();
        $token = $profile->ensureQrToken();

        // สร้างลูกค้าที่มีเบอร์นี้ก่อน
        \App\Models\Customer::create([
            'phone' => '0812345678',
            'name'  => 'ทดสอบ QR',
        ]);

        $this->get("/shop-qr/{$token}?phone=0812345678")
            ->assertOk()
            ->assertSee('0812345678')
            ->assertSee('ทดสอบ QR');
    }
}
