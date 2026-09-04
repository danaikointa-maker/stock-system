<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Symfony\Component\HttpFoundation\Response;

/**
 * ทำให้ทุก URL ที่ชี้กลับมาโดเมนตัวเอง กลายเป็น path เปล่า ๆ (relative)
 * ครอบคลุมทั้ง Location header ตอน redirect และ href/action ในหน้า HTML
 *
 * ทำไมต้องมี:
 * เวลารันหลัง proxy ที่บังคับ token ต่อ request (เช่น sandbox preview, tunnel บางเจ้า)
 * ถ้าเซิร์ฟเวอร์ตอบเป็น URL เต็ม เบราว์เซอร์จะถือว่าเป็นการเปิดหน้าใหม่จากศูนย์
 * ทำให้บริบทของ proxy (token/header ที่ผูกกับ session ปัจจุบัน) หลุดหายไป
 * แล้วขึ้น error ทำนอง "Missing Traffic Access Token"
 *
 * ถ้าตอบเป็น path เปล่า ๆ เช่น "/dashboard" เบราว์เซอร์จะประกอบ URL เองจากหน้าปัจจุบัน
 * ซึ่งยังอยู่ในบริบทเดิมของ proxy ครบถ้วน
 *
 * URL ที่ชี้ออกไปโดเมนอื่นจะไม่ถูกแตะต้อง
 */
class RelativeRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->makeLocationRelative($request, $response);
        $this->makeHtmlLinksRelative($request, $response);

        return $response;
    }

    private function makeLocationRelative(Request $request, Response $response): void
    {
        if (! $response instanceof SymfonyRedirect) {
            return;
        }

        $target = (string) $response->headers->get('Location');
        $relative = $this->toRelative($target, $request);

        if ($relative !== null) {
            $response->headers->set('Location', $relative);
        }
    }

    private function makeHtmlLinksRelative(Request $request, Response $response): void
    {
        $type = (string) $response->headers->get('Content-Type');

        if (! str_contains(strtolower($type), 'text/html')) {
            return;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return;
        }

        $basePath = $this->detectBasePath();

        // ตัด scheme+host ของตัวเองออกจาก href/action/src ให้เหลือแต่ path
        // ถ้ามี subdirectory (เช่น /stock-system) ให้เติมเข้าไปด้วย
        foreach ($this->ownRoots($request) as $root) {
            $content = str_replace(
                [
                    'href="' . $root . '/',
                    "href='" . $root . '/',
                    'action="' . $root . '/',
                    "action='" . $root . '/',
                    'src="' . $root . '/',
                    "src='" . $root . '/',
                ],
                [
                    'href="' . $basePath . '/', "href='" . $basePath . '/',
                    'action="' . $basePath . '/', "action='" . $basePath . '/',
                    'src="' . $basePath . '/', "src='" . $basePath . '/',
                ],
                $content
            );

            // กรณีชี้มาที่ root พอดี ไม่มี path ต่อท้าย
            $content = str_replace(
                ['href="' . $root . '"', 'action="' . $root . '"'],
                ['href="' . $basePath . '/"', 'action="' . $basePath . '/"'],
                $content
            );
        }

        $response->setContent($content);
    }

    /** รูปแบบ scheme+host ที่ถือว่าเป็น "ตัวเอง" */
    private function ownRoots(Request $request): array
    {
        $host = $request->getHttpHost(); // มี port ติดมาด้วยถ้ามี

        return array_unique([
            'https://' . $host,
            'http://' . $host,
            rtrim((string) config('app.url'), '/'),
        ]);
    }

    private function toRelative(string $target, Request $request): ?string
    {
        if ($target === '' || ! preg_match('#^https?://#i', $target)) {
            return null;
        }

        $parts = parse_url($target);

        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        if (strcasecmp($parts['host'], $request->getHost()) !== 0) {
            return null;
        }

        $path = ($parts['path'] ?? '/')
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');

        // รองรับ subdirectory: หา base path จาก SCRIPT_NAME
        // เช่น /stock-system/public/index.php → base = /stock-system
        $basePath = $this->detectBasePath();
        if ($basePath && str_starts_with($path, '/')) {
            $path = $basePath . $path;
        }

        return $path;
    }

    /** หา base path ของ app (subdirectory) */
    private function detectBasePath(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        // /stock-system/public/index.php → /stock-system
        if (preg_match('#^(.+)/public/index\.php$#', $scriptName, $m)) {
            return rtrim($m[1], '/');
        }
        return '';
    }
}
