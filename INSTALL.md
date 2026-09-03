# วิธีติดตั้ง (Laravel 11/12 + MySQL 8)

## 1. สร้างโปรเจกต์และคัดลอกไฟล์

```bash
composer create-project laravel/laravel stock-app
cd stock-app
composer require laravel/sanctum
php artisan install:api

S=/path/to/stock-system
cp -r $S/app/*                       app/
cp    $S/database/migrations/*.php   database/migrations/
cp    $S/database/seeders/DemoSeeder.php database/seeders/
cp    $S/routes/web.php              routes/web.php
cp    $S/routes/api.php              routes/api.php
cp -r $S/resources/views/*           resources/views/
```

> `app/Models/User.php` และ `app/Http/Controllers/Controller.php` จะทับของเดิม — ตั้งใจแล้ว

### ลงทะเบียน middleware + policy (`bootstrap/app.php`)

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'active.user' => \App\Http\Middleware\EnsureUserIsActive::class,
    ]);
    $middleware->redirectGuestsTo('/login');

    // ทำให้ลิงก์/redirect เป็น path เปล่า ๆ แทน URL เต็ม (ดูหัวข้อ 8)
    $middleware->web(prepend: [
        \App\Http\Middleware\RelativeRedirects::class,
    ]);

    // จำเป็นถ้ารันหลัง proxy/HTTPS — ไม่งั้นล็อกอินไม่ผ่าน (ดูหัวข้อ 8)
    $middleware->trustProxies(at: '*', headers:
        Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
        Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
        Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
        Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
        Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
    );
})
```

`bootstrap/providers.php`
```php
return [
    App\Providers\AppServiceProvider::class,    // <- ต้องมี (บังคับ https ให้อัตโนมัติ)
    App\Providers\AuthServiceProvider::class,   // <- เพิ่มบรรทัดนี้
];
```

> `AppServiceProvider.php` ในแพ็กเกจนี้ถูกแก้เพิ่มให้บังคับ URL เป็น https เมื่อเข้าผ่านโดเมนจริง
> **ต้องคัดลอกทับของเดิมด้วย** ไม่งั้นจะล็อกอินไม่ผ่านเมื่อ deploy หลัง proxy (ดูหัวข้อ 8)

## 2. ตั้งค่า .env

```env
DB_CONNECTION=mysql
DB_DATABASE=stock_system
DB_USERNAME=root
DB_PASSWORD=

CACHE_STORE=redis      # สำคัญ! RateLimiter ต้องใช้ cache ที่ persist
REDIS_HOST=127.0.0.1
```

`config/database.php` → ตรวจว่า mysql ใช้
```php
'charset' => 'utf8mb4',
'collation' => 'utf8mb4_unicode_ci',
```

## 3. รัน migration + demo data

```bash
php artisan migrate
php artisan db:seed --class=DemoSeeder
```

ผลลัพธ์: สายงาน 6 ระดับ + สินค้า 1 ตัว + ล็อต 1000 ชิ้น + QR 1000 ใบ
ไฟล์สั่งพิมพ์ QR: `storage/app/qr_print_L2601.csv`

```bash
php artisan serve   # เปิด http://localhost:8000/login
```

**บัญชีทดสอบ (รหัสผ่าน `password` ทุกบัญชี)**

| อีเมล | ระดับ | เห็นข้อมูลได้ |
|---|---|---|
| admin@demo.test  | เจ้าของระบบ | ทั้งหมด 6 หน่วยงาน |
| wh@demo.test     | คลังใหญ่    | 5 หน่วยงาน |
| swh@demo.test    | คลังย่อย    | 4 หน่วยงาน |
| agent@demo.test  | ตัวแทนขาย   | 3 หน่วยงาน |
| shop@demo.test   | ร้านค้า     | 2 หน่วยงาน |
| seller@demo.test | ผู้ขาย      | เฉพาะตัวเอง |

> **หมายเหตุ:** trigger ของ MySQL ใน migration `000100` จะข้ามอัตโนมัติบน driver อื่น
> เพราะตรรกะลำดับชั้นถูกย้ายไปไว้ที่ `OrgNode::booted()` แล้ว (ใช้ได้ทุก database)

## 4. ทดสอบ flow ครบวงจร

```php
// tinker
use App\Models\{OrgNode, Product, Customer, User};
use App\Services\{TransferService, SaleService, QrScanService};

$wh    = OrgNode::where('code','WH-BKK')->first();
$swh   = OrgNode::where('code','SWH-NT')->first();
$agent = OrgNode::where('code','AG-001')->first();
$shop  = OrgNode::where('code','SH-001')->first();
$p     = Product::first();
$admin = User::first();
auth()->login($admin);

$ts = app(TransferService::class);

// คลังใหญ่ -> คลังย่อย 300 ชิ้น
$t = $ts->create($wh, $swh, [['product_id'=>$p->id,'qty'=>300]]);
$ts->approve($t, $admin);  $ts->ship($t);  $ts->receive($t, $admin);

// คลังย่อย -> ตัวแทน -> ร้านค้า
$t2 = $ts->create($swh, $agent, [['product_id'=>$p->id,'qty'=>100]]);
$ts->approve($t2,$admin); $ts->ship($t2); $ts->receive($t2,$admin);

$t3 = $ts->create($agent, $shop, [['product_id'=>$p->id,'qty'=>50]]);
$ts->approve($t3,$admin); $ts->ship($t3); $ts->receive($t3,$admin);

// ร้านขาย 2 ชิ้น -> QR เปลี่ยนเป็น sold (พร้อมให้สแกน)
$sale = app(SaleService::class)->create($shop, [['product_id'=>$p->id,'qty'=>2]]);

// ลูกค้าสแกน
$qrRow = \App\Models\ProductQrcode::where('status','sold')->first();
$cust  = Customer::firstOrCreate(['phone'=>'0812345678'],['name'=>'ทดสอบ']);
// secret ดูได้จาก CSV คอลัมน์ที่ 3
app(QrScanService::class)->scan($qrRow->qr_token, $cust, 'SECRET_FROM_CSV', ['ip'=>'1.1.1.1']);
```

ตรวจสต๊อกทั้งสาย:
```php
app(\App\Services\StockService::class)->subtreeSummary($wh);
```

## 5. หน้าเว็บ (Web UI)

| URL | หน้า | สิทธิ์ที่ต้องมี |
|---|---|---|
| `/login` | เข้าสู่ระบบ (อีเมลหรือเบอร์โทร) | — |
| `/dashboard` | ภาพรวม KPI + กราฟ + งานค้าง + ปุ่มทางลัด | ทุกคน |
| `/pos` | **เปิดบิลขาย (POS)** — ตะกร้า, ส่วนลด, ผูกเบอร์ลูกค้า | `sell` + มีร้านค้า/ผู้ขายในสายงาน |
| `/pos/history` | ประวัติการขาย + ฟิลเตอร์วันที่/สถานะ | ทุกคน (เห็นเฉพาะในสายงาน) |
| `/pos/receipt/{sale}` | ใบเสร็จ (สั่งพิมพ์ได้) | เห็นบิลนั้นได้ |
| `PATCH /pos/{sale}/void` | ยกเลิกบิล + คืนสต๊อกอัตโนมัติ | `void-sale` |
| `/transfers` | **ใบโอนสินค้า** 3 แท็บ: ทั้งหมด / รอฉันอนุมัติ / รอฉันรับของ | ทุกคน |
| `/transfers/create` | สร้างใบโอนไปหน่วยงานลูก | มีหน่วยงานลูก |
| `/transfers/{id}` | รายละเอียด + stepper 4 ขั้น + ปุ่มดำเนินการ | อยู่ในสายงานของใบโอน |
| `PATCH /transfers/{id}/{approve\|reject\|ship\|receive\|cancel}` | ดำเนินการตามขั้นตอน | ดูตารางด้านล่าง |
| `/members` | รายชื่อสมาชิก + ตารางสิทธิ์ | `manage-members` |
| `/members/create` | เพิ่มสมาชิก (เลือกบทบาท) | `manage-members` |
| `/nodes` | โครงสร้างสายงานแบบต้นไม้ | ทุกคน |
| `/nodes/create` | เปิดหน่วยงานลูกใหม่ | `manage-nodes` |
| `/nodes/{id}` | รายละเอียด + สมาชิก + สต๊อก | อยู่ในสายงาน |
| `/reports/summary` | สรุปผลประกอบการ | `view-reports` |
| `/reports/stock` | สต๊อกคงเหลือ + ของใกล้หมด | `view-reports` |
| `/reports/movements` | การ์ดสินค้า | `view-reports` |
| `/reports/qr` | QR + คะแนน + จับพฤติกรรมโกง | `view-reports` |
| `/reports/export/{sales\|stock\|products}` | ส่งออก CSV (UTF-8 BOM) | `view-reports` |
| `/products` | **สินค้าและล็อต QR** — รายการสินค้า + ยอดคงเหลือรวม | `manage-products` |
| `/products/create`, `/products/{id}/edit` | เพิ่ม/แก้ไขสินค้า ราคา คะแนนต่อชิ้น | `manage-products` |
| `/products/{id}` | รายละเอียด + เปิดล็อตการผลิต + ออก QR | `manage-products` |
| `/products/{id}/lots/{lot}/qr.csv` | ดาวน์โหลดไฟล์สั่งพิมพ์ QR (มีรหัสใต้ฟิล์ม) | `manage-products` |
| `/stock/count` | **นับสต๊อกและปรับยอด** พร้อมคำนวณผลต่างสด | `adjust-stock` |
| `/customers` | **ลูกค้าและคะแนนสะสม** + ตรวจยอดคะแนนย้อนหลัง | `view-reports` |
| `/customers/{id}` | ประวัติคะแนน ปรับคะแนน ระงับลูกค้า | `view-reports` (แก้ไขต้อง `manage-members`) |
| `/customers/rewards` | **ของรางวัลและคำขอแลก** จัดส่ง/ยกเลิก | `view-reports` (แก้ไขต้อง `manage-products`) |
| `/profile` | เปลี่ยนรหัสผ่าน | ทุกคน |

### 5.2 หน้าลูกค้าปลายทาง (สาธารณะ — ไม่ต้องล็อกอิน)

QR ที่พิมพ์ลงบรรจุภัณฑ์ให้ฝังลิงก์ `https://โดเมนของคุณ/s/{token}` ลูกค้าสแกนแล้วเข้าหน้านี้ได้ทันที

| URL | หน้า |
|---|---|
| `/s/{token}` | เปิดจากการสแกน QR — เติมรหัสให้อัตโนมัติ เหลือกรอกเบอร์ + รหัสใต้ฟิล์มขูด |
| `/scan` | หน้าสแกนแบบกรอกรหัสเอง |
| `/scan/result` | ผลการสแกน (ได้กี่คะแนน / ทำไมไม่สำเร็จ) |
| `/scan/wallet` | กระเป๋าคะแนน ประวัติ และแลกของรางวัล |

ลูกค้ายืนยันตัวด้วย**เบอร์โทรอย่างเดียว** ระบบสร้างบัญชีลูกค้าให้อัตโนมัติเมื่อสแกนครั้งแรก
และจำเบอร์ไว้ในเซสชันเพื่อไม่ต้องกรอกซ้ำ

### 5.1 วงจรใบโอนสินค้า (ใครทำอะไรได้)

| ขั้น | ผู้ทำ | สถานะ | ผลต่อสต๊อก |
|---|---|---|---|
| สร้าง | ต้นทาง (`transfer-out`) | `pending_approve` | ยังไม่กระทบ |
| อนุมัติ / ปฏิเสธ | **ต้นทางเท่านั้น** | `approved` / `rejected` | อนุมัติ = **จองของ** (`qty_reserved`) |
| ส่งของ (ระบุจำนวนจริงต่อบรรทัด) | ต้นทาง | `shipped` | ตัด `qty_on_hand` ต้นทาง, ปลดจองส่วนที่ไม่ได้ส่ง, ขึ้น `qty_in_transit` ปลายทาง |
| รับของ (ระบุจำนวนที่นับได้) | **ปลายทางเท่านั้น** | `received` | รับเข้าเท่าจำนวนที่ส่ง แล้วตัดส่วนที่ขาดเป็น `damage` |
| ยกเลิก | ต้นทาง (ก่อนส่ง) | `cancelled` | ปลดจองคืนทั้งหมด |

> **สำคัญ:** ปลายทางอนุมัติใบโอนของตัวเองไม่ได้ และรับของก่อนต้นทางกดส่งไม่ได้ (403 ทั้งคู่)
> การรับของจะบันทึกขาเข้าเท่ากับจำนวนที่ต้นทางส่งเสมอ แล้วจึงตัดของหายเป็นรายการ `damage` แยก
> เพื่อให้ยอดตัดออกของต้นทางกับยอดรับเข้าของปลายทางตรงกัน และตรวจสอบของหายย้อนหลังได้

## 6. API ที่พร้อมใช้

| Method | Endpoint | หมายเหตุ |
|---|---|---|
| GET  | `/api/qr/{token}` | ดูข้อมูลก่อนยืนยัน (public) |
| POST | `/api/qr/{token}/redeem` | รับคะแนน (public, throttle 30/min) |
| GET  | `/api/stock` | สต๊อกในสายงานตัวเอง |
| GET  | `/api/stock/low` | ของใกล้หมด |
| GET  | `/api/stock/movements` | การ์ดสินค้า |
| GET  | `/api/stock/tree/{node}` | สรุปทั้ง subtree |
| POST | `/api/stock/adjust` | ปรับยอดจากการนับ |
| GET/POST | `/api/transfers` | รายการ / สร้างใบโอน |
| POST | `/api/transfers/{id}/approve\|reject\|ship\|receive` | เดินสถานะ |

## 7. สิ่งที่ควรทำต่อ (ยังไม่ได้ทำในแพ็กเกจนี้)

- **OTP** ยืนยันเบอร์ก่อน redeem (ตอนนี้ `firstOrCreate` เลย — production ต้องมี)
- **Policy** สำหรับ `Transfer`/`Sale` แทน `abort_unless` ใน controller
- **Job รายวัน**: `PointService::reconcile()` กระทบยอดคะแนน, ตรวจของหมดอายุ
- **สร้างรูป QR จริง**: `composer require simplesoftwareio/simple-qrcode` แล้ว render จาก `scanUrl()`
- **Partition** ตาราง `stock_movements` / `qr_scan_logs` รายเดือนเมื่อข้อมูลโต

## 8. เมื่อรันหลัง reverse proxy / HTTPS (สำคัญมาก)

ถ้าเปิดผ่านโดเมนที่มี proxy คั่น (nginx, Cloudflare, ngrok, sandbox preview) แล้วเจออาการ
**กดปุ่ม "เข้าสู่ระบบ" แล้วเด้งกลับหน้า login เฉย ๆ / หน้าขาว / 419 Page Expired**
สาเหตุคือ Laravel สร้าง `<form action="http://...">` ทั้งที่หน้าเว็บโหลดมาแบบ `https://`
เบราว์เซอร์จะบล็อกการ submit เพราะเป็น **mixed content** — และบล็อกแบบเงียบ ไม่มี error ให้เห็น

แพ็กเกจนี้แก้ให้แล้ว 2 ชั้น (คัดลอกไปพร้อมโค้ดได้เลย)

**ชั้นที่ 1 — `app/Providers/AppServiceProvider.php`** บังคับ scheme เป็น https อัตโนมัติ
เมื่อเข้าผ่านโดเมนจริง (ไม่ใช่ `localhost` / IP) ทำให้ใช้ได้แม้ proxy ไม่ส่ง `X-Forwarded-Proto`
ต้องลงทะเบียนใน `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
];
```

**ชั้นที่ 2 — `bootstrap/app.php`** เชื่อ header จาก proxy (กรณี proxy ส่งมาถูกต้อง):

```php
use Illuminate\Http\Request;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'active.user' => \App\Http\Middleware\EnsureUserIsActive::class,
    ]);
    $middleware->redirectGuestsTo('/login');

    $middleware->trustProxies(at: '*', headers:
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB
    );
})
```

ตอน deploy จริงให้ตั้ง `APP_URL` ให้ตรงกับโดเมนที่ผู้ใช้เข้า แล้ว `php artisan config:clear`

```env
APP_URL=https://stock.example.com
```

| สถานการณ์ | ผลลัพธ์ |
|---|---|
| เข้า `http://localhost:8000` ตอน dev | URL เป็น `http://` ตามปกติ ไม่พัง |
| เข้าผ่านโดเมน https + proxy ส่ง `X-Forwarded-Proto` | URL เป็น `https://` |
| เข้าผ่านโดเมน https + proxy **ไม่ส่ง** header | URL เป็น `https://` (ชั้นที่ 1 ช่วยไว้) |

**ชั้นที่ 3 — `app/Http/Middleware/RelativeRedirects.php`**

ถ้า proxy บังคับ token ต่อ request (sandbox preview, tunnel บางเจ้า) แล้วเจอ error

```
Missing Traffic Access Token
Token header "e2b-traffic-access-token" is missing.
```

สาเหตุคือ `route()` ของ Laravel สร้าง URL เต็ม เช่น `https://your-domain/dashboard`
พอ redirect หรือกด submit ฟอร์ม เบราว์เซอร์จะถือว่าเป็นการเปิดหน้าใหม่จากศูนย์
บริบทของ proxy (token ที่ผูกกับ session ปัจจุบัน) เลยหลุดหาย

middleware ตัวนี้จะแปลง URL ที่ชี้กลับมาโดเมนตัวเองให้เหลือแค่ path
ทั้งใน `Location` header และใน `href` / `action` / `src` ของหน้า HTML

```
เดิม  action="https://your-domain/login"   ->  ใหม่  action="/login"
เดิม  Location: https://your-domain/dashboard  ->  ใหม่  Location: /dashboard
```

เบราว์เซอร์จะประกอบ URL เองจากหน้าปัจจุบัน ทำให้ token ยังติดไปด้วย
URL ที่ชี้ออกไปโดเมนอื่นจะไม่ถูกแตะต้อง

> ต้องลงทะเบียนแบบ `prepend` ใน `bootstrap/app.php` (ไม่ใช่ `append`)
> เพราะ redirect ของระบบ auth ถูกสร้างก่อน middleware ชั้นท้าย ๆ จะทำงาน

**ชั้นที่ 4 — คุกกี้ SameSite (กรณีเว็บถูกฝังใน iframe)**

อาการ: **ล็อกอินผ่าน แต่ไม่เด้งไปหน้า dashboard — วนกลับมาหน้า login เหมือนเดิม**

เกิดเมื่อหน้าเว็บถูกเปิดอยู่ใน `<iframe>` ของโดเมนอื่น (พาเนล preview,
ระบบภายในที่ฝังเว็บเราไว้) เบราว์เซอร์ถือว่าเป็น third-party context
คุกกี้ที่เป็น `SameSite=Lax` (ค่า default ของ Laravel) จะ **ไม่ถูกส่ง** ไปกับ request
พอ redirect ไป `/dashboard` session จึงหายไป ระบบเลยเด้งกลับหน้า login

`AppServiceProvider` จัดการให้แล้ว โดยตั้งค่าเฉพาะตอนเสิร์ฟผ่าน https:

```php
config([
    'session.same_site' => 'none',
    'session.secure'    => true,   // SameSite=None บังคับว่าต้องมี Secure
]);
```

| บริบท | คุกกี้ | ผล |
|---|---|---|
| `http://localhost` ตอน dev | `SameSite=Lax` | ปกติ ไม่พัง |
| https เปิดตรง ๆ | `SameSite=None; Secure` | ล็อกอินได้ |
| https ฝังใน iframe โดเมนอื่น | `SameSite=None; Secure` | ล็อกอินได้ |

> ถ้า deploy แบบเปิดตรง ๆ ไม่ได้ฝัง iframe จะตั้งเป็น `lax` ก็ได้ (ปลอดภัยกว่าเล็กน้อย)
> แต่ `none` + `secure` ใช้งานได้ทั้งสองแบบ

**ชั้นที่ 5 — `app/Http/Middleware/PartitionedCookies.php` (CHIPS)**

Chrome รุ่นใหม่บล็อกคุกกี้ third-party ใน iframe **ต่อให้ตั้ง `SameSite=None; Secure` แล้วก็ตาม**
ต้องเติมแอตทริบิวต์ `Partitioned` ตามมาตรฐาน CHIPS ด้วย
เบราว์เซอร์จะเก็บคุกกี้แยกกระปุกตามเว็บแม่ที่ฝัง แต่ยังส่งกลับมาให้เราตามปกติ

```
Set-Cookie: laravel-session=...; secure; httponly; samesite=none; partitioned
```

ลงทะเบียนแบบ `prepend` ใน `bootstrap/app.php`:

```php
$middleware->web(prepend: [
    \App\Http\Middleware\PartitionedCookies::class,
]);
```

> ต้องเป็น `prepend` เท่านั้น เพราะคุกกี้ session ถูกแนบตอน "ขากลับ" โดย middleware ชั้นใน
> ถ้าใช้ `append` middleware จะทำงานก่อนคุกกี้ถูกสร้าง ทำให้เติม `Partitioned` ไม่ทัน
> middleware ตัวนี้ทำงานเฉพาะตอน https ดังนั้น dev บน localhost ไม่ได้รับผลกระทบ

> บนโปรดักชันควรระบุ IP ของ proxy แทน `'*'` ใน `trustProxies` เพื่อความปลอดภัย
> ถ้าต้องการบังคับ https เองแบบตายตัว ตั้ง `FORCE_HTTPS=true` ใน `.env` ได้

## 9. การทดสอบอัตโนมัติ

ระบบมีชุดทดสอบ 47 เคส ครอบคลุมกฎธุรกิจที่พังแล้วเสียหายเป็นเงิน — รันก่อน deploy ทุกครั้ง

```bash
php artisan test
```

ชุดทดสอบใช้ SQLite ในหน่วยความจำ ไม่แตะฐานข้อมูลจริง แบ่งเป็น 4 ไฟล์

| ไฟล์ | ครอบคลุม |
|---|---|
| `tests/Feature/TransferWorkflowTest.php` | วงจรใบโอนครบ 8 เคส — จองของ, ส่งบางส่วน, **รับของขาดต้องไม่หักซ้ำ**, ยกเลิกปลดจอง, โอนข้ามระดับ, สมุดเคลื่อนไหวตรงกับยอดคงเหลือ |
| `tests/Feature/SaleAndScanTest.php` | ขายตัดสต๊อก, ยกเลิกบิลคืนสต๊อก, ขายเกินสต๊อกต้อง rollback, สแกน QR ได้ครั้งเดียว, ลูกค้าถูกระงับสแกนไม่ได้ |
| `tests/Feature/PermissionTest.php` | เมทริกซ์สิทธิ์ 17 คู่ (หน้า × บทบาท) + ปลายทางอนุมัติเองไม่ได้ + รับก่อนส่งไม่ได้ + ขายนอกสายงานไม่ได้ |
| `tests/Feature/CatalogAndPointsTest.php` | ออก QR เกินจำนวนผลิตไม่ได้, เลขล็อตซ้ำไม่ได้, นับสต๊อกปรับยอด, **ช่องนับว่างต้องไม่ตีความเป็น 0**, แลก/ยกเลิกของรางวัลคืนคะแนน, ยอดคะแนนตรงกับประวัติ |

> เคสที่พิมพ์ตัวหนาคือบั๊กที่เคยเกิดจริงในระบบนี้และแก้ไปแล้ว — เทสต์เหล่านี้กันไม่ให้กลับมาอีก

## 10. ระบบแต้ม v3 และความปลอดภัย (RoaMembers)

### Migration ใหม่

| ไฟล์ | สร้างอะไร |
|---|---|
| `2026_02_01_000100_create_points_v3_tables.php` | แพ็กเกจ · สมาชิกร้าน · วงเงินรายเดือน · กระเป๋าแต้ม · การแลก · ใบเบิกเงิน · หน้าร้าน · LINE/Google |
| `2026_02_01_000200_create_security_tables.php` | security_events · audit_trails · login_attempts · error_logs · admin_alerts · blocked_entities · security_rules |

Migration ตัวแรกสร้าง **trigger กันยอดติดลบ 6 ตัว** ให้อัตโนมัติบน MySQL
(SQLite ข้ามไป เพราะไม่รองรับ SIGNAL)

### ด่านป้องกัน Over-pay

การแลกแต้มต้องผ่านทั้ง 2 ด้าน ไม่งั้น rollback ทั้งรายการ

1. แต้มของลูกค้าในกระเป๋าร้านผู้ออกต้องพอ
2. วงเงินรายเดือนของร้านที่รับแลกต้องพอ

มี 3 ชั้นซ้อนกัน: ตรวจก่อนทำ -> ล็อกแถวแล้วตรวจซ้ำ (กัน race condition) -> trigger ที่ฐานข้อมูล

### ความปลอดภัยที่เพิ่มเข้ามา

- `SecurityService` บันทึกเหตุการณ์ + ตรวจจับพฤติกรรมผิดปกติ + ระงับ IP อัตโนมัติ
- `BlockBannedEntities` ปฏิเสธ IP/บัญชีที่ถูกระงับตั้งแต่ด่านแรก
- `SecurityHeaders` ใส่ security headers ทุก response
- `AuditRequest` เฝ้าดู 403 / 429 / การดาวน์โหลดข้อมูลจำนวนมาก
- `AuditObserver` บันทึกค่าเดิม -> ค่าใหม่ ทุกการแก้ไขข้อมูลสำคัญ
- ข้อมูลอ่อนไหว (รหัสผ่าน โทเคน) ถูกกรองออกก่อนบันทึก log เสมอ

### รันเทสต์

```
php artisan test
```

ได้ **57 passed** (ต้องใช้ MySQL เพราะทดสอบ trigger ด้วย)

## 11. หน้าสแกนสำหรับลูกค้า (RoaMembers)

### หน้าจอที่ใช้งานได้แล้ว

| URL | หน้า |
|---|---|
| `/s/{token}` | เปิดจาก QR บนซองสินค้า — แสดงชื่อสินค้าและแต้มที่จะได้ |
| `/scan` | หน้ากรอกเบอร์เพื่อรับแต้ม |
| `/scan/result` | ผลลัพธ์หลังสแกน (มีคอนเฟตตีเมื่อสำเร็จ) |
| `/scan/wallet` | กระเป๋าแต้ม แยกตามร้าน + ประวัติ + บัญชีฉัน |
| `/scan/statement` | ประวัติแบบพิมพ์/บันทึกเป็น PDF ได้ |
| `/auth/line/redirect` | เข้าสู่ระบบด้วย LINE |
| `/auth/google/redirect` | เข้าสู่ระบบด้วย Google |

### สิ่งที่ต้องตั้งค่าเพิ่ม

1. คัดลอกบล็อกใน `config-services-addition.php.example`
   ไปต่อท้าย `config/services.php`
2. ใส่ค่า LINE/Google ใน `.env`
3. คัดลอกโฟลเดอร์ `public/brand/` ไปยัง `public/` ของโปรเจกต์ (โลโก้)

### เรื่อง GPS ที่ต้องเข้าใจ

เบราว์เซอร์ **ไม่สามารถ** ดึงตำแหน่งแบบเบื้องหลังได้ ทั้ง iOS และ Android
บังคับให้ขึ้น popup ขออนุญาตเสมอ ระบบจึงออกแบบให้ทำงานได้แม้ผู้ใช้ปฏิเสธ
โดยบันทึกว่า `denied` แล้วให้แต้มตามปกติ — ไม่งั้นจะเสียลูกค้าจำนวนมาก

เมื่อได้ตำแหน่งมา ระบบจะตรวจ 2 อย่าง

- `far_from_shop` สแกนห่างจากร้านที่ใกล้สุดเกิน 5 กม.
- `impossible_travel` เบอร์เดียวสแกนสองที่ไกลกันเกินกว่าจะเดินทางทัน

ทั้งสองกรณีบันทึกลง `security_events` ให้แอดมินตรวจสอบ

### รันเทสต์

```
php artisan test
```

ได้ **69 passed** (ต้องใช้ MySQL เพราะทดสอบ trigger)

## 12. เคาน์เตอร์รับแลกแต้ม (ร้านค้า)

### หน้าจอ

| URL | หน้า | ต้องมีสิทธิ์ |
|---|---|---|
| `/redeem` | เคาน์เตอร์รับแลกแต้ม | `accept-redeem` |
| `/redeem/history` | ประวัติการรับแลก | `accept-redeem` |
| `/redeem/receipt/{id}` | ใบเสร็จ (พิมพ์ได้) | `accept-redeem` |

### ขั้นตอนใช้งานหน้าร้าน

1. พนักงานค้นหาลูกค้าด้วยเบอร์โทร
2. เลือกกระเป๋าแต้ม (ลูกค้าอาจมีแต้มจากหลายร้าน)
3. เลือกประเภท: สินค้า / บริการ / ส่วนลด / เงินสด
4. ถ้าเป็นสินค้า ต้องระบุจำนวนที่จ่ายออก ระบบตัดสต๊อกและบันทึกเลขล็อต
5. ยืนยัน แล้วพิมพ์ใบเสร็จให้ลูกค้า

### สิทธิ์ที่เพิ่มเข้ามา

| ability | ใครได้ |
|---|---|
| `accept-redeem` | เจ้าของระบบ, ร้านค้า, ผู้ขาย |
| `manage-shop` | เจ้าของระบบ, ร้านค้า |
| `claim-money` | เจ้าของระบบ, ร้านค้า |
| `manage-subscriptions` | เจ้าของระบบ, คลัง, ตัวแทน |
| `manage-packages` / `approve-claim` / `view-security` | เจ้าของระบบเท่านั้น |

ผู้ขาย (ระดับ 6) ใช้วงเงินของร้านแม่ เพราะสมาชิกผูกไว้ที่ระดับร้าน

### การป้องกันที่ทดสอบแล้ว

- แลกเกินแต้มลูกค้าไม่ได้
- แลกเกินวงเงินรายเดือนของร้านไม่ได้
- ใช้กระเป๋าแต้มของลูกค้าคนอื่นไม่ได้ (บันทึกเป็น security_events ระดับ high)
- ร้านที่ยังไม่สมัคร/สมาชิกหมดอายุ รับแลกไม่ได้
- ดูใบเสร็จของร้านอื่นไม่ได้ (404)
- ทุกกรณีที่ถูกปฏิเสธ rollback ทั้งหมด ไม่มีการหักบางส่วน

### หน้าตาระบบ

เปลี่ยนธีมหลังบ้านเป็นสีแบรนด์ RoaMembers แล้ว
(ส้ม #F04800 / เขียว #006018) พร้อมโลโก้ในแถบข้าง

### รันเทสต์

```
php artisan test
```

ได้ **81 passed** ผ่านทั้งบน SQLite และ MySQL

## 13. ตั้งค่าหน้าร้าน และหน้าร้านสาธารณะ

### หน้าจอ

| URL | หน้า | สิทธิ์ |
|---|---|---|
| `/shop/settings` | ตั้งค่าหน้าร้าน | `manage-shop` |
| `/shop/preview` | ดูตัวอย่างก่อนเผยแพร่ | `manage-shop` |
| `/r/{slug}` | หน้าร้านสาธารณะ | ไม่ต้องล็อกอิน |

หมายเหตุ: ใช้ prefix `/r/` แยกจาก `/shop/*`
ถ้าใช้ `/shop/{slug}` route จะไปดักคำว่า settings/preview/rewards ทำให้หน้าตั้งค่า 404

### สิ่งที่เจ้าของร้านทำได้เอง

- อัปโหลดโลโก้และรูปปก
- ตั้งชื่อร้าน คำโปรย รายละเอียด เบอร์โทร LINE ที่อยู่ พิกัด
- เลือกเทมเพลต 6 แบบ (กาแฟ / อาหาร / ล้างรถ / เสริมสวย / ร้านยา / ค้าปลีก)
- กำหนดสีเองได้ถ้าไม่อยากใช้สีของเทมเพลต
- เปิด/ปิดบล็อกบนหน้าร้าน (รายการแลกแต้ม, แกลเลอรี, เวลาทำการ, แผนที่, ติดต่อ, จองคิว)
- เพิ่ม/ปิด/ลบ รายการของรางวัลที่รับแลก
- ตั้งสถานะ ร่าง / เผยแพร่

### ความปลอดภัยของการอัปโหลด (ทดสอบแล้ว)

- ตรวจชนิดไฟล์ด้วย `mimes` (ดูเนื้อไฟล์จริง ไม่ใช่แค่นามสกุล)
- จำกัดขนาด 3 MB และมิติภาพไม่เกิน 4000x4000
- **ตั้งชื่อไฟล์ใหม่ด้วยค่าสุ่มเสมอ** ไม่ใช้ชื่อจากผู้ใช้
  (กัน path traversal และไฟล์สคริปต์)
- เก็บใน `storage/app/public` ไม่ใช่ `public` โดยตรง
- ทดสอบอัปโหลดไฟล์ PHP ที่ตั้งชื่อเป็น .png แล้ว ถูกปฏิเสธจริง

### การกันข้ามร้าน

แก้หรือลบของรางวัลของร้านอื่นไม่ได้ (คืน 404)
และบันทึกเป็น `security_events` ระดับ high ให้แอดมินตรวจ

### slug ไม่เปลี่ยนตามชื่อร้าน

สร้างครั้งเดียวตอนบันทึกครั้งแรก ถ้าร้านเปลี่ยนชื่อภายหลัง
ลิงก์เดิมที่ลูกค้าบันทึกไว้จะยังใช้ได้

### ต้องรันก่อนใช้งานจริง

```
php artisan storage:link
```

เพื่อให้รูปที่อัปโหลดแสดงบนเว็บได้

### รันเทสต์

```
php artisan test
```

ได้ **97 passed** ผ่านทั้งบน SQLite และ MySQL

## 14. เบิกเงินคืน (ร้านค้า) และอนุมัติจ่าย (เจ้าของระบบ)

### หน้าจอ

| URL | หน้า | สิทธิ์ |
|---|---|---|
| `/claims` | รายการใบเบิกของร้าน + งวดที่เบิกได้ | `claim-money` |
| `/claims/{id}` | รายละเอียดใบเบิก | `claim-money` |
| `/admin/claims` | ใบเบิกทั้งระบบ | `approve-claim` |
| `/admin/claims/{id}` | ตรวจสอบ อนุมัติ จ่ายเงิน | `approve-claim` |

### วงจรใบเบิก

```
draft      ร้านสร้างจากรายการที่ยังไม่เคยเบิก (ยกเลิกได้)
submitted  ร้านยื่น -> แจ้งเตือนเข้า admin_alerts อัตโนมัติ
approved   เจ้าของระบบอนุมัติ
paid       บันทึกวิธีจ่าย + เลขอ้างอิง
rejected   ปฏิเสธพร้อมเหตุผล -> รายการถูกปลดให้ยื่นใหม่ได้
```

### กฎที่บังคับไว้ (ทดสอบครบแล้ว)

- **เบิกได้เฉพาะงวดที่ผ่านมาแล้ว** งวดปัจจุบันยอดยังเปลี่ยนได้
- **1 ร้าน 1 งวด 1 ใบ** บังคับด้วย unique index
- **รายการที่ผูกใบเบิกแล้วจะไม่ถูกดึงมาเบิกซ้ำ** (`claim_id`)
- ยอดต่ำกว่าขั้นต่ำ (`claim_min_points` = 400) เบิกไม่ได้
- ร้านอนุมัติใบเบิกตัวเองไม่ได้ (ไม่มีสิทธิ์ `approve-claim`)
- จ่ายเงินโดยยังไม่อนุมัติไม่ได้
- ร้านอื่นเปิดดูใบเบิกของเราไม่ได้ (404 + บันทึก `security_events`)
- ปฏิเสธต้องระบุเหตุผลเสมอ

### หมายเหตุการออกแบบ

ไม่มีตารางยอดสะสมแยก — "ยอดรอเบิก" คำนวณจาก `point_redemptions`
ที่ `claim_id` เป็น null โดยตรง วิธีนี้ยอดสรุปไม่มีทางเพี้ยนจากยอดจริง

### รันเทสต์

```
php artisan test
```

ได้ **115 passed** ผ่านทั้งบน SQLite และ MySQL

## 15. หน้าแอดมิน: สมาชิกร้าน แพ็กเกจ และศูนย์ความปลอดภัย

### หน้าจอ

| URL | หน้า | สิทธิ์ |
|---|---|---|
| `/subscriptions` | รายการสมาชิกร้านในสายงาน | `manage-subscriptions` |
| `/subscriptions/create` | สมัครร้านใหม่ (ตัวแทนกรอก) | `manage-subscriptions` |
| `/subscriptions/{id}` | รายละเอียด · ยืนยันชำระ · ต่ออายุ · ยกเลิก | `manage-subscriptions` |
| `/admin/packages` | แพ็กเกจ ค่าแต้ม และค่าตั้งค่าระบบ | `manage-packages` |
| `/admin/security` | ศูนย์ความปลอดภัย (5 แท็บ) | `view-security` |

### หน้าสมัครสมาชิก

ตัวแทนแค่เลือกร้านและแพ็กเกจ ระบบคำนวณให้ทันทีบนหน้าจอ
วันหมดอายุ · วงเงินรับแลก · คอมมิชชั่นตัวแทน · ยอดที่ร้านต้องชำระ

- แพ็กเกจฟรี (ราคา 0) เปิดวงเงินให้ทันที
- แพ็กเกจมีราคา ต้องยืนยันการชำระก่อนจึงเปิดวงเงิน
- ร้านหนึ่งมีสมาชิกใช้งานพร้อมกันได้ใบเดียว
- ตัวแทนเห็นเฉพาะร้านในสายงานตัวเอง สมัครให้ร้านนอกสายไม่ได้ (403 + บันทึก log)
- ค่าถูกล็อกตอนสมัคร แอดมินแก้แพ็กเกจภายหลังไม่กระทบสัญญาเดิม

### ศูนย์ความปลอดภัย

| แท็บ | เนื้อหา |
|---|---|
| ภาพรวม | แจ้งเตือนค้าง · เหตุการณ์ร้ายแรง · รายการที่ถูกระงับ |
| เหตุการณ์ | กรองตามชนิด/ความรุนแรง/IP · ทำเครื่องหมายว่าตรวจแล้ว |
| ประวัติแก้ไขข้อมูล | ใครแก้อะไร ค่าเดิม -> ค่าใหม่ |
| การเข้าสู่ระบบ | เตือน IP ที่ล็อกอินพลาดเกิน 5 ครั้งใน 24 ชม. |
| ข้อผิดพลาด | error ของระบบที่ยังไม่แก้ |

ระงับ IP/บัญชีด้วยตนเองได้ ทั้งชั่วคราวและถาวร

### การเปลี่ยนค่าแต้ม

กระทบเงินทั้งระบบ จึงบังคับว่า

- ต้องระบุเหตุผลทุกครั้ง
- บันทึกลง `point_value_history` (ค่าเดิม -> ค่าใหม่ ใครแก้ เมื่อไร)
- บันทึกเป็น `security_events` ระดับ high
- แก้ผ่านฟอร์มตั้งค่าทั่วไปไม่ได้ ต้องใช้ฟอร์มเฉพาะเท่านั้น
- ไม่ย้อนหลังกับใบเบิกที่ออกไปแล้ว เพราะใบเบิกล็อกอัตราไว้

### คำสั่งที่ต้องตั้ง scheduler

```php
// routes/console.php หรือ bootstrap/app.php
$schedule->command('roamembers:reset-allowances')->monthlyOn(1, '00:05');
$schedule->command('roamembers:expire-points')->dailyAt('01:00');
```

| คำสั่ง | หน้าที่ |
|---|---|
| `roamembers:reset-allowances` | เปิดวงเงินเดือนใหม่ + ปิดสมาชิกหมดอายุ (รันซ้ำได้ ไม่สร้างซ้ำ) |
| `roamembers:expire-points` | ตัดแต้มลูกค้าที่หมดอายุ |

### รันเทสต์

```
php artisan test
```

ได้ **147 passed** ผ่านทั้งบน SQLite และ MySQL

## 16. ระบบแจ้งเตือน LINE และอีเมล

### หลักการสำคัญ

การแจ้งเตือน **ต้องไม่ทำให้ธุรกรรมหลักพัง**
ทุกจุดจึงแค่ "เข้าคิว" ไม่ส่งทันที และครอบ try/catch ไว้เสมอ

ทดสอบแล้ว: จำลอง LINE ตอบ 500 → ลูกค้ายังแลกแต้มได้ ยอดถูกต้อง

### หน้าจอ

| URL | หน้า | สิทธิ์ |
|---|---|---|
| `/profile/notify` | ผูก LINE และดูประวัติแจ้งเตือน | ทุกบทบาท |

### จำนวนไอดีที่ผูกได้

| ประเภท | LINE | เบอร์โทร |
|---|---|---|
| ผู้ซื้อ (ลูกค้า) | 1 ไอดี : 1 บัญชี | 1 : 1 |
| ผู้ใช้ระบบ | หลายไอดี (ค่าเริ่มต้น 5 ปรับรายคนได้ที่ `users.max_social_links`) | 1 : 1 |

### เหตุการณ์ที่แจ้งเตือน

| เหตุการณ์ | แจ้งใคร | เนื้อหา |
|---|---|---|
| สแกนได้แต้ม | ลูกค้า | สินค้า · แต้มที่ได้ · แต้มสะสม · วันหมดอายุ |
| แลกแต้มสำเร็จ | ลูกค้า + ร้าน | รายการ · ร้าน · แต้มที่ใช้ · แต้มคงเหลือ · รหัสอ้างอิง |
| ใบเบิกอนุมัติ/จ่าย/ปฏิเสธ | ร้าน | สถานะ · จำนวนเงิน · เลขอ้างอิง |
| วงเงินใกล้หมด | ร้าน | คงเหลือกี่แต้ม กี่ % |
| เหตุการณ์ความปลอดภัยร้ายแรง | เจ้าของระบบ | รายละเอียดเหตุการณ์ |

ลูกค้าที่มีแค่เบอร์โทร (ยังไม่ผูก LINE) **ยังไม่ส่งอะไร**
ตามที่ตกลงไว้ รอจนกว่าจะผูก SMS gateway

### คิวและการลองใหม่

- ส่งไม่สำเร็จจะลองใหม่อัตโนมัติ สูงสุด 3 ครั้ง
- ครบแล้วเปลี่ยนเป็น `failed` พร้อมบันทึกสาเหตุ
- ล็อกแถวก่อนส่ง กันตัวส่งสองตัวหยิบรายการเดียวกัน (ส่งซ้ำ)

### ต้องตั้งค่าเพิ่ม

```env
LINE_CHANNEL_ACCESS_TOKEN=...
```

เป็นคนละตัวกับ `LINE_CLIENT_ID` ที่ใช้ล็อกอิน
ตัวนี้มาจาก **Messaging API** ใช้ส่งข้อความ

ถ้ายังไม่ตั้ง ระบบยังทำงานได้ปกติ แค่คิวจะขึ้นสถานะ `failed`
พร้อมข้อความบอกว่ายังไม่ได้ตั้ง token

### เพิ่ม scheduler

```php
$schedule->command('roamembers:send-notifications')
    ->everyMinute()->withoutOverlapping();
```

`withoutOverlapping()` สำคัญมาก กันสองรอบทำงานทับกันจนส่งข้อความซ้ำ

### รันเทสต์

```
php artisan test
```

ได้ **161 passed** ผ่านทั้งบน SQLite และ MySQL
