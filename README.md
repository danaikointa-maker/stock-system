# ระบบสต๊อกสินค้าหลายระดับ + QR สะสมคะแนน (Laravel)

ระบบจัดการสต๊อกสินค้า 6 ระดับสายงาน พร้อมระบบ QR สะสมแต้มและแลกของรางวัล
สำหรับร้านค้า/ธุรกิจที่ต้องการ Track & Trace สินค้าตั้งแต่คลังใหญ่ถึงมือผู้บริโภค

## ✨ ฟีเจอร์หลัก

- **สายงาน 6 ระดับ**: เจ้าของ → คลังใหญ่ → คลังย่อย → ตัวแทน → ร้านค้า → ผู้ขาย
- **POS เปิดบิลขาย** ตัดสต๊อกอัตโนมัติ ผูกเบอร์ลูกค้าสะสมแต้ม
- **QR สะสมแต้ม**: ติดสินค้า → ลูกค้าสแกน → ได้แต้ม (กันโกง 5 ชั้น)
- **QR ร้านค้า**: ติดหน้าร้าน → ลูกค้าสแกน → แลกของรางวัล
- **ใบโอนสินค้า**: draft → approve → ship → receive (จอง/ตัด/รับครบ)
- **ระบบแต้ม v3**: กระเป๋าแยกตามร้าน, วงเงินรายเดือน, FIFO
- **เบิกเงินคืน**: ร้านยื่นใบเบิก → admin อนุมัติ → จ่ายเงิน
- **ศูนย์ความปลอดภัย**: audit trail, rate limit, IP block
- **แจ้งเตือน LINE/Email**: เข้าคิว ไม่ล่มตามบริการภายนอก
- **169 automated tests** ครอบคลุมทุกกฎธุรกิจ

## 🚀 ติดตั้งเร็ว (Quick Start)

### Linux / macOS

```bash
git clone https://github.com/danaikointa-maker/stock-system.git
cd stock-system
bash install.sh
```

### Windows

```cmd
git clone https://github.com/danaikointa-maker/stock-system.git
cd stock-system
install.bat
```

สคริปต์ติดตั้งจะทำ 7 ขั้นตอนอัตโนมัติ:
1. ตรวจสอบ dependencies (PHP, Composer, Node.js)
2. ตั้งค่า .env (SQLite สำหรับ dev)
3. สร้าง storage directories
4. `composer install` — PHP dependencies
5. `php artisan key:generate`
6. `php artisan migrate --seed` — สร้างตาราง + ข้อมูลตัวอย่าง
7. Build frontend (ถ้ามี Node.js)

### รันเซิร์ฟเวอร์

```bash
php artisan serve
# เปิด http://localhost:8000/login
```

### บัญชีทดสอบ (รหัสผ่าน: `password` ทุกบัญชี)

| อีเมล | ระดับ | สิทธิ์หลัก |
|---|---|---|
| `admin@demo.test` | เจ้าของระบบ | ทุกอย่าง |
| `wh@demo.test` | คลังใหญ่ | จัดการสต๊อก โอนของ |
| `swh@demo.test` | คลังย่อย | รับของ โอนต่อ |
| `agent@demo.test` | ตัวแทนขาย | จัดการสมาชิก โอนของ |
| `shop@demo.test` | ร้านค้า | POS + ตั้งค่าร้าน + QR |
| `seller@demo.test` | ผู้ขาย | POS เท่านั้น |

## 📋 ความต้องการของระบบ

| รายการ | ขั้นต่ำ | แนะนำ |
|---|---|---|
| **PHP** | 8.2+ | 8.4 |
| **Composer** | 2.x | 2.7+ |
| **Node.js** | 18+ (ถ้า build frontend) | 20 LTS |
| **Database** | SQLite (dev) | MySQL 8 (production) |

### PHP Extensions ที่ต้องเปิด

```
mbstring, xml, curl, zip, pdo_sqlite, gd, openssl, bcmath
```

<details>
<summary>วิธีติดตั้ง dependencies (ทีละ OS)</summary>

#### Ubuntu / Debian

```bash
sudo apt update
sudo apt install -y php php-cli php-mbstring php-xml php-curl \
    php-zip php-sqlite3 php-mysql php-gd php-bcmath unzip curl

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js (optional)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

#### macOS (Homebrew)

```bash
brew install php composer node
```

#### Windows

1. **PHP**: ดาวน์โหลดจาก https://windows.php.net/download
   - Extract ไปที่ `C:\php`
   - เพิ่ม `C:\php` ใน System PATH
   - Copy `php.ini-development` → `php.ini`
   - เปิด extensions: `extension=mbstring`, `extension=curl`, `extension=openssl`, `extension=pdo_sqlite`, `extension=gd`, `extension=zip`, `extension=fileinfo`

2. **Composer**: ดาวน์โหลดจาก https://getcomposer.org/Composer-Setup.exe

3. **Node.js**: ดาวน์โหลดจาก https://nodejs.org (LTS)

</details>

## 🏗️ โครงสร้างโปรเจค

```
stock-system/
├── app/                          # Source code หลัก
│   ├── Console/Commands/         # Artisan commands (3)
│   ├── Enums/                    # Role, Status enums (5)
│   ├── Exceptions/               # Custom exceptions (3)
│   ├── Http/Controllers/         # Web (17) + API (3)
│   ├── Http/Middleware/          # Security middleware (6)
│   ├── Models/                   # Eloquent models (38)
│   ├── Observers/                # Audit observer (1)
│   ├── Policies/                 # Authorization (5)
│   ├── Providers/                # App + Auth (2)
│   └── Services/                 # Business logic (12)
├── config/                       # Laravel config (11)
├── database/
│   ├── migrations/               # DB schema (12)
│   ├── seeders/                  # Demo data (2)
│   └── factories/                # Test factories (1)
├── resources/views/              # Blade templates (45)
├── routes/                       # Web + API + Console (3)
├── tests/                        # 12 feature + 1 unit tests
├── public/                       # Web root (brand logos)
├── bootstrap/                    # App bootstrap
├── storage/                      # Framework dirs
├── install.sh                    # 🔧 Linux/macOS installer
├── install.bat                   # 🔧 Windows installer
├── composer.json + .lock         # PHP dependencies
├── package.json + .lock          # JS dependencies
├── phpunit.xml                   # Test config
├── .env.example                  # Environment template
└── README.md / INSTALL.md / PERMISSIONS.md
```

## 🧪 ทดสอบระบบ

```bash
# รันทุกเทสต์
php artisan test

# รันเฉพาะกลุ่ม
php artisan test --filter=TransferWorkflowTest
php artisan test --filter=SaleAndScanTest
php artisan test --filter=ShopSettingTest
```

**169 tests, 419 assertions** ครอบคลุม:

| ไฟล์ | ครอบคลุม |
|---|---|
| TransferWorkflowTest | วงจรใบโอน 8 เคส |
| SaleAndScanTest | ขาย + QR scan |
| PermissionTest | สิทธิ์ 17 คู่ |
| CatalogAndPointsTest | สินค้า + แต้ม |
| ClaimTest | ใบเบิก 18 เคส |
| RedeemDeskTest | เคาน์เตอร์แลกแต้ม |
| NotificationTest | แจ้งเตือน |
| ShopSettingTest | ตั้งค่าร้าน + QR ร้านค้า |
| SubscriptionTest | สมาชิกร้าน |

## 🌐 Deploy ขึ้น Production

### Linux Server

```bash
git clone https://github.com/danaikointa-maker/stock-system.git /var/www/stock
cd /var/www/stock

# ตั้ง .env สำหรับ production
cp .env.example .env
nano .env    # แก้ DB_CONNECTION=mysql, DB_HOST, DB_DATABASE, ฯลฯ

# ติดตั้งแบบ production
bash install.sh prod
```

### ตั้งค่า Web Server (Nginx)

```nginx
server {
    listen 80;
    server_name stock.example.com;
    root /var/www/stock/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### ตั้งค่า Scheduler (Cron)

```bash
# เพิ่มใน crontab -e
* * * * * cd /var/www/stock && php artisan schedule:run >> /dev/null 2>&1
```

| Command | หน้าที่ | ความถี่ |
|---|---|---|
| `roamembers:reset-allowances` | รีเซตวงเงินเดือน + ปิดสมาชิกหมดอายุ | ทุกวันที่ 1 |
| `roamembers:expire-points` | ตัดแต้มหมดอายุ | ทุกวัน 01:00 |
| `roamembers:send-notifications` | ส่งแจ้งเตือนค้าง | ทุกนาที |

### Windows Server (IIS)

1. ติดตั้ง PHP for IIS: https://php.iis.net
2. ชี้ Site ไปที่ `public/` folder
3. Import `web.config` (Laravel สร้างให้อัตโนมัติ)
4. ตั้ง Task Scheduler สำหรับ artisan commands

## 📖 เอกสารเพิ่มเติม

- [INSTALL.md](INSTALL.md) — คู่มือติดตั้งละเอียด (step-by-step)
- [PERMISSIONS.md](PERMISSIONS.md) — ตารางสิทธิ์ 6 บทบาท × ทุกหน้า

## 📄 License

Private — All rights reserved.
