<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * เพิ่ม HTTP security headers ทุก response
 *
 * ป้องกัน: clickjacking, MIME sniffing, XSS, ข้อมูลรั่วผ่าน referrer
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // กันเว็บอื่นเอาหน้าเราไปฝัง iframe (clickjacking)
        // หมายเหตุ: ถ้าต้องให้ฝังได้ ให้เปลี่ยนเป็น ALLOW-FROM หรือใช้ CSP frame-ancestors
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // ห้ามเบราว์เซอร์เดาชนิดไฟล์เอง (กัน XSS ผ่านไฟล์อัปโหลด)
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // ไม่ส่ง URL เต็มไปเว็บอื่น
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // ปิดการเข้าถึงอุปกรณ์ที่ไม่จำเป็น (ยกเว้น geolocation ที่ระบบใช้)
        $response->headers->set(
            'Permissions-Policy',
            'camera=(self), microphone=(), geolocation=(self), payment=()',
        );

        // บังคับ HTTPS 1 ปี (เฉพาะตอนเสิร์ฟผ่าน https จริง)
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        return $response;
    }
}
