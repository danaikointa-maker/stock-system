<?php

namespace App\Http\Middleware;

use App\Services\SecurityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * เฝ้าดู request ที่มีความเสี่ยง
 *
 *  - 403 ติดต่อกันหลายครั้ง = พยายามเข้าถึงสิ่งที่ไม่มีสิทธิ์
 *  - 429 = ยิงถี่เกินกำหนด
 *  - ดาวน์โหลดข้อมูลจำนวนมาก = อาจกำลังดูดข้อมูลออก
 */
class AuditRequest
{
    public function __construct(private SecurityService $security)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $status = $response->getStatusCode();

        if ($status === 403) {
            $this->security->log(
                SecurityService::E_PERMISSION_DENIED,
                "เข้าถึงโดยไม่มีสิทธิ์: {$request->method()} /{$request->path()}",
                'medium',
                ['path' => $request->path()],
            );
        }

        if ($status === 429) {
            $this->security->log(
                SecurityService::E_RATE_LIMIT,
                "ยิง request ถี่เกินกำหนด: /{$request->path()}",
                'medium',
                ['path' => $request->path()],
            );
        }

        // ดาวน์โหลดรายงาน/ข้อมูลจำนวนมาก
        if ($request->routeIs('*.export') || $request->has('export')) {
            $this->security->log(
                SecurityService::E_BULK_EXPORT,
                "ดาวน์โหลดข้อมูลออกจากระบบ: /{$request->path()}",
                'low',
                ['path' => $request->path(), 'query' => $request->query()],
            );
        }

        return $response;
    }
}
