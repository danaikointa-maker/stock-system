<?php

/**
 * ตั้งค่าไฟล์ .env ให้ใช้ MySQL
 *
 * เรียกใช้จากสคริปต์ติดตั้ง โดยรันในโฟลเดอร์โปรเจกต์ Laravel
 * รับค่าผ่าน environment variable เพื่อเลี่ยงปัญหาอักขระพิเศษบน Windows
 *
 *   ST_DB_HOST, ST_DB_PORT, ST_DB_NAME, ST_DB_USER, ST_DB_PASS, ST_APP_URL
 *
 * แยกเป็นไฟล์ต่างหากเพราะการเขียน PHP ยาว ๆ ในบรรทัดเดียวบน .bat
 * มีปัญหาเรื่องอักขระ % ! " ^ ที่ Windows ตีความก่อน
 */

$en = getenv('ST_LANG') === 'en';

/** เลือกข้อความตามภาษา (CMD รุ่นเก่าแสดงภาษาไทยไม่ได้) */
$msg = function (string $th, string $enText) use ($en): string {
    return $en ? $enText : $th;
};

$envFile = getcwd() . DIRECTORY_SEPARATOR . '.env';
$example = getcwd() . DIRECTORY_SEPARATOR . '.env.example';

if (! file_exists($envFile)) {
    if (! file_exists($example)) {
        fwrite(STDERR, $msg("ไม่พบไฟล์ .env และ .env.example", "Neither .env nor .env.example was found") . PHP_EOL);
        exit(1);
    }
    copy($example, $envFile);
}

$host = getenv('ST_DB_HOST') ?: '127.0.0.1';
$port = getenv('ST_DB_PORT') ?: '3306';
$name = getenv('ST_DB_NAME') ?: 'stock_system';
$user = getenv('ST_DB_USER') ?: 'root';
$pass = getenv('ST_DB_PASS');
$pass = $pass === false ? '' : $pass;
$url  = getenv('ST_APP_URL') ?: 'http://localhost/stock-app/public';

$env = file_get_contents($envFile);

/** ตั้งค่าคีย์ใน .env ถ้ายังไม่มีให้เพิ่มต่อท้าย */
$set = function (string $key, string $value) use (&$env): void {
    // ใส่เครื่องหมายคำพูดถ้าค่ามีช่องว่างหรืออักขระพิเศษ
    if ($value !== '' && preg_match('/[\s#"\']/', $value)) {
        $value = '"' . str_replace('"', '\"', $value) . '"';
    }

    if (preg_match('/^' . preg_quote($key, '/') . '=.*$/m', $env)) {
        $env = preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', $key . '=' . $value, $env);
    } else {
        $env = rtrim($env) . PHP_EOL . $key . '=' . $value;
    }
};

$set('DB_CONNECTION', 'mysql');
$set('DB_HOST', $host);
$set('DB_PORT', $port);
$set('DB_DATABASE', $name);
$set('DB_USERNAME', $user);
$set('DB_PASSWORD', $pass);
$set('APP_URL', $url);
$set('APP_ENV', 'local');
$set('APP_DEBUG', 'true');
$set('SESSION_DRIVER', 'database');

$env = preg_replace("/\n{3,}/", "\n\n", $env);

file_put_contents($envFile, rtrim($env) . PHP_EOL);

echo $msg(
    "ตั้งค่า .env เรียบร้อย (MySQL: {$user}@{$host}:{$port}/{$name})",
    "Configured .env for MySQL: {$user}@{$host}:{$port}/{$name}"
) . PHP_EOL;
echo "APP_URL = {$url}" . PHP_EOL;
