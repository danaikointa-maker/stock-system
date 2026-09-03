<?php

namespace Tests\Feature;

use App\Models\OrgNode;
use App\Models\Product;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TransferService;
use PHPUnit\Framework\Attributes\DataProvider;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ทดสอบสิทธิ์การเข้าถึงหน้าเว็บและการดำเนินการข้ามสายงาน */
class PermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    private function user(string $prefix): User
    {
        return User::where('email', "{$prefix}@demo.test")->firstOrFail();
    }

    public static function pageMatrix(): array
    {
        // [เส้นทาง, บทบาท, สถานะที่คาดหวัง]
        return [
            'คลังใหญ่เข้า POS ไม่ได้'        => ['/pos', 'wh', 403],
            'ตัวแทนเข้า POS ไม่ได้'          => ['/pos', 'agent', 403],
            'ร้านค้าเข้า POS ได้'            => ['/pos', 'shop', 200],
            'ผู้ขายเข้า POS ได้'             => ['/pos', 'seller', 200],
            'แอดมินเข้า POS ได้'             => ['/pos', 'admin', 200],
            'ผู้ขายสร้างใบโอนไม่ได้'         => ['/transfers/create', 'seller', 403],
            'ร้านค้าสร้างใบโอนได้'           => ['/transfers/create', 'shop', 200],
            'ผู้ขายเข้าหน้าสมาชิกไม่ได้'      => ['/members', 'seller', 403],
            'ร้านค้าเข้าหน้าสมาชิกได้'        => ['/members', 'shop', 200],
            'ผู้ขายจัดการสินค้าไม่ได้'        => ['/products', 'seller', 403],
            'ร้านค้าจัดการสินค้าไม่ได้'       => ['/products', 'shop', 403],
            'แอดมินจัดการสินค้าได้'          => ['/products', 'admin', 200],
            'ผู้ขายนับสต๊อกไม่ได้'            => ['/stock/count', 'seller', 403],
            'คลังใหญ่นับสต๊อกได้'            => ['/stock/count', 'wh', 200],
            // ผู้ขายมี ability view-reports จึงดูข้อมูลลูกค้าได้ (แต่แก้ไขไม่ได้ — คุมด้วย manage-members ในหน้า)
            'ผู้ขายดูลูกค้าได้'               => ['/customers', 'seller', 200],
            'แอดมินดูลูกค้าได้'              => ['/customers', 'admin', 200],
            'แอดมินดูของรางวัลได้'           => ['/customers/rewards', 'admin', 200],
        ];
    }

    #[DataProvider('pageMatrix')]
    public function test_สิทธิ์เข้าหน้าเว็บ(string $uri, string $role, int $expected): void
    {
        $response = $this->actingAs($this->user($role))->get($uri);

        $this->assertSame($expected, $response->getStatusCode(),
            "เส้นทาง {$uri} สำหรับ {$role} ควรได้ {$expected}");
    }

    public function test_ปลายทางอนุมัติใบโอนของตัวเองไม่ได้(): void
    {
        $wh  = OrgNode::where('code', 'WH-BKK')->firstOrFail();
        $swh = OrgNode::where('code', 'SWH-NT')->firstOrFail();

        $transfer = app(TransferService::class)->create(
            $wh, $swh, [['product_id' => Product::first()->id, 'qty' => 10]],
        );

        $this->actingAs($this->user('swh'))
            ->patch("/transfers/{$transfer->id}/approve")
            ->assertStatus(403);

        $this->assertSame('pending_approve', $transfer->fresh()->status->value);
    }

    public function test_ต้นทางอนุมัติได้(): void
    {
        $wh  = OrgNode::where('code', 'WH-BKK')->firstOrFail();
        $swh = OrgNode::where('code', 'SWH-NT')->firstOrFail();

        $transfer = app(TransferService::class)->create(
            $wh, $swh, [['product_id' => Product::first()->id, 'qty' => 10]],
        );

        $this->actingAs($this->user('wh'))
            ->patch("/transfers/{$transfer->id}/approve")
            ->assertRedirect();

        $this->assertSame('approved', $transfer->fresh()->status->value);
    }

    public function test_รับของก่อนส่งไม่ได้(): void
    {
        $wh  = OrgNode::where('code', 'WH-BKK')->firstOrFail();
        $swh = OrgNode::where('code', 'SWH-NT')->firstOrFail();

        $transfer = app(TransferService::class)->create(
            $wh, $swh, [['product_id' => Product::first()->id, 'qty' => 10]],
        );
        app(TransferService::class)->approve($transfer, $this->user('wh'));

        $this->actingAs($this->user('swh'))
            ->patch("/transfers/{$transfer->id}/receive")
            ->assertStatus(403);
    }

    public function test_ขายในนามหน่วยงานนอกสายงานไม่ได้(): void
    {
        $wh = OrgNode::where('code', 'WH-BKK')->firstOrFail();

        $this->actingAs($this->user('shop'))
            ->post('/pos', [
                'org_node_id'    => $wh->id,
                'payment_method' => 'cash',
                'items'          => [['product_id' => Product::first()->id, 'qty' => 1, 'unit_price' => 15]],
            ])
            ->assertStatus(403);
    }

    public function test_ผู้ใช้ที่ถูกระงับเข้าระบบไม่ได้(): void
    {
        $user = $this->user('shop');
        $user->update(['is_active' => false]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect();
    }

    public function test_หน้าลูกค้าสแกนQRเข้าได้โดยไม่ต้องล็อกอิน(): void
    {
        $this->get('/scan')->assertStatus(200);
        $this->get('/s/sometoken')->assertStatus(200);
    }
}
