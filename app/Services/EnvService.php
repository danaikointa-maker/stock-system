<?php

namespace App\Services;

/**
 * อ่าน/เขียนไฟล์ .env อย่างปลอดภัย
 *
 * - อ่าน: parse ค่าจากไฟล์ .env
 * - เขียน: แก้ไขเฉพาะ key ที่ต้องการ ไม่กระทบ key อื่น
 * - รองรับค่าที่มีเครื่องหมาย = ใน value
 * - ล้าง config cache หลังเขียน
 */
class EnvService
{
    private string $path;

    public function __construct()
    {
        $this->path = base_path('.env');
    }

    /** อ่านค่าทั้งหมดจาก .env เป็น array */
    public function all(): array
    {
        if (! file_exists($this->path)) {
            return [];
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $result = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // ข้าม comment
            if (str_starts_with($line, '#')) {
                continue;
            }

            // แยก key=value
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // ลบ quotes ที่ครอบ
                if (preg_match('/^["\'](.*)["\']\s*$/', $value, $m)) {
                    $value = $m[1];
                }

                $result[$key] = $value;
            }
        }

        return $result;
    }

    /** อ่านค่าเดียวจาก .env */
    public function get(string $key, ?string $default = null): ?string
    {
        $all = $this->all();

        return $all[$key] ?? $default;
    }

    /**
     * เขียนค่าหลายค่าลง .env
     *
     * - ถ้า key มีอยู่แล้ว → แก้ไขค่า
     * - ถ้า key ยังไม่มี → เพิ่มต่อท้าย
     *
     * @param  array<string, string|null>  $values  key => value (null = ลบ key)
     */
    public function set(array $values): void
    {
        $content = file_exists($this->path) ? file_get_contents($this->path) : '';

        foreach ($values as $key => $value) {
            $key = strtoupper(trim($key));

            // ถ้า value เป็น null → ลบ key
            if ($value === null) {
                $content = preg_replace("/^{$key}=.*$/m", '', $content);
                continue;
            }

            // เตรียม value (ใส่ quotes ถ้ามีช่องว่าง)
            $formatted = $this->formatValue($value);

            // ถ้า key มีอยู่แล้ว → แก้ไข
            if (preg_match("/^{$key}=.*$/m", $content)) {
                $content = preg_replace("/^{$key}=.*$/m", "{$key}={$formatted}", $content);
            }
            // ถ้า key ยังไม่มี → เพิ่มต่อท้าย (uncomment ถ้ามี # key=)
            elseif (preg_match("/^# *{$key}=.*$/m", $content)) {
                $content = preg_replace("/^# *{$key}=.*$/m", "{$key}={$formatted}", $content);
            } else {
                $content .= "\n{$key}={$formatted}";
            }
        }

        // ลบ blank lines ซ้ำ
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        file_put_contents($this->path, $content);

        // ล้าง config cache
        $this->clearCache();
    }

    /** เช็คว่า .env มีไฟล์หรือไม่ */
    public function exists(): bool
    {
        return file_exists($this->path);
    }

    /** สร้าง .env จาก .env.example */
    public function createFromExample(): bool
    {
        $example = base_path('.env.example');

        if (! file_exists($example)) {
            return false;
        }

        if (! file_exists($this->path)) {
            copy($example, $this->path);

            return true;
        }

        return false;
    }

    /** Format value สำหรับเขียนลง .env */
    private function formatValue(string $value): string
    {
        // ถ้าว่างเปล่า → ปล่อยว่าง
        if ($value === '') {
            return '';
        }

        // ถ้ามีช่องว่าง, quotes, หรือ special chars → ครอบ "..."
        if (preg_match('/[\s"\'#\\\\]/', $value) || str_contains($value, '=')) {
            return '"' . addcslashes($value, '"\\') . '"';
        }

        return $value;
    }

    /** ล้าง Laravel config cache */
    private function clearCache(): void
    {
        $cacheFile = base_path('bootstrap/cache/config.php');
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
    }
}
