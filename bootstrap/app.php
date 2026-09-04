<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active.user'  => \App\Http\Middleware\EnsureUserIsActive::class,
            'setup.check'  => \App\Http\Middleware\CheckSetupComplete::class,
        ]);
        $middleware->redirectGuestsTo('/login');

        // ตรวจสอบว่า setup แล้วหรือยัง (prepend ทุก web request)
        $middleware->web(append: [
            \App\Http\Middleware\CheckSetupComplete::class,
        ]);

        // ทำให้ redirect เป็น path เปล่า ๆ (/dashboard) แทน URL เต็ม
        // กัน error "Missing Traffic Access Token" ตอนรันหลัง proxy ที่ใช้ token
        $middleware->web(prepend: [
            \App\Http\Middleware\RelativeRedirects::class,
        ]);

        // เติม Partitioned ให้คุกกี้ ให้ใช้งานได้เมื่อเว็บถูกฝังใน iframe
        // ต้อง prepend เพราะคุกกี้ถูกแนบตอนขากลับโดย middleware ชั้นใน
        $middleware->web(prepend: [
            \App\Http\Middleware\PartitionedCookies::class,
        ]);

        // อยู่หลัง reverse proxy (preview/e2b, nginx, Cloudflare) ต้องเชื่อ header
        // X-Forwarded-* ไม่งั้น Laravel จะสร้าง URL เป็น http:// ทั้งที่ผู้ใช้เข้าผ่าน https://
        // แล้วเบราว์เซอร์จะบล็อกการ redirect เพราะเป็น mixed content
        $middleware->trustProxies(at: '*', headers:
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
