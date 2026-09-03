@echo off

REM ============================================================
REM  Start the server again (use this AFTER installing)
REM
REM  This does not reinstall anything and does not erase data.
REM  It only starts the server for the project you installed.
REM
REM  Only needed for the SQLite install (install-sqlite.bat).
REM  If you installed into XAMPP/Laragon, just start Apache instead.
REM ============================================================

title Stock System

set "APP=%USERPROFILE%\stock-app"

if not exist "%APP%\artisan" (
    echo.
    echo   [ERROR] The system is not installed yet.
    echo.
    echo   Please run  install-sqlite.bat  first.
    echo.
    pause
    exit /b 1
)

cd /d "%APP%"

echo.
echo ============================================================
echo    MULTI-LEVEL STOCK SYSTEM
echo ============================================================
echo.
echo    OPEN THIS ADDRESS      http://localhost:8000
echo.
echo    LOGIN   password for every account is:  password
echo      admin@demo.test     System owner
echo      shop@demo.test      Shop - point of sale
echo.
echo    Customer QR scan page   http://localhost:8000/scan
echo.
echo    Press Ctrl+C to stop the server.
echo.
echo ============================================================
echo.

start "" http://localhost:8000
php artisan serve --host=0.0.0.0 --port=8000

pause
