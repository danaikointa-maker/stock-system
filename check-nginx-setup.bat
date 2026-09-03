@echo off
setlocal EnableDelayedExpansion

REM ============================================================
REM  Diagnose the Nginx / Laravel setup
REM
REM  Run this if you get 404 on http://localhost/stock-system/login
REM  It checks the folder layout, the .env file and PHP,
REM  then prints the exact alias path you should use.
REM ============================================================

title Stock System - Check setup

set "BASE=C:\app\project\stock-system"

echo.
echo ============================================================
echo   STOCK SYSTEM - SETUP CHECK
echo ============================================================
echo.
echo   Looking under: %BASE%
echo.

REM ------------------------------------------------------------
REM  1. Where is the Laravel root?
REM ------------------------------------------------------------
set "APPROOT="

if exist "%BASE%\artisan" (
    set "APPROOT=%BASE%"
    echo   [OK] Laravel root found at the top level.
) else (
    if exist "%BASE%\stock-system\artisan" (
        set "APPROOT=%BASE%\stock-system"
        echo   [!!] Laravel is nested one level deeper.
    )
)

if not defined APPROOT (
    echo   [ERROR] Could not find Laravel ^(no artisan file^).
    echo.
    echo   Checked:
    echo     %BASE%\artisan
    echo     %BASE%\stock-system\artisan
    echo.
    echo   Open %BASE% and look for the folder that contains
    echo   "artisan" and a "public" folder inside it.
    echo.
    pause
    exit /b 1
)

echo        Laravel root : !APPROOT!
echo.

REM ------------------------------------------------------------
REM  2. Does public\index.php exist?
REM ------------------------------------------------------------
if exist "!APPROOT!\public\index.php" (
    echo   [OK] Found  !APPROOT!\public\index.php
) else (
    echo   [ERROR] Missing  !APPROOT!\public\index.php
    echo           The installation is incomplete.
    echo.
    pause
    exit /b 1
)

REM ------------------------------------------------------------
REM  3. vendor + sanctum installed?
REM ------------------------------------------------------------
if exist "!APPROOT!\vendor\autoload.php" (
    echo   [OK] Dependencies installed ^(vendor folder^)
) else (
    echo   [ERROR] vendor folder missing - run the installer again.
    echo.
    pause
    exit /b 1
)

if exist "!APPROOT!\vendor\laravel\sanctum" (
    echo   [OK] Laravel Sanctum installed
) else (
    echo   [!!] Sanctum missing. Run inside !APPROOT! :
    echo        php artisan install:api
)

REM ------------------------------------------------------------
REM  4. .env present and APP_URL correct?
REM ------------------------------------------------------------
if exist "!APPROOT!\.env" (
    echo   [OK] .env file exists
    findstr /b /c:"APP_URL=" "!APPROOT!\.env"
    REM With an nginx alias, /stock-system/ already points at public/,
    REM so the correct APP_URL has NO /public on the end.
    findstr /b /c:"APP_URL=http://localhost/stock-system" "!APPROOT!\.env" >nul 2>&1
    if errorlevel 1 (
        echo   [!!] APP_URL should be:
        echo            APP_URL=http://localhost/stock-system
        echo        Fix it, then run:  php artisan config:clear
    ) else (
        echo   [OK] APP_URL looks correct
    )
) else (
    echo   [ERROR] .env missing - run the installer again.
)

REM ------------------------------------------------------------
REM  5. Storage writable
REM ------------------------------------------------------------
echo test > "!APPROOT!\storage\logs\_writetest.tmp" 2>nul
if exist "!APPROOT!\storage\logs\_writetest.tmp" (
    echo   [OK] storage folder is writable
    del "!APPROOT!\storage\logs\_writetest.tmp" >nul 2>&1
) else (
    echo   [!!] storage\logs is NOT writable - Laravel will show a blank page.
)

REM ------------------------------------------------------------
REM  6. Print the exact nginx alias path
REM ------------------------------------------------------------
set "NGPATH=!APPROOT:\=/!/public"

echo.
echo ============================================================
echo    USE THIS PATH IN YOUR NGINX CONFIG
echo ============================================================
echo.
echo    !NGPATH!
echo.
echo    File to edit:
echo      C:\app\etc\nginx\alias\stock-system.conf
echo.
echo    It must appear in 4 places:
echo      alias "!NGPATH!/";
echo      SCRIPT_FILENAME  "!NGPATH!/index.php"    ^(in @stock_fallback^)
echo      DOCUMENT_ROOT    "!NGPATH!"              ^(twice^)
echo.
echo    After editing, reload Nginx in Laragon.
echo.
echo ============================================================
echo.
pause
