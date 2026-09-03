<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\OrgNode;
use App\Models\PointTransaction;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\ProductQrcode;
use App\Models\Reward;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\PointService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ทดสอบหน้าสินค้า/ล็อต/QR, การนับสต๊อก และระบบคะแนน-ของรางวัล
 * ครอบคลุมบั๊กที่เจอตอนทดสอบ: enum ของ point_transactions และ reward_redemptions
 */
class CatalogAndPointsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->admin = User::where('email', 'admin@demo.test')->firstOrFail();
    }

    public function test_สร้างสินค้าและเปิดล็อตแล้วออกQRได้(): void
    {
        $this->actingAs($this->admin)
            ->post('/products', [
                'sku' => 'SKU-T1', 'name' => 'สินค้าทดสอบ', 'pack_size' => 1,
                'cost_price' => 10, 'retail_price' => 25, 'points_per_unit' => 2,
                'status' => 'active',
            ])->assertRedirect();

        $product = Product::where('sku', 'SKU-T1')->firstOrFail();

        $this->actingAs($this->admin)
            ->post("/products/{$product->id}/lots", ['lot_no' => 'LT-1', 'qty_produced' => 100])
            ->assertRedirect();

        $lot = ProductLot::where('lot_no', 'LT-1')->firstOrFail();

        $this->actingAs($this->admin)
            ->post("/products/{$product->id}/lots/{$lot->id}/qr", ['qty' => 40])
            ->assertRedirect();

        $this->assertSame(40, ProductQrcode::where('lot_id', $lot->id)->count());
    }

    public function test_ออกQRเกินจำนวนที่ผลิตไม่ได้(): void
    {
        $product = Product::firstOrFail();
        $lot = $product->lots()->create(['lot_no' => 'LT-CAP', 'qty_produced' => 50]);

        $this->actingAs($this->admin)
            ->post("/products/{$product->id}/lots/{$lot->id}/qr", ['qty' => 40])
            ->assertRedirect();

        // ขอเกินโควตาที่เหลือ (เหลือ 10)
        $this->actingAs($this->admin)
            ->post("/products/{$product->id}/lots/{$lot->id}/qr", ['qty' => 30])
            ->assertSessionHasErrors('qty');

        $this->assertSame(40, ProductQrcode::where('lot_id', $lot->id)->count(),
            'จำนวน QR ต้องไม่เพิ่มเมื่อขอเกินโควตา');
    }

    public function test_เลขล็อตซ้ำในสินค้าเดียวกันไม่ได้(): void
    {
        $product = Product::firstOrFail();
        $product->lots()->create(['lot_no' => 'DUP', 'qty_produced' => 10]);

        $this->actingAs($this->admin)
            ->post("/products/{$product->id}/lots", ['lot_no' => 'DUP', 'qty_produced' => 5])
            ->assertSessionHasErrors('lot_no');

        $this->assertSame(1, ProductLot::where('lot_no', 'DUP')->count());
    }

    public function test_นับสต๊อกแล้วปรับยอดพร้อมบันทึกการ์ดสินค้า(): void
    {
        $node = OrgNode::where('code', 'WH-BKK')->firstOrFail();
        $balance = StockBalance::where('org_node_id', $node->id)
            ->where('qty_on_hand', '>', 10)->firstOrFail();
        $sys = $balance->qty_on_hand;

        $this->actingAs($this->admin)->post('/stock/count', [
            'org_node_id' => $node->id,
            'note'        => 'นับประจำเดือน',
            'counted'     => [['balance_id' => $balance->id, 'qty' => $sys - 3]],
        ])->assertRedirect();

        $this->assertSame($sys - 3, $balance->fresh()->qty_on_hand);

        $movement = StockMovement::where('org_node_id', $node->id)
            ->where('type', 'adjust_out')->latest('id')->firstOrFail();

        $this->assertSame(3, (int) $movement->qty);
        $this->assertSame($this->admin->id, (int) $movement->created_by);
    }

    /** เว้นว่าง = ไม่ได้นับ ต้องไม่ถูกตีความว่านับได้ 0 */
    public function test_เว้นช่องนับว่างต้องไม่แตะยอด(): void
    {
        $node = OrgNode::where('code', 'WH-BKK')->firstOrFail();
        $balance = StockBalance::where('org_node_id', $node->id)
            ->where('qty_on_hand', '>', 0)->firstOrFail();
        $before = $balance->qty_on_hand;

        $this->actingAs($this->admin)->post('/stock/count', [
            'org_node_id' => $node->id,
            'counted'     => [['balance_id' => $balance->id, 'qty' => null]],
        ])->assertRedirect();

        $this->assertSame($before, $balance->fresh()->qty_on_hand,
            'ช่องว่างต้องไม่ถูกตีความว่านับได้ 0');
    }

    public function test_ปรับคะแนนด้วยมือต้องระบุเหตุผล(): void
    {
        $customer = Customer::create(['phone' => '0870001234', 'name' => 'ทดสอบ', 'points_balance' => 0]);

        $this->actingAs($this->admin)
            ->post("/customers/{$customer->id}/points", ['points' => 50])
            ->assertSessionHasErrors('note');

        $this->assertSame(0, $customer->fresh()->points_balance);

        // ใส่เหตุผลแล้วต้องผ่าน — และต้องใช้ type ที่ schema รองรับ
        $this->actingAs($this->admin)
            ->post("/customers/{$customer->id}/points", ['points' => 50, 'note' => 'ชดเชยเคลม'])
            ->assertRedirect();

        $this->assertSame(50, $customer->fresh()->points_balance);
    }

    public function test_แลกของรางวัลแล้วยกเลิกต้องคืนคะแนนและคืนสต๊อก(): void
    {
        $customer = Customer::create(['phone' => '0870005555', 'name' => 'ทดสอบ', 'points_balance' => 200]);
        $reward = Reward::create([
            'name' => 'เสื้อยืด', 'points_cost' => 50, 'stock_qty' => 10, 'status' => 'active',
        ]);

        $redemption = app(PointService::class)->redeemReward($customer, $reward, 'ที่อยู่ทดสอบ');

        $this->assertSame(150, $customer->fresh()->points_balance);
        $this->assertSame(9, $reward->fresh()->stock_qty);

        // ยกเลิก -> ต้องคืนคะแนน + คืนของรางวัล (สถานะที่ schema รองรับคือ rejected)
        $this->actingAs($this->admin)
            ->patch("/customers/redemptions/{$redemption->id}/ship", ['status' => 'rejected'])
            ->assertRedirect();

        $this->assertSame(200, $customer->fresh()->points_balance, 'ต้องคืนคะแนนครบ');
        $this->assertSame(10, $reward->fresh()->stock_qty, 'ต้องคืนของรางวัลเข้าสต๊อก');
        $this->assertSame('rejected', $redemption->fresh()->status);
    }

    public function test_ดำเนินการรายการแลกซ้ำไม่ได้(): void
    {
        $customer = Customer::create(['phone' => '0870006666', 'name' => 'ทดสอบ', 'points_balance' => 200]);
        $reward = Reward::create([
            'name' => 'กระบอกน้ำ', 'points_cost' => 50, 'stock_qty' => 5, 'status' => 'active',
        ]);
        $redemption = app(PointService::class)->redeemReward($customer, $reward);

        $this->actingAs($this->admin)
            ->patch("/customers/redemptions/{$redemption->id}/ship", ['status' => 'shipped'])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->patch("/customers/redemptions/{$redemption->id}/ship", ['status' => 'rejected'])
            ->assertSessionHasErrors('redeem');

        $this->assertSame('shipped', $redemption->fresh()->status);
        $this->assertSame(150, $customer->fresh()->points_balance, 'คะแนนต้องไม่ถูกคืนซ้ำ');
    }

    public function test_แลกของรางวัลเกินคะแนนที่มีไม่ได้(): void
    {
        $customer = Customer::create(['phone' => '0870007777', 'name' => 'ทดสอบ', 'points_balance' => 10]);
        $reward = Reward::create([
            'name' => 'ของแพง', 'points_cost' => 500, 'stock_qty' => 5, 'status' => 'active',
        ]);

        $this->expectException(\Throwable::class);

        try {
            app(PointService::class)->redeemReward($customer, $reward);
        } finally {
            $this->assertSame(10, $customer->fresh()->points_balance);
            $this->assertSame(5, $reward->fresh()->stock_qty);
        }
    }

    public function test_ยอดคะแนนตรงกับผลรวมประวัติเสมอ(): void
    {
        $customer = Customer::create(['phone' => '0870008888', 'name' => 'ทดสอบ', 'points_balance' => 0]);
        $points = app(PointService::class);

        $points->earn($customer, 30, 'earn_scan', note: 'สแกน 1');
        $points->earn($customer, 20, 'earn_scan', note: 'สแกน 2');
        $points->deduct($customer, 15, 'redeem', note: 'แลกของ');

        $sum = (int) PointTransaction::where('customer_id', $customer->id)->sum('points');

        $this->assertSame(35, $customer->fresh()->points_balance);
        $this->assertSame($sum, $customer->fresh()->points_balance);
        $this->assertSame($sum, $customer->fresh()->recalculatedBalance());
    }
}
