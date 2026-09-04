<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\EnvService;
use Illuminate\Http\Request;

/**
 * หน้าตั้งค่าระบบ (Admin)
 *
 * แก้ไขค่า .env จาก UI ได้โดยตรง เฉพาะค่าที่จำเป็นต้องเปลี่ยน:
 * - App: name, URL, timezone, debug
 * - Social Login: LINE, Google credentials
 * - Point Settings: expiry days, points multiplier
 * - Mail: mailer, host, port
 */
class AdminSettingsController extends Controller
{
    /** รายการค่าที่ตั้งได้จาก UI */
    private const EDITABLE_GROUPS = [
        'app' => [
            'title'  => '🏢 ตั้งค่าแอป',
            'fields' => [
                'APP_NAME'     => ['label' => 'ชื่อระบบ',       'type' => 'text',     'hint' => 'เช่น RoaMembers, ร้านป้าสมจิตร'],
                'APP_URL'      => ['label' => 'URL ของเว็บ',    'type' => 'url',      'hint' => 'เช่น https://members.myshop.com'],
                'APP_TIMEZONE' => ['label' => 'เขตเวลา',        'type' => 'select',   'options' => ['Asia/Bangkok' => 'Bangkok (GMT+7)', 'Asia/Singapore' => 'Singapore (GMT+8)', 'UTC' => 'UTC']],
                'APP_DEBUG'    => ['label' => 'โหมดดีบั๊ก',     'type' => 'toggle',   'hint' => 'เปิดเฉพาะตอนพัฒนา — ปิดเมื่อใช้งานจริง'],
            ],
        ],
        'social' => [
            'title'  => '🔐 Social Login',
            'fields' => [
                'LINE_CLIENT_ID'     => ['label' => 'LINE Channel ID',     'type' => 'text',     'hint' => 'ได้จาก LINE Developers Console'],
                'LINE_CLIENT_SECRET' => ['label' => 'LINE Channel Secret', 'type' => 'password', 'hint' => 'ได้จาก LINE Developers Console'],
                'GOOGLE_CLIENT_ID'     => ['label' => 'Google Client ID',     'type' => 'text',     'hint' => 'ได้จาก Google Cloud Console'],
                'GOOGLE_CLIENT_SECRET' => ['label' => 'Google Client Secret', 'type' => 'password', 'hint' => 'ได้จาก Google Cloud Console'],
            ],
        ],
        'mail' => [
            'title'  => '📧 อีเมล',
            'fields' => [
                'MAIL_MAILER'    => ['label' => 'Mailer',     'type' => 'select', 'options' => ['log' => 'Log (ทดสอบ)', 'smtp' => 'SMTP', 'sendmail' => 'Sendmail']],
                'MAIL_HOST'      => ['label' => 'SMTP Host',  'type' => 'text',   'hint' => 'เช่น smtp.gmail.com'],
                'MAIL_PORT'      => ['label' => 'SMTP Port',  'type' => 'text',   'hint' => 'เช่น 587'],
                'MAIL_USERNAME'  => ['label' => 'Username',   'type' => 'text'],
                'MAIL_PASSWORD'  => ['label' => 'Password',   'type' => 'password'],
                'MAIL_FROM_ADDRESS' => ['label' => 'อีเมลผู้ส่ง', 'type' => 'email', 'hint' => 'เช่น noreply@myshop.com'],
                'MAIL_FROM_NAME'    => ['label' => 'ชื่อผู้ส่ง',   'type' => 'text'],
            ],
        ],
    ];

    public function __construct(private EnvService $env)
    {
    }

    /** แสดงหน้าตั้งค่า */
    public function index()
    {
        $currentValues = $this->env->all();
        $groups = self::EDITABLE_GROUPS;

        return view('admin.settings', [
            'groups'        => $groups,
            'currentValues' => $currentValues,
        ]);
    }

    /** บันทึกค่าที่ตั้ง */
    public function update(Request $request)
    {
        $allFields = [];
        foreach (self::EDITABLE_GROUPS as $group) {
            foreach ($group['fields'] as $key => $meta) {
                $allFields[$key] = $meta;
            }
        }

        $updates = [];
        foreach ($request->all() as $key => $value) {
            $key = strtoupper($key);
            if (isset($allFields[$key])) {
                // toggle → true/false
                if ($allFields[$key]['type'] === 'toggle') {
                    $updates[$key] = $value ? 'true' : 'false';
                } else {
                    $updates[$key] = $value;
                }
            }
        }

        if (! empty($updates)) {
            $this->env->set($updates);

            // ล้าง cache
            \Illuminate\Support\Facades\Artisan::call('config:clear');
        }

        return back()->with('status', '✅ บันทึกการตั้งค่าเรียบร้อย');
    }
}
