<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * บังคับให้ทุก URL ที่ระบบสร้าง (form action, redirect, asset) เป็น https
         * เมื่อเข้าผ่านโดเมนที่เป็น https
         *
         * ทำไมต้องมี: ถ้าอยู่หลัง reverse proxy (nginx, Cloudflare, sandbox preview)
         * แล้ว proxy ไม่ส่ง header X-Forwarded-Proto มา Laravel จะเข้าใจว่าเป็น http
         * แล้วสร้าง <form action="http://..."> ทั้งที่หน้าเว็บโหลดมาแบบ https
         * เบราว์เซอร์จะบล็อกการ submit เพราะเป็น mixed content
         * อาการที่เห็นคือ "กดปุ่มเข้าสู่ระบบแล้วไม่มีอะไรเกิดขึ้น" หรือเด้งกลับหน้า login
         *
         * เช็คจาก APP_URL เป็นหลัก (ตั้งค่าชัดเจนที่สุด) และเผื่อกรณี proxy ส่ง header มาถูก
         */
        if ($this->shouldForceHttps()) {
            URL::forceScheme('https');

            /*
             * เมื่อเสิร์ฟผ่าน https ให้คุกกี้ session ใช้ SameSite=None
             *
             * ทำไมต้องมี: ถ้าหน้าเว็บถูกเปิดอยู่ใน iframe ของโดเมนอื่น
             * (เช่น พาเนล preview, ระบบที่ฝังเว็บเราเข้าไป)
             * เบราว์เซอร์จะถือว่าเป็น third-party context
             * คุกกี้ SameSite=Lax (ค่า default) จะ "ไม่ถูกส่ง" ไปกับ request
             *
             * อาการคือ ล็อกอินผ่าน แต่พอ redirect ไป /dashboard
             * session หายไปแล้ว ระบบเลยเด้งกลับหน้า login วนไม่จบ
             *
             * SameSite=None บังคับว่าต้องมี Secure ด้วย จึงตั้งคู่กันเสมอ
             * และทำเฉพาะตอน https เท่านั้น เพื่อไม่ให้ dev บน localhost พัง
             */
            config([
                'session.same_site' => 'none',
                'session.secure' => true,
            ]);
        }
    }

    private function shouldForceHttps(): bool
    {
        // 1) ตั้ง APP_URL เป็น https ไว้ชัดเจน — เชื่อค่านี้ก่อน (ใช้ตอน deploy จริง)
        if (str_starts_with((string) config('app.url'), 'https://')) {
            return true;
        }

        // 2) สั่งเปิดตรง ๆ ผ่าน .env
        if (filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        $request = request();

        if (! $request) {
            return false;
        }

        // 3) proxy บอกมาว่าต้นทางเป็น https
        if (strtolower((string) $request->header('X-Forwarded-Proto')) === 'https') {
            return true;
        }

        // 4) ต่อผ่าน https ตรง ๆ (ไม่มี proxy คั่น)
        if ($request->isSecure()) {
            return true;
        }

        // 5) proxy บางตัว (เช่น sandbox preview) ไม่ส่ง X-Forwarded-Proto มาเลย
        //    แต่ผู้ใช้เข้าผ่าน https อยู่ — เดาจากโดเมนที่ browser ขอมาแทน
        //
        //    ระวัง: ห้ามเหมาว่า "ไม่ใช่ localhost = https" เพราะโดเมนสำหรับ
        //    พัฒนาในเครื่อง เช่น stock-system.test ของ Laragon ก็ไม่ใช่ localhost
        //    แต่เสิร์ฟผ่าน http ธรรมดา ถ้าเผลอบังคับ https จะออกคุกกี้แบบ
        //    secure ทำให้เบราว์เซอร์ไม่ส่งคุกกี้กลับ แล้วล็อกอินไม่ผ่าน (419)
        //
        //    จึงยกเว้นนามสกุลโดเมนที่ใช้กันในเครื่อง และ hostname ที่ไม่มีจุด
        $host = strtolower((string) $request->getHost());

        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        // hostname ที่ไม่มีจุดเลย (เช่น "myserver") = เครื่องในวงแลน
        if (! str_contains($host, '.')) {
            return false;
        }

        foreach (['.localhost', '.test', '.local', '.localdomain', '.invalid', '.example'] as $devTld) {
            if (str_ends_with($host, $devTld)) {
                return false;
            }
        }

        return true;
    }
}
