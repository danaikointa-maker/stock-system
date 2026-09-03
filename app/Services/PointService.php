<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\Reward;
use App\Models\RewardRedemption;
use Illuminate\Support\Facades\DB;

/**
 * บัญชีคะแนน — point_transactions เป็น ledger (append-only)
 * customers.points_balance เป็นยอดสรุปที่อัปเดตในทรานแซกชันเดียวกัน
 */
class PointService
{
    /** เพิ่มคะแนน คืนยอดคงเหลือใหม่ */
    public function earn(
        Customer $customer,
        int $points,
        string $type = 'earn_scan',
        ?string $refType = null,
        ?int $refId = null,
        ?string $note = null,
    ): int {
        if ($points <= 0) {
            return $customer->points_balance;
        }

        return $this->post($customer, $points, $type, $refType, $refId, $note);
    }

    /** หักคะแนน */
    public function deduct(
        Customer $customer,
        int $points,
        string $type = 'redeem',
        ?string $refType = null,
        ?int $refId = null,
        ?string $note = null,
    ): int {
        if ($points <= 0) {
            throw new \InvalidArgumentException('จำนวนคะแนนต้องมากกว่า 0');
        }

        return $this->post($customer, -$points, $type, $refType, $refId, $note);
    }

    /** แลกของรางวัล */
    public function redeemReward(Customer $customer, Reward $reward, ?string $address = null): RewardRedemption
    {
        return DB::transaction(function () use ($customer, $reward, $address) {
            $reward = Reward::whereKey($reward->id)->lockForUpdate()->firstOrFail();

            if ($reward->status !== 'active' || $reward->stock_qty < 1) {
                throw new \RuntimeException('ของรางวัลนี้หมดแล้ว');
            }

            $redemption = RewardRedemption::create([
                'customer_id' => $customer->id,
                'reward_id'   => $reward->id,
                'points_used' => $reward->points_cost,
                'status'      => 'pending',
                'address'     => $address,
            ]);

            $this->deduct(
                $customer, $reward->points_cost, 'redeem',
                RewardRedemption::class, $redemption->id, "แลก {$reward->name}"
            );

            $reward->decrement('stock_qty');

            return $redemption;
        });
    }

    /** ยกเลิกรายการคะแนน (คืนคะแนนกลับ) */
    public function reverse(PointTransaction $original, ?string $note = null): int
    {
        $customer = $original->customer;

        return $this->post(
            $customer, -$original->points, 'reverse',
            $original->ref_type, $original->ref_id,
            $note ?? "ยกเลิกรายการ #{$original->id}"
        );
    }

    /** กระทบยอดจาก ledger — เรียกจาก scheduled job รายวัน */
    public function reconcile(Customer $customer): int
    {
        return DB::transaction(function () use ($customer) {
            $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $actual = (int) $customer->pointTransactions()->sum('points');

            if ($actual !== $customer->points_balance) {
                $customer->update(['points_balance' => $actual]);
            }

            return $actual;
        });
    }

    /** เขียน ledger + อัปเดตยอดสรุป (atomic) */
    private function post(
        Customer $customer, int $delta, string $type,
        ?string $refType, ?int $refId, ?string $note,
    ): int {
        return DB::transaction(function () use ($customer, $delta, $type, $refType, $refId, $note) {
            $locked = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $newBalance = $locked->points_balance + $delta;

            if ($newBalance < 0) {
                throw new \RuntimeException(
                    "คะแนนไม่พอ: มี {$locked->points_balance} ต้องใช้ " . abs($delta)
                );
            }

            PointTransaction::create([
                'customer_id'   => $locked->id,
                'type'          => $type,
                'points'        => $delta,
                'balance_after' => $newBalance,
                'ref_type'      => $refType,
                'ref_id'        => $refId,
                'expires_at'    => $delta > 0 ? now()->addYear()->toDateString() : null,
                'note'          => $note,
            ]);

            $locked->update([
                'points_balance' => $newBalance,
                'tier'           => $this->tierFor($newBalance),
            ]);

            $customer->refresh();

            return $newBalance;
        });
    }

    private function tierFor(int $balance): string
    {
        return match (true) {
            $balance >= 10000 => 'platinum',
            $balance >= 5000  => 'gold',
            $balance >= 1000  => 'silver',
            default           => 'bronze',
        };
    }
}
