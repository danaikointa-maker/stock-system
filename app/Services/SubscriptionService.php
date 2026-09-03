<?php

namespace App\Services;

use App\Models\OrgNode;
use App\Models\ShopMonthlyAllowance;
use App\Models\ShopPackage;
use App\Models\ShopSubscription;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * สมัครสมาชิกให้ร้านค้า — ตัวแทนเป็นคนกรอก
 *
 * ตัวแทนแค่เลือกแพ็กเกจ ระบบคำนวณทุกอย่างให้
 *   - วันเริ่ม/วันหมดอายุ ตามจำนวนเดือนของแพ็กเกจ
 *   - วงเงินแต้มรายเดือน
 *   - คอมมิชชั่นของตัวแทน
 *
 * ค่าทั้งหมดถูกล็อกไว้ในใบสมัคร ถ้าแอดมินแก้ราคาแพ็กเกจภายหลัง
 * สัญญาเดิมของร้านจะไม่เปลี่ยน (ป้องกันข้อพิพาท)
 */
class SubscriptionService
{
    public function __construct(private SecurityService $security)
    {
    }

    /**
     * สร้างใบสมัครให้ร้าน
     *
     * @param  OrgNode  $shop       ร้านที่สมัคร
     * @param  OrgNode  $recruiter  ตัวแทนที่พาเข้ามา (ได้คอมมิชชั่น)
     */
    public function subscribe(
        OrgNode $shop,
        ShopPackage $package,
        OrgNode $recruiter,
        ?string $startsOn = null,
        bool $autoRenew = false,
        ?string $note = null,
    ): ShopSubscription {
        if (! $package->is_active) {
            throw new RuntimeException('แพ็กเกจนี้ถูกปิดใช้งานแล้ว');
        }

        // ร้านหนึ่งมีสมาชิกที่ใช้งานอยู่ได้ครั้งละหนึ่งใบเท่านั้น
        $active = ShopSubscription::where('shop_node_id', $shop->id)
            ->whereIn('status', ['active', 'pending_payment'])
            ->first();

        if ($active) {
            throw new RuntimeException(
                "ร้านนี้มีสมาชิกอยู่แล้ว ({$active->code}) กรุณายกเลิกใบเดิมก่อน",
            );
        }

        return DB::transaction(function () use ($shop, $package, $recruiter, $startsOn, $autoRenew, $note) {
            $start = $startsOn ? now()->parse($startsOn)->startOfDay() : now()->startOfDay();

            $sub = ShopSubscription::create([
                'code'                => $this->generateCode(),
                'shop_node_id'        => $shop->id,
                'package_id'          => $package->id,
                'recruiter_node_id'   => $recruiter->id,
                // ล็อกค่าไว้ ณ วันสมัคร
                'monthly_point_limit' => $package->monthly_point_limit,
                'price_paid'          => $package->price,
                'allow_rollover'      => $package->allow_rollover,
                'allow_cash_redeem'   => $package->allow_cash_redeem,
                'commission_amount'   => $package->commissionFor(),
                'starts_on'           => $start,
                'ends_on'             => (clone $start)->addMonths($package->duration_months)->subDay(),
                'status'              => $package->price > 0 ? 'pending_payment' : 'active',
                'auto_renew'          => $autoRenew,
                'paid_at'             => $package->price > 0 ? null : now(),
                'note'                => $note,
            ]);

            // แพ็กเกจฟรีเปิดวงเงินให้ทันที
            if ($sub->status === 'active') {
                $this->openAllowance($sub);
            }

            return $sub->fresh();
        });
    }

    /** ยืนยันการชำระเงิน แล้วเปิดวงเงินให้ร้านใช้ได้ */
    public function confirmPayment(
        ShopSubscription $sub,
        int $userId,
        ?string $paymentRef = null,
    ): ShopSubscription {
        if ($sub->status !== 'pending_payment') {
            throw new RuntimeException('ใบสมัครนี้ไม่ได้อยู่ในสถานะรอชำระเงิน');
        }

        return DB::transaction(function () use ($sub, $userId, $paymentRef) {
            $sub->update([
                'status'      => 'active',
                'paid_at'     => now(),
                'payment_ref' => $paymentRef,
                'approved_by' => $userId,
            ]);

            $this->openAllowance($sub->fresh());

            return $sub->fresh();
        });
    }

    /** ยกเลิกสมาชิก */
    public function cancel(ShopSubscription $sub, string $reason): ShopSubscription
    {
        if (in_array($sub->status, ['cancelled', 'expired'], true)) {
            throw new RuntimeException('ใบสมัครนี้ถูกยกเลิกหรือหมดอายุแล้ว');
        }

        $sub->update([
            'status'        => 'cancelled',
            'cancelled_at'  => now(),
            'cancel_reason' => $reason,
        ]);

        $this->security->log(
            'subscription_cancelled',
            "ยกเลิกสมาชิกร้าน {$sub->shop->name} ({$sub->code}): {$reason}",
            'medium',
            ['subscription_id' => $sub->id],
        );

        return $sub->fresh();
    }

    /** ต่ออายุด้วยแพ็กเกจเดิมหรือแพ็กเกจใหม่ */
    public function renew(ShopSubscription $old, ?ShopPackage $package = null): ShopSubscription
    {
        $package = $package ?: $old->package;

        if (! $package) {
            throw new RuntimeException('ไม่พบแพ็กเกจสำหรับต่ออายุ');
        }

        return DB::transaction(function () use ($old, $package) {
            $old->update(['status' => 'expired']);

            // เริ่มนับต่อจากวันหมดอายุเดิม ถ้ายังไม่หมด
            $start = $old->ends_on->isFuture()
                ? $old->ends_on->copy()->addDay()
                : now()->startOfDay();

            return $this->subscribe(
                $old->shop,
                $package,
                $old->recruiter,
                $start->toDateString(),
                $old->auto_renew,
                "ต่ออายุจาก {$old->code}",
            );
        });
    }

    /**
     * เปิดวงเงินของเดือนปัจจุบัน
     *
     * เดือนที่สมัครกลางเดือนได้วงเงินเต็ม ไม่คิดตามสัดส่วน
     * เพื่อความเรียบง่ายและเป็นผลดีกับร้าน
     */
    public function openAllowance(ShopSubscription $sub, ?string $periodYm = null): ShopMonthlyAllowance
    {
        $period = $periodYm ?: now()->format('Y-m');

        $existing = ShopMonthlyAllowance::where('shop_node_id', $sub->shop_node_id)
            ->where('period_ym', $period)
            ->first();

        if ($existing) {
            return $existing;
        }

        // ยกยอดจากเดือนก่อน ถ้าแพ็กเกจอนุญาต
        $rollover = 0;

        if ($sub->allow_rollover) {
            $prev = ShopMonthlyAllowance::where('shop_node_id', $sub->shop_node_id)
                ->where('period_ym', now()->parse($period . '-01')->subMonth()->format('Y-m'))
                ->first();

            $rollover = max(0, (int) ($prev->remaining_points ?? 0));
        }

        $limit = (int) $sub->monthly_point_limit;

        return ShopMonthlyAllowance::create([
            'subscription_id'  => $sub->id,
            'shop_node_id'     => $sub->shop_node_id,
            'period_ym'        => $period,
            'limit_points'     => $limit,
            'rollover_points'  => $rollover,
            'remaining_points' => $limit + $rollover,
        ]);
    }

    /**
     * รีเซตวงเงินรายเดือนให้ทุกร้านที่สมาชิกยังใช้งานได้
     * เรียกจาก scheduled command ทุกวันที่ 1
     *
     * @return array{opened: int, expired: int}
     */
    public function resetMonthly(?string $periodYm = null): array
    {
        $period = $periodYm ?: now()->format('Y-m');
        $opened = 0;

        // ปิดสมาชิกที่หมดอายุก่อน
        $expired = ShopSubscription::where('status', 'active')
            ->whereDate('ends_on', '<', now())
            ->update(['status' => 'expired']);

        ShopSubscription::where('status', 'active')
            ->whereDate('ends_on', '>=', now())
            ->chunkById(200, function ($subs) use ($period, &$opened) {
                foreach ($subs as $sub) {
                    $this->openAllowance($sub, $period);
                    $opened++;
                }
            });

        return ['opened' => $opened, 'expired' => (int) $expired];
    }

    private function generateCode(): string
    {
        return 'SUB-' . now()->format('ym') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
