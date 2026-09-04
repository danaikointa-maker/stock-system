<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\NotificationQueue;
use App\Models\PointRedemption;
use App\Models\ReimbursementClaim;
use App\Models\SocialLink;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;

/**
 * ระบบแจ้งเตือน LINE / Email
 *
 * หลักการสำคัญ
 *   การแจ้งเตือน "ต้องไม่ทำให้ธุรกรรมหลักพัง"
 *   ทุกเมธอดจึงแค่เข้าคิว ไม่ส่งทันที และครอบ try/catch ไว้เสมอ
 *   ถ้าเข้าคิวไม่ได้ก็แค่บันทึก log ไม่โยน exception ออกไป
 *
 * การส่งจริงทำโดย command roamembers:send-notifications
 * ที่รันเป็นระยะ (ทุก 1-5 นาที)
 */
class NotificationService
{
    /** ── เข้าคิว ───────────────────────────────────────── */

    /**
     * แจ้งลูกค้าว่าได้รับแต้ม
     *
     * ตามที่ระบุไว้: บอกว่าสินค้าอะไร ได้กี่แต้ม มีสะสมเท่าไร หมดอายุเมื่อไร
     * ถ้าลูกค้ามีแค่เบอร์โทร (ยังไม่ผูก LINE) จะยังไม่ส่งอะไร
     * รอจนกว่าจะผูก SMS gateway ภายหลัง
     */
    public function pointsEarned(
        Customer $customer,
        string $productName,
        int $points,
        int $totalBalance,
        ?string $expiresAt = null,
    ): void {
        $this->safely(function () use ($customer, $productName, $points, $totalBalance, $expiresAt) {
            $expireText = $expiresAt
                ? "\nแต้มหมดอายุ {$expiresAt}"
                : '';

            $body = "ได้รับแต้มแล้ว\n\n"
                . "สินค้า: {$productName}\n"
                . "ได้รับ: +" . number_format($points) . " แต้ม\n"
                . "แต้มสะสมทั้งหมด: " . number_format($totalBalance) . " แต้ม"
                . $expireText
                . "\n\nดูแต้มและแลกของรางวัลได้ที่หน้าเว็บ " . config('app.name', 'RaoMembers');

            $this->queueForCustomer(
                $customer,
                'points_earned',
                config('app.name', 'RaoMembers') . ': ได้รับ ' . number_format($points) . ' แต้ม',
                $body,
                ['points' => $points, 'balance' => $totalBalance, 'product' => $productName],
            );
        });
    }

    /** แจ้งลูกค้าว่าใช้แต้มแลกอะไรไป */
    public function redemptionConfirmed(PointRedemption $redemption): void
    {
        $this->safely(function () use ($redemption) {
            $customer = $redemption->customer;

            if (! $customer) {
                return;
            }

            $shopName = $redemption->shop->name ?? 'ร้านค้า';
            $balance = (int) \App\Models\CustomerPointWallet::where('customer_id', $customer->id)
                ->sum('balance');

            $body = "ใช้แต้มเรียบร้อย\n\n"
                . "รายการ: {$redemption->reward_name}\n"
                . "ที่ร้าน: {$shopName}\n"
                . "ใช้ไป: -" . number_format($redemption->points_used) . " แต้ม\n"
                . "แต้มคงเหลือ: " . number_format($balance) . " แต้ม\n"
                . "รหัสอ้างอิง: {$redemption->code}\n\n"
                . "ขอบคุณที่ใช้บริการ";

            $this->queueForCustomer(
                $customer,
                'redemption_confirmed',
                config('app.name', 'RaoMembers') . ': ใช้แต้ม ' . number_format($redemption->points_used) . ' แต้ม',
                $body,
                ['redemption_id' => $redemption->id],
                'PointRedemption',
                $redemption->id,
            );

            // แจ้งร้านด้วยว่ามีลูกค้ามาแลก
            $this->notifyShopUsers(
                $redemption->accepting_node_id,
                'shop_redeem_received',
                config('app.name', 'RaoMembers') . ': มีลูกค้าแลกแต้ม',
                "มีลูกค้าแลกแต้มที่ร้านคุณ\n\n"
                    . "รายการ: {$redemption->reward_name}\n"
                    . "แต้ม: " . number_format($redemption->points_used) . "\n"
                    . "มูลค่าที่เบิกได้: " . number_format($redemption->cash_value, 2) . " บาท\n"
                    . "รหัส: {$redemption->code}",
                ['redemption_id' => $redemption->id],
            );
        });
    }

    /** แจ้งเมื่อใบเบิกเปลี่ยนสถานะ */
    public function claimStatusChanged(ReimbursementClaim $claim, string $event): void
    {
        $this->safely(function () use ($claim, $event) {
            [$subject, $body] = match ($event) {
                'approved' => [
                    config('app.name', 'RaoMembers') . ': ใบเบิกได้รับอนุมัติ',
                    "ใบเบิก {$claim->code} ได้รับอนุมัติแล้ว\n\n"
                        . "งวด: {$claim->period_ym}\n"
                        . "จำนวนเงิน: " . number_format($claim->total_amount, 2) . " บาท\n\n"
                        . "รอรับเงินโอนตามรอบการจ่าย",
                ],
                'paid' => [
                    config('app.name', 'RaoMembers') . ': จ่ายเงินใบเบิกแล้ว',
                    "จ่ายเงินใบเบิก {$claim->code} เรียบร้อยแล้ว\n\n"
                        . "จำนวนเงิน: " . number_format($claim->total_amount, 2) . " บาท\n"
                        . ($claim->payment_ref ? "อ้างอิง: {$claim->payment_ref}\n" : '')
                        . "\nขอบคุณที่ร่วมโครงการ",
                ],
                'rejected' => [
                    config('app.name', 'RaoMembers') . ': ใบเบิกถูกปฏิเสธ',
                    "ใบเบิก {$claim->code} ถูกปฏิเสธ\n\n"
                        . "เหตุผล: {$claim->reject_reason}\n\n"
                        . "รายการถูกปลดแล้ว คุณสามารถยื่นใบเบิกใหม่ได้",
                ],
                default => [config('app.name', 'RaoMembers') . ': อัปเดตใบเบิก', "ใบเบิก {$claim->code} มีการเปลี่ยนแปลง"],
            };

            $this->notifyShopUsers(
                $claim->claimant_node_id,
                'claim_' . $event,
                $subject,
                $body,
                ['claim_id' => $claim->id],
                'ReimbursementClaim',
                $claim->id,
            );
        });
    }

    /** แจ้งร้านว่าวงเงินใกล้หมด */
    public function allowanceLow(int $shopNodeId, int $remaining, int $total): void
    {
        $this->safely(function () use ($shopNodeId, $remaining, $total) {
            $pct = $total > 0 ? round($remaining * 100 / $total) : 0;

            $this->notifyShopUsers(
                $shopNodeId,
                'allowance_low',
                config('app.name', 'RaoMembers') . ': วงเงินรับแลกใกล้หมด',
                "วงเงินรับแลกแต้มเดือนนี้ใกล้หมดแล้ว\n\n"
                    . "คงเหลือ: " . number_format($remaining) . " จาก " . number_format($total) . " แต้ม ({$pct}%)\n\n"
                    . "เมื่อหมดจะรับแลกไม่ได้จนกว่าจะขึ้นเดือนใหม่",
                ['remaining' => $remaining, 'total' => $total],
            );
        });
    }

    /** แจ้งเตือนแอดมินเรื่องความปลอดภัย */
    public function adminAlert(string $title, string $body, array $payload = []): void
    {
        $this->safely(function () use ($title, $body, $payload) {
            $admins = User::where('role', 'system_admin')->where('is_active', true)->get();

            foreach ($admins as $admin) {
                $this->queueForUser($admin, 'admin_alert', $title, $body, $payload);
            }
        });
    }

    /** ── ส่งจริง ───────────────────────────────────────── */

    /**
     * ส่งรายการที่รออยู่ในคิว
     *
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function dispatchPending(int $limit = 50): array
    {
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        $rows = NotificationQueue::query()
            ->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
            ->whereColumn('attempts', '<', 'max_attempts')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            // ล็อกแถวกันตัวส่งสองตัวหยิบรายการเดียวกัน
            $claimed = NotificationQueue::where('id', $row->id)
                ->where('status', 'pending')
                ->update(['status' => 'sending', 'attempts' => $row->attempts + 1]);

            if ($claimed === 0) {
                $skipped++;
                continue;
            }

            $row->refresh();

            try {
                $messageId = match ($row->channel) {
                    'line'  => $this->sendLine($row),
                    'email' => $this->sendEmail($row),
                };

                $row->update([
                    'status'              => 'sent',
                    'sent_at'             => now(),
                    'error_message'       => null,
                    'provider_message_id' => $messageId,
                ]);

                $sent++;
            } catch (\Throwable $e) {
                // ครบจำนวนครั้งแล้วถือว่าล้มเหลวถาวร ไม่งั้นกลับไปรอคิวใหม่
                $isFinal = $row->attempts >= $row->max_attempts;

                $row->update([
                    'status'        => $isFinal ? 'failed' : 'pending',
                    'error_message' => substr($e->getMessage(), 0, 500),
                ]);

                Log::warning('ส่งแจ้งเตือนไม่สำเร็จ', [
                    'id'      => $row->id,
                    'channel' => $row->channel,
                    'attempt' => $row->attempts,
                    'error'   => $e->getMessage(),
                ]);

                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => $skipped];
    }

    /** ส่งข้อความผ่าน LINE Messaging API */
    private function sendLine(NotificationQueue $row): ?string
    {
        $token = config('services.line.channel_access_token');

        if (! $token) {
            throw new \RuntimeException('ยังไม่ได้ตั้งค่า LINE_CHANNEL_ACCESS_TOKEN');
        }

        $response = Http::withToken($token)
            ->timeout(15)
            ->post('https://api.line.me/v2/bot/message/push', [
                'to'       => $row->destination,
                'messages' => [['type' => 'text', 'text' => substr($row->body, 0, 4900)]],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                'LINE API ตอบกลับ ' . $response->status() . ': ' . substr($response->body(), 0, 200),
            );
        }

        return $response->header('x-line-request-id') ?: null;
    }

    /** ส่งอีเมล */
    private function sendEmail(NotificationQueue $row): ?string
    {
        Mail::raw($row->body, function ($m) use ($row) {
            $m->to($row->destination)->subject($row->subject ?: config('app.name', 'RaoMembers'));
        });

        return null;
    }

    /** ── ตัวช่วยภายใน ──────────────────────────────────── */

    /** เข้าคิวให้ลูกค้า (LINE ถ้าผูกไว้) */
    private function queueForCustomer(
        Customer $customer,
        string $template,
        string $subject,
        string $body,
        array $payload = [],
        ?string $refType = null,
        ?int $refId = null,
    ): void {
        $links = SocialLink::where('owner_type', 'customer')
            ->where('owner_id', $customer->id)
            ->where('notify_enabled', true)
            ->get();

        foreach ($links as $link) {
            if ($link->provider === 'line') {
                $this->push('line', 'customer', $customer->id, $link->provider_uid,
                    $template, $subject, $body, $payload, $refType, $refId);
            } elseif ($link->email) {
                $this->push('email', 'customer', $customer->id, $link->email,
                    $template, $subject, $body, $payload, $refType, $refId);
            }
        }

        // ไม่มีช่องทางเลย = มีแค่เบอร์โทร -> ยังไม่ส่งอะไรจนกว่าจะผูก SMS gateway
    }

    /** เข้าคิวให้ผู้ใช้ระบบ — ผูก LINE ได้หลายไอดี ส่งทุกไอดีที่เปิดรับ */
    private function queueForUser(
        User $user,
        string $template,
        string $subject,
        string $body,
        array $payload = [],
        ?string $refType = null,
        ?int $refId = null,
    ): void {
        $links = SocialLink::where('owner_type', 'user')
            ->where('owner_id', $user->id)
            ->where('notify_enabled', true)
            ->get();

        foreach ($links as $link) {
            if ($link->provider === 'line') {
                $this->push('line', 'user', $user->id, $link->provider_uid,
                    $template, $subject, $body, $payload, $refType, $refId);
            }
        }

        // อีเมลของบัญชีผู้ใช้ ส่งเสมอถ้ามี
        if ($user->email) {
            $this->push('email', 'user', $user->id, $user->email,
                $template, $subject, $body, $payload, $refType, $refId);
        }
    }

    /** แจ้งผู้ใช้ทุกคนที่สังกัดร้านนั้น */
    private function notifyShopUsers(
        int $shopNodeId,
        string $template,
        string $subject,
        string $body,
        array $payload = [],
        ?string $refType = null,
        ?int $refId = null,
    ): void {
        $users = User::where('org_node_id', $shopNodeId)->where('is_active', true)->get();

        foreach ($users as $user) {
            $this->queueForUser($user, $template, $subject, $body, $payload, $refType, $refId);
        }
    }

    /** ใส่ลงคิว */
    private function push(
        string $channel,
        string $recipientType,
        ?int $recipientId,
        string $destination,
        string $template,
        string $subject,
        string $body,
        array $payload = [],
        ?string $refType = null,
        ?int $refId = null,
    ): void {
        NotificationQueue::create([
            'channel'        => $channel,
            'recipient_type' => $recipientType,
            'recipient_id'   => $recipientId,
            'destination'    => substr($destination, 0, 191),
            'template'       => $template,
            'subject'        => substr($subject, 0, 200),
            'body'           => $body,
            'payload'        => $payload,
            'status'         => 'pending',
            'ref_type'       => $refType,
            'ref_id'         => $refId,
        ]);
    }

    /**
     * ครอบการทำงานไว้ไม่ให้ระเบิดออกไปข้างนอก
     *
     * การแจ้งเตือนล้มเหลวต้องไม่ทำให้การสแกน/แลกแต้ม/เบิกเงิน พังตามไปด้วย
     */
    private function safely(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::warning('เข้าคิวแจ้งเตือนไม่สำเร็จ', ['error' => $e->getMessage()]);
        }
    }
}
