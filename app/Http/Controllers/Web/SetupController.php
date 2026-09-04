<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\EnvService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Setup Wizard — ติดตั้งระบบครั้งแรกผ่านหน้าเว็บ
 *
 * Steps:
 *  1. ตรวจสอบ requirements (PHP, extensions, permissions)
 *  2. ตั้งค่า database (SQLite / MySQL)
 *  3. ตั้งค่า APP (name, URL, timezone)
 *  4. สร้าง admin account
 *  5. Social Login (optional)
 *  6. ยืนยัน + ติดตั้ง
 *  7. เสร็จสิ้น
 */
class SetupController extends Controller
{
    public function __construct(private EnvService $env)
    {
    }

    // ── Step 1: Requirements ──────────────────────────────────
    public function wizard()
    {
        $checks = $this->checkRequirements();
        $allPassed = collect($checks)->every(fn ($c) => $c['pass']);

        return view('setup.wizard', [
            'step'       => 1,
            'totalSteps' => 6,
            'checks'     => $checks,
            'allPassed'  => $allPassed,
        ]);
    }

    // ── Step 2: Database ──────────────────────────────────────
    public function database()
    {
        $currentDriver = $this->env->get('DB_CONNECTION', 'sqlite');

        return view('setup.wizard', [
            'step'       => 2,
            'totalSteps' => 6,
            'driver'     => $currentDriver,
            'db'         => [
                'host'     => $this->env->get('DB_HOST', '127.0.0.1'),
                'port'     => $this->env->get('DB_PORT', '3306'),
                'database' => $this->env->get('DB_DATABASE', 'roamembers'),
                'username' => $this->env->get('DB_USERNAME', 'root'),
                'password' => $this->env->get('DB_PASSWORD', ''),
            ],
        ]);
    }

    public function saveDatabase(Request $request)
    {
        $data = $request->validate([
            'driver'      => 'required|in:sqlite,mysql',
            'host'        => 'nullable|string',
            'port'        => 'nullable|string',
            'database'    => 'nullable|string',
            'username'    => 'nullable|string',
            'password'    => 'nullable|string',
        ]);

        if ($data['driver'] === 'sqlite') {
            // สร้างไฟล์ SQLite
            $dbPath = database_path('database.sqlite');
            if (! file_exists($dbPath)) {
                touch($dbPath);
            }

            $this->env->set([
                'DB_CONNECTION' => 'sqlite',
                'DB_HOST'       => null,
                'DB_PORT'       => null,
                'DB_DATABASE'   => null,
                'DB_USERNAME'   => null,
                'DB_PASSWORD'   => null,
            ]);
        } else {
            // MySQL — ทดสอบ connection ก่อน
            $this->env->set([
                'DB_CONNECTION' => 'mysql',
                'DB_HOST'       => $data['host'] ?? '127.0.0.1',
                'DB_PORT'       => $data['port'] ?? '3306',
                'DB_DATABASE'   => $data['database'] ?? 'roamembers',
                'DB_USERNAME'   => $data['username'] ?? 'root',
                'DB_PASSWORD'   => $data['password'] ?? '',
            ]);

            // ล้าง config cache แล้วทดสอบ
            try {
                config(['database.connections.mysql' => [
                    'host'     => $data['host'] ?? '127.0.0.1',
                    'port'     => $data['port'] ?? '3306',
                    'database' => $data['database'] ?? 'roamembers',
                    'username' => $data['username'] ?? 'root',
                    'password' => $data['password'] ?? '',
                    'driver'   => 'mysql',
                    'charset'  => 'utf8mb4',
                    'collation'=> 'utf8mb4_unicode_ci',
                ]]);
                config(['database.default' => 'mysql']);
                DB::purge('mysql');
                DB::connection('mysql')->getPdo();
            } catch (\Throwable $e) {
                return back()->withErrors(['db' => 'เชื่อมต่อ MySQL ไม่สำเร็จ: ' . $e->getMessage()])
                    ->withInput();
            }
        }

        return redirect()->route('setup.app');
    }

    // ── Step 3: App Config ────────────────────────────────────
    public function appConfig()
    {
        return view('setup.wizard', [
            'step'       => 3,
            'totalSteps' => 6,
            'app'        => [
                'name'     => $this->env->get('APP_NAME', 'RaoMembers'),
                'url'      => $this->env->get('APP_URL', url('/')),
                'timezone' => $this->env->get('APP_TIMEZONE', 'Asia/Bangkok'),
                'debug'    => $this->env->get('APP_DEBUG', 'false'),
            ],
        ]);
    }

    public function saveAppConfig(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'url'      => 'required|url',
            'timezone' => 'required|string',
            'debug'    => 'nullable|in:true,false',
        ]);

        $this->env->set([
            'APP_NAME'     => $data['name'],
            'APP_URL'      => rtrim($data['url'], '/'),
            'APP_TIMEZONE' => $data['timezone'],
            'APP_DEBUG'    => $data['debug'] ?? 'false',
            'APP_ENV'      => ($data['debug'] ?? 'false') === 'true' ? 'local' : 'production',
        ]);

        return redirect()->route('setup.admin');
    }

    // ── Step 4: Admin Account ─────────────────────────────────
    public function admin()
    {
        return view('setup.wizard', [
            'step'       => 4,
            'totalSteps' => 6,
        ]);
    }

    public function saveAdmin(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:120',
            'email'    => 'required|email|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // เก็บไว้ใน session ก่อน (จะสร้างจริงตอน step 6)
        session(['setup_admin' => $data]);

        return redirect()->route('setup.social');
    }

    // ── Step 5: Social Login (optional) ───────────────────────
    public function social()
    {
        return view('setup.wizard', [
            'step'       => 5,
            'totalSteps' => 6,
            'social'     => [
                'line_id'     => $this->env->get('LINE_CLIENT_ID', ''),
                'line_secret' => $this->env->get('LINE_CLIENT_SECRET', ''),
                'google_id'     => $this->env->get('GOOGLE_CLIENT_ID', ''),
                'google_secret' => $this->env->get('GOOGLE_CLIENT_SECRET', ''),
            ],
        ]);
    }

    public function saveSocial(Request $request)
    {
        $data = $request->validate([
            'line_id'       => 'nullable|string',
            'line_secret'   => 'nullable|string',
            'google_id'     => 'nullable|string',
            'google_secret' => 'nullable|string',
        ]);

        $this->env->set([
            'LINE_CLIENT_ID'     => $data['line_id'] ?: null,
            'LINE_CLIENT_SECRET' => $data['line_secret'] ?: null,
            'GOOGLE_CLIENT_ID'     => $data['google_id'] ?: null,
            'GOOGLE_CLIENT_SECRET' => $data['google_secret'] ?: null,
        ]);

        return redirect()->route('setup.install');
    }

    // ── Step 6: Install & Finish ──────────────────────────────
    public function install()
    {
        return view('setup.wizard', [
            'step'       => 6,
            'totalSteps' => 6,
        ]);
    }

    /** ประมวลผลติดตั้ง (AJAX) */
    public function runInstall(Request $request)
    {
        $results = [];

        try {
            // 1. Generate APP_KEY (ถ้ายังไม่มี)
            if (empty(config('app.key'))) {
                Artisan::call('key:generate', ['--force' => true]);
                $results[] = ['ok' => true, 'msg' => 'สร้าง APP_KEY สำเร็จ'];
            } else {
                $results[] = ['ok' => true, 'msg' => 'APP_KEY มีอยู่แล้ว'];
            }

            // 2. Run migrations
            Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true]);
            $results[] = ['ok' => true, 'msg' => 'สร้างฐานข้อมูล + ข้อมูลตัวอย่าง สำเร็จ'];

            // 3. สร้าง admin จาก session
            $admin = session('setup_admin');
            if ($admin) {
                $user = User::updateOrCreate(
                    ['email' => $admin['email']],
                    [
                        'name'     => $admin['name'],
                        'password' => Hash::make($admin['password']),
                        'role'     => \App\Enums\Role::SystemAdmin,
                        'status'   => 'active',
                    ]
                );
                $results[] = ['ok' => true, 'msg' => "สร้างผู้ดูแลระบบ: {$admin['email']}"];
            }

            // 4. สร้าง storage symlink
            try {
                Artisan::call('storage:link', ['--force' => true]);
                $results[] = ['ok' => true, 'msg' => 'สร้าง storage symlink'];
            } catch (\Throwable $e) {
                $results[] = ['ok' => true, 'msg' => 'storage symlink มีอยู่แล้ว'];
            }

            // 5. สร้าง placeholder logo
            $logoPath = public_path('brand/logo.svg');
            if (! file_exists($logoPath)) {
                @mkdir(dirname($logoPath), 0755, true);
                file_put_contents($logoPath, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192"><rect width="192" height="192" rx="32" fill="#1A1A1A"/><g transform="translate(96,96)"><polygon points="0,-52 12,-16 50,-16 20,6 32,42 0,22 -32,42 -20,6 -50,-16 -12,-16" fill="#D4A84B"/><circle r="14" fill="#1A1A1A"/></g><text x="96" y="170" text-anchor="middle" font-family="Arial,sans-serif" font-weight="800" font-size="28" fill="#D4A84B" letter-spacing="4">RM</text></svg>');
                $results[] = ['ok' => true, 'msg' => 'สร้าง placeholder logo'];
            }

            // 6. ล้าง cache ทั้งหมด
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('cache:clear');
            $results[] = ['ok' => true, 'msg' => 'ล้าง cache ทั้งหมด'];

            // 7. ตั้งค่า setup_complete = true
            Setting::put('setup_complete', '1', 'system');
            $results[] = ['ok' => true, 'msg' => '✅ ติดตั้งเสร็จสมบูรณ์!'];

            return response()->json(['success' => true, 'results' => $results]);

        } catch (\Throwable $e) {
            $results[] = ['ok' => false, 'msg' => '❌ เกิดข้อผิดพลาด: ' . $e->getMessage()];
            return response()->json(['success' => false, 'results' => $results], 500);
        }
    }

    // ── Helpers ───────────────────────────────────────────────

    /** ตรวจสอบ system requirements */
    private function checkRequirements(): array
    {
        $checks = [];

        // PHP Version
        $checks[] = [
            'name'  => 'PHP Version',
            'need'  => '≥ 8.2',
            'have'  => PHP_VERSION,
            'pass'  => version_compare(PHP_VERSION, '8.2.0', '>='),
        ];

        // PHP Extensions
        $extensions = [
            'mbstring', 'xml', 'curl', 'zip', 'sqlite3', 'gd',
            'pdo', 'fileinfo', 'openssl', 'tokenizer',
        ];

        foreach ($extensions as $ext) {
            $checks[] = [
                'name'  => "ext-{$ext}",
                'need'  => 'ติดตั้ง',
                'have'  => extension_loaded($ext) ? 'มี' : 'ไม่มี',
                'pass'  => extension_loaded($ext),
            ];
        }

        // MySQL extension (optional but recommended)
        $checks[] = [
            'name'  => 'ext-pdo_mysql',
            'need'  => 'แนะนำ (สำหรับ MySQL)',
            'have'  => extension_loaded('pdo_mysql') ? 'มี' : 'ไม่มี',
            'pass'  => true, // optional
            'warn'  => ! extension_loaded('pdo_mysql'),
        ];

        // Writable directories
        $dirs = [
            'storage/'           => base_path('storage'),
            'storage/framework/' => base_path('storage/framework'),
            'storage/logs/'      => base_path('storage/logs'),
            'bootstrap/cache/'   => base_path('bootstrap/cache'),
        ];

        foreach ($dirs as $label => $path) {
            $writable = is_writable($path);
            $checks[] = [
                'name'  => "Writable: {$label}",
                'need'  => 'เขียนได้',
                'have'  => $writable ? 'เขียนได้' : 'เขียนไม่ได้',
                'pass'  => $writable,
            ];
        }

        // Composer
        $checks[] = [
            'name'  => 'vendor/autoload.php',
            'need'  => 'ติดตั้งแล้ว',
            'have'  => file_exists(base_path('vendor/autoload.php')) ? 'มี' : 'ไม่มี — ต้องติดตั้ง',
            'pass'  => file_exists(base_path('vendor/autoload.php')),
        ];

        // .env
        $checks[] = [
            'name'  => '.env file',
            'need'  => 'มีไฟล์',
            'have'  => file_exists(base_path('.env')) ? 'มี' : 'ไม่มี — จะสร้างให้',
            'pass'  => true, // will create automatically
        ];

        // APP_KEY
        $hasKey = ! empty($this->env->get('APP_KEY'));
        $checks[] = [
            'name'  => 'APP_KEY',
            'need'  => 'ตั้งค่า',
            'have'  => $hasKey ? 'ตั้งค่าแล้ว' : 'ยังไม่ได้ตั้ง — จะสร้างให้',
            'pass'  => true, // will generate automatically
        ];

        return $checks;
    }

    /** สร้าง .env ถ้ายังไม่มี */
    public function createEnv()
    {
        if (! file_exists(base_path('.env'))) {
            $this->env->createFromExample();
        }

        return redirect()->route('setup.wizard');
    }
}
