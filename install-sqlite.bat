@echo off
setlocal EnableDelayedExpansion

REM ============================================================
REM  Multi-Level Stock System
REM  Simple install - uses SQLite, no MySQL needed
REM
REM  Usage: double-click this file
REM  Requires: PHP 8.2+ (Composer is downloaded automatically)
REM
REM  RESUMABLE: if the install stops halfway (closed window, power cut,
REM  network error), just run this file again. It remembers which steps
REM  finished and continues from there instead of starting over.
REM
REM  All messages are in English so old CMD fonts can show them.
REM ============================================================

title Stock System - Install

set "SRC=%~dp0"
set "SRC=%SRC:~0,-1%"
set "APP=%USERPROFILE%\stock-app"
set "STATE=%SRC%\install-state-sqlite.cmd"

REM ------------------------------------------------------------
REM  Load previous progress, if any
REM ------------------------------------------------------------
set "DONE_STEP=0"
if exist "%STATE%" call "%STATE%"

echo.
echo ============================================================
echo   MULTI-LEVEL STOCK SYSTEM - Simple install (SQLite)
echo ============================================================
echo.
echo   Source folder : %SRC%
echo   Install to    : %APP%
echo.

if !DONE_STEP! GTR 0 (
    echo   A previous installation was found, stopped after step !DONE_STEP! of 8.
    echo.
    echo   [R] Resume  - continue and keep existing data
    echo   [S] Start over - redo everything ^(ERASES all existing data^)
    echo   [Q] Quit
    echo.
    set "CHOICE="
    set /p CHOICE=  Choose [R] :
    if /i "!CHOICE!"=="Q" goto :quit
    if /i "!CHOICE!"=="S" (
        echo.
        echo   Starting over from the beginning.
        set "DONE_STEP=0"
        del "%STATE%" >nul 2>&1
    ) else (
        echo.
        echo   Resuming from step !DONE_STEP!.
    )
    echo.
)

REM ---------- 1. PHP (always runs - it is fast) ----------
echo [1/8] Checking PHP...
where php >nul 2>&1
if errorlevel 1 (
    echo.
    echo   [ERROR] PHP not found on this computer.
    echo.
    echo   How to install:
    echo     1. Download PHP 8.2 or newer from https://windows.php.net/download/
    echo        Choose the "Thread Safe" zip file.
    echo     2. Extract it to  C:\php
    echo     3. Add  C:\php  to your system PATH
    echo        (search for "Environment Variables" in the Start menu)
    echo     4. Open a NEW Command Prompt and type  php -v  to check.
    echo.
    goto :error
)
for /f "delims=" %%v in ('php -r "echo PHP_VERSION;" 2^>nul') do set "PHPVER=%%v"
echo       Found PHP !PHPVER!

php -r "exit(version_compare(PHP_VERSION,'8.2.0','>=') ? 0 : 1);" >nul 2>&1
if errorlevel 1 (
    echo.
    echo   [ERROR] PHP is too old ^(!PHPVER!^). Version 8.2 or newer is required.
    echo.
    goto :error
)

REM ---------- 2. extensions (always runs) ----------
echo [2/8] Checking PHP extensions...
set "MISSING="
for %%e in (pdo_sqlite mbstring openssl fileinfo curl) do (
    php -r "exit(extension_loaded('%%e') ? 0 : 1);" >nul 2>&1
    if errorlevel 1 set "MISSING=!MISSING! %%e"
)
if not "!MISSING!"=="" (
    echo.
    echo   [ERROR] Missing PHP extension:!MISSING!
    echo.
    for /f "delims=" %%i in ('php -r "echo php_ini_loaded_file();" 2^>nul') do set "INIFILE=%%i"
    echo   Edit this file:  !INIFILE!
    echo   ^(if it does not exist, copy php.ini-development to php.ini^)
    echo.
    echo   Remove the  ;  in front of the  extension=  line for each missing item.
    echo   Example:   ;extension=pdo_sqlite    becomes    extension=pdo_sqlite
    echo.
    echo   Save the file and run this installer again.
    echo.
    goto :error
)
echo       All required extensions are present.

REM ---------- 3. Composer (always runs) ----------
echo [3/8] Checking Composer...
set "COMPOSER_CMD="
where composer >nul 2>&1
if not errorlevel 1 (
    set "COMPOSER_CMD=composer"
) else (
    if exist "%SRC%\composer.phar" (
        set "COMPOSER_CMD=php "%SRC%\composer.phar""
    ) else (
        echo       Composer not found - downloading it now...
        php -r "copy('https://getcomposer.org/composer-stable.phar','%SRC%\composer.phar');" >nul 2>&1
        if not exist "%SRC%\composer.phar" (
            echo.
            echo   [ERROR] Could not download Composer ^(no internet?^).
            echo   Install it manually: https://getcomposer.org/Composer-Setup.exe
            echo.
            goto :error
        )
        set "COMPOSER_CMD=php "%SRC%\composer.phar""
    )
)
echo       Ready.

REM ---------- 4. Laravel ----------
if !DONE_STEP! GEQ 4 (
    echo [4/8] Laravel project .... already created
    goto :step5
)

echo [4/8] Preparing Laravel project...
if exist "%APP%\artisan" (
    echo       Existing project found - reusing it.
) else (
    echo       Downloading Laravel ^(this takes 2-5 minutes^)...
    %COMPOSER_CMD% create-project laravel/laravel "%APP%" --no-interaction --quiet
    if not exist "%APP%\artisan" (
        echo.
        echo   [ERROR] Could not create the Laravel project.
        echo   Check your internet connection, then run this file again
        echo   and choose [R] to resume.
        echo.
        goto :error
    )
)
call :save 4

:step5
REM ---------- 5. Sanctum + copy code ----------
if !DONE_STEP! GEQ 5 (
    echo [5/8] Application code ... already copied
    goto :step6
)

echo [5/8] Installing API support and copying code...

REM Laravel 11+ does not ship Sanctum by default, but this system uses
REM "auth:sanctum" for its API routes and HasApiTokens on the User model.
REM Without this step the seeder fails with:
REM   Trait "Laravel\Sanctum\HasApiTokens" not found
cd /d "%APP%"
if not exist "%APP%\vendor\laravel\sanctum" (
    echo       Installing Laravel Sanctum...
    php artisan install:api --no-interaction >nul 2>&1
    if not exist "%APP%\vendor\laravel\sanctum" (
        echo.
        echo   [ERROR] Could not install Laravel Sanctum.
        echo   Run this inside %APP% to see the details:
        echo       php artisan install:api
        echo.
        goto :error
    )
)

xcopy "%SRC%\app"                    "%APP%\app\"                    /E /I /Y /Q >nul
xcopy "%SRC%\resources\views"        "%APP%\resources\views\"        /E /I /Y /Q >nul
xcopy "%SRC%\database\migrations"    "%APP%\database\migrations\"    /E /I /Y /Q >nul
xcopy "%SRC%\database\seeders"       "%APP%\database\seeders\"       /E /I /Y /Q >nul
xcopy "%SRC%\routes"                 "%APP%\routes\"                 /E /I /Y /Q >nul
if exist "%SRC%\tests" xcopy "%SRC%\tests" "%APP%\tests\" /E /I /Y /Q >nul
copy /Y "%SRC%\bootstrap-app.php.example" "%APP%\bootstrap\app.php" >nul

REM Laravel ships an ExampleTest that expects "/" to return 200,
REM but this system redirects guests to /login (302). Remove it.
if exist "%APP%\tests\Feature\ExampleTest.php" del "%APP%\tests\Feature\ExampleTest.php" >nul 2>&1

echo       Done.
call :save 5

:step6
REM ---------- 6. providers ----------
if !DONE_STEP! GEQ 6 (
    echo [6/8] Providers .......... already registered
    goto :step7
)

echo [6/8] Registering providers...
> "%APP%\bootstrap\providers.php" (
    echo ^<?php
    echo.
    echo return [
    echo     App\Providers\AppServiceProvider::class,
    echo     App\Providers\AuthServiceProvider::class,
    echo ];
)
echo       Done.
call :save 6

:step7
REM ---------- 7. .env ----------
if !DONE_STEP! GEQ 7 (
    echo [7/8] Configuration ...... already written
    goto :step8
)

echo [7/8] Configuring SQLite database...
cd /d "%APP%"
copy /Y "%SRC%\setup-env.php" "%APP%\setup-env.php" >nul
set "ST_LANG=en"
php setup-env.php
if errorlevel 1 (
    del "%APP%\setup-env.php" >nul 2>&1
    echo.
    echo   [ERROR] Could not write the .env file.
    echo.
    goto :error
)
del "%APP%\setup-env.php" >nul 2>&1

REM Only generate a key if there is not one already, so that resuming
REM does not invalidate data encrypted with the previous key.
findstr /b /c:"APP_KEY=base64:" ".env" >nul 2>&1
if errorlevel 1 (
    php artisan key:generate --force --quiet
) else (
    echo       Application key already set - keeping it.
)

copy /Y "%SRC%\patch-phpunit.php" "%APP%\patch-phpunit.php" >nul
set "ST_LANG=en"
php patch-phpunit.php
del "%APP%\patch-phpunit.php" >nul 2>&1
echo       Done.
call :save 7

:step8
REM ---------- 8. database ----------
if !DONE_STEP! GEQ 8 (
    echo [8/8] Tables + demo data . already created - your data is kept
    goto :finish
)

echo [8/8] Creating tables and demo data...
cd /d "%APP%"
set "ST_LANG=en"
php artisan migrate:fresh --seed --force
if errorlevel 1 (
    echo.
    echo   [ERROR] Could not create the database.
    echo   Run this inside %APP% to see the details:
    echo       php artisan migrate:fresh --seed
    echo.
    echo   Then run this file again and choose [R] to resume.
    echo.
    goto :error
)
echo       Done.
call :save 8

:finish
REM Installation finished - remove the progress file
del "%STATE%" >nul 2>&1

cls
echo.
echo ============================================================
echo    INSTALLATION COMPLETE
echo ============================================================
echo.
echo    OPEN THIS ADDRESS      http://localhost:8000
echo.
echo    LOGIN ACCOUNTS   password for every account is:  password
echo.
echo      admin@demo.test     System owner - full access
echo      wh@demo.test        Main warehouse
echo      swh@demo.test       Sub warehouse
echo      agent@demo.test     Sales agent
echo      shop@demo.test      Shop - point of sale
echo      seller@demo.test    Seller
echo.
echo    Customer QR scan page ^(no login needed^)
echo      http://localhost:8000/scan
echo.
echo    Installed in  %APP%
echo.
echo ============================================================
echo.
echo    Opening your browser...
echo    Press Ctrl+C or close this window to stop the server.
echo.

cd /d "%APP%"
start "" http://localhost:8000
php artisan serve --host=0.0.0.0 --port=8000

goto :end

REM ============================================================
REM  Save progress so the next run can continue from here
REM ============================================================
:save
set "DONE_STEP=%~1"
> "%STATE%" (
    echo REM Progress file for install-sqlite.bat
    echo REM Delete this file to force a clean install.
    echo set "DONE_STEP=!DONE_STEP!"
)
goto :eof

:quit
echo.
echo   Cancelled. Your progress is kept - run this file again to resume.
echo.
pause
exit /b 0

:error
echo ============================================================
echo   INSTALLATION STOPPED
echo ============================================================
echo.
echo   Your progress was saved ^(finished steps: !DONE_STEP! of 8^).
echo   Fix the problem above, then run this file again
echo   and choose [R] to continue from where it stopped.
echo.
pause
exit /b 1

:end
echo.
echo Server stopped.
pause
