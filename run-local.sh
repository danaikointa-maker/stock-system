#!/usr/bin/env bash
# ติดตั้งและรันระบบสต๊อกสินค้าบนเครื่องตัวเอง (ใช้ SQLite ไม่ต้องตั้ง MySQL)
#
# วิธีใช้:  bash run-local.sh
# ต้องมี: php 8.2+, composer
# เสร็จแล้วเปิด http://localhost:8000

set -e
SRC="$(cd "$(dirname "$0")" && pwd)"
APP="${1:-$HOME/stock-app}"

echo "==> สร้างโปรเจกต์ Laravel ที่ $APP"
if [ ! -d "$APP" ]; then
  composer create-project laravel/laravel "$APP" --no-interaction
fi
cd "$APP"

echo "==> ติดตั้ง API support (Sanctum)"
# Laravel 11+ ไม่ได้ติดตั้ง Sanctum มาให้ แต่ระบบนี้ใช้ auth:sanctum
# ถ้าข้ามขั้นนี้ seeder จะพังด้วย Trait "Laravel\Sanctum\HasApiTokens" not found
if [ ! -d vendor/laravel/sanctum ]; then
  php artisan install:api --no-interaction >/dev/null 2>&1
fi

echo "==> คัดลอกโค้ดระบบสต๊อก"
cp -r "$SRC/app/." app/
cp -r "$SRC/resources/views/." resources/views/
cp -r "$SRC/database/migrations/." database/migrations/
cp -r "$SRC/database/seeders/." database/seeders/
cp -r "$SRC/routes/." routes/
[ -d "$SRC/tests" ] && cp -r "$SRC/tests/." tests/ || true
# ExampleTest ของ Laravel คาดว่า / คืน 200 แต่ระบบเรา redirect ไป login
rm -f tests/Feature/ExampleTest.php

echo "==> ลงทะเบียน providers"
cat > bootstrap/providers.php <<'PHP'
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
];
PHP

echo "==> ตั้งค่า middleware (trustProxies + relative URL)"
cp "$SRC/bootstrap-app.php.example" bootstrap/app.php

echo "==> ตั้งค่าฐานข้อมูล SQLite"
cp "$SRC/setup-env.php" .
php setup-env.php
rm -f setup-env.php

echo "==> สร้างตารางและใส่ข้อมูลตัวอย่าง"
php artisan key:generate --force
php artisan migrate:fresh --seed --force

echo "==> ทดสอบระบบ"
php artisan test || true

echo ""
echo "=================================================="
echo " พร้อมใช้งานแล้ว  ->  http://localhost:8000"
echo ""
echo "   ผู้ดูแลระบบ   admin@demo.test  / password"
echo "   คลังใหญ่      wh@demo.test     / password"
echo "   ร้านค้า (POS) shop@demo.test   / password"
echo ""
echo "   หน้าลูกค้าสแกน QR (ไม่ต้องล็อกอิน)  /scan"
echo "=================================================="
echo ""
php artisan serve --host=0.0.0.0 --port=8000
