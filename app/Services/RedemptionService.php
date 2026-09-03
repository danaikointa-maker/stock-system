<?php

namespace App\Services;

use App\Exceptions\RedemptionException;
use App\Models\Customer;
use App\Models\CustomerPointWallet;
use App\Models\OrgNode;
use App\Models\PointLot;
use App\Models\PointRedemption;
use App\Models\RedemptionAttempt;
use App\Models\ShopMonthlyAllowance;
use App\Models\StockBalance;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

/**
 * บริการแลกแต้ม — จุดเดียวที่ได้รับอนุญาตให้หักแต้ม
 *
 * ด่านตรวจก่อนหักแต้ม (ต้องผ่านทุกข้อ)
 *   1) ลูกค้าต้องไม่ถูกระงับ
 *   2) แต้มของลูกค้าในกระเป๋าร้านผู้ออกต้องพอ
 *   3) ร้านที่รับแลกต้องมีสมาชิกที่ยังไม่หมดอายุ
 *   4) วงเงินรายเดือนของร้านต้องพอ
 *   5) ถ้าแลกสินค้า -> สต๊อกต้องพอ + ล็อตต้องไม่หมดอายุ
 *
 * ทุกอย่างอยู่ในทรานแซกชันเดียว ถ้าพลาดข้อใดข้อหนึ่ง rollback ทั้งหมด
 * และยังมี trigger ที่ระดับฐานข้อมูลเป็นด่านสุดท้ายอีกชั้น
 */
class RedemptionService
{
    public function __construct(
        private SecurityService $security,
        private NotificationService $notify,
    ) {
    }

    /**
     * ตรวจว่าแลกได้ไหม โดยยังไม่หักอะไร (ใช้แสดงผลหน้าจอ)
     *
     * @return array{ok: bool, reason: string|null, message: string}
     */
    public function check(
        Customer $customer,
        OrgNode $issuerNode,
        OrgNode $acceptingNode,
        int $points,
    ): array {
        if ($customer->status !== 'active') {
            return $this->fail('blocked', 'บัญชีของคุณถูกระงับการใช้งาน');
        }

        if ($points <= 0) {
            return $this->fail('blocked', 'จำนวนแต้มต้องมากกว่า 0');
        }

        $wallet = CustomerPointWallet::where('customer_id', $customer->id)
            ->where('issuer_node_id', $issuerNode->id)
            ->first();

        if (! $wallet || $wallet->balance < $points) {
            $have = $wallet->balance ?? 0;

            return $this->fail(
                'insufficient_customer_points',
                "แต้มไม่พอ มีอยู่ {$have} แต้ม ต้องใช้ {$points} แต้ม",
            );
        }

        $allowance = $this->currentAllowance($acceptingNode);

        if (! $allowance) {
            return $this->fail('subscription_inactive', 'ร้านนี้ยังไม่พร้อมรับแลกแต้ม');
        }

        if ($allowance->remaining_points < $points) {
            return $this->fail(
                'insufficient_shop_allowance',
                "วงเงินของร้านเดือนนี้ไม่พอ เหลือ {$allowance->remaining_points} แต้ม",
            );
        }

        return ['ok' => true, 'reason' => null, 'message' => 'แลกได้'];
    }

    /**
     * ทำรายการแลกแต้มจริง
     *
     * @param  array<int, array{product_id:int, lot_id:int|null, qty:int}>  $items
     *         รายการสินค้า (เฉพาะ redeem_type = goods)
     *
     * @throws RedemptionException
     */
    public function redeem(
        Customer $customer,
        OrgNode $issuerNode,
        OrgNode $acceptingNode,
        int $points,
        string $rewardName,
        string $redeemType = 'goods',
        array $items = [],
        ?int $confirmedBy = null,
    ): PointRedemption {
        // ตรวจก่อนเข้าทรานแซกชัน เพื่อบันทึกเหตุผลที่ถูกปฏิเสธ
        $check = $this->check($customer, $issuerNode, $acceptingNode, $points);

        if (! $check['ok']) {
            $this->recordAttempt($customer, $acceptingNode, $points, $rewardName, $check['reason'], $check['message']);

            // แลกเกินตัวถือเป็นเรื่องที่ต้องจับตา
            if ($check['reason'] === 'insufficient_customer_points') {
                $this->security->log(
                    SecurityService::E_OVER_REDEEM,
                    "พยายามแลกเกินแต้มที่มี: {$check['message']}",
                    'medium',
                    ['customer_id' => $customer->id, 'points' => $points],
                );
            }

            throw new RedemptionException($check['message'], $check['reason']);
        }

        if ($redeemType === 'goods' && $items === []) {
            throw new RedemptionException('การแลกสินค้าต้องระบุรายการสินค้า', 'blocked');
        }

        if ($redeemType !== 'goods' && $items !== []) {
            throw new RedemptionException('การแลกประเภทนี้ไม่ต้องระบุสินค้า', 'blocked');
        }

        return DB::transaction(function () use (
            $customer, $issuerNode, $acceptingNode, $points,
            $rewardName, $redeemType, $items, $confirmedBy
        ) {
            // ล็อกแถวกัน race condition (สองคนแลกพร้อมกัน)
            $wallet = CustomerPointWallet::where('customer_id', $customer->id)
                ->where('issuer_node_id', $issuerNode->id)
                ->lockForUpdate()
                ->firstOrFail();

            $allowance = ShopMonthlyAllowance::where('shop_node_id', $acceptingNode->id)
                ->where('period_ym', $this->currentPeriod())
                ->lockForUpdate()
                ->firstOrFail();

            // ตรวจซ้ำหลังล็อก เพราะยอดอาจเปลี่ยนระหว่างรอ
            if ($wallet->balance < $points) {
                throw new RedemptionException(
                    "แต้มไม่พอ มีอยู่ {$wallet->balance} แต้ม",
                    'insufficient_customer_points',
                );
            }

            if ($allowance->remaining_points < $points) {
                throw new RedemptionException(
                    "วงเงินของร้านเดือนนี้ไม่พอ เหลือ {$allowance->remaining_points} แต้ม",
                    'insufficient_shop_allowance',
                );
            }

            $pointValue = (float) SystemSetting::get('point_value_baht', 0.25);

            $redemption = PointRedemption::create([
                'code'              => $this->generateCode(),
                'customer_id'       => $customer->id,
                'issuer_node_id'    => $issuerNode->id,
                'accepting_node_id' => $acceptingNode->id,
                'allowance_id'      => $allowance->id,
                'redeem_type'       => $redeemType,
                'reward_name'       => $rewardName,
                'points_used'       => $points,
                'point_value'       => $pointValue,
                'cash_value'        => round($points * $pointValue, 2),
                'status'            => 'confirmed',
                'redeemed_at'       => now(),
                'confirmed_by'      => $confirmedBy,
            ]);

            if ($redeemType === 'goods') {
                $this->deductStock($redemption, $acceptingNode, $items);
            }

            $this->deductPointsFifo($wallet, $points);
            $this->deductAllowance($allowance, $points);

            $this->recordAttempt($customer, $acceptingNode, $points, $rewardName, 'ok', 'สำเร็จ');

            $fresh = $redemption->fresh();

            // แจ้งเตือนลูกค้าและร้าน (เข้าคิว ไม่ส่งทันที)
            // ถ้าแจ้งเตือนล้มเหลวต้องไม่ทำให้การแลกแต้มพัง
            $this->notify->redemptionConfirmed($fresh);

            return $fresh;
        });
    }

    /**
     * หักแต้มแบบ FIFO — ใช้ล็อตที่ใกล้หมดอายุก่อน
     * เพื่อไม่ให้ลูกค้าเสียแต้มไปเปล่า ๆ
     */
    private function deductPointsFifo(CustomerPointWallet $wallet, int $points): void
    {
        $remaining = $points;

        $lots = PointLot::where('wallet_id', $wallet->id)
            ->where('is_expired', false)
            ->where('points_left', '>', 0)
            ->orderBy('expires_at')       // ใกล้หมดอายุก่อน
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($lot->points_left, $remaining);
            $lot->decrement('points_left', $take);
            $remaining -= $take;
        }

        if ($remaining > 0) {
            // ยอดในกระเป๋ากับผลรวมล็อตไม่ตรงกัน = ข้อมูลผิดปกติ
            $this->security->log(
                SecurityService::E_DATA_TAMPER,
                "ยอดแต้มในกระเป๋าไม่ตรงกับผลรวมล็อต (wallet #{$wallet->id})",
                'critical',
                ['wallet_id' => $wallet->id, 'shortfall' => $remaining],
            );

            throw new RedemptionException('ข้อมูลแต้มไม่สอดคล้อง กรุณาติดต่อผู้ดูแลระบบ', 'blocked');
        }

        $wallet->decrement('balance', $points);
        $wallet->increment('lifetime_used', $points);
        $wallet->update(['last_activity_at' => now()]);
    }

    /** ตัดวงเงินรายเดือนของร้าน */
    private function deductAllowance(ShopMonthlyAllowance $allowance, int $points): void
    {
        $allowance->increment('used_points', $points);
        $allowance->increment('redemption_count');
        $allowance->refresh();

        $total = $allowance->limit_points + $allowance->rollover_points + $allowance->topup_points;
        $allowance->update(['remaining_points' => $total - $allowance->used_points]);

        // เตือนเมื่อวงเงินใกล้หมด
        $threshold = (int) SystemSetting::get('low_balance_percent', 20);
        $percentLeft = $total > 0 ? ($allowance->remaining_points * 100 / $total) : 0;

        if ($percentLeft <= $threshold && ! $allowance->low_alerted_at) {
            $allowance->update(['low_alerted_at' => now()]);

            $this->security->raiseAlert(
                'shop_allowance_low',
                'warning',
                "วงเงินร้านใกล้หมด เหลือ {$allowance->remaining_points} แต้ม",
                ['shop_node_id' => $allowance->shop_node_id, 'percent_left' => round($percentLeft, 1)],
            );
        }
    }

    /**
     * ตัดสต๊อกสินค้าตามล็อตที่ระบุ
     * ล็อตหมดอายุถูกกันด้วย trigger อีกชั้น
     */
    private function deductStock(PointRedemption $redemption, OrgNode $shop, array $items): void
    {
        foreach ($items as $item) {
            $balance = StockBalance::where('org_node_id', $shop->id)
                ->where('product_id', $item['product_id'])
                ->when($item['lot_id'] ?? null, fn ($q, $lot) => $q->where('lot_id', $lot))
                ->lockForUpdate()
                ->first();

            $available = $balance ? ($balance->qty_on_hand - $balance->qty_reserved) : 0;

            if ($available < $item['qty']) {
                throw new RedemptionException(
                    "สินค้าไม่พอ คงเหลือ {$available} ต้องการ {$item['qty']}",
                    'out_of_stock',
                );
            }

            $product = DB::table('products')->find($item['product_id']);
            $lot = ($item['lot_id'] ?? null)
                ? DB::table('product_lots')->find($item['lot_id'])
                : null;

            if ($lot && $lot->expiry_date && $lot->expiry_date < now()->toDateString()) {
                throw new RedemptionException('ล็อตสินค้านี้หมดอายุแล้ว', 'lot_expired');
            }

            DB::table('redemption_items')->insert([
                'redemption_id'   => $redemption->id,
                'product_id'      => $item['product_id'],
                'lot_id'          => $item['lot_id'] ?? null,
                'from_node_id'    => $shop->id,
                'qty'             => $item['qty'],
                'sku_snapshot'    => $product->sku ?? '',
                'name_snapshot'   => $product->name ?? '',
                'lot_no_snapshot' => $lot->lot_no ?? null,
                'expiry_snapshot' => $lot->expiry_date ?? null,
                'points_total'    => 0,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $balance->decrement('qty_on_hand', $item['qty']);
        }
    }

    /** วงเงินเดือนปัจจุบันของร้าน (ถ้าสมาชิกยังใช้งานได้) */
    private function currentAllowance(OrgNode $shop): ?ShopMonthlyAllowance
    {
        return ShopMonthlyAllowance::query()
            ->where('shop_node_id', $shop->id)
            ->where('period_ym', $this->currentPeriod())
            ->whereHas('subscription', function ($q) {
                $q->where('status', 'active')->whereDate('ends_on', '>=', now());
            })
            ->first();
    }

    private function currentPeriod(): string
    {
        return now()->format('Y-m');
    }

    private function generateCode(): string
    {
        return 'RDM-' . now()->format('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    private function fail(string $reason, string $message): array
    {
        return ['ok' => false, 'reason' => $reason, 'message' => $message];
    }

    /** บันทึกทุกความพยายาม ทั้งสำเร็จและถูกปฏิเสธ */
    private function recordAttempt(
        Customer $customer,
        OrgNode $shop,
        int $points,
        string $rewardName,
        string $result,
        string $detail,
    ): void {
        RedemptionAttempt::create([
            'customer_id'      => $customer->id,
            'shop_node_id'     => $shop->id,
            'points_requested' => $points,
            'reward_name'      => substr($rewardName, 0, 200),
            'result'           => $result,
            'detail'           => substr($detail, 0, 255),
            'created_at'       => now(),
        ]);
    }
}
