<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * ตรวจสอบว่าระบบติดตั้งเสร็จสมบูรณ์หรือยัง
 *
 * ถ้ายังไม่ setup → redirect ไป /setup
 * ยกเว้น: /setup เอง, /up (health check), /api/*
 */
class CheckSetupComplete
{
    public function handle(Request $request, Closure $next)
    {
        // อนุญาตให้เข้าหน้า setup, health check, API ได้เสมอ
        if ($this->isExempt($request)) {
            return $next($request);
        }

        // ตรวจสอบว่า setup แล้วหรือยัง
        if (! $this->isSetupComplete()) {
            return redirect()->route('setup.wizard');
        }

        return $next($request);
    }

    private function isExempt(Request $request): bool
    {
        // ข้ามใน test environment เสมอ
        if (app()->runningUnitTests()) {
            return true;
        }

        $path = $request->path();

        return $request->is('setup*')
            || $request->is('up')
            || $request->is('api/*')
            || str_starts_with($path, '_debugbar')
            || str_starts_with($path, 'favicon');
    }

    private function isSetupComplete(): bool
    {
        // ตรวจสอบ .env
        if (! file_exists(base_path('.env'))) {
            return false;
        }

        // ตรวจสอบ APP_KEY
        if (empty(config('app.key'))) {
            return false;
        }

        // ตรวจสอบ vendor (Composer)
        if (! file_exists(base_path('vendor/autoload.php'))) {
            return false;
        }

        // ตรวจสอบ settings table + setup_complete flag
        try {
            $complete = \App\Models\Setting::val('setup_complete');
            return $complete === '1';
        } catch (\Throwable $e) {
            // table ไม่มี หรือ DB ยังไม่พร้อม
            return false;
        }
    }
}
