<?php

namespace App\Services;

use App\Enums\QrStatus;
use App\Models\Customer;
use App\Models\ProductLot;
use App\Models\ProductQrcode;
use App\Models\OrgNode;
use App\Models\QrScanLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * สแกน QR รับคะแนน
 *
 * มาตรการกันโกง
 *  1. qr_token สุ่ม 32 ตัว — เดา URL ไม่ได้
 *  2. secret_hash = รหัสใต้ฟิล์มขูด — กันคนถ่ายรูป QR บนชั้นวางไปสแกนชิงคะแนน
 *  3. conditional UPDATE — 1 QR ใช้ได้ครั้งเดียวจริง แม้ยิงพร้อมกันหลาย request
 *  4. rate limit ต่อเบอร์ / ต่อ IP
 *  5. log ทุกครั้งไม่ว่าสำเร็จหรือไม่ + แจ้งเตือนพฤติกรรมผิดปกติ
 */
class QrScanService
{
    public const MAX_SCANS_PER_CUSTOMER_PER_DAY = 20;
    public const MAX_SCANS_PER_IP_PER_HOUR = 60;

    public function __construct(
        private PointService $points,
        private PointEarningService $earning,
        private NotificationService $notify,
    ) {}

    /** สร้าง QR ล่วงหน้าเป็น batch ตอนสร้างล็อต — คืน array ของ ['serial','token','secret'] */
    public function generateForLot(ProductLot $lot, int $qty, ?int $nodeId = null, int $chunk = 1000): array
    {
        $product = $lot->product;
        $plainSecrets = [];
        $created = 0;

        // running number ต่อจากของเดิม
        $startNo = (int) (ProductQrcode::max('id') ?? 0);

        while ($created < $qty) {
            $take = min($chunk, $qty - $created);
            $rows = [];

            for ($i = 0; $i < $take; $i++) {
                $serial = sprintf('%s-%s-%06d', $product->sku, $lot->lot_no, $startNo + $created + $i + 1);
                $token  = Str::lower(Str::random(32));
                $secret = strtoupper(Str::random(8)); // รหัสใต้ฟิล์มขูด พิมพ์ลงบรรจุภัณฑ์

                $plainSecrets[] = ['serial_no' => $serial, 'qr_token' => $token, 'secret' => $secret];

                $rows[] = [
                    'product_id'      => $product->id,
                    'lot_id'          => $lot->id,
                    'serial_no'       => $serial,
                    'qr_token'        => $token,
                    'secret_hash'     => hash('sha256', $secret),
                    'points'          => $product->points_per_unit,
                    'current_node_id' => $nodeId,
                    'status'          => QrStatus::Created->value,
                    'expires_at'      => $lot->expiry_date?->copy()->addYear(),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
            }

            ProductQrcode::insert($rows);
            $created += $take;
        }

        // คืนรหัสดิบให้เอาไปสั่งพิมพ์ครั้งเดียว — ห้ามเก็บลง DB
        return $plainSecrets;
    }

    /**
     * ประมวลผลการสแกน
     *
     * @return array{ok:bool, result:string, message:string, points:int, balance:int|null}
     */
    public function scan(
        string $token,
        Customer $customer,
        ?string $secret = null,
        array $context = [],
    ): array {
        $qr = ProductQrcode::where('qr_token', $token)->first();

        // --- ลูกค้าถูกระงับ ---
        if ($customer->isBlocked()) {
            return $this->fail($qr, $customer, $token, 'blocked', 'บัญชีของคุณถูกระงับการรับคะแนน', $context);
        }

        // --- rate limit ---
        if ($this->isRateLimited($customer, $context['ip'] ?? null)) {
            return $this->fail($qr, $customer, $token, 'rate_limited',
                'คุณสแกนบ่อยเกินกำหนด กรุณาลองใหม่ภายหลัง', $context);
        }

        // --- token ไม่มีจริง ---
        if (! $qr) {
            return $this->fail(null, $customer, $token, 'invalid', 'QR Code นี้ไม่ถูกต้อง', $context);
        }

        // --- หมดอายุ ---
        if ($qr->expires_at && $qr->expires_at->isPast()) {
            return $this->fail($qr, $customer, $token, 'expired', 'QR Code นี้หมดอายุแล้ว', $context);
        }

        // --- ถูกใช้ไปแล้ว / ถูกยกเลิก ---
        if (in_array($qr->status, [QrStatus::Redeemed, QrStatus::Void], true)) {
            return $this->fail($qr, $customer, $token, 'already_used',
                'QR Code นี้ถูกใช้รับคะแนนไปแล้ว', $context);
        }

        // --- ตรวจรหัสใต้ฟิล์มขูด ---
        if (! $qr->verifySecret($secret)) {
            return $this->fail($qr, $customer, $token, 'invalid', 'รหัสใต้ฟิล์มไม่ถูกต้อง', $context);
        }

        return DB::transaction(function () use ($qr, $customer, $token, $context) {
            // conditional update — ถ้า affected = 0 แปลว่ามีคนชิงไปแล้วเสี้ยววินาทีก่อน
            $affected = ProductQrcode::where('id', $qr->id)
                ->whereIn('status', [QrStatus::Sold->value, QrStatus::InStock->value])
                ->update([
                    'status'                  => QrStatus::Redeemed->value,
                    'redeemed_at'             => now(),
                    'redeemed_by_customer_id' => $customer->id,
                    'updated_at'              => now(),
                ]);

            if ($affected === 0) {
                return $this->fail($qr, $customer, $token, 'already_used',
                    'QR Code นี้ถูกใช้รับคะแนนไปแล้ว', $context);
            }

            // QR ที่ยัง in_stock (ยังไม่ถูกขายออก) = ของอาจหลุดจากคลัง -> เตือนแอดมิน
            if ($qr->status === QrStatus::InStock) {
                Log::warning('สแกน QR ที่ยังไม่ถูกขายออกจากร้าน', [
                    'qrcode_id' => $qr->id, 'lot_id' => $qr->lot_id,
                    'node_id' => $qr->current_node_id, 'customer_id' => $customer->id,
                ]);
            }

            // เก็บแต้มเข้าระบบเดิมไว้เพื่อความเข้ากันได้ย้อนหลัง
            $balance = $this->points->earn(
                customer: $customer,
                points: $qr->points,
                type: 'earn_scan',
                refType: ProductQrcode::class,
                refId: $qr->id,
                note: "สแกน {$qr->serial_no}",
            );

            // เก็บเข้ากระเป๋าแยกตามร้านผู้ออกแต้ม (ระบบ wallet v3)
            // issuer = ร้านที่ QR ถูกเปิดใช้ ถ้าไม่ระบุใช้ร้านที่ของอยู่ตอนนี้
            $issuerId = $qr->issuer_node_id ?? $qr->activated_node_id ?? $qr->current_node_id;

            if ($issuerId && $issuer = OrgNode::find($issuerId)) {
                $walletResult = $this->earning->earn(
                    customer: $customer,
                    issuer: $issuer,
                    points: (int) $qr->points,
                    sourceType: 'scan',
                    sourceId: $qr->id,
                );
                $balance = $walletResult['balance'];
            }

            $scanLogId = $this->log($qr, $customer, $token, 'success', $context, $qr->points);

            // แจ้งลูกค้าว่าได้แต้ม (เข้าคิว ไม่ส่งทันที)
            $this->notify->pointsEarned(
                customer: $customer,
                productName: $qr->product->name ?? 'สินค้า',
                points: (int) $qr->points,
                totalBalance: (int) $balance,
                expiresAt: now()->addMonths(
                    (int) \App\Models\SystemSetting::get('point_expire_months', 12)
                )->format('j M Y'),
            );

            return [
                'ok'           => true,
                'scan_log_id'  => $scanLogId,
                'result'  => 'success',
                'message' => "รับ {$qr->points} คะแนนเรียบร้อย",
                'points'  => $qr->points,
                'balance' => $balance,
                'product' => $qr->product->name,
            ];
        });
    }

    private function isRateLimited(Customer $customer, ?string $ip): bool
    {
        $custKey = 'qr-scan:cust:' . $customer->id;
        $ipKey   = 'qr-scan:ip:' . ($ip ?? 'unknown');

        if (RateLimiter::tooManyAttempts($custKey, self::MAX_SCANS_PER_CUSTOMER_PER_DAY)) {
            return true;
        }

        if ($ip && RateLimiter::tooManyAttempts($ipKey, self::MAX_SCANS_PER_IP_PER_HOUR)) {
            return true;
        }

        RateLimiter::hit($custKey, 86400);
        if ($ip) {
            RateLimiter::hit($ipKey, 3600);
        }

        return false;
    }

    private function fail(
        ?ProductQrcode $qr, Customer $customer, string $token,
        string $result, string $message, array $context,
    ): array {
        $scanLogId = $this->log($qr, $customer, $token, $result, $context);

        return [
            'ok'          => false,
            'scan_log_id' => $scanLogId,
            'result'  => $result,
            'message' => $message,
            'points'  => 0,
            'balance' => $customer->points_balance,
        ];
    }

    /** บันทึกทุกครั้งที่สแกน แล้วคืน id ไว้ผูกกับตำแหน่ง GPS */
    private function log(
        ?ProductQrcode $qr, ?Customer $customer, string $token,
        string $result, array $context, int $points = 0,
    ): int {
        $log = QrScanLog::create([
            'qrcode_id'      => $qr?->id,
            'raw_token'      => $token,
            'customer_id'    => $customer?->id,
            'org_node_id'    => $context['node_id'] ?? $qr?->current_node_id,
            'result'         => $result,
            'points_awarded' => $points,
            'ip_address'     => $context['ip'] ?? null,
            'user_agent'     => substr((string) ($context['user_agent'] ?? ''), 0, 255) ?: null,
            'lat'            => $context['lat'] ?? null,
            'lng'            => $context['lng'] ?? null,
            'scanned_at'     => now(),
        ]);

        return (int) $log->id;
    }
}
