# Windows Installation Guide

All installer messages are in **English** because older Windows Command Prompt
uses a raster font that cannot display Thai characters.

Thai documentation is still available in `อ่านก่อน-Windows.md`
and `อ่านก่อน-Windows-WebServer.md` (readable in Notepad / VS Code).

---

## Which installer should I use?

| Your situation | Use this file |
|---|---|
| You already have **XAMPP / Laragon / WAMP** | `install-webserver.bat` |
| You just want it running, no setup | `install-sqlite.bat` |
| Already installed, want to start it again | `start-server.bat` |

---

## Option A — Install into XAMPP / Laragon / WAMP

Uses your existing **Apache + MySQL**.

### Steps

1. Start **Apache** and **MySQL** first
   - XAMPP: open XAMPP Control Panel, click Start on both
   - Laragon: click **Start All**
   - WAMP: wait until the tray icon turns green
2. Double-click **`install-webserver.bat`**
3. Answer the questions — **press Enter to accept every default**
4. Open `http://localhost/stock-app`

### What it asks

| Question | Default | Notes |
|---|---|---|
| Web folder | detected automatically | e.g. `C:\xampp\htdocs` |
| Project folder name | `stock-app` | change if you like |
| MySQL host | `127.0.0.1` | |
| MySQL port | `3306` | **Laragon often uses `3307`** |
| Database name | `stock_system` | created automatically |
| Username | `root` | |
| Password | *(empty)* | XAMPP/Laragon usually has none |

Takes about **3-5 minutes** (downloads Laravel).

---

## Option B — Simple install (SQLite)

No MySQL, no web server needed. Only PHP 8.2+.

1. Double-click **`install-sqlite.bat`**
2. Open `http://localhost:8000`

Next time, just double-click **`start-server.bat`** — starts in 2 seconds.

---

## Installing PHP (if you do not have it)

Skip this if you use XAMPP or Laragon — they include PHP already.

1. Download PHP 8.2+ from https://windows.php.net/download/
   Choose the **Thread Safe** zip file
2. Extract to `C:\php`
3. Copy `php.ini-development` and rename the copy to `php.ini`
4. Open `php.ini` and remove the `;` in front of these lines:

   ```ini
   extension=pdo_sqlite
   extension=pdo_mysql
   extension=mbstring
   extension=openssl
   extension=fileinfo
   extension=curl
   ```

5. Add `C:\php` to your system PATH
   - Press Start, search for `Environment Variables`
   - Select `Path` under System variables, click Edit
   - Click New, type `C:\php`, click OK
6. Open a **new** Command Prompt and type `php -v` to verify

---

## Login accounts

Password for **every** account is: **`password`**

| Email | Role | Access |
|---|---|---|
| `admin@demo.test` | System owner | Everything |
| `wh@demo.test` | Main warehouse | Receive stock, approve transfers |
| `swh@demo.test` | Sub warehouse | Transfer to agents |
| `agent@demo.test` | Sales agent | Distribute to shops |
| `shop@demo.test` | Shop | Point of sale |
| `seller@demo.test` | Seller | Front counter sales |

**Customer QR scan page** (no login required): add `/scan` to the address

---

## If the installer stops halfway

Both installers **remember their progress**. If the window closes, the power
cuts out, or the download fails, just run the same file again.

You will see:

```
A previous installation was found, stopped after step 5 of 9.

  [R] Resume  - continue and keep existing data
  [S] Start over - redo everything (ERASES all data)
  [Q] Quit
```

Press **Enter** (or `R`) to continue from where it stopped.
Finished steps are skipped, so nothing is repeated and nothing is lost.

Notes:

- The MySQL password is **never written to disk**. When resuming,
  you will be asked for it again - that is normal.
- Your application key is kept on resume, so data already saved
  stays readable.
- Progress is tracked in `install-state.cmd` (or `install-state-sqlite.cmd`).
  Delete that file if you want to force a completely fresh install.
- The file is removed automatically once the install completes.

## Laragon with Nginx

Laragon's nginx.conf only includes these two lines:

    include "C:/app/etc/nginx/php_upstream.conf";
    include "C:/app/etc/nginx/sites-enabled/*.conf";

There is no alias/ include, so any file placed in C:/app/etc/nginx/alias/
is silently ignored. That produces a 404 on every page, with an empty error.log.

Use the ready-made site file instead:

1. Copy `stock-system.laragon.conf` to
   `C:/app/etc/nginx/sites-enabled/stock-system.conf`
2. Reload Nginx (right-click Laragon tray icon, Nginx, Reload)
3. Set `APP_URL=http://stock-system.test` in `.env`,
   then run `php artisan config:clear`
4. Open `http://stock-system.test`

If the hostname does not resolve, add this line to
`C:\Windows\System32\drivers\etc\hosts`:

    127.0.0.1  stock-system.test

## Troubleshooting

### `php` is not recognized as a command
`C:\php` is not in your PATH, or you did not open a new Command Prompt.
Redo steps 5-6 of "Installing PHP".

### Missing PHP extension
Open the `php.ini` file the installer shows you and remove the `;`
in front of the `extension=` line for each missing item. Then restart Apache.

### 404 Not Found on every page (home page works)
Apache `mod_rewrite` is off.

1. XAMPP Control Panel, click **Config** next to Apache, choose `httpd.conf`
2. Find `#LoadModule rewrite_module modules/mod_rewrite.so` and delete the `#`
3. Find `AllowOverride None` inside the block covering `htdocs`,
   change it to `AllowOverride All`
4. Save and **restart Apache**

### 403 Forbidden
Make sure the `htdocs` block in `httpd.conf` has `Require all granted`.
Also confirm you are opening `http://localhost/stock-app`
rather than opening the file from File Explorer.

### Cannot connect to MySQL
- Is MySQL started in the control panel?
- Laragon sometimes uses port **3307** instead of 3306
  (check Laragon menu > Database)
- If you set a root password, enter it when the installer asks

### Trait "Laravel\Sanctum\HasApiTokens" not found

This happens if Sanctum was not installed before seeding.
The installers handle it automatically, but if you hit it manually,
run this inside the project folder:

```
php artisan install:api
php artisan migrate:fresh --seed
```

Laravel 11 and newer do not ship Sanctum by default, but this system
uses it for the API routes.

### Blank white page
Open `storage\logs\laravel.log` inside the project folder and read the last lines.
Or set `APP_DEBUG=true` in `.env` to see the full error.

### CSS missing, layout broken
Check that `APP_URL` in `.env` matches the address you actually use:

```env
APP_URL=http://localhost/stock-app/public
```

Then run `php artisan config:clear` in the project folder.

### Reset all data
In the project folder:

```
php artisan migrate:fresh --seed
```

### Port 8000 already in use (SQLite install)
Open the `.bat` file in Notepad, find `--port=8000` on the last lines,
change it to another number such as `--port=8080`,
then open `http://localhost:8080`.

---

## Running the test suite

Inside the project folder:

```
php artisan test
```

You should see **47 passed**.

---

## Making Thai display correctly in Command Prompt

Not required — the installers are English only. But if you want Thai to work
in the terminal generally, use **Windows Terminal** instead of the old
Command Prompt. It supports Unicode fonts properly.

You can install it free from the Microsoft Store, or on Windows 11 it is
already the default.
