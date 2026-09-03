<?php

/**
 * เพิ่ม APP_URL=http://localhost เข้าไปใน phpunit.xml
 *
 * ทำไมต้องมี:
 * เวลาติดตั้งลงโฟลเดอร์ย่อยของ web server (เช่น http://localhost/stock-app/public)
 * ค่า APP_URL ใน .env จะมี path ติดมาด้วย
 *
 * ตอนรัน php artisan test ชุดทดสอบจะใช้ APP_URL เป็น base URL
 * ทำให้ยิง request ไปที่ /stock-app/public/products แทนที่จะเป็น /products
 * ซึ่งไม่ตรงกับ route ที่ลงทะเบียนไว้ ผลคือได้ 404 และเทสต์ fail ยกชุด
 *
 * แก้โดยบังคับ APP_URL ตอนเทสต์ให้เป็น http://localhost เสมอ
 * ไม่กระทบการใช้งานจริง เพราะ Laravel อ่าน host จาก request เองอยู่แล้ว
 */

$en = getenv('ST_LANG') === 'en';

/** เลือกข้อความตามภาษา (CMD รุ่นเก่าแสดงภาษาไทยไม่ได้) */
$msg = function (string $th, string $enText) use ($en): string {
    return $en ? $enText : $th;
};

$file = getcwd() . DIRECTORY_SEPARATOR . 'phpunit.xml';

if (! file_exists($file)) {
    // ไม่มี phpunit.xml ก็ไม่ต้องทำอะไร (ไม่ถือว่าผิดพลาด)
    exit(0);
}

$xml = file_get_contents($file);

// มีอยู่แล้วก็ข้าม
if (preg_match('/<env\s+name="APP_URL"/', $xml)) {
    echo $msg("phpunit.xml มี APP_URL อยู่แล้ว", "phpunit.xml already has APP_URL") . PHP_EOL;
    exit(0);
}

// แทรกต่อจากบรรทัด APP_ENV
$patched = preg_replace(
    '/(<env\s+name="APP_ENV"[^>]*\/>)/',
    "$1\n        <env name=\"APP_URL\" value=\"http://localhost\"/>",
    $xml,
    1
);

if ($patched === null || $patched === $xml) {
    // หา APP_ENV ไม่เจอ ลองแทรกหลัง <php>
    $patched = preg_replace(
        '/(<php>)/',
        "$1\n        <env name=\"APP_URL\" value=\"http://localhost\"/>",
        $xml,
        1
    );
}

if ($patched === null || $patched === $xml) {
    fwrite(STDERR, $msg(
        "แทรก APP_URL ใน phpunit.xml ไม่สำเร็จ (ข้ามไป)",
        "Could not insert APP_URL into phpunit.xml (skipped)"
    ) . PHP_EOL);
    exit(0);
}

file_put_contents($file, $patched);

echo $msg(
    "ตั้งค่า phpunit.xml เรียบร้อย (APP_URL สำหรับเทสต์)",
    "Configured phpunit.xml (test APP_URL)"
) . PHP_EOL;
