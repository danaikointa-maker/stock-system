<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\OrgNode;
use App\Models\PointRedemption;
use App\Models\ReimbursementClaim;
use App\Models\User;
use App\Services\ClaimService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ทดสอบใบเบิกเงินคืน
 *
 * เน้นเรื่องเงิน จึงต้องกันให้แน่น
 *   - เบิกซ้ำงวดเดิมไม่ได้
 *   - รายการที่เบิกไปแล้วต้องไม่ถูกดึงมาเบิกอีก
 *   - เบิกงวดปัจจุบันที่ยังไม่จบไม่ได้
 *   - ร้านอื่นเข้าถึงใบเบิกของเราไม่ได้
 *   - เฉพาะเจ้าของระบบเท่านั้นที่อนุมัติ/จ่ายได้
 */
class ClaimTest extends TestCase
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

    private function adminUser(): User
    {
        return User::where('email', 'admin@demo.test')->firstOrFail();
    }

    /** สร้างรายการแลกแต้มย้อนหลังในงวดที่ระบุ */
    private function makeRedemptions(string $periodYm, int $count = 3, int $pointsEach = 400): void
    {
        $shop = $this->shop();

        $customer = Customer::firstOrCreate(
            ['phone' => '0812345678'],
            ['name' => 'ลูกค้าทดสอบ', 'status' => 'active'],
        );

        [$y, $m] = explode('-', $periodYm);
        $when = now()->setDate((int) $y, (int) $m, 10)->setTime(12, 0);

        for ($i = 0; $i < $count; $i++) {
            PointRedemption::create([
                'code'              => 'RDM-T' . random_int(100000, 999999),
                'customer_id'       => $customer->id,
                'issuer_node_id'    => $shop->id,
                'accepting_node_id' => $shop->id,
                'redeem_type'       => 'service',
                'reward_name'       => 'บริการทดสอบ ' . ($i + 1),
                'points_used'       => $pointsEach,
                'point_value'       => 0.25,
                'cash_value'        => $pointsEach * 0.25,
                'status'            => 'confirmed',
                'redeemed_at'       => $when,
            ]);
        }
    }

    private function lastMonth(): string
    {
        return now()->subMonth()->format('Y-m');
    }

    public function test_ร้านเข้าหน้าเบิกเงินได้(): void
    {
        $this->actingAs($this->shopUser())
            ->get('/claims')
            ->assertOk()
            ->assertSee('เบิกเงินคืน');
    }

    public function test_คลังใหญ่เข้าหน้าเบิกเงินไม่ได้(): void
    {
        $wh = User::where('email', 'wh@demo.test')->firstOrFail();

        $this->actingAs($wh)->get('/claims')->assertForbidden();
    }

    public function test_สร้างใบเบิกแล้วยอดถูกต้อง(): void
    {
        $period = $this->lastMonth();
        $this->makeRedemptions($period, 3, 400);

        $this->actingAs($this->shopUser())
            ->post('/claims', ['period_ym' => $period])
            ->assertRedirect();

        $claim = ReimbursementClaim::firstOrFail();

        $this->assertSame(1200, (int) $claim->total_points);
        $this->assertEquals(300.0, (float) $claim->total_amount);
        $this->assertSame(3, (int) $claim->entry_count);
        $this->assertSame('draft', $claim->status);

        // รายการต้องถูกผูกกับใบเบิกแล้ว
        $this->assertSame(0, PointRedemption::whereNull('claim_id')->count());
    }

    public function test_เบิกงวดปัจจุบันที่ยังไม่จบไม่ได้(): void
    {
        $this->makeRedemptions(now()->format('Y-m'), 3, 400);

        $this->actingAs($this->shopUser())
            ->post('/claims', ['period_ym' => now()->format('Y-m')])
            ->assertSessionHasErrors('period_ym');

        $this->assertDatabaseCount('reimbursement_claims', 0);
    }

    public function test_เบิกซ้ำงวดเดิมไม่ได้(): void
    {
        $period = $this->lastMonth();
        $this->makeRedemptions($period, 3, 400);

        $this->actingAs($this->shopUser())->post('/claims', ['period_ym' => $period]);

        // ครั้งที่สองต้องถูกปฏิเสธ
        $this->actingAs($this->shopUser())
            ->post('/claims', ['period_ym' => $period])
            ->assertSessionHasErrors('period_ym');

        $this->assertDatabaseCount('reimbursement_claims', 1);
    }

    public function test_งวดที่ไม่มีรายการเบิกไม่ได้(): void
    {
        $this->actingAs($this->shopUser())
            ->post('/claims', ['period_ym' => $this->lastMonth()])
            ->assertSessionHasErrors('period_ym');
    }

    public function test_ยอดต่ำกว่าขั้นต่ำเบิกไม่ได้(): void
    {
        // ขั้นต่ำ 400 แต้ม สร้างแค่ 100
        $this->makeRedemptions($this->lastMonth(), 1, 100);

        $this->actingAs($this->shopUser())
            ->post('/claims', ['period_ym' => $this->lastMonth()])
            ->assertSessionHasErrors('period_ym');
    }

    public function test_ยื่นใบเบิกแล้วสถานะเปลี่ยนและแจ้งเตือนแอดมิน(): void
    {
        $period = $this->lastMonth();
        $this->makeRedemptions($period, 3, 400);

        $this->actingAs($this->shopUser())->post('/claims', ['period_ym' => $period]);
        $claim = ReimbursementClaim::firstOrFail();

        $this->actingAs($this->shopUser())
            ->patch("/claims/{$claim->id}/submit")
            ->assertRedirect();

        $this->assertSame('submitted', $claim->fresh()->status);
        $this->assertNotNull($claim->fresh()->submitted_at);

        $this->assertDatabaseHas('admin_alerts', ['alert_type' => 'claim_submitted']);
    }

    public function test_ยกเลิกใบร่างแล้วรายการกลับมาเบิกได้(): void
    {
        $period = $this->lastMonth();
        $this->makeRedemptions($period, 3, 400);

        $this->actingAs($this->shopUser())->post('/claims', ['period_ym' => $period]);
        $claim = ReimbursementClaim::firstOrFail();

        $this->actingAs($this->shopUser())
            ->delete("/claims/{$claim->id}")
            ->assertRedirect();

        $this->assertDatabaseCount('reimbursement_claims', 0);
        // รายการต้องถูกปลดออก
        $this->assertSame(3, PointRedemption::whereNull('claim_id')->count());
    }

    public function test_ร้านอื่นดูใบเบิกของเราไม่ได้(): void
    {
        $period = $this->lastMonth();
        $this->makeRedemptions($period, 3, 400);

        $this->actingAs($this->shopUser())->post('/claims', ['period_ym' => $period]);
        $claim = ReimbursementClaim::firstOrFail();

        // เปลี่ยนเจ้าของเป็นร้านอื่น
        $claim->update(['claimant_node_id' => OrgNode::where('code', 'WH-BKK')->first()->id]);

        $this->actingAs($this->shopUser())
            ->get("/claims/{$claim->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('security_events', [
            'event_type' => 'permission_denied',
            'severity'   => 'high',
        ]);
    }

    public function test_ร้านอนุมัติใบเบิกตัวเองไม่ได้(): void
    {
        $period = $this->lastMonth();
        $this->makeRedemptions($period, 3, 400);

        $this->actingAs($this->shopUser())->post('/claims', ['period_ym' => $period]);
        $claim = ReimbursementClaim::firstOrFail();
        $this->actingAs($this->shopUser())->patch("/claims/{$claim->id}/submit");

        // ร้านไม่มีสิทธิ์ approve-claim
        $this->actingAs($this->shopUser())
            ->patch("/admin/claims/{$claim->id}/approve")
            ->assertForbidden();

        $this->assertSame('submitted', $claim->fresh()->status);
    }

    public function test_เจ้าของระบบอนุมัติและจ่ายเงินได้(): void
    {
        $period = $this->lastMonth();
        $this->makeRedemptions($period, 3, 400);

        $this->actingAs($this->shopUser())->post('/claims', ['period_ym' => $period]);
        $claim = ReimbursementClaim::firstOrFail();
        $this->actingAs($this->shopUser())->patch("/claims/{$claim->id}/submit");

        // อนุมัติ
        $this->actingAs($this->adminUser())
            ->patch("/admin/claims/{$claim->id}/approve", ['note' => 'ตรวจแล้วถูกต้อง'])
            ->assertRedirect();

        $this->assertSame('approved', $claim->fresh()->status);

        // จ่ายเงิน
        $this->actingAs($this->adminUser())
            ->patch("/admin/claims/{$claim->id}/pay", [
                'payment_method' => 'transfer',
                'payment_ref'    => 'TRX-12345',
            ])
            ->assertRedirect();

        $fresh = $claim->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertSame('TRX-12345', $fresh->payment_ref);
        $this->assertNotNull($fresh->paid_at);
    }

    public function test_จ่ายเงินโดยยังไม่อนุมัติไม่ได้(): void
    {
        $period = $this->lastMonth();
        $this->makeRedemptions($period, 3, 400);

        $this->actingAs($this->shopUser())->post('/claims', ['period_ym' => $period]);
        $claim = ReimbursementClaim::firstOrFail();
        $this->actingAs($this->shopUser())->patch("/claims/{$claim->id}/submit");

        // ข้ามขั้นอนุมัติ
        $this->actingAs($this->adminUser())
            ->patch("/admin/claims/{$claim->id}/pay", ['payment_method' => 'transfer'])
            ->assertSessionHasErrors('status');

        $this->assertSame('submitted', $claim->fresh()->status);
    }

    public function test_ปฏิเสธแล้วรายการกลับมาเบิกใหม่ได้(): void
    {
        $period = $this->lastMonth();
        $this->makeRedemptions($period, 3, 400);

        $this->actingAs($this->shopUser())->post('/claims', ['period_ym' => $period]);
        $claim = ReimbursementClaim::firstOrFail();
        $this->actingAs($this->shopUser())->patch("/claims/{$claim->id}/submit");

        $this->actingAs($this->adminUser())
            ->patch("/admin/claims/{$claim->id}/reject", ['reject_reason' => 'ยอดไม่ตรง'])
            ->assertRedirect();

        $this->assertSame('rejected', $claim->fresh()->status);

        // รายการต้องถูกปลดให้เบิกใหม่ได้
        $this->assertSame(3, PointRedemption::whereNull('claim_id')->count());

        // ยื่นใหม่ได้
        $this->actingAs($this->shopUser())
            ->post('/claims', ['period_ym' => $period])
            ->assertRedirect();

        $this->assertSame('draft', $claim->fresh()->status);
    }

    public function test_ปฏิเสธต้องระบุเหตุผล(): void
    {
        $period = $this->lastMonth();
        $this->makeRedemptions($period, 3, 400);

        $this->actingAs($this->shopUser())->post('/claims', ['period_ym' => $period]);
        $claim = ReimbursementClaim::firstOrFail();
        $this->actingAs($this->shopUser())->patch("/claims/{$claim->id}/submit");

        $this->actingAs($this->adminUser())
            ->patch("/admin/claims/{$claim->id}/reject", [])
            ->assertSessionHasErrors('reject_reason');
    }

    public function test_รายการที่เบิกแล้วไม่ถูกดึงมาเบิกซ้ำในงวดถัดไป(): void
    {
        $p1 = now()->subMonths(2)->format('Y-m');
        $p2 = now()->subMonth()->format('Y-m');

        $this->makeRedemptions($p1, 2, 500);   // 1000 แต้ม
        $this->makeRedemptions($p2, 2, 300);   // 600 แต้ม

        $this->actingAs($this->shopUser())->post('/claims', ['period_ym' => $p1]);
        $this->actingAs($this->shopUser())->post('/claims', ['period_ym' => $p2]);

        $claims = ReimbursementClaim::orderBy('period_ym')->get();

        $this->assertCount(2, $claims);
        $this->assertSame(1000, (int) $claims[0]->total_points);
        $this->assertSame(600, (int) $claims[1]->total_points);
    }

    public function test_เจ้าของระบบเห็นใบเบิกของทุกร้าน(): void
    {
        $period = $this->lastMonth();
        $this->makeRedemptions($period, 3, 400);

        $this->actingAs($this->shopUser())->post('/claims', ['period_ym' => $period]);

        $this->actingAs($this->adminUser())
            ->get('/admin/claims')
            ->assertOk()
            ->assertSee('อนุมัติใบเบิกเงิน');
    }

    public function test_ยอดรวมใบเบิกตรงกับผลรวมรายการเสมอ(): void
    {
        $period = $this->lastMonth();
        $this->makeRedemptions($period, 5, 350);

        $this->actingAs($this->shopUser())->post('/claims', ['period_ym' => $period]);
        $claim = ReimbursementClaim::firstOrFail();

        $sum = PointRedemption::where('claim_id', $claim->id)
            ->selectRaw('SUM(points_used) p, SUM(cash_value) a')->first();

        $this->assertSame((int) $sum->p, (int) $claim->total_points);
        $this->assertEquals((float) $sum->a, (float) $claim->total_amount);
    }
}
