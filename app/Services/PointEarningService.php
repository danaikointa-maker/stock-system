<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPointWallet;
use App\Models\OrgNode;
use App\Models\PointLot;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

/**
 * ออกแต้มให้ลูกค้า (ระบบ wallet v3)
 *
 * แต้มถูกเก็บแยกตาม "ร้านผู้ออกแต้ม" (issuer_node_id)
 * และแบ่งเป็นล็อตเพื่อให้หมดอายุแบบ FIFO ทีละก้อน
 *
 * หมายเหตุสำคัญเรื่องวงเงิน:
 *   วงเงินรายเดือนของร้าน (shop_monthly_allowances) ใช้กับ "การรับแลก" เท่านั้น
 *   การออกแต้มตอนลูกค้าสแกน QR ไม่ตัดวงเงิน เพราะถูกจำกัดโดยธรรมชาติอยู่แล้ว
 *   จากจำนวน QR ที่พิมพ์ออกมา ซึ่งเจ้าของระบบเป็นผู้ควบคุม
 */
class PointEarningService
{
    /**
     * เพิ่มแต้มเข้ากระเป๋าของลูกค้า
     *
     * @param  OrgNode  $issuer  ร้านที่เป็นเจ้าของแต้มก้อนนี้
     * @return array{wallet: CustomerPointWallet, lot: PointLot, balance: int}
     */
    public function earn(
        Customer $customer,
        OrgNode $issuer,
        int $points,
        string $sourceType = 'scan',
        ?int $sourceId = null,
    ): array {
        if ($points <= 0) {
            throw new \InvalidArgumentException('จำนวนแต้มต้องมากกว่า 0');
        }

        return DB::transaction(function () use ($customer, $issuer, $points, $sourceType, $sourceId) {
            $wallet = CustomerPointWallet::firstOrCreate(
                ['customer_id' => $customer->id, 'issuer_node_id' => $issuer->id],
                ['balance' => 0, 'lifetime_earned' => 0, 'lifetime_used' => 0],
            );

            // ล็อกแถวกันสองคำขอเข้าพร้อมกัน
            $wallet = CustomerPointWallet::where('id', $wallet->id)->lockForUpdate()->first();

            $months = (int) SystemSetting::get('point_expire_months', 12);

            $lot = PointLot::create([
                'wallet_id'   => $wallet->id,
                'points_in'   => $points,
                'points_left' => $points,
                'earned_at'   => now(),
                'expires_at'  => now()->addMonths($months),
                'source_type' => $sourceType,
                'source_id'   => $sourceId,
            ]);

            $wallet->increment('balance', $points);
            $wallet->increment('lifetime_earned', $points);
            $wallet->update(['last_activity_at' => now()]);

            return [
                'wallet'  => $wallet->fresh(),
                'lot'     => $lot,
                'balance' => (int) $wallet->fresh()->balance,
            ];
        });
    }

    /** ยอดแต้มรวมทุกกระเป๋าของลูกค้า */
    public function totalBalance(Customer $customer): int
    {
        return (int) CustomerPointWallet::where('customer_id', $customer->id)->sum('balance');
    }

    /** กระเป๋าทั้งหมดพร้อมชื่อร้านผู้ออก */
    public function wallets(Customer $customer)
    {
        return CustomerPointWallet::with('issuer')
            ->where('customer_id', $customer->id)
            ->where('balance', '>', 0)
            ->orderByDesc('balance')
            ->get();
    }

    /**
     * แต้มที่กำลังจะหมดอายุใน N วัน
     * ใช้แจ้งเตือนลูกค้าให้รีบใช้
     */
    public function expiringSoon(Customer $customer, int $days = 30)
    {
        return PointLot::query()
            ->whereHas('wallet', fn ($q) => $q->where('customer_id', $customer->id))
            ->where('is_expired', false)
            ->where('points_left', '>', 0)
            ->whereBetween('expires_at', [now(), now()->addDays($days)])
            ->orderBy('expires_at')
            ->get();
    }

    /**
     * ตัดแต้มที่หมดอายุแล้ว (เรียกจาก scheduled command รายวัน)
     *
     * @return int จำนวนแต้มที่ถูกตัดทิ้ง
     */
    public function expireOverdue(): int
    {
        $totalExpired = 0;

        PointLot::query()
            ->where('is_expired', false)
            ->where('points_left', '>', 0)
            ->where('expires_at', '<', now())
            ->chunkById(500, function ($lots) use (&$totalExpired) {
                foreach ($lots as $lot) {
                    DB::transaction(function () use ($lot, &$totalExpired) {
                        $lost = (int) $lot->points_left;

                        $lot->update(['points_left' => 0, 'is_expired' => true]);

                        $wallet = CustomerPointWallet::where('id', $lot->wallet_id)
                            ->lockForUpdate()
                            ->first();

                        if ($wallet) {
                            // กันยอดติดลบถ้าข้อมูลไม่สอดคล้อง
                            $wallet->update([
                                'balance' => max(0, (int) $wallet->balance - $lost),
                            ]);
                        }

                        $totalExpired += $lost;
                    });
                }
            });

        return $totalExpired;
    }
}
