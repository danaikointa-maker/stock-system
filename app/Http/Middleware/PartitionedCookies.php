<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * เติมแอตทริบิวต์ Partitioned ให้คุกกี้ทุกตัว เมื่อเสิร์ฟผ่าน https
 *
 * ทำไมต้องมี:
 * Chrome รุ่นใหม่บล็อกคุกกี้ third-party ในหน้าที่ถูกฝังใน <iframe>
 * ต่อให้ตั้ง SameSite=None; Secure ไว้แล้วก็ยังโดนบล็อกอยู่ดี
 * ทางแก้ตามมาตรฐาน CHIPS คือเติม Partitioned เข้าไปด้วย
 * เบราว์เซอร์จะเก็บคุกกี้แยกกระปุกตามเว็บแม่ที่ฝัง แต่ยังส่งให้เราตามปกติ
 *
 * อาการถ้าไม่มี: ล็อกอินผ่าน แต่ session หายตอน redirect
 * ทำให้เด้งกลับหน้า login วนไม่จบ
 *
 * ทำเฉพาะตอน https เท่านั้น เพื่อไม่ให้ dev บน localhost พัง
 */
class PartitionedCookies
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isSecure()) {
            return $response;
        }

        $bag = $response->headers;
        $cookies = $bag->getCookies();

        if ($cookies === []) {
            return $response;
        }

        $bag->removeCookie('__dummy__'); // no-op เพื่อความชัดเจนของเจตนา

        foreach ($cookies as $cookie) {
            // ลบตัวเดิมออกก่อน แล้วใส่ตัวใหม่ที่มี Partitioned
            $bag->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());

            $bag->setCookie(new Cookie(
                $cookie->getName(),
                $cookie->getValue(),
                $cookie->getExpiresTime(),
                $cookie->getPath(),
                $cookie->getDomain(),
                true,                       // secure (บังคับ เพราะ SameSite=None ต้องการ)
                $cookie->isHttpOnly(),
                $cookie->isRaw(),
                Cookie::SAMESITE_NONE,
                true                        // partitioned (CHIPS)
            ));
        }

        return $response;
    }
}
