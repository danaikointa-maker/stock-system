<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Detect Base Path (subdirectory)
|--------------------------------------------------------------------------
| SCRIPT_NAME = /stock-system/public/index.php → base = /stock-system
| SCRIPT_NAME = /public/index.php              → base = (ว่าง)
*/
$__basePath = '';
$__scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
if (preg_match('#^(.+)/public/index\.php$#', $__scriptName, $__m)) {
    $__basePath = rtrim($__m[1], '/');
}

/*
|--------------------------------------------------------------------------
| Pre-flight Checks — ก่อน Laravel boot
|--------------------------------------------------------------------------
*/

$setupRedirect = false;

// 1. vendor
if (! file_exists(__DIR__ . '/../vendor/autoload.php')) {
    $setupRedirect = true;
}

// 2. .env
if (! file_exists(__DIR__ . '/../.env')) {
    if (file_exists(__DIR__ . '/../.env.example')) {
        copy(__DIR__ . '/../.env.example', __DIR__ . '/../.env');
    }
    $setupRedirect = true;
}

// 3. APP_KEY
$envContent = '';
if (file_exists(__DIR__ . '/../.env')) {
    $envContent = file_get_contents(__DIR__ . '/../.env');
    if (! preg_match('/^APP_KEY=.+$/m', $envContent)) {
        $setupRedirect = true;
    }
}

// 4. Database
$dbReady = false;
$dbDriver = 'sqlite';
if (! $setupRedirect && $envContent) {
    if (preg_match('/^DB_CONNECTION=(.+)$/m', $envContent, $m)) {
        $dbDriver = trim($m[1]);
    }
    try {
        if ($dbDriver === 'sqlite') {
            $dbPath = __DIR__ . '/../database/database.sqlite';
            if (file_exists($dbPath) && filesize($dbPath) > 0) {
                $pdo = new PDO('sqlite:' . $dbPath);
                $r = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='sessions'");
                $dbReady = $r && $r->fetch() !== false;
            }
        } elseif ($dbDriver === 'mysql') {
            $host = '127.0.0.1'; $port = '3306'; $dbname = ''; $user = 'root'; $pass = '';
            if (preg_match('/^DB_HOST=(.+)$/m', $envContent, $m))     $host   = trim($m[1]);
            if (preg_match('/^DB_PORT=(.+)$/m', $envContent, $m))     $port   = trim($m[1]);
            if (preg_match('/^DB_DATABASE=(.+)$/m', $envContent, $m)) $dbname = trim($m[1]);
            if (preg_match('/^DB_USERNAME=(.+)$/m', $envContent, $m)) $user   = trim($m[1]);
            if (preg_match('/^DB_PASSWORD=(.+)$/m', $envContent, $m)) $pass   = trim($m[1]);
            if ($dbname) {
                $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname}", $user, $pass, [PDO::ATTR_TIMEOUT => 3]);
                $r = $pdo->query("SHOW TABLES LIKE 'sessions'");
                $dbReady = $r && $r->fetch() !== false;
            }
        }
    } catch (\Throwable $e) {
        $dbReady = false;
    }
    if (! $dbReady) {
        $setupRedirect = true;
    }
}

// ── แสดงหน้า pre-check ถ้ายังไม่พร้อม ─────────────────────────
if ($setupRedirect) {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    // ลบ base path ออกจาก request path
    $appPath = $__basePath ? substr($requestPath, strlen($__basePath)) : $requestPath;

    if ($appPath !== '/setup' && ! str_starts_with($appPath, '/setup/')) {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        // ตรวจสอบสถานะ
        $checks = [];
        $checks[] = ['PHP ' . PHP_VERSION, version_compare(PHP_VERSION, '8.2.0', '>=')];

        $vendorOk = file_exists(__DIR__ . '/../vendor/autoload.php');
        $checks[] = ['Composer dependencies', $vendorOk];

        $envOk = file_exists(__DIR__ . '/../.env');
        $checks[] = ['.env file', $envOk];

        $appKeyOk = $envOk && (bool) preg_match('/^APP_KEY=.+$/m', $envContent);
        $checks[] = ['APP_KEY (encryption key)', $appKeyOk];

        $checks[] = ["Database ({$dbDriver})", $dbReady];

        foreach (['mbstring', 'xml', 'curl', 'zip', 'pdo', 'openssl'] as $ext) {
            $checks[] = ["ext-{$ext}", extension_loaded($ext)];
        }
        foreach (['storage', 'bootstrap/cache'] as $dir) {
            $p = __DIR__ . '/../' . $dir;
            $checks[] = ["เขียนได้: {$dir}/", is_dir($p) && is_writable($p)];
        }

        $allOk = true;
        $checksHtml = '';
        foreach ($checks as [$label, $ok]) {
            if (! $ok) $allOk = false;
            $icon = $ok ? '✅' : '❌';
            $cls  = $ok ? 'ok' : 'fail';
            $checksHtml .= "<div class=\"check\"><span class=\"{$cls}\">{$icon}</span> {$label}</div>\n";
        }

        // Action area
        $actionHtml = '';
        if (! $vendorOk) {
            $actionHtml = '<div class="alert warn">⚠️ <b>ยังไม่ได้ติดตั้ง dependencies</b><br>รันคำสั่ง: <code>composer install</code><br>หรือ <code>bash install.sh</code></div>';
        } elseif ($vendorOk && $envOk && $appKeyOk && ! $dbReady) {
            $actionHtml = '<div class="alert ok">✅ ไฟล์พร้อมแล้ว — ❌ ฐานข้อมูลยังไม่ได้สร้าง</div>';
            $actionHtml .= '<button class="btn" onclick="goSetup()">🚀 เริ่มติดตั้ง →</button>';
        } elseif ($allOk) {
            $actionHtml .= '<button class="btn" onclick="goSetup()">เริ่มติดตั้ง →</button>';
        } else {
            $actionHtml = '<p style="font-size:12px;color:#C62828">❌ กรุณาแก้ไขรายการที่ ❌ ด้านบน แล้วรีเฟรชหน้านี้</p>';
        }

        // Embed base path ลงใน JS โดยตรง
        $setupUrl = $__basePath . '/setup';

        echo <<<HTML
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ติดตั้งระบบ · RoaMembers</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Noto Sans Thai","Sarabun",-apple-system,sans-serif;background:#F6F5F0;color:#1A1A14;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.box{background:#fff;border:1px solid #E6E4DA;border-radius:20px;padding:40px;max-width:500px;width:100%;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.06)}
.icon{font-size:48px;margin-bottom:16px}
h1{font-size:22px;margin-bottom:8px}
p{font-size:14px;color:#6E6E63;margin-bottom:20px;line-height:1.7}
.checks{text-align:left;background:#F6F5F0;border-radius:12px;padding:16px;margin-bottom:20px;font-size:13px}
.check{padding:6px 0;display:flex;align-items:center;gap:8px}
.check .ok{color:#006018}.check .fail{color:#C62828}
.btn{display:inline-block;padding:14px 32px;background:#F04800;color:#fff;border-radius:12px;font-size:15px;font-weight:700;border:none;cursor:pointer;transition:.15s;font-family:inherit}
.btn:hover{background:#C23800}
.alert{padding:12px;border-radius:10px;font-size:13px;margin-bottom:16px}
.alert.ok{background:#E8F5E9;border:1px solid #A5D6A7;color:#1B5E20}
.alert.warn{background:#FFF3E0;border:1px solid #FFE0B2;color:#E65100}
.note{font-size:12px;color:#6E6E63;margin-top:16px}
code{background:#1A1A14;color:#4CAF50;padding:4px 8px;border-radius:6px;font-size:12px}
</style>
</head>
<body>
<div class="box">
  <div class="icon">🚀</div>
  <h1>ยินดีต้อนรับสู่ RoaMembers</h1>
  <p>ระบบตรวจพบว่ายังไม่ได้ติดตั้ง<br>กรุณาติดตั้งระบบก่อนใช้งาน</p>
  <div class="checks">{$checksHtml}</div>
  {$actionHtml}
  <p class="note">RoaMembers Stock & Loyalty System</p>
</div>
<script>
function goSetup(){window.location.href="{$setupUrl}";}
</script>
</body></html>
HTML;
        exit;
    }
}

// ── Laravel boot ─────────────────────────────────────────────

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

// ถ้า database ยังไม่พร้อม → ใช้ file session
if (isset($dbReady) && ! $dbReady) {
    $sessionDir = __DIR__ . '/../storage/framework/sessions';
    if (! is_dir($sessionDir)) {
        @mkdir($sessionDir, 0775, true);
    }
    $_ENV['SESSION_DRIVER'] = 'file';
    putenv('SESSION_DRIVER=file');
}

// ส่ง base path ให้ Laravel รู้ (สำหรับ URL generation)
if ($__basePath) {
    $_ENV['APP_BASE_PATH'] = $__basePath;
}

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->handleRequest(Request::capture());
