<?php

namespace Tests\Feature;

use App\Models\OrgNode;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TransferService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ทดสอบวงจรใบโอนสินค้า — ครอบคลุมบั๊ก "หักซ้ำสองรอบ" ที่เคยเกิดจริง
 */
class TransferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private OrgNode $wh;
    private OrgNode $swh;
    private Product $product;
    private User $whUser;
    private User $swhUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);

        $this->wh      = OrgNode::where('code', 'WH-BKK')->firstOrFail();
        $this->swh     = OrgNode::where('code', 'SWH-NT')->firstOrFail();
        $this->product = Product::firstOrFail();
        $this->whUser  = User::where('email', 'wh@demo.test')->firstOrFail();
        $this->swhUser = User::where('email', 'swh@demo.test')->firstOrFail();
    }

    private function onHand(OrgNode $node): int
    {
        return (int) StockBalance::where('org_node_id', $node->id)
            ->where('product_id', $this->product->id)->sum('qty_on_hand');
    }

    private function reserved(OrgNode $node): int
    {
        return (int) StockBalance::where('org_node_id', $node->id)
            ->where('product_id', $this->product->id)->sum('qty_reserved');
    }

    private function makeTransfer(int $qty = 60): Transfer
    {
        return app(TransferService::class)->create(
            $this->wh, $this->swh, [['product_id' => $this->product->id, 'qty' => $qty]],
        );
    }

    public function test_การสร้างใบโอนยังไม่กระทบสต๊อก(): void
    {
        $before = $this->onHand($this->wh);
        $transfer = $this->makeTransfer(60);

        $this->assertSame('pending_approve', $transfer->status->value);
        $this->assertSame($before, $this->onHand($this->wh));
        $this->assertSame(0, $this->reserved($this->wh));
    }

    public function test_การอนุมัติจะจองของแต่ยังไม่ตัดยอด(): void
    {
        $before = $this->onHand($this->wh);
        $transfer = $this->makeTransfer(60);

        app(TransferService::class)->approve($transfer, $this->whUser);

        $this->assertSame('approved', $transfer->fresh()->status->value);
        $this->assertSame($before, $this->onHand($this->wh), 'อนุมัติแล้วยอดคงเหลือต้องยังไม่ลด');
        $this->assertSame(60, $this->reserved($this->wh), 'ต้องจองของ 60 ชิ้น');
    }

    /**
     * เคสสำคัญ: ส่ง 55 จาก 60 แล้วรับจริง 53
     * ต้นทางต้องลด 55 / ปลายทางต้องเพิ่มสุทธิ 53 / มี damage 2
     * เคยมีบั๊กหักซ้ำทำให้ปลายทางได้แค่ 51
     */
    public function test_รับของขาดต้องไม่หักซ้ำสองรอบ(): void
    {
        $svc = app(TransferService::class);
        $whBefore  = $this->onHand($this->wh);
        $swhBefore = $this->onHand($this->swh);

        $transfer = $this->makeTransfer(60);
        $svc->approve($transfer, $this->whUser);

        $item = $transfer->items()->first();
        $svc->ship($transfer, [$item->id => 55]);

        $this->assertSame($whBefore - 55, $this->onHand($this->wh), 'ต้นทางต้องลดเท่าที่ส่งจริง');
        $this->assertSame(0, $this->reserved($this->wh), 'ส่วนที่ไม่ได้ส่งต้องปลดจอง');

        $svc->receive($transfer, $this->swhUser, [$item->id => 53]);

        $this->assertSame('received', $transfer->fresh()->status->value);
        $this->assertSame($swhBefore + 53, $this->onHand($this->swh),
            'ปลายทางต้องเพิ่มสุทธิ 53 (รับเข้า 55 แล้วตัด damage 2) ไม่ใช่ 51');

        $damage = StockMovement::where('ref_id', $transfer->id)
            ->where('type', 'damage')->first();

        $this->assertNotNull($damage, 'ต้องบันทึกของหายเป็นรายการ damage');
        $this->assertSame(2, (int) $damage->qty);
    }

    public function test_ยอดตัดออกต้นทางเท่ากับยอดรับเข้าปลายทางเสมอ(): void
    {
        $svc = app(TransferService::class);
        $transfer = $this->makeTransfer(60);
        $svc->approve($transfer, $this->whUser);
        $item = $transfer->items()->first();
        $svc->ship($transfer, [$item->id => 55]);
        $svc->receive($transfer, $this->swhUser, [$item->id => 53]);

        $out = (int) StockMovement::where('ref_id', $transfer->id)
            ->where('org_node_id', $this->wh->id)
            ->where('type', 'transfer_out')->sum('qty');

        $in = (int) StockMovement::where('ref_id', $transfer->id)
            ->where('org_node_id', $this->swh->id)
            ->where('type', 'transfer_in')->sum('qty');

        $this->assertSame($out, $in, 'สองฝั่งต้องบันทึกจำนวนเท่ากัน ส่วนที่หายแยกเป็น damage');
    }

    public function test_ยกเลิกใบโอนต้องปลดจองคืน(): void
    {
        $svc = app(TransferService::class);
        $before = $this->onHand($this->wh);

        $transfer = $this->makeTransfer(30);
        $svc->approve($transfer, $this->whUser);
        $this->assertSame(30, $this->reserved($this->wh));

        $svc->cancel($transfer);

        $this->assertSame('cancelled', $transfer->fresh()->status->value);
        $this->assertSame(0, $this->reserved($this->wh), 'ยกเลิกแล้วต้องปลดจองทั้งหมด');
        $this->assertSame($before, $this->onHand($this->wh));
    }

    public function test_โอนข้ามระดับไม่ได้(): void
    {
        $shop = OrgNode::where('code', 'SH-001')->firstOrFail();

        $this->expectException(\App\Exceptions\InvalidTransferException::class);

        app(TransferService::class)->create(
            $this->wh, $shop, [['product_id' => $this->product->id, 'qty' => 1]],
        );
    }

    public function test_โอนเกินสต๊อกไม่ได้(): void
    {
        $before = $this->onHand($this->wh);
        $count  = Transfer::count();

        try {
            $this->makeTransfer(999999);
            $this->fail('ควรโยน exception เมื่อโอนเกินสต๊อก');
        } catch (\App\Exceptions\InsufficientStockException|\App\Exceptions\InvalidTransferException $e) {
            // คาดหวัง
        }

        $this->assertSame($count, Transfer::count(), 'ต้องไม่มีใบโอนค้างในระบบ');
        $this->assertSame($before, $this->onHand($this->wh), 'สต๊อกต้องไม่ถูกแตะ');
    }

    public function test_สมุดเคลื่อนไหวตรงกับยอดคงเหลือ(): void
    {
        $svc = app(TransferService::class);
        $transfer = $this->makeTransfer(60);
        $svc->approve($transfer, $this->whUser);
        $item = $transfer->items()->first();
        $svc->ship($transfer, [$item->id => 55]);
        $svc->receive($transfer, $this->swhUser, [$item->id => 53]);

        foreach (StockBalance::all() as $balance) {
            $base = StockMovement::where('org_node_id', $balance->org_node_id)
                ->where('product_id', $balance->product_id);

            $in = (clone $base)->where('direction', 'in')->sum('qty');
            $out = (clone $base)->where('direction', 'out')->sum('qty');

            $this->assertSame(
                (int) ($in - $out),
                (int) $balance->qty_on_hand,
                "ยอดคงเหลือของหน่วยงาน {$balance->org_node_id} ไม่ตรงกับสมุดเคลื่อนไหว",
            );
        }
    }
}
