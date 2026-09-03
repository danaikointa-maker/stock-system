<?php

/**
 * สร้างฐานข้อมูล MySQL ถ้ายังไม่มี
 *
 * ใช้เป็นตัวสำรองกรณีหา mysql.exe ไม่เจอ
 * รับค่าผ่าน environment variable:
 *   ST_DB_HOST, ST_DB_PORT, ST_DB_NAME, ST_DB_USER, ST_DB_PASS
 *
 * คืนค่า exit code 0 = สำเร็จ, 1 = ต่อ MySQL ไม่ได้
 */

$en = getenv('ST_LANG') === 'en';

/** เลือกข้อความตามภาษา (CMD รุ่นเก่าแสดงภาษาไทยไม่ได้) */
$msg = function (string $th, string $enText) use ($en): string {
    return $en ? $enText : $th;
};

$host = getenv('ST_DB_HOST') ?: '127.0.0.1';
$port = getenv('ST_DB_PORT') ?: '3306';
$name = getenv('ST_DB_NAME') ?: 'stock_system';
$user = getenv('ST_DB_USER') ?: 'root';
$pass = getenv('ST_DB_PASS');
$pass = $pass === false ? '' : $pass;

// ตรวจชื่อฐานข้อมูลกัน SQL injection (ชื่อ table/db ใส่ผ่าน prepared statement ไม่ได้)
if (! preg_match('/^[A-Za-z0-9_]+$/', $name)) {
    fwrite(STDERR, $msg(
        "ชื่อฐานข้อมูลใช้ได้เฉพาะ a-z A-Z 0-9 และ _ เท่านั้น",
        "Database name may only contain a-z A-Z 0-9 and _"
    ) . PHP_EOL);
    exit(1);
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port}",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]
    );

    $pdo->exec(
        "CREATE DATABASE IF NOT EXISTS `{$name}` "
        . "CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );

    echo $msg("ฐานข้อมูล {$name} พร้อมใช้งาน", "Database {$name} is ready") . PHP_EOL;
    exit(0);
} catch (PDOException $e) {
    fwrite(STDERR, $msg("เชื่อมต่อ MySQL ไม่ได้: ", "Could not connect to MySQL: ") . $e->getMessage() . PHP_EOL);
    exit(1);
}
