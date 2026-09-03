<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * เตะผู้ใช้ที่ถูกระงับออกจากระบบทันที
 * แม้ session ยังอยู่ก็ตาม (กรณีหัวหน้ากดระงับระหว่างที่ลูกน้องยัง login ค้างอยู่)
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['login' => 'บัญชีของคุณถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแล']);
        }

        if ($user && ! $user->org_node_id) {
            Auth::logout();

            return redirect()->route('login')
                ->withErrors(['login' => 'บัญชีนี้ยังไม่ได้ผูกกับหน่วยงาน กรุณาติดต่อผู้ดูแลระบบ']);
        }

        return $next($request);
    }
}
