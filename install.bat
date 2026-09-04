@echo off
chcp 65001 >nul 2>&1
setlocal EnableDelayedExpansion

REM ============================================================
REM  RoaMembers Stock System — Windows Installer
REM ============================================================
REM  วิธีใช้:  ดับเบิลคลิก หรือเปิด CMD แล้วพิมพ์  install.bat
REM
REM  ความต้องการ:
REM    - PHP 8.2+ (https://windows.php.net/download)
REM    - Composer  (https://getcomposer.org)
REM    - Node.js 18+ (https://nodejs.org) — ถ้าต้องการ build frontend
REM
REM  ถ้ายังไม่มี ให้ดาวน์โหลดมาติดตั้งก่อน แล้วรันสคริปต์นี้อีกครั้ง
REM ============================================================

echo.
echo ====================================================
echo   RoaMembers Stock System - Windows Installer
echo ====================================================
echo.

REM --- 1. ตรวจ PHP ---
echo [1/7] Checking PHP...
where php >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo.
    echo   [X] Not found: PHP
    echo.
    echo   Please install PHP 8.2+ from:
    echo     https://windows.php.net/download
    echo.
    echo   After install, add PHP to PATH:
    echo     1. Search "Environment Variables" in Start Menu
    echo     2. Edit System PATH
    echo     3. Add: C:\php
    echo.
    echo   Then run install.bat again.
    echo.
    pause
    exit /b 1
)
for /f "tokens=*" %%v in ('php -r "echo PHP_MAJOR_VERSION.'.'PHP_MINOR_VERSION;"') do set PHPVER=%%v
echo       PHP %PHPVER% [OK]

REM ตรวจ extensions
for %%e in (mbstring xml curl zip pdo_sqlite gd openssl) do (
    php -m 2>nul | findstr /i "^%%e$" >nul
    if !ERRORLEVEL! equ 0 (
        echo       ext-%%e [OK]
    ) else (
        echo       ext-%%e [MISSING] - Enable in php.ini: extension=%%e
    )
)

REM --- 2. ตรวจ Composer ---
echo.
echo [2/7] Checking Composer...
where composer >nul 2>&1
if %ERRORLEVEL% neq 0 (
    echo.
    echo   [X] Not found: Composer
    echo.
    echo   Download from: https://getcomposer.org/Composer-Setup.exe
    echo   Run the installer, then run install.bat again.
    echo.
    pause
    exit /b 1
)
echo       Composer [OK]

REM --- 3. ตรวจ Node.js (optional) ---
set HAS_NODE=0
where node >nul 2>&1
if %ERRORLEVEL% equ 0 (
    set HAS_NODE=1
    for /f "tokens=*" %%v in ('node -v') do set NODEVER=%%v
    echo [3/7] Node.js !NODEVER! [OK]
) else (
    echo [3/7] Node.js [SKIP] - Not required for API-only mode
)

REM --- 4. ตั้งค่า .env ---
echo.
echo [4/7] Setting up .env...
if not exist .env (
    copy .env.example .env >nul
    echo       Created .env from .env.example
) else (
    echo       .env already exists [SKIP]
)

REM ใช้ SQLite สำหรับ development
REM Note: sed ไม่ได้ใน Windows, ใช้ PHP แทน
php -r "$e=file_get_contents('.env');$e=preg_replace('/^DB_CONNECTION=.*$/m','DB_CONNECTION=sqlite',$e);$e=preg_replace('/^DB_(HOST|PORT|DATABASE|USERNAME|PASSWORD)=.*$/m','',$e);if(!preg_match('/^APP_URL=/m',$e))$e.=PHP_EOL.'APP_URL=http://localhost:8000';file_put_contents('.env',rtrim($e).PHP_EOL);"
if not exist database mkdir database
if not exist database\database.sqlite type nul > database\database.sqlite
echo       SQLite configured

REM --- 5. สร้าง storage directories ---
echo.
echo [5/7] Creating storage directories...
if not exist storage\framework\views mkdir storage\framework\views
if not exist storage\framework\sessions mkdir storage\framework\sessions
if not exist storage\framework\cache mkdir storage\framework\cache
if not exist storage\framework\data mkdir storage\framework\data
if not exist storage\logs mkdir storage\logs
if not exist storage\app\public mkdir storage\app\public
echo       Done

REM --- 6. ติดตั้ง PHP dependencies ---
echo.
echo [6/7] Installing PHP dependencies (Composer)...
composer install --no-interaction 2>&1
if %ERRORLEVEL% neq 0 (
    echo.
    echo   [X] Composer install failed
    echo   Try: composer install --no-interaction
    echo.
    pause
    exit /b 1
)
echo       Composer install [OK]

REM --- 7. ตั้งค่า Laravel ---
echo.
echo [7/7] Setting up Laravel...
php artisan key:generate --force 2>&1
php artisan config:clear >nul 2>&1
php artisan route:clear >nul 2>&1
php artisan view:clear >nul 2>&1

echo       Running migrations...
php artisan migrate:fresh --seed --force 2>&1

REM storage:link
php artisan storage:link --force >nul 2>&1

REM --- Build frontend (optional) ---
if %HAS_NODE% equ 1 (
    echo.
    echo       Building frontend assets...
    call npm install --silent 2>nul
    call npm run build 2>nul
    echo       Frontend build [OK]
)

REM --- สรุป ---
echo.
echo ====================================================
echo   Installation Complete!
echo ====================================================
echo.
echo   Start server:
echo     php artisan serve
echo.
echo   Then open: http://localhost:8000/login
echo.
echo   Run tests:
echo     php artisan test
echo.
echo   Test accounts (password: password):
echo     admin@demo.test   - System Admin
echo     wh@demo.test      - Main Warehouse
echo     swh@demo.test     - Sub Warehouse
echo     agent@demo.test   - Sales Agent
echo     shop@demo.test    - Shop Owner (POS + Settings)
echo     seller@demo.test  - Seller
echo.
echo   Important URLs:
echo     http://localhost:8000/login   - Login
echo     http://localhost:8000/scan    - QR Scan (no login)
echo.
echo ====================================================
echo.
pause
