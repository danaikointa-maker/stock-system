<?php

/**
 * ไฟล์นี้ให้คัดลอกไปวางที่ "รากโปรเจกต์" (ข้าง ๆ โฟลเดอร์ public)
 * แล้วตั้งชื่อเป็น index.php
 *
 * ทำไมต้องมี:
 * Laravel ออกแบบให้ web server ชี้เข้าที่โฟลเดอร์ public เท่านั้น
 * แต่ถ้าติดตั้งใน htdocs แบบโฟลเดอร์ย่อย ผู้ใช้มักเข้า
 *     http://localhost/stock-app
 * ซึ่งจะเจอ 403 หรือหน้ารายชื่อไฟล์แทน
 *
 * ไฟล์นี้พาไป public/ ให้อัตโนมัติ
 *
 * ทำไมไม่ใช้ .htaccess RewriteRule:
 * - ถ้า rewrite เข้า public/ แบบภายใน Laravel จะคำนวณ path ของ route ผิด
 *   แล้วขึ้น 404 ทุกหน้า
 * - ถ้าใช้ R=301 Apache มักแปลง path ในเครื่องออกมาเป็น URL
 *   เช่น http://localhost/tmp/htdocs/stock-app/public/ ซึ่งผิด
 * การ redirect แบบ relative ด้วย PHP ทำงานถูกต้องเสมอ
 * ไม่ว่าจะติดตั้งในโฟลเดอร์ชื่ออะไรหรือลึกแค่ไหน
 *
 * หมายเหตุด้านความปลอดภัย:
 * บนเซิร์ฟเวอร์จริงควรตั้ง DocumentRoot ให้ชี้ที่ public โดยตรงจะปลอดภัยกว่า
 * เพราะไฟล์ .env และโฟลเดอร์ vendor จะอยู่นอก web root
 */

$path = $_SERVER['REQUEST_URI'] ?? '/';

// ตัด query string ออกก่อน แล้วค่อยเอามาต่อกลับ
$query = '';
if (($pos = strpos($path, '?')) !== false) {
    $query = substr($path, $pos);
    $path = substr($path, 0, $pos);
}

// เติม / ปิดท้ายถ้ายังไม่มี เพื่อให้ redirect แบบ relative ทำงานถูก
if (! str_ends_with($path, '/')) {
    header('Location: ' . basename($path) . '/public/' . $query, true, 301);
    exit;
}

header('Location: public/' . $query, true, 301);
exit;
