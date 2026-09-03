# ติดตั้งบน Web Server ที่มีอยู่แล้ว (XAMPP / Laragon / WAMP)

> **หมายเหตุเรื่องภาษา**
> ข้อความในไฟล์ติดตั้ง (`.bat`) เป็น **ภาษาอังกฤษทั้งหมด**
> เพราะ Command Prompt รุ่นเก่าของ Windows ใช้ฟอนต์ที่แสดงภาษาไทยไม่ได้
> จะขึ้นเป็นตัวขยะหรือเครื่องหมายคำถาม
> คู่มือภาษาไทย (ไฟล์ `.md`) ยังอ่านได้ปกติใน Notepad หรือ VS Code



สำหรับเครื่องที่ติดตั้ง **Apache + MySQL** ไว้แล้ว
ใช้ web server ของคุณเอง ไม่ใช้ `php artisan serve`

> ถ้ายังไม่มี web server และอยากรันแบบง่ายที่สุด
> ให้ใช้ `install-sqlite.bat` แทน (ใช้ SQLite ไม่ต้องตั้งอะไรเลย)

---

## ขั้นตอน

### 1. เปิด Apache และ MySQL ก่อน

- **XAMPP** → เปิด XAMPP Control Panel → กด Start ทั้ง `Apache` และ `MySQL`
- **Laragon** → เปิด Laragon → กด **Start All**
- **WAMP** → เปิด WAMP → รอไอคอนเป็นสีเขียว

### 2. ดับเบิลคลิก `install-webserver.bat`

สคริปต์จะถามข้อมูลทีละอย่าง **กด Enter ผ่านได้ทุกข้อถ้าใช้ค่าเริ่มต้น**

| คำถาม | ค่าเริ่มต้น | หมายเหตุ |
|---|---|---|
| โฟลเดอร์เว็บ | หาให้อัตโนมัติ | เช่น `C:\xampp\htdocs` |
| ชื่อโฟลเดอร์โปรเจกต์ | `stock-app` | เปลี่ยนได้ตามต้องการ |
| MySQL host | `127.0.0.1` | |
| MySQL port | `3306` | Laragon บางรุ่นใช้ `3307` |
| ชื่อฐานข้อมูล | `stock_system` | สร้างให้อัตโนมัติถ้ายังไม่มี |
| ชื่อผู้ใช้ | `root` | |
| รหัสผ่าน | *(ว่าง)* | XAMPP/Laragon ปกติไม่มีรหัส กด Enter ผ่าน |

ใช้เวลาประมาณ **3-5 นาที** (ต้องดาวน์โหลด Laravel)

### 3. เปิดเว็บ

```
http://localhost/stock-app
```

---

## บัญชีสำหรับเข้าใช้งาน

รหัสผ่านทุกบัญชีคือ **`password`**

| อีเมล | บทบาท | เห็นอะไร |
|---|---|---|
| `admin@demo.test` | เจ้าของระบบ | ทุกอย่าง |
| `wh@demo.test` | คลังใหญ่ | รับของเข้า อนุมัติใบโอน |
| `swh@demo.test` | คลังย่อย | โอนต่อให้ตัวแทน |
| `agent@demo.test` | ตัวแทนขาย | กระจายให้ร้านค้า |
| `shop@demo.test` | ร้านค้า | เปิดบิลขาย POS |
| `seller@demo.test` | ผู้ขาย | ขายหน้าร้าน |

**หน้าลูกค้าสแกน QR** (ไม่ต้องล็อกอิน) → `http://localhost/stock-app/public/scan`

---

## ปัญหาที่พบบ่อย

### ขึ้น 404 Not Found ทุกหน้า (แต่หน้าแรกเข้าได้)

Apache ยังไม่ได้เปิด `mod_rewrite`

1. เปิด XAMPP Control Panel → กด **Config** ข้าง Apache → เลือก `httpd.conf`
2. หาบรรทัด `#LoadModule rewrite_module modules/mod_rewrite.so`
3. ลบเครื่องหมาย `#` ข้างหน้าออก
4. หาคำว่า `AllowOverride None` ในบล็อกที่ครอบ `htdocs` แล้วเปลี่ยนเป็น `AllowOverride All`
5. บันทึกแล้ว **Restart Apache**

### ขึ้น 403 Forbidden

ตรวจว่าในไฟล์ `httpd.conf` บล็อกของ `htdocs` มี `Require all granted` อยู่
และเข้า URL ให้ถูก — ต้องเป็น `http://localhost/stock-app` ไม่ใช่เปิดไฟล์จาก File Explorer

### เชื่อมต่อ MySQL ไม่ได้

- ตรวจว่ากด Start MySQL ใน Control Panel แล้ว
- Laragon บางรุ่นใช้พอร์ต `3307` ไม่ใช่ `3306`
  ดูได้จาก Laragon → เมนู **Database** → ดูเลขพอร์ต
- ถ้าตั้งรหัส root ไว้ ต้องใส่ตอนสคริปต์ถาม

### หน้าเว็บขาว ไม่มีอะไรเลย

เปิดไฟล์ `storage\logs\laravel.log` ในโฟลเดอร์โปรเจกต์ ดูบรรทัดล่างสุด
หรือเปิด `.env` แล้วตั้ง `APP_DEBUG=true` เพื่อให้แสดง error เต็ม

### CSS ไม่โหลด หน้าเบี้ยว

เปิด `.env` ตรวจว่า `APP_URL` ตรงกับ URL ที่เข้าจริง เช่น

```env
APP_URL=http://localhost/stock-app/public
```

แล้วรัน (ใน Command Prompt ที่โฟลเดอร์โปรเจกต์)

```
php artisan config:clear
```

### อยากล้างข้อมูลเริ่มใหม่

เปิด Command Prompt ไปที่โฟลเดอร์โปรเจกต์ แล้วพิมพ์

```
php artisan migrate:fresh --seed
```

### อยากให้เข้าแบบ URL สั้น โดยไม่มี /public

ทำให้แล้วอัตโนมัติ — สคริปต์วางไฟล์ `index.php` กับ `.htaccess` ที่รากโปรเจกต์ไว้ให้
ทำให้เข้า `http://localhost/stock-app` แล้วเด้งไป `public/` เอง

ถ้าอยากได้แบบสะอาดกว่านี้ (ใช้บนเซิร์ฟเวอร์จริง) ให้ตั้ง VirtualHost ชี้ DocumentRoot
ไปที่โฟลเดอร์ `public` โดยตรง — ปลอดภัยกว่าเพราะไฟล์ `.env` จะอยู่นอก web root

**Laragon** ทำให้อัตโนมัติอยู่แล้ว แค่กด Reload Apache จะได้ `http://stock-app.test`

---

## รันชุดทดสอบ

ในโฟลเดอร์โปรเจกต์

```
php artisan test
```

ควรได้ **47 passed**

---

## ใช้ MySQL ผ่าน phpMyAdmin แทนการให้สคริปต์สร้าง

ถ้าอยากสร้างฐานข้อมูลเองก่อน

1. เข้า `http://localhost/phpmyadmin`
2. กด **New** สร้างฐานข้อมูลชื่อ `stock_system`
   เลือก Collation เป็น **`utf8mb4_unicode_ci`** (สำคัญ ไม่งั้นภาษาไทยเพี้ยน)
3. รันสคริปต์ติดตั้งตามปกติ — จะข้ามขั้นตอนสร้างฐานข้อมูลไปเอง

อีกทางคือใช้ไฟล์ `database/schema.sql` นำเข้าผ่าน phpMyAdmin โดยตรง
(สร้างครบ 21 ตาราง + ข้อมูลตัวอย่าง) แต่วิธีนี้จะไม่มี Laravel migration history
เหมาะกับกรณีอยากดูโครงสร้างฐานข้อมูลอย่างเดียว
