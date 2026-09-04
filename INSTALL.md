# คู่มือติดตั้งระบบ (Installation Guide)

## สารบัญ

1. [ความต้องการของระบบ](#1-ความต้องการของระบบ)
2. [ติดตั้ง Dependencies](#2-ติดตั้ง-dependencies)
3. [Clone และติดตั้งโปรเจค](#3-clone-และติดตั้งโปรเจค)
4. [ตั้งค่าฐานข้อมูล](#4-ตั้งค่าฐานข้อมูล)
5. [รันเซิร์ฟเวอร์](#5-รันเซิร์ฟเวอร์)
6. [Deploy Production (Linux)](#6-deploy-production-linux)
7. [Deploy Production (Windows)](#7-deploy-production-windows)
8. [Troubleshooting](#8-troubleshooting)

---

## 1. ความต้องการของระบบ

| รายการ | ขั้นต่ำ | แนะนำ |
|---|---|---|
| **PHP** | 8.2+ | 8.4 |
| **Composer** | 2.x | 2.7+ |
| **Node.js** | 18+ (optional) | 20 LTS |
| **Database** | SQLite (dev) | MySQL 8 (prod) |
| **RAM** | 512 MB | 1 GB+ |
| **Disk** | 200 MB | 1 GB+ |

### PHP Extensions ที่ต้องเปิด

```
mbstring    — ตัวอักษรหลายภาษา
xml         — XML parsing
curl        — HTTP requests
zip         — File compression
pdo_sqlite  — SQLite driver (dev)
pdo_mysql   — MySQL driver (prod)
gd          — Image processing
openssl     — Encryption
bcmath      — Precision math (เงิน/แต้ม)
fileinfo    — File type detection
```

---

## 2. ติดตั้ง Dependencies

### Linux (Ubuntu / Debian)

```bash
# อัปเดต package
sudo apt update

# ติดตั้ง PHP + extensions
sudo apt install -y \
    php php-cli php-fpm \
    php-mbstring php-xml php-curl php-zip \
    php-sqlite3 php-mysql php-gd php-bcmath \
    unzip curl git

# ติดตั้ง Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# ติดตั้ง Node.js (optional — สำหรับ build frontend)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# ตรวจสอบ
php -v           # PHP 8.x
composer -V      # Composer 2.x
node -v          # v20.x (ถ้าติดตั้ง)
```

### macOS

```bash
# ติดตั้ง Homebrew (ถ้ายังไม่มี)
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# ติดตั้ง PHP, Composer, Node.js
brew install php composer node

# ตรวจสอบ
php -v
composer -V
node -v
```

### Windows

<details>
<summary>วิธีติดตั้งทีละขั้นตอน</summary>

#### ขั้นตอนที่ 1: ติดตั้ง PHP

1. ไปที่ https://windows.php.net/download
2. ดาวน์โหลด **VS16 x64 Thread Safe** (zip)
3. Extract ไปที่ `C:\php`
4. เพิ่ม `C:\php` ใน System PATH:
   - กด `Win + S` → ค้นหา "Environment Variables"
   - คลิก "Edit the system environment variables"
   - คลิก "Environment Variables..."
   - ภายใต้ "System variables" → เลือก `Path` → คลิก "Edit"
   - คลิก "New" → พิมพ์ `C:\php` → OK
5. ตั้งค่า `php.ini`:
   ```
   copy C:\php\php.ini-development C:\php\php.ini
   notepad C:\php\php.ini
   ```
   Uncomment บรรทัดเหล่านี้ (ลบ `;` หน้าบรรทัด):
   ```ini
   extension=curl
   extension=fileinfo
   extension=gd
   extension=mbstring
   extension=openssl
   extension=pdo_mysql
   extension=pdo_sqlite
   extension=zip
   
   ; ตั้งค่า timezone
   date.timezone = Asia/Bangkok
   
   ; เพิ่ม upload limit
   upload_max_filesize = 10M
   post_max_size = 12M
   ```

#### ขั้นตอนที่ 2: ติดตั้ง Composer

1. ดาวน์โหลดจาก https://getcomposer.org/Composer-Setup.exe
2. รันตัวติดตั้ง → เลือก PHP path → Next → Finish

#### ขั้นตอนที่ 3: ติดตั้ง Node.js (Optional)

1. ดาวน์โหลดจาก https://nodejs.org → เลือก **LTS**
2. รันตัวติดตั้ง → Next → Next → Finish

#### ขั้นตอนที่ 4: ติดตั้ง Git (ถ้ายังไม่มี)

1. ดาวน์โหลดจาก https://git-scm.com/download/win
2. รันตัวติดตั้ง → ใช้ค่า default

#### ตรวจสอบ

เปิด **Command Prompt** (CMD) แล้วพิมพ์:
```cmd
php -v
composer -V
node -v
git --version
```

</details>

---

## 3. Clone และติดตั้งโปรเจค

### วิธีที่ 1: ใช้ Install Script (แนะนำ)

**Linux / macOS:**
```bash
git clone https://github.com/danaikointa-maker/stock-system.git
cd stock-system
bash install.sh
```

**Windows:**
```cmd
git clone https://github.com/danaikointa-maker/stock-system.git
cd stock-system
install.bat
```

### วิธีที่ 2: ติดตั้งด้วยตนเอง

```bash
git clone https://github.com/danaikointa-maker/stock-system.git
cd stock-system

# ตั้งค่า .env
cp .env.example .env

# สร้าง storage directories
mkdir -p storage/framework/{views,sessions,cache,data}
mkdir -p storage/logs

# ติดตั้ง PHP dependencies
composer install

# ตั้งค่า SQLite (สำหรับ dev)
# แก้ .env:
#   DB_CONNECTION=sqlite
#   (ลบหรือ comment บรรทัด DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD)
touch database/database.sqlite

# Generate APP_KEY
php artisan key:generate

# สร้างตาราง + ข้อมูลตัวอย่าง
php artisan migrate:fresh --seed

# สร้าง symlink สำหรับไฟล์สาธารณะ
php artisan storage:link

# (Optional) Build frontend
npm install && npm run build
```

---

## 4. ตั้งค่าฐานข้อมูล

### Development (SQLite) — ค่าเริ่มต้น

ไม่ต้องตั้งค่าอะไรเพิ่ม — `install.sh` / `install.bat` จะจัดการให้

```env
DB_CONNECTION=sqlite
```

### Production (MySQL)

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=stock_system
DB_USERNAME=root
DB_PASSWORD=your_secure_password
```

สร้างฐานข้อมูล MySQL:
```sql
CREATE DATABASE stock_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

แล้วรัน:
```bash
# Linux
bash install.sh prod

# หรือรันเอง
php artisan migrate:fresh --seed
```

---

## 5. รันเซิร์ฟเวอร์

### Development Server

```bash
php artisan serve
# เปิด http://localhost:8000/login
```

### รันเทสต์

```bash
php artisan test
# ควรได้: 169 passed (419 assertions)
```

### บัญชีทดสอบ

| อีเมล | รหัสผ่าน | ระดับ |
|---|---|---|
| `admin@demo.test` | `password` | เจ้าของระบบ |
| `wh@demo.test` | `password` | คลังใหญ่ |
| `swh@demo.test` | `password` | คลังย่อย |
| `agent@demo.test` | `password` | ตัวแทนขาย |
| `shop@demo.test` | `password` | ร้านค้า (POS) |
| `seller@demo.test` | `password` | ผู้ขาย |

---

## 6. Deploy Production (Linux)

### Nginx + PHP-FPM

```bash
# ติดตั้ง Nginx + PHP-FPM
sudo apt install nginx php-fpm

# Clone โปรเจค
sudo git clone https://github.com/danaikointa-maker/stock-system.git /var/www/stock
cd /var/www/stock

# ตั้ง .env สำหรับ production
cp .env.example .env
nano .env
```

ตั้งค่า `.env`:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://stock.example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=stock_system
DB_USERNAME=stock_user
DB_PASSWORD=secure_password

CACHE_STORE=redis
SESSION_DRIVER=database
QUEUE_CONNECTION=sync

# LINE (ถ้าใช้แจ้งเตือน)
LINE_CHANNEL_ACCESS_TOKEN=your_line_token
```

```bash
# ติดตั้งแบบ production
bash install.sh prod

# ตั้ง permissions
sudo chown -R www-data:www-data /var/www/stock/storage /var/www/stock/bootstrap/cache
sudo chmod -R 775 /var/www/stock/storage /var/www/stock/bootstrap/cache
```

สร้าง Nginx config:
```bash
sudo nano /etc/nginx/sites-available/stock
```

```nginx
server {
    listen 80;
    server_name stock.example.com;
    root /var/www/stock/public;
    index index.php;

    client_max_body_size 10M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/stock /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

# ตั้ง Cron สำหรับ Scheduler
sudo crontab -e
# เพิ่มบรรทัดนี้:
# * * * * * cd /var/www/stock && php artisan schedule:run >> /dev/null 2>&1
```

### HTTPS ด้วย Let's Encrypt

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d stock.example.com
```

---

## 7. Deploy Production (Windows)

### IIS + PHP

1. **เปิด IIS**: Server Manager → Add Roles → Web Server (IIS)
2. **ติดตั้ง PHP for IIS**: https://php.iis.net
3. **สร้าง Site ใหม่**:
   - Physical path: `C:\inetpub\stock\public`
   - Default document: `index.php`
4. **URL Rewrite**:
   - ติดตั้ง IIS URL Rewrite Module: https://www.iis.net/downloads/microsoft/url-rewrite
   - สร้าง `web.config` ใน `public/`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <rewrite>
            <rules>
                <rule name="Laravel" stopProcessing="true">
                    <match url="^(.*)$" />
                    <conditions>
                        <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
                        <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
                    </conditions>
                    <action type="Rewrite" url="index.php" />
                </rule>
            </rules>
        </rewrite>
    </system.webServer>
</configuration>
```

5. **ตั้ง Task Scheduler** สำหรับ artisan commands:
   - เปิด Task Scheduler → Create Task
   - Trigger: Daily, every 1 minute
   - Action: `php C:\inetpub\stock\artisan schedule:run`

---

## 8. Troubleshooting

### ปัญหาที่พบบ่อย

| ปัญหา | สาเหตุ | วิธีแก้ |
|---|---|---|
| `Please provide a valid cache path` | storage/framework/views/ ไม่มี | `mkdir -p storage/framework/views` |
| `GD extension is not installed` | PHP-GD ไม่ได้เปิด | เปิด `extension=gd` ใน php.ini |
| `Class "Laravel\Sanctum\HasApiTokens" not found` | Sanctum ไม่ได้ติดตั้ง | `php artisan install:api` |
| ล็อกอินแล้วเด้งกลับหน้า login | Mixed content (http/https) | ตั้ง `APP_URL` ให้ตรง + trust proxy |
| `SQLSTATE[HY000]: General error: 1 no such table` | ยังไม่ได้ migrate | `php artisan migrate` |
| composer install ช้า/timeout | Network | `composer install --prefer-dist` |
| `php: command not found` | PHP ไม่อยู่ใน PATH | เพิ่ม PATH หรือใช้ full path |

### คำสั่งที่มีประโยชน์

```bash
# ล้าง cache ทั้งหมด
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# ดู route ทั้งหมด
php artisan route:list

# สร้าง storage symlink
php artisan storage:link

# รัน seeder ใหม่
php artisan db:seed --class=DemoSeeder

# ดู log
tail -f storage/logs/laravel.log
```

---

## 📞 สนับสนุน

- **เอกสารสิทธิ์**: [PERMISSIONS.md](PERMISSIONS.md)
- **GitHub Issues**: https://github.com/danaikointa-maker/stock-system/issues
