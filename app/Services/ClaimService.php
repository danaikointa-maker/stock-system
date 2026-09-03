<?php

namespace App\Services;

use App\Models\OrgNode;
use App\Models\PointRedemption;
use App\Models\ReimbursementClaim;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ใบเบิกเงินคืน — ร้านเบิกจากเจ้าของระบบโดยตรง
 *
 * วงจร
 *   draft     ร้านสร้างใบเบิกจากรายการที่ยังไม่เคยเบิก
 *   submitted ร้านยื่นให้เจ้าของระบบพิจารณา (แก้ไม่ได้แล้ว)
 *   approved  เจ้าของระบบอนุมัติ
 *   paid      จ่ายเงินแล้ว บันทึกเลขอ้างอิงการโอน
 *   rejected  ปฏิเสธพร้อมเหตุผล -> รายการกลับมาเบิกใหม่ได้
 *
 * กันเบิกซ้ำ 2 ชั้น
 *   1) unique (claimant_node_id, period_ym) — 1 ร้าน 1 งวด 1 ใบ
 *   2) point_redemptions.claim_id — รายการที่ผูกใบเบิกแล้วจะไม่ถูกดึงมาอีก
 */
class ClaimService
{
    public function __construct(
        private SecurityService $security,
        private NotificationService $notify,
    ) {
    }

    /**
     * สร้างใบเบิกจากรายการที่ยังไม่เคยเบิกในงวดนั้น
     *
     * @param  string  $periodYm  รูปแบบ Y-m เช่น 2026-09
     */
    public function createDraft(OrgNode $shop, string $periodYm): ReimbursementClaim
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $periodYm)) {
            throw new RuntimeException('รูปแบบงวดไม่ถูกต้อง');
        }

        // ห้ามเบิกงวดที่ยังไม่จบ เพราะยอดจะเปลี่ยนได้อีก
        if ($periodYm >= now()->format('Y-m')) {
            throw new RuntimeException('เบิกได้เฉพาะงวดที่ผ่านมาแล้วเท่านั้น');
        }

        $existing = ReimbursementClaim::where('claimant_node_id', $shop->id)
            ->where('period_ym', $periodYm)
            ->first();

        if ($existing && $existing->status !== 'rejected') {
            throw new RuntimeException("งวด {$periodYm} มีใบเบิกอยู่แล้ว ({$existing->code})");
        }

        return DB::transaction(function () use ($shop, $periodYm, $existing) {
            [$start, $end] = $this->periodRange($periodYm);

            // ล็อกรายการไว้กันสองคำขอสร้างใบเบิกพร้อมกัน
            $rows = PointRedemption::query()
                ->where('accepting_node_id', $shop->id)
                ->where('status', 'confirmed')
                ->whereNull('claim_id')
                ->whereBetween('redeemed_at', [$start, $end])
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                throw new RuntimeException("งวด {$periodYm} ไม่มีรายการที่ต้องเบิก");
            }

            $minPoints = (int) SystemSetting::get('claim_min_points', 400);
            $totalPoints = (int) $rows->sum('points_used');

            if ($totalPoints < $minPoints) {
                throw new RuntimeException(
                    "ยอดขั้นต่ำในการเบิกคือ {$minPoints} แต้ม (งวดนี้มี {$totalPoints} แต้ม)",
                );
            }

            // ใบที่เคยถูกปฏิเสธ นำกลับมาใช้ใหม่ได้
            $claim = $existing ?: new ReimbursementClaim();

            $claim->fill([
                'code'             => $existing->code ?? $this->generateCode($periodYm),
                'claimant_node_id' => $shop->id,
                'period_ym'        => $periodYm,
                'total_points'     => $totalPoints,
                'point_value'      => (float) SystemSetting::get('point_value_baht', 0.25),
                'total_amount'     => round($rows->sum('cash_value'), 2),
                'entry_count'      => $rows->count(),
                'status'           => 'draft',
                'reject_reason'    => null,
            ])->save();

            // ผูกรายการเข้ากับใบเบิก กันถูกดึงไปเบิกซ้ำ
            PointRedemption::whereIn('id', $rows->pluck('id'))
                ->update(['claim_id' => $claim->id]);

            return $claim->fresh();
        });
    }

    /** ร้านยื่นใบเบิก */
    public function submit(ReimbursementClaim $claim): ReimbursementClaim
    {
        if ($claim->status !== 'draft') {
            throw new RuntimeException('ยื่นได้เฉพาะใบเบิกที่เป็นร่างเท่านั้น');
        }

        $claim->update([
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->security->raiseAlert(
            'claim_submitted',
            'info',
            "มีใบเบิกเงินใหม่รออนุมัติ: {$claim->code}",
            [
                'claim_id' => $claim->id,
                'shop'     => $claim->claimant->name ?? '',
                'amount'   => (float) $claim->total_amount,
            ],
            route('admin.claims.show', $claim),
        );

        return $claim->fresh();
    }

    /** เจ้าของระบบอนุมัติ */
    public function approve(ReimbursementClaim $claim, int $userId, ?string $note = null): ReimbursementClaim
    {
        if ($claim->status !== 'submitted') {
            throw new RuntimeException('อนุมัติได้เฉพาะใบเบิกที่ยื่นเข้ามาแล้ว');
        }

        $claim->update([
            'status'      => 'approved',
            'approved_at' => now(),
            'approved_by' => $userId,
            'note'        => $note,
        ]);

        $this->notify->claimStatusChanged($claim->fresh(), 'approved');

        return $claim->fresh();
    }

    /** บันทึกการจ่ายเงิน */
    public function markPaid(
        ReimbursementClaim $claim,
        int $userId,
        string $method,
        ?string $ref = null,
    ): ReimbursementClaim {
        if ($claim->status !== 'approved') {
            throw new RuntimeException('บันทึกการจ่ายได้เฉพาะใบที่อนุมัติแล้ว');
        }

        return DB::transaction(function () use ($claim, $userId, $method, $ref) {
            $claim->update([
                'status'         => 'paid',
                'paid_at'        => now(),
                'payment_method' => $method,
                'payment_ref'    => $ref,
            ]);

            // หมายเหตุ: ไม่ต้องมีตารางยอดสะสมแยก
            // "ยอดรอเบิก" คำนวณจาก point_redemptions ที่ claim_id เป็น null
            // และ "เบิกไปแล้ว" คือรายการที่ผูกกับใบเบิกสถานะ paid
            // วิธีนี้ไม่มีทางที่ยอดสรุปจะเพี้ยนจากยอดจริง

            $this->security->log(
                'claim_paid',
                "จ่ายเงินใบเบิก {$claim->code} จำนวน {$claim->total_amount} บาท",
                'info',
                ['claim_id' => $claim->id, 'paid_by' => $userId],
            );

            $this->notify->claimStatusChanged($claim->fresh(), 'paid');

            return $claim->fresh();
        });
    }

    /** ปฏิเสธใบเบิก — ปลดรายการให้กลับมาเบิกใหม่ได้ */
    public function reject(ReimbursementClaim $claim, int $userId, string $reason): ReimbursementClaim
    {
        if (! in_array($claim->status, ['submitted', 'approved'], true)) {
            throw new RuntimeException('ปฏิเสธได้เฉพาะใบที่ยังไม่จ่ายเงิน');
        }

        return DB::transaction(function () use ($claim, $userId, $reason) {
            $claim->update([
                'status'        => 'rejected',
                'reject_reason' => $reason,
                'approved_by'   => $userId,
                'approved_at'   => now(),
            ]);

            // ปลดรายการออกจากใบเบิก เพื่อให้ยื่นใหม่ได้
            PointRedemption::where('claim_id', $claim->id)->update(['claim_id' => null]);

            $this->notify->claimStatusChanged($claim->fresh(), 'rejected');

            return $claim->fresh();
        });
    }

    /** ยกเลิกใบร่าง */
    public function discardDraft(ReimbursementClaim $claim): void
    {
        if ($claim->status !== 'draft') {
            throw new RuntimeException('ยกเลิกได้เฉพาะใบร่าง');
        }

        DB::transaction(function () use ($claim) {
            PointRedemption::where('claim_id', $claim->id)->update(['claim_id' => null]);
            $claim->delete();
        });
    }

    /**
     * งวดที่ยังไม่ได้เบิก (มีรายการค้างอยู่)
     *
     * @return array<int, array{period: string, points: int, amount: float, count: int}>
     */
    public function unclaimedPeriods(OrgNode $shop): array
    {
        $rows = PointRedemption::query()
            ->where('accepting_node_id', $shop->id)
            ->where('status', 'confirmed')
            ->whereNull('claim_id')
            ->where('redeemed_at', '<', now()->startOfMonth())
            ->get(['redeemed_at', 'points_used', 'cash_value']);

        return $rows
            ->groupBy(fn ($r) => $r->redeemed_at?->format('Y-m'))
            ->filter(fn ($g, $k) => (bool) $k)
            ->map(fn ($g, $period) => [
                'period' => $period,
                'points' => (int) $g->sum('points_used'),
                'amount' => round($g->sum('cash_value'), 2),
                'count'  => $g->count(),
            ])
            ->sortKeysDesc()
            ->values()
            ->all();
    }

    /** ช่วงวันที่ของงวด */
    private function periodRange(string $periodYm): array
    {
        [$y, $m] = explode('-', $periodYm);
        $start = now()->setDate((int) $y, (int) $m, 1)->startOfMonth();

        return [$start, (clone $start)->endOfMonth()];
    }

    private function generateCode(string $periodYm): string
    {
        return 'CLM-' . str_replace('-', '', $periodYm) . '-' . strtoupper(bin2hex(random_bytes(2)));
    }
}
