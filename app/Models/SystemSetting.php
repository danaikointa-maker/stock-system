<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * ค่าคงที่ของระบบ — แก้ได้เฉพาะเจ้าของระบบ/แอดมิน
 * อ่านผ่าน cache เพื่อลดภาระฐานข้อมูล
 */
class SystemSetting extends Model
{
    protected $fillable = ['skey', 'svalue', 'value_type', 'description', 'owner_only', 'updated_by'];
    protected $casts = ['owner_only' => 'boolean'];

    /**
     * อ่านค่าตั้งค่าจากระบบ
     *
     * สำคัญ: cache เก็บเฉพาะ "ค่าดิบ" ไม่เก็บ Eloquent model ทั้งก้อน
     * เพราะถ้าเก็บ model แล้ว serialize ลง cache ไฟล์/ฐานข้อมูล
     * ตอน deserialize กลับมาจะกลายเป็น __PHP_Incomplete_Class
     * ในบาง request ที่ยังโหลด class ไม่ทัน แล้วทำให้ระบบพังทั้งหน้า
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cached = Cache::remember("setting.{$key}", 300, function () use ($key) {
            $row = static::where('skey', $key)->first();

            return $row
                ? ['value' => $row->svalue, 'type' => $row->value_type]
                : null;
        });

        if (! $cached) {
            return $default;
        }

        return match ($cached['type']) {
            'int'     => (int) $cached['value'],
            'decimal' => (float) $cached['value'],
            'bool'    => filter_var($cached['value'], FILTER_VALIDATE_BOOL),
            'json'    => json_decode($cached['value'], true),
            default   => $cached['value'],
        };
    }

    public static function put(string $key, mixed $value): void
    {
        static::where('skey', $key)->update(['svalue' => (string) $value]);
        Cache::forget("setting.{$key}");
    }
}
