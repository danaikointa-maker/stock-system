<?php

/**
 * ตั้งค่าไฟล์ .env ให้ใช้ SQLite
 *
 * เรียกใช้จากสคริปต์ติดตั้ง (ติดตั้งและรัน.bat หรือ run-local.sh)
 * โดยรันในโฟลเดอร์โปรเจกต์ Laravel:  php setup-env.php
 *
 * แยกออกมาเป็นไฟล์ต่างหากเพราะการเขียน PHP ยาว ๆ ในบรรทัดเดียวบน .bat
 * มีปัญหาเรื่องอักขระพิเศษ (% ! " ^) ที่ Windows ตีความก่อน
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

$env = file_get_contents($envFile);

// ใช้ SQLite แทน MySQL
$env = preg_replace('/^DB_CONNECTION=.*$/m', 'DB_CONNECTION=sqlite', $env);

// ลบค่า MySQL ที่ไม่ใช้ออก (คอมเมนต์ทิ้งไว้ให้เห็นว่าเคยมี)
$env = preg_replace('/^DB_(HOST|PORT|DATABASE|USERNAME|PASSWORD)=.*$/m', '', $env);

// URL สำหรับรันในเครื่อง
if (preg_match('/^APP_URL=/m', $env)) {
    $env = preg_replace('/^APP_URL=.*$/m', 'APP_URL=http://localhost:8000', $env);
} else {
    $env .= PHP_EOL . 'APP_URL=http://localhost:8000';
}

// เก็บ session ในฐานข้อมูล
if (! preg_match('/^SESSION_DRIVER=/m', $env)) {
    $env .= PHP_EOL . 'SESSION_DRIVER=database';
}

// ยุบบรรทัดว่างซ้อนกันให้เหลือบรรทัดเดียว
$env = preg_replace("/\n{3,}/", "\n\n", $env);

file_put_contents($envFile, rtrim($env) . PHP_EOL);

// สร้างไฟล์ฐานข้อมูล SQLite ถ้ายังไม่มี
$sqlite = getcwd() . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.sqlite';

if (! file_exists($sqlite)) {
    touch($sqlite);
}

echo $msg("ตั้งค่า .env เรียบร้อย (SQLite)", "Configured .env for SQLite") . PHP_EOL;
