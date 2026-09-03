<?php

namespace App\Http\Middleware;

use App\Services\SecurityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ด่านแรกสุด: ปฏิเสธ IP หรือบัญชีที่ถูกระงับ
 *
 * ทำงานก่อน middleware อื่นทั้งหมด เพื่อไม่ให้ผู้โจมตี
 * เข้าถึงตรรกะส่วนอื่นของระบบได้เลย
 */
class BlockBannedEntities
{
    public function __construct(private SecurityService $security)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        if ($ip && $this->security->isBlocked('ip', $ip)) {
            $this->security->log(
                SecurityService::E_BLOCKED_ACCESS,
                "IP ที่ถูกระงับพยายามเข้าใช้งาน: {$ip}",
                'high',
                ['ip' => $ip, 'path' => $request->path()],
            );

            abort(403, 'การเข้าใช้งานของคุณถูกระงับชั่วคราว กรุณาติดต่อผู้ดูแลระบบ');
        }

        if ($user = $request->user()) {
            if ($this->security->isBlocked('user', (string) $user->id)) {
                auth()->logout();
                abort(403, 'บัญชีของคุณถูกระงับ กรุณาติดต่อผู้ดูแลระบบ');
            }
        }

        return $next($request);
    }
}
