@echo off
setlocal EnableDelayedExpansion

REM ============================================================
REM  Multi-Level Stock System
REM  Install into an existing web server (XAMPP / Laragon / WAMP)
REM
REM  Uses your Apache + MySQL. Does NOT use "php artisan serve".
REM
REM  RESUMABLE: if the install stops halfway (closed window, power cut,
REM  network error), just run this file again. It remembers which steps
REM  finished and continues from there instead of starting over.
REM
REM  All messages are in English so old CMD fonts can show them.
REM ============================================================

title Stock System - Install on Web Server

set "SRC=%~dp0"
set "SRC=%SRC:~0,-1%"
set "STATE=%SRC%\install-state.cmd"

REM ------------------------------------------------------------
REM  Load previous progress, if any
REM ------------------------------------------------------------
set "DONE_STEP=0"
set "WEBROOT="
set "FOLDER="
set "DBHOST="
set "DBPORT="
set "DBNAME="
set "DBUSER="

if exist "%STATE%" call "%STATE%"

echo.
echo ============================================================
echo   MULTI-LEVEL STOCK SYSTEM
echo   Install into existing web server
echo ============================================================
echo.

if !DONE_STEP! GTR 0 (
    echo   A previous installation was found, stopped after step !DONE_STEP! of 9.
    echo.
    echo     Project folder : !WEBROOT!\!FOLDER!
    echo     Database       : !DBNAME!
    echo.
    echo   [R] Resume  - continue from step and keep existing data
    echo   [S] Start over - redo everything ^(ERASES all data in !DBNAME!^)
    echo   [Q] Quit
    echo.
    set "CHOICE="
    set /p CHOICE=  Choose [R] :
    if /i "!CHOICE!"=="Q" goto :quit
    if /i "!CHOICE!"=="S" (
        echo.
        echo   Starting over from the beginning.
        set "DONE_STEP=0"
        set "WEBROOT="
        set "FOLDER="
        set "DBHOST="
        set "DBPORT="
        set "DBNAME="
        set "DBUSER="
        del "%STATE%" >nul 2>&1
    ) else (
        echo.
        echo   Resuming from step !DONE_STEP!.
    )
    echo.
)

REM ============================================================
REM  1. Find web root
REM ============================================================
if !DONE_STEP! GEQ 1 (
    echo [1/9] Web folder ......... already set: !WEBROOT!\!FOLDER!
    goto :step2
)

echo [1/9] Looking for a web server...

set "SERVERNAME="
set "MYSQLBIN="

REM --- XAMPP ---
for %%d in (C D E F) do (
    if exist "%%d:\xampp\htdocs" (
        if not defined WEBROOT (
            set "WEBROOT=%%d:\xampp\htdocs"
            set "SERVERNAME=XAMPP"
            if exist "%%d:\xampp\mysql\bin\mysql.exe" set "MYSQLBIN=%%d:\xampp\mysql\bin"
        )
    )
)

REM --- Laragon ---
if not defined WEBROOT (
    for %%d in (C D E F) do (
        if exist "%%d:\laragon\www" (
            if not defined WEBROOT (
                set "WEBROOT=%%d:\laragon\www"
                set "SERVERNAME=Laragon"
                for /d %%m in ("%%d:\laragon\bin\mysql\*") do (
                    if exist "%%m\bin\mysql.exe" set "MYSQLBIN=%%m\bin"
                )
            )
        )
    )
)

REM --- WAMP ---
if not defined WEBROOT (
    for %%d in (C D E F) do (
        if exist "%%d:\wamp64\www" (
            if not defined WEBROOT (
                set "WEBROOT=%%d:\wamp64\www"
                set "SERVERNAME=WAMP64"
                for /d %%m in ("%%d:\wamp64\bin\mysql\*") do (
                    if exist "%%m\bin\mysql.exe" set "MYSQLBIN=%%m\bin"
                )
            )
        )
        if exist "%%d:\wamp\www" (
            if not defined WEBROOT (
                set "WEBROOT=%%d:\wamp\www"
                set "SERVERNAME=WAMP"
                for /d %%m in ("%%d:\wamp\bin\mysql\*") do (
                    if exist "%%m\bin\mysql.exe" set "MYSQLBIN=%%m\bin"
                )
            )
        )
    )
)

if defined WEBROOT (
    echo       Found !SERVERNAME!  ^>  !WEBROOT!
) else (
    echo       Not found automatically.
)

echo.
echo   IMPORTANT: this must be your web server folder, so Apache can see it.
echo   Example:  C:\xampp\htdocs   or   C:\laragon\www
echo.
echo   Press Enter to accept the value in brackets, or type a new one.
echo.
set "IN="
set /p IN=  Web folder [!WEBROOT!] :
if not "!IN!"=="" set "WEBROOT=!IN!"

if not defined WEBROOT (
    echo.
    echo   [ERROR] You must provide a web folder.
    goto :error
)
if not exist "!WEBROOT!" (
    echo.
    echo   [ERROR] Folder not found:  !WEBROOT!
    goto :error
)

set "FOLDER=stock-app"
set "IN="
set /p IN=  Project folder name [!FOLDER!] :
if not "!IN!"=="" set "FOLDER=!IN!"

call :save 1

:step2
set "APP=!WEBROOT!\!FOLDER!"

REM ============================================================
REM  2. Check PHP  (always runs - it is fast and sets PHPCMD)
REM ============================================================
echo.
echo [2/9] Checking PHP...

set "PHPCMD=php"
where php >nul 2>&1
if errorlevel 1 (
    for %%d in (C D E F) do (
        if exist "%%d:\xampp\php\php.exe" if "!PHPCMD!"=="php" set "PHPCMD=%%d:\xampp\php\php.exe"
    )
    if "!PHPCMD!"=="php" (
        for %%d in (C D E F) do (
            for /d %%p in ("%%d:\laragon\bin\php\*") do (
                if exist "%%p\php.exe" if "!PHPCMD!"=="php" set "PHPCMD=%%p\php.exe"
            )
        )
    )
    if "!PHPCMD!"=="php" (
        echo.
        echo   [ERROR] PHP not found.
        echo.
        echo   If you use XAMPP:   add  C:\xampp\php  to your system PATH.
        echo   If you use Laragon: open Laragon, click Terminal,
        echo                       then run this file from that terminal.
        goto :error
    )
)
for /f "delims=" %%v in ('"!PHPCMD!" -r "echo PHP_VERSION;" 2^>nul') do set "PHPVER=%%v"
echo       Found PHP !PHPVER!

"!PHPCMD!" -r "exit(version_compare(PHP_VERSION,'8.2.0','>=') ? 0 : 1);" >nul 2>&1
if errorlevel 1 (
    echo.
    echo   [ERROR] PHP is too old ^(!PHPVER!^). Version 8.2 or newer is required.
    echo.
    echo   XAMPP:   download a newer version from https://www.apachefriends.org/
    echo   Laragon: switch version from the menu  PHP ^> Version
    goto :error
)

set "MISSING="
for %%e in (pdo_mysql mbstring openssl fileinfo curl) do (
    "!PHPCMD!" -r "exit(extension_loaded('%%e') ? 0 : 1);" >nul 2>&1
    if errorlevel 1 set "MISSING=!MISSING! %%e"
)
if not "!MISSING!"=="" (
    echo.
    echo   [ERROR] Missing PHP extension:!MISSING!
    echo.
    for /f "delims=" %%i in ('"!PHPCMD!" -r "echo php_ini_loaded_file();" 2^>nul') do set "INIFILE=%%i"
    echo   Edit this file:  !INIFILE!
    echo   Remove the  ;  in front of the  extension=  line for each missing item,
    echo   then restart Apache and run this installer again.
    goto :error
)
echo       All required extensions are present.

REM ============================================================
REM  3. Check Composer  (always runs - it is fast)
REM ============================================================
echo [3/9] Checking Composer...
set "COMPOSER_CMD="
where composer >nul 2>&1
if not errorlevel 1 (
    set "COMPOSER_CMD=composer"
) else (
    if exist "%SRC%\composer.phar" (
        set "COMPOSER_CMD="!PHPCMD!" "%SRC%\composer.phar""
    ) else (
        echo       Composer not found - downloading it now...
        "!PHPCMD!" -r "copy('https://getcomposer.org/composer-stable.phar','%SRC%\composer.phar');" >nul 2>&1
        if not exist "%SRC%\composer.phar" (
            echo.
            echo   [ERROR] Could not download Composer.
            echo   Install it manually: https://getcomposer.org/Composer-Setup.exe
            goto :error
        )
        set "COMPOSER_CMD="!PHPCMD!" "%SRC%\composer.phar""
    )
)
echo       Ready.

REM ============================================================
REM  4. Database settings
REM ============================================================
set "DBPASS="

if !DONE_STEP! GEQ 7 (
    echo [4/9] Database settings .. already saved: !DBNAME! on !DBHOST!:!DBPORT!
    goto :step5
)

if !DONE_STEP! GEQ 4 (
    echo.
    echo [4/9] Database settings - using saved values
    echo         host !DBHOST!   port !DBPORT!   database !DBNAME!   user !DBUSER!
    echo.
    echo   The password is never saved to disk, so please type it again.
    echo   ^(XAMPP/Laragon usually has none - just press Enter^)
    set /p DBPASS=  Password :
    goto :step5
)

echo.
echo [4/9] MySQL database settings
echo.
echo   Press Enter to accept the default value.
echo.

set "DBHOST=127.0.0.1"
set "IN="
set /p IN=  MySQL host [!DBHOST!] :
if not "!IN!"=="" set "DBHOST=!IN!"

set "DBPORT=3306"
set "IN="
set /p IN=  MySQL port [!DBPORT!] :
if not "!IN!"=="" set "DBPORT=!IN!"

set "DBNAME=stock_system"
set "IN="
set /p IN=  Database name [!DBNAME!] :
if not "!IN!"=="" set "DBNAME=!IN!"

set "DBUSER=root"
set "IN="
set /p IN=  Username [!DBUSER!] :
if not "!IN!"=="" set "DBUSER=!IN!"

echo.
echo   MySQL password ^(XAMPP/Laragon usually has none - just press Enter^)
set /p DBPASS=  Password :

call :save 4

:step5
REM ============================================================
REM  5. Create Laravel project
REM ============================================================
if !DONE_STEP! GEQ 5 (
    echo [5/9] Laravel project .... already created
    goto :step6
)

echo.
echo [5/9] Preparing Laravel project...
if exist "!APP!\artisan" (
    echo       Existing project found - reusing it.
) else (
    echo       Downloading Laravel ^(this takes 2-5 minutes^)...
    !COMPOSER_CMD! create-project laravel/laravel "!APP!" --no-interaction --quiet
    if not exist "!APP!\artisan" (
        echo.
        echo   [ERROR] Could not create the Laravel project.
        echo   Check your internet connection, then run this file again
        echo   and choose [R] to resume.
        goto :error
    )
)
call :save 5

:step6
REM ============================================================
REM  6. Install Sanctum and copy application code
REM ============================================================
if !DONE_STEP! GEQ 6 (
    echo [6/9] Application code ... already copied
    goto :step7
)

echo [6/9] Installing API support and copying code...

REM Laravel 11+ does not ship Sanctum by default, but this system uses
REM "auth:sanctum" for its API routes and HasApiTokens on the User model.
REM Without this step the seeder fails with:
REM   Trait "Laravel\Sanctum\HasApiTokens" not found
cd /d "!APP!"
if not exist "!APP!\vendor\laravel\sanctum" (
    echo       Installing Laravel Sanctum...
    "!PHPCMD!" artisan install:api --no-interaction >nul 2>&1
    if not exist "!APP!\vendor\laravel\sanctum" (
        echo.
        echo   [ERROR] Could not install Laravel Sanctum.
        echo   Run this inside !APP! to see the details:
        echo       php artisan install:api
        goto :error
    )
)

xcopy "%SRC%\app"                 "!APP!\app\"                 /E /I /Y /Q >nul
xcopy "%SRC%\resources\views"     "!APP!\resources\views\"     /E /I /Y /Q >nul
xcopy "%SRC%\database\migrations" "!APP!\database\migrations\" /E /I /Y /Q >nul
xcopy "%SRC%\database\seeders"    "!APP!\database\seeders\"    /E /I /Y /Q >nul
xcopy "%SRC%\routes"              "!APP!\routes\"              /E /I /Y /Q >nul
if exist "%SRC%\tests" xcopy "%SRC%\tests" "!APP!\tests\" /E /I /Y /Q >nul
copy /Y "%SRC%\bootstrap-app.php.example" "!APP!\bootstrap\app.php" >nul

REM Laravel ships an ExampleTest that expects "/" to return 200,
REM but this system redirects guests to /login (302). Remove it.
if exist "!APP!\tests\Feature\ExampleTest.php" del "!APP!\tests\Feature\ExampleTest.php" >nul 2>&1

> "!APP!\bootstrap\providers.php" (
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
REM ============================================================
REM  7. Create database and configure .env
REM ============================================================
set "APPURL=http://localhost/!FOLDER!/public"

if !DONE_STEP! GEQ 7 (
    echo [7/9] Database + config .. already configured
    goto :step8
)

echo [7/9] Creating database and writing configuration...

cd /d "!APP!"

set "DBCREATED="
if defined MYSQLBIN (
    if "!DBPASS!"=="" (
        "!MYSQLBIN!\mysql.exe" -h !DBHOST! -P !DBPORT! -u !DBUSER! -e "CREATE DATABASE IF NOT EXISTS `!DBNAME!` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >nul 2>&1
    ) else (
        "!MYSQLBIN!\mysql.exe" -h !DBHOST! -P !DBPORT! -u !DBUSER! -p!DBPASS! -e "CREATE DATABASE IF NOT EXISTS `!DBNAME!` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >nul 2>&1
    )
    if not errorlevel 1 set "DBCREATED=1"
)

if not defined DBCREATED (
    copy /Y "%SRC%\create-db.php" "!APP!\create-db.php" >nul
    set "ST_DB_HOST=!DBHOST!"
    set "ST_DB_PORT=!DBPORT!"
    set "ST_DB_NAME=!DBNAME!"
    set "ST_DB_USER=!DBUSER!"
    set "ST_DB_PASS=!DBPASS!"
    set "ST_LANG=en"
    "!PHPCMD!" create-db.php
    if errorlevel 1 (
        del "!APP!\create-db.php" >nul 2>&1
        echo.
        echo   [ERROR] Could not connect to MySQL.
        echo.
        echo   Please check:
        echo     - Is MySQL started in the control panel?
        echo     - Are the username and password correct?
        echo     - Laragon sometimes uses port 3307 instead of 3306.
        echo.
        echo   Fix it, then run this file again and choose [R] to resume.
        goto :error
    )
    del "!APP!\create-db.php" >nul 2>&1
)
echo       Database !DBNAME! is ready.

copy /Y "%SRC%\setup-env-mysql.php" "!APP!\setup-env-mysql.php" >nul
set "ST_DB_HOST=!DBHOST!"
set "ST_DB_PORT=!DBPORT!"
set "ST_DB_NAME=!DBNAME!"
set "ST_DB_USER=!DBUSER!"
set "ST_DB_PASS=!DBPASS!"
set "ST_APP_URL=!APPURL!"
set "ST_LANG=en"
"!PHPCMD!" setup-env-mysql.php
if errorlevel 1 (
    del "!APP!\setup-env-mysql.php" >nul 2>&1
    echo.
    echo   [ERROR] Could not write the .env file.
    goto :error
)
del "!APP!\setup-env-mysql.php" >nul 2>&1

REM Only generate a key if there is not one already, so that resuming
REM does not invalidate data that was encrypted with the previous key.
findstr /b /c:"APP_KEY=base64:" ".env" >nul 2>&1
if errorlevel 1 (
    "!PHPCMD!" artisan key:generate --force --quiet
) else (
    echo       Application key already set - keeping it.
)

REM Fix phpunit base URL, otherwise tests request /!FOLDER!/public/... and 404
copy /Y "%SRC%\patch-phpunit.php" "!APP!\patch-phpunit.php" >nul
set "ST_LANG=en"
"!PHPCMD!" patch-phpunit.php
del "!APP!\patch-phpunit.php" >nul 2>&1
echo       Done.
call :save 7

:step8
REM ============================================================
REM  8. Create tables and demo data
REM ============================================================
if !DONE_STEP! GEQ 8 (
    echo [8/9] Tables + demo data . already created - your data is kept
    goto :step9
)

echo [8/9] Creating tables and demo data...
cd /d "!APP!"
"!PHPCMD!" artisan migrate:fresh --seed --force
if errorlevel 1 (
    echo.
    echo   [ERROR] Could not create the database tables.
    echo   Run this inside !APP! to see the details:
    echo       php artisan migrate:fresh --seed
    echo.
    echo   Then run this file again and choose [R] to resume.
    goto :error
)
call :save 8

:step9
REM ============================================================
REM  9. Finishing touches
REM ============================================================
echo [9/9] Finishing up...
cd /d "!APP!"
"!PHPCMD!" artisan storage:link >nul 2>&1
"!PHPCMD!" artisan optimize:clear >nul 2>&1

if not exist "!APP!\index.php" (
    copy /Y "%SRC%\index-root.example.php" "!APP!\index.php" >nul
)
if not exist "!APP!\.htaccess" (
    copy /Y "%SRC%\htaccess-root.example" "!APP!\.htaccess" >nul
)
echo       Done.

REM Installation finished - remove the progress file
del "%STATE%" >nul 2>&1

REM ============================================================
REM  Finished
REM ============================================================
cls
echo.
echo ============================================================
echo    INSTALLATION COMPLETE
echo ============================================================
echo.
echo    Folder     : !APP!
echo    Database   : !DBNAME!  ^(MySQL^)
echo.
echo ------------------------------------------------------------
echo    OPEN THIS ADDRESS
echo.
echo       http://localhost/!FOLDER!
echo.
echo    ^(full address: !APPURL!^)
echo.
echo ------------------------------------------------------------
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
echo      !APPURL!/scan
echo.
echo ============================================================
echo.
echo    Keep Apache and MySQL running.
echo.
echo    If you get 404 or Forbidden, see  README-Windows.md
echo.
echo ============================================================
echo.

start "" "!APPURL!"
pause
goto :end

REM ============================================================
REM  Save progress so the next run can continue from here
REM  The MySQL password is deliberately NOT saved to disk.
REM ============================================================
:save
set "DONE_STEP=%~1"
> "%STATE%" (
    echo REM Progress file for install-webserver.bat
    echo REM Delete this file to force a clean install.
    echo set "DONE_STEP=!DONE_STEP!"
    echo set "WEBROOT=!WEBROOT!"
    echo set "FOLDER=!FOLDER!"
    echo set "DBHOST=!DBHOST!"
    echo set "DBPORT=!DBPORT!"
    echo set "DBNAME=!DBNAME!"
    echo set "DBUSER=!DBUSER!"
)
goto :eof

:quit
echo.
echo   Cancelled. Your progress is kept - run this file again to resume.
echo.
pause
exit /b 0

:error
echo.
echo ============================================================
echo   INSTALLATION STOPPED
echo ============================================================
echo.
echo   Your progress was saved ^(finished steps: !DONE_STEP! of 9^).
echo   Fix the problem above, then run this file again
echo   and choose [R] to continue from where it stopped.
echo.
pause
exit /b 1

:end
endlocal
