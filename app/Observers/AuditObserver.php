<?php

namespace App\Observers;

use App\Models\AuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * บันทึกทุกการเปลี่ยนแปลงข้อมูลสำคัญ
 *
 * เก็บค่าเดิม -> ค่าใหม่ พร้อมว่าใครแก้ จาก IP ไหน
 * ใช้เป็นหลักฐานเวลาตรวจสอบย้อนหลัง
 */
class AuditObserver
{
    /** ฟิลด์ที่ห้ามบันทึกลง log เด็ดขาด */
    private const SECRET_FIELDS = [
        'password', 'remember_token', 'api_token',
        'secret_hash', 'two_factor_secret',
    ];

    public function created(Model $model): void
    {
        $this->record($model, 'created', null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $old = [];
        foreach (array_keys($changes) as $field) {
            $old[$field] = $model->getOriginal($field);
        }

        $this->record($model, 'updated', $old, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $model->getOriginal(), null);
    }

    private function record(Model $model, string $action, ?array $old, ?array $new): void
    {
        $user = Auth::user();
        $request = request();

        AuditTrail::create([
            'auditable_type' => $model::class,
            'auditable_id'   => $model->getKey(),
            'action'         => $action,
            'old_values'     => $old ? $this->hide($old) : null,
            'new_values'     => $new ? $this->hide($new) : null,
            'changed_fields' => $new ? array_keys($new) : null,
            'user_id'        => $user?->id,
            'user_label'     => $user?->email ?? $user?->name,
            'ip_address'     => $request?->ip(),
            'route'          => $request?->path(),
            'created_at'     => now(),
        ]);
    }

    private function hide(array $values): array
    {
        foreach (self::SECRET_FIELDS as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = '***ซ่อนไว้***';
            }
        }

        return $values;
    }
}
