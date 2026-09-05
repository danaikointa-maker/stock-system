<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * เรียก DemoSeeder เพื่อสร้างข้อมูลตัวอย่างทั้งหมด
     * (สายงาน 6 ระดับ, ผู้ใช้ 6 บทบาท, สินค้า, ล็อต, QR code)
     *
     * ไฟล์นี้ต้องทับของเดิมที่ Laravel สร้างมาให้
     * ไม่งั้น php artisan db:seed จะสร้างแค่ผู้ใช้ทดสอบ 1 คน
     * แล้วจะล็อกอินด้วย admin@demo.test ไม่ได้
     */
    public function run(): void
    {
        $this->call([
            DemoSeeder::class,
            AccountingTestSeeder::class,
        ]);
    }
}
