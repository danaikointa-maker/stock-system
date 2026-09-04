# 🔐 คู่มือตั้งค่า Social Login (LINE / Google)

ระบบรองรับการเข้าสู่ระบบด้วย **LINE** และ **Google** สำหรับลูกค้าปลายทาง  
เขียนเรียก OAuth เองโดยไม่พึ่ง Socialite — ลด dependency, ควบคุมความปลอดภัยได้ละเอียดกว่า

---

## 📋 สารบัญ

1. [ตั้งค่า LINE Login](#-ตั้งค่า-line-login)
2. [ตั้งค่า Google OAuth](#-ตั้งค่า-google-oauth)
3. [เพิ่มค่าใน .env](#-เพิ่มค่าใน-env)
4. [ทดสอบ](#-ทดสอบ)
5. [Troubleshooting](#-troubleshooting)

---

## 🟢 ตั้งค่า LINE Login

### ขั้นตอนที่ 1: สร้าง LINE Login Channel

1. ไปที่ **LINE Developers Console**: https://developers.line.biz/console/
2. คลิก **Create a new provider** (ถ้ายังไม่มี)
   - Provider name: `ชื่อร้านของคุณ`
3. คลิก **Create a LINE Login channel**
   - Channel type: `LINE Login`
   - Channel name: `RoaMembers Login` (หรือชื่อที่ต้องการ)
   - Channel description: `เข้าสู่ระบบสะสมแต้ม`
4. คลิก **Create**

### ขั้นตอนที่ 2: ตั้งค่า Channel

1. ในหน้า Channel settings → แท็บ **Basic settings**
2. จดบันทึก:
   - **Channel ID** → เก็บไว้ (ใช้ใน `LINE_CLIENT_ID`)
   - **Channel secret** → คลิก **Issue** แล้วจดบันทึก (ใช้ใน `LINE_CLIENT_SECRET`)

3. แท็บ **LINE Login** → ตั้งค่า:
   - **Callback URL**: เพิ่ม URL ที่ระบบจะเรียกกลับ
     ```
     https://yourdomain.com/auth/line/callback
     ```
     > ⚠️ **ต้องใช้ HTTPS เท่านั้น** — localhost ใช้ไม่ได้กับ LINE

4. **OpenID Connect** → Email address permission:
   - ติ๊ก ✅ **Apply** (ถ้าต้องการอีเมล — ต้องขอสิทธิ์เพิ่ม)

### ขั้นตอนที่ 3: เพิ่มค่าใน .env

```env
LINE_CLIENT_ID=1234567890
LINE_CLIENT_SECRET=abcdef1234567890abcdef1234567890
```

---

## 🔵 ตั้งค่า Google OAuth

### ขั้นตอนที่ 1: สร้าง Google Cloud Project

1. ไปที่ **Google Cloud Console**: https://console.cloud.google.com/
2. เลือก Project หรือสร้างใหม่:
   - คลิกเมนูด้านบน → **New Project**
   - Project name: `RoaMembers`
   - คลิก **Create**

### ขั้นตอนที่ 2: ตั้งค่า OAuth Consent Screen

1. เมนูซ้าย → **APIs & Services** → **OAuth consent screen**
2. เลือก **External** → คลิก **Create**
3. กรอกข้อมูล:
   - App name: `RoaMembers`
   - User support email: `อีเมลของคุณ`
   - Developer contact: `อีเมลของคุณ`
4. คลิก **SAVE AND CONTINUE** → ข้าม Scopes → **SAVE AND CONTINUE**
5. **Test users** → เพิ่มอีเมลที่ต้องการทดสอบ → **SAVE AND CONTINUE**

### ขั้นตอนที่ 3: สร้าง OAuth Credentials

1. เมนูซ้าย → **APIs & Services** → **Credentials**
2. คลิก **+ CREATE CREDENTIALS** → **OAuth client ID**
3. Application type: **Web application**
4. Name: `RoaMembers Web`
5. **Authorized redirect URIs** → เพิ่ม:
   ```
   https://yourdomain.com/auth/google/callback
   ```
   > สำหรับ development:
   ```
   http://localhost:8000/auth/google/callback
   ```
6. คลิก **Create**

### ขั้นตอนที่ 4: จดบันทึก Credentials

จะได้:
- **Client ID** → `xxxxx.apps.googleusercontent.com`
- **Client Secret** → `GOCSPX-xxxxxxxxxx`

### ขั้นตอนที่ 5: เพิ่มค่าใน .env

```env
GOOGLE_CLIENT_ID=123456789012-xxxxxxxxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxx
```

---

## ⚙️ เพิ่มค่าใน .env

เปิดไฟล์ `.env` แล้วเพิ่ม:

```env
# ─── Social Login ─────────────────────────────────────────────
# LINE Login: https://developers.line.biz/console/
LINE_CLIENT_ID=1234567890
LINE_CLIENT_SECRET=abcdef1234567890abcdef1234567890

# Google OAuth: https://console.cloud.google.com/apis/credentials
GOOGLE_CLIENT_ID=123456789012-xxxxxxxxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxx
```

### ล้าง cache config

```bash
php artisan config:clear
php artisan cache:clear
```

---

## ✅ ทดสอบ

1. เปิดหน้า `/scan`
2. ปุ่ม **"เข้าสู่ระบบด้วย LINE"** และ **"เข้าสู่ระบบด้วย Google"** ต้องเปิดใช้งานได้
   (ไม่ใช่สีเทา disabled)
3. คลิกปุ่ม → ต้อง redirect ไปหน้า consent ของ LINE/Google
4. อนุญาต → ต้อง redirect กลับ `/auth/{provider}/callback`
5. ลูกค้าใหม่ → redirect ไป `/scan` พร้อมข้อความ "กรุณาเพิ่มเบอร์โทร"
6. ลูกค้าเดิม (มีเบอร์แล้ว) → redirect ไป `/scan/wallet`

---

## 🔧 Troubleshooting

### ปุ่ม LINE/Google เป็นสีเทา (disabled)

**สาเหตุ**: ยังไม่ได้ตั้งค่า credentials ใน `.env`  
**แก้**: ตรวจสอบว่าเพิ่มค่าแล้ว และรัน `php artisan config:clear`

### LINE: "Invalid redirect_uri"

**สาเหตุ**: Callback URL ไม่ตรงกับที่ตั้งค่าใน LINE Developers Console  
**แก้**: 
- ตรวจสอบ URL ใน LINE Console → แท็บ LINE Login → Callback URL
- ต้องตรงกับ `https://yourdomain.com/auth/line/callback` พอดี (รวม https://)

### Google: "redirect_uri_mismatch"

**สาเหตุ**: Redirect URI ไม่ตรงกับที่ตั้งค่าใน Google Cloud Console  
**แก้**:
- ไปที่ Google Cloud Console → Credentials → OAuth Client → Authorized redirect URIs
- เพิ่ม URL ให้ตรงกับที่ใช้จริง

### LINE: ไม่ได้รับ email

**สาเหตุ**: LINE Login ไม่ส่ง email มาโดยตรง ต้องขอ permission เพิ่ม  
**แก้**: ระบบทำงานได้โดยไม่ต้องใช้ email — ใช้ LINE User ID แทน

### "การเข้าสู่ระบบไม่ปลอดภัย กรุณาลองใหม่"

**สาเหตุ**: OAuth state ไม่ตรงกัน (อาจเกิดจาก session หมดอายุ หรือ CSRF)  
**แก้**: ลองใหม่อีกครั้ง — เปิดหน้า scan ใหม่แล้วคลิกปุ่ม login อีกที

### development ใน localhost ใช้ LINE ไม่ได้

**สาเหตุ**: LINE Login ต้องการ HTTPS เท่านั้น  
**แก้**: 
- ใช้ **Google** แทน (รองรับ `http://localhost`)
- หรือใช้ [ngrok](https://ngrok.com/) เพื่อสร้าง HTTPS tunnel:
  ```bash
  ngrok http 8000
  ```
  แล้วใช้ URL ที่ ngrok ให้ เป็น callback URL

---

## 🏗️ Architecture

```
ลูกค้า → คลิก "Login with LINE/Google"
    ↓
SocialAuthController::redirect()
    ├── สร้าง state token (กัน CSRF)
    └── redirect → OAuth Provider
    ↓
OAuth Provider → อนุญาต
    ↓
SocialAuthController::callback()
    ├── ตรวจ state (กัน CSRF)
    ├── แลก code → access_token
    ├── ดึง profile (uid, name, picture)
    ├── findOrCreateCustomer()
    │   ├── มี SocialLink เดิม → อัปเดตข้อมูล
    │   └── ใหม่ → สร้าง Customer + SocialLink
    └── login session → redirect
```

### ตารางที่เกี่ยวข้อง

| ตาราง | หน้าที่ |
|-------|---------|
| `customers` | ข้อมูลลูกค้า (name, phone) |
| `social_links` | ผูก LINE/Google กับ customer (provider, uid) |
| `security_logs` | บันทึกทุกการ login/error |

### มาตรการความปลอดภัย

- ✅ **state parameter** กัน CSRF ทุกครั้ง
- ✅ **1 provider UID = 1 customer** เท่านั้น
- ✅ **hash_equals** ตรวจ state (กัน timing attack)
- ✅ **timeout 15s** ทุก HTTP call (กัน hang)
- ✅ **SecurityService::logLogin** บันทึกทุกการ login
- ✅ ไม่ใช้ Socialite — ควบคุม code เองทั้งหมด
