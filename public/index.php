<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Pre-flight Checks — ก่อน Laravel boot
|--------------------------------------------------------------------------
| ตรวจสอบว่าพร้อมใช้งานไหม ก่อนที่ Laravel จะ boot
| ถ้าไม่พร้อม → redirect ไปหน้า Setup Wizard
|
| ทำไมไม่ใช้ middleware?
| เพราะ middleware ทำงานหลัง Laravel boot แล้ว
| ถ้า .env หรือ vendor/ ไม่มี → Laravel crash ก่อน middleware จะทำงาน (504)
*/

$setupRedirect = null;

// 1. ตรวจสอบ vendor/autoload.php
if (! file_exists(__DIR__ . '/../vendor/autoload.php')) {
    $setupRedirect = true;
}

// 2. ตรวจสอบ .env — ถ้าไม่มี สร้างจาก .env.example
if (! file_exists(__DIR__ . '/../.env')) {
    if (file_exists(__DIR__ . '/../.env.example')) {
        copy(__DIR__ . '/../.env.example', __DIR__ . '/../.env');
    }
    $setupRedirect = true;
}

// 3. ตรวจสอบ APP_KEY — ถ้าว่าง ต้อง setup
if (file_exists(__DIR__ . '/../.env')) {
    $envContent = file_get_contents(__DIR__ . '/../.env');
    if (! preg_match('/^APP_KEY=.+$/m', $envContent)) {
        $setupRedirect = true;
    }
}

// 4. ตรวจสอบ database — ถ้ายังไม่มี table ต้อง setup
if (file_exists(__DIR__ . '/../.env') && ! $setupRedirect) {
    $envContent = file_get_contents(__DIR__ . '/../.env');

    // ดึง DB_CONNECTION
    $dbDriver = 'sqlite';
    if (preg_match('/^DB_CONNECTION=(.+)$/m', $envContent, $m)) {
        $dbDriver = trim($m[1]);
    }

    $dbReady = false;

    try {
        if ($dbDriver === 'sqlite') {
            $dbPath = __DIR__ . '/../database/database.sqlite';
            if (file_exists($dbPath) && filesize($dbPath) > 0) {
                $pdo = new PDO('sqlite:' . $dbPath);
                $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='sessions'");
                $dbReady = $result && $result->fetch() !== false;
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
                $result = $pdo->query("SHOW TABLES LIKE 'sessions'");
                $dbReady = $result && $result->fetch() !== false;
            }
        }
    } catch (\Throwable $e) {
        $dbReady = false;
    }

    if (! $dbReady) {
        $setupRedirect = true;
    }
}

// ถ้าต้อง redirect → ทำ raw PHP redirect (ไม่พึ่ง Laravel)
if ($setupRedirect) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $requestPath = parse_url($requestUri, PHP_URL_PATH);

    // อนุญาตให้เข้า /setup ได้ (เพื่อติดตั้ง)
    // แต่ทุกหน้าอื่น → redirect ไป /setup
    if ($requestPath !== '/setup' && ! str_starts_with($requestPath, '/setup/')) {
        // แสดงหน้า setup pre-check (ไม่พึ่ง Laravel)
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
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
.check .ok{color:#006018}
.check .fail{color:#C62828}
.btn{display:inline-block;padding:14px 32px;background:#F04800;color:#fff;border-radius:12px;font-size:15px;font-weight:700;text-decoration:none;transition:.15s}
.btn:hover{background:#C23800}
.note{font-size:12px;color:#6E6E63;margin-top:16px}
.loader{display:inline-block;width:16px;height:16px;border:2px solid #E6E4DA;border-top-color:#F04800;border-radius:50%;animation:spin .6s linear infinite;margin-right:6px;vertical-align:middle}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>
<div class="box">
  <div class="icon">🚀</div>
  <h1>ยินดีต้อนรับสู่ RoaMembers</h1>
  <p>ระบบตรวจพบว่ายังไม่ได้ติดตั้ง<br>กรุณาติดตั้งระบบก่อนใช้งาน</p>
  <div class="checks">
HTML;

        // ตรวจสอบสถานะ
        $checks = [];

        // PHP Version
        $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
        $checks[] = ['php', 'PHP ' . PHP_VERSION, $phpOk];

        // vendor
        $vendorOk = file_exists(__DIR__ . '/../vendor/autoload.php');
        $checks[] = ['vendor', 'Composer dependencies', $vendorOk];

        // .env
        $envOk = file_exists(__DIR__ . '/../.env');
        $checks[] = ['env', '.env file', $envOk];

        // APP_KEY
        $appKeyOk = false;
        if ($envOk) {
            $envContent = file_get_contents(__DIR__ . '/../.env');
            $appKeyOk = (bool) preg_match('/^APP_KEY=.+$/m', $envContent);
        }
        $checks[] = ['key', 'APP_KEY (encryption key)', $appKeyOk];

        // Database
        $dbReady = false;
        if ($envOk && $appKeyOk) {
            $dbDriver = 'sqlite';
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
                } else {
                    $dbReady = true; // MySQL assumed OK if credentials exist
                }
            } catch (\Throwable $e) {}
        }
        $checks[] = ['db', "Database ({$dbDriver})", $dbReady];

        // extensions
        $exts = ['mbstring', 'xml', 'curl', 'zip', 'pdo', 'openssl'];
        foreach ($exts as $ext) {
            $checks[] = ['ext', "ext-{$ext}", extension_loaded($ext)];
        }

        // writable dirs
        $dirs = ['storage', 'bootstrap/cache'];
        foreach ($dirs as $dir) {
            $path = __DIR__ . '/../' . $dir;
            $ok = is_dir($path) && is_writable($path);
            $checks[] = ['dir', "เขียนได้: {$dir}/", $ok];
        }

        $allOk = true;
        foreach ($checks as $c) {
            if (! $c[2]) $allOk = false;
            $icon = $c[2] ? '✅' : '❌';
            $class = $c[2] ? 'ok' : 'fail';
            echo "<div class=\"check\"><span class=\"{$class}\">{$icon}</span> {$c[1]}</div>\n";
        }

        echo '</div>';

        if (! $vendorOk) {
            // vendor ไม่มี → แสดงปุ่มติดตั้ง + คำแนะนำ
            echo '<p style="background:#FFF3E0;border:1px solid #FFE0B2;border-radius:10px;padding:12px;font-size:13px;color:#E65100;margin-bottom:16px">';
            echo '⚠️ <b>ยังไม่ได้ติดตั้ง dependencies</b><br>';
            echo 'กรุณารันคำสั่งนี้ก่อน:<br>';
            echo '<code style="background:#1A1A14;color:#4CAF50;padding:8px 12px;border-radius:8px;display:block;margin-top:8px;font-size:12px">composer install</code>';
            echo '</p>';
            echo '<p style="font-size:12px;color:#6E6E63">หรือรัน <code>bash install.sh</code> เพื่อติดตั้งทั้งหมดอัตโนมัติ</p>';
        } elseif ($vendorOk && $envOk && $appKeyOk && ! $dbReady) {
            // vendor + .env + APP_KEY พร้อม แต่ database ยังไม่ได้ migrate → ไป /setup
            echo '<p style="background:#E8F5E9;border:1px solid #A5D6A7;border-radius:10px;padding:12px;font-size:13px;color:#1B5E20;margin-bottom:16px">';
            echo '✅ ไฟล์และ dependencies พร้อมแล้ว<br>';
            echo '❌ ฐานข้อมูลยังไม่ได้สร้าง — กดปุ่มด้านล่างเพื่อติดตั้ง';
            echo '</p>';
            echo '<a href="/setup" class="btn">🚀 เริ่มติดตั้ง →</a>';
        } elseif ($allOk) {
            echo '<a href="/setup" class="btn">เริ่มติดตั้ง →</a>';
        } else {
            echo '<p style="font-size:12px;color:#C62828">❌ กรุณาแก้ไขรายการที่ ❌ ด้านบน แล้วรีเฟรชหน้านี้</p>';
        }

        echo '<p class="note">RoaMembers Stock & Loyalty System</p>';
        echo '</div></body></html>';
        exit;
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// ถ้า database ยังไม่พร้อม → ใช้ file session (ไม่พึ่ง database)
// ป้องกัน 500 error ตอนเข้า /setup
// ใช้ 'file' แทน 'array' เพื่อให้ session persist ข้าม request (CSRF ทำงาน)
if (isset($dbReady) && ! $dbReady) {
    // สร้าง storage/framework/sessions ถ้ายังไม่มี
    $sessionDir = __DIR__ . '/../storage/framework/sessions';
    if (! is_dir($sessionDir)) {
        @mkdir($sessionDir, 0775, true);
    }
    $_ENV['SESSION_DRIVER'] = 'file';
    putenv('SESSION_DRIVER=file');
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
