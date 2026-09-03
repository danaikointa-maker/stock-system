<?php

namespace App\Services;

use App\Models\AdminAlert;
use App\Models\BlockedEntity;
use App\Models\LoginAttempt;
use App\Models\SecurityEvent;
use App\Models\SecurityRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ศูนย์กลางงานความปลอดภัย
 *
 * หน้าที่
 *   1) บันทึกเหตุการณ์ด้านความปลอดภัยทุกอย่าง
 *   2) ตรวจจับพฤติกรรมผิดปกติตามกฎที่ตั้งไว้
 *   3) ระงับ IP/บัญชี อัตโนมัติเมื่อเกินเกณฑ์
 *   4) แจ้งเตือนแอดมินทันทีเมื่อเจอเรื่องร้ายแรง
 */
class SecurityService
{
    /** ชนิดเหตุการณ์ที่ระบบรู้จัก */
    public const E_PERMISSION_DENIED   = 'permission_denied';
    public const E_LOGIN_FAILED        = 'login_failed';
    public const E_LOGIN_SUCCESS       = 'login_success';
    public const E_RATE_LIMIT          = 'rate_limit_hit';
    public const E_QR_REUSE            = 'qr_reuse_attempt';
    public const E_QR_INVALID          = 'qr_invalid_token';
    public const E_OVER_REDEEM         = 'over_redeem_attempt';
    public const E_QUOTA_EXCEEDED      = 'quota_exceeded';
    public const E_SUSPICIOUS_GEO      = 'suspicious_location';
    public const E_DATA_TAMPER         = 'data_tamper_attempt';
    public const E_PRIVILEGE_ESCALATE  = 'privilege_escalation';
    public const E_SETTING_CHANGED     = 'critical_setting_changed';
    public const E_BULK_EXPORT         = 'bulk_data_export';
    public const E_BLOCKED_ACCESS      = 'blocked_entity_access';

    /**
     * บันทึกเหตุการณ์ความปลอดภัย
     * แล้วตรวจว่าเข้าเกณฑ์ที่ต้องเตือน/ระงับหรือไม่
     */
    public function log(
        string $eventType,
        string $message,
        string $severity = 'low',
        array $context = [],
        ?string $targetType = null,
        ?int $targetId = null,
    ): SecurityEvent {
        $request = request();
        [$actorType, $actorId, $actorLabel] = $this->resolveActor();

        $event = SecurityEvent::create([
            'event_type'  => $eventType,
            'severity'    => $severity,
            'actor_type'  => $actorType,
            'actor_id'    => $actorId,
            'actor_label' => $actorLabel,
            'route'       => $request?->path(),
            'method'      => $request?->method(),
            'ip_address'  => $request?->ip(),
            'user_agent'  => substr((string) $request?->userAgent(), 0, 255),
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'message'     => $message,
            'context'     => $this->scrub($context),
            'created_at'  => now(),
        ]);

        // เรื่องร้ายแรงแจ้งแอดมินทันที ไม่ต้องรอสะสม
        if (in_array($severity, ['high', 'critical'], true)) {
            $this->raiseAlert(
                $eventType,
                $severity === 'critical' ? 'critical' : 'danger',
                "พบเหตุการณ์ผิดปกติ: {$message}",
                $context + ['event_id' => $event->id],
            );
        }

        $this->evaluateRules($eventType, $request?->ip());

        return $event;
    }

    /** บันทึกการพยายามเข้าสู่ระบบ */
    public function logLogin(
        string $loginInput,
        bool $succeeded,
        ?int $userId = null,
        ?string $failureReason = null,
        string $guard = 'web',
    ): void {
        $request = request();
        $ip = $request?->ip();

        LoginAttempt::create([
            'login_input'    => substr($loginInput, 0, 191),
            'guard'          => $guard,
            'succeeded'      => $succeeded,
            'failure_reason' => $failureReason,
            'user_id'        => $userId,
            'ip_address'     => $ip,
            'user_agent'     => substr((string) $request?->userAgent(), 0, 255),
            'created_at'     => now(),
        ]);

        if (! $succeeded) {
            $this->log(
                self::E_LOGIN_FAILED,
                "เข้าสู่ระบบไม่สำเร็จ: {$loginInput} ({$failureReason})",
                'low',
                ['login' => $loginInput, 'reason' => $failureReason],
            );
        }
    }

    /**
     * ตรวจว่า IP หรือบัญชีนี้ถูกระงับอยู่ไหม
     * เรียกจาก middleware ทุก request
     */
    public function isBlocked(string $entityType, string $value): bool
    {
        $blocked = BlockedEntity::query()
            ->where('entity_type', $entityType)
            ->where('entity_value', $value)
            ->where('is_active', true)
            ->first();

        if (! $blocked) {
            return false;
        }

        // หมดเวลาระงับแล้ว ปลดล็อกอัตโนมัติ
        if ($blocked->block_type === 'temporary'
            && $blocked->blocked_until
            && $blocked->blocked_until->isPast()) {
            $blocked->update(['is_active' => false]);

            return false;
        }

        $blocked->increment('hit_count');

        return true;
    }

    /** ระงับ IP หรือบัญชี */
    public function block(
        string $entityType,
        string $value,
        string $reason,
        ?int $minutes = 30,
        bool $permanent = false,
    ): BlockedEntity {
        $entity = BlockedEntity::updateOrCreate(
            ['entity_type' => $entityType, 'entity_value' => $value],
            [
                'reason'        => $reason,
                'block_type'    => $permanent ? 'permanent' : 'temporary',
                'blocked_until' => $permanent ? null : now()->addMinutes($minutes),
                'blocked_by'    => Auth::id(),
                'is_active'     => true,
            ],
        );

        $this->raiseAlert(
            'entity_blocked',
            $permanent ? 'critical' : 'danger',
            "ระงับการใช้งาน {$entityType}: {$value}",
            ['reason' => $reason, 'permanent' => $permanent],
        );

        return $entity;
    }

    /**
     * สร้างการแจ้งเตือนให้แอดมิน
     *
     * เรื่องร้ายแรงจะถูกส่งเข้า LINE/อีเมลของแอดมินด้วย
     * ผ่านคิวแจ้งเตือน (ไม่ส่งทันที เพื่อไม่ให้ช้า)
     */
    public function raiseAlert(
        string $type,
        string $severity,
        string $title,
        array $data = [],
        ?string $link = null,
    ): AdminAlert {
        if (in_array($severity, ['danger', 'critical'], true)) {
            try {
                app(NotificationService::class)->adminAlert(
                    $title,
                    $title . "\n\n" . json_encode($this->scrub($data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    $data,
                );
            } catch (\Throwable) {
                // แจ้งเตือนล้มเหลวต้องไม่ทำให้บันทึก alert ไม่ได้
            }
        }

        return AdminAlert::create([
            'alert_type' => $type,
            'severity'   => $severity,
            'title'      => substr($title, 0, 200),
            'body'       => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
            'data'       => $this->scrub($data),
            'link'       => $link,
            'status'     => 'new',
        ]);
    }

    /**
     * ตรวจกฎ: เหตุการณ์ชนิดนี้เกิดถี่เกินเกณฑ์หรือยัง
     * ถ้าเกิน -> เตือน หรือระงับตามที่กฎกำหนด
     */
    private function evaluateRules(string $eventType, ?string $ip): void
    {
        if (! $ip) {
            return;
        }

        $rule = SecurityRule::query()
            ->where('code', $eventType)
            ->where('is_enabled', true)
            ->first();

        if (! $rule) {
            return;
        }

        $count = SecurityEvent::query()
            ->where('event_type', $eventType)
            ->where('ip_address', $ip)
            ->where('created_at', '>=', now()->subMinutes($rule->window_minutes))
            ->count();

        if ($count < $rule->threshold) {
            return;
        }

        match ($rule->action) {
            'block_temp' => $this->block('ip', $ip, "อัตโนมัติ: {$rule->name} ({$count} ครั้ง)", $rule->block_minutes),
            'block_perm' => $this->block('ip', $ip, "อัตโนมัติ: {$rule->name}", null, true),
            'alert'      => $this->raiseAlert(
                $eventType,
                'danger',
                "{$rule->name}: พบ {$count} ครั้งจาก IP {$ip}",
                ['ip' => $ip, 'count' => $count, 'window' => $rule->window_minutes],
            ),
            default      => null,
        };
    }

    /**
     * ระบุว่าใครเป็นคนทำ
     *
     * ต้องเช็คว่า guard 'customer' ถูกประกาศไว้จริงก่อนเรียก
     * ไม่งั้น Laravel จะโยน InvalidArgumentException
     * แล้วทำให้การบันทึก log ล้มไปด้วย ซึ่งอันตรายกว่าเดิม
     */
    private function resolveActor(): array
    {
        try {
            if ($user = Auth::user()) {
                return ['user', $user->id, $user->email ?? $user->name];
            }

            if (config('auth.guards.customer')) {
                if ($customer = Auth::guard('customer')->user()) {
                    return ['customer', $customer->id, $customer->phone];
                }
            }
        } catch (\Throwable) {
            // การระบุตัวตนล้มเหลวต้องไม่ทำให้บันทึก log ไม่ได้
        }

        return ['guest', null, null];
    }

    /**
     * ตัดข้อมูลอ่อนไหวออกก่อนบันทึก log
     * ห้ามเก็บรหัสผ่าน โทเคน หรือเลขบัตรลงฐานข้อมูลเด็ดขาด
     */
    private function scrub(array $data): array
    {
        $secretKeys = [
            'password', 'password_confirmation', 'current_password',
            'token', '_token', 'api_token', 'access_token', 'refresh_token',
            'secret', 'client_secret', 'authorization', 'cookie',
            'credit_card', 'cvv', 'card_number', 'id_card',
        ];

        foreach ($data as $key => $value) {
            $lower = strtolower((string) $key);

            foreach ($secretKeys as $secret) {
                if (str_contains($lower, $secret)) {
                    $data[$key] = '***ซ่อนไว้***';
                    continue 2;
                }
            }

            if (is_array($value)) {
                $data[$key] = $this->scrub($value);
            }
        }

        return $data;
    }
}
