---
slug: rao-members-stock-system
title: RaoMembers Stock & Loyalty System
tagline: ระบบสต๊อกสินค้า 6 ระดับสายงาน + QR สะสมแต้ม + ระบบบัญชีครบวงจร
short_desc: >
  ระบบจัดการสต๊อกสินค้าหลายระดับ (เจ้าของ→คลัง→ตัวแทน→ร้านค้า→ผู้ขาย)
  พร้อม POS ขายหน้าร้าน, QR สะสมแต้ม/แลกของรางวัล, ใบโอนสินค้า,
  ระบบบัญชีและการเงินครบวงจร (บิลเรียกเก็บ/รับ/จ่าย, ใบกำกับภาษี,
  ใบหัก ณ ที่จ่าย, งบการเงิน), ศูนย์ความปลอดภัย และ Workflow Guide

category: บริหารจัดการ / สต๊อกสินค้า / Loyalty
system_type: software
sub_type: Web App (ใช้ผ่านเบราว์เซอร์)
tags: [สต๊อก, POS, QR Code, สะสมแต้ม, โอนสินค้า, บัญชี, ใบกำกับภาษี, Laravel]
php_version: PHP 8.2+
status: production
featured: true
users_label: ใช้งานจริง

cover_image: https://images.pexels.com/photos/4483610/pexels-photo-4483610.jpeg?auto=compress&cs=tinysrgb&fit=crop&h=627&w=1200
gallery:
  - https://images.pexels.com/photos/4483610/pexels-photo-4483610.jpeg?auto=compress&cs=tinysrgb&fit=crop&h=627&w=1200
  - https://images.pexels.com/photos/5668882/pexels-photo-5668882.jpeg?auto=compress&cs=tinysrgb&fit=crop&h=627&w=1200
  - https://images.pexels.com/photos/3184292/pexels-photo-3184292.jpeg?auto=compress&cs=tinysrgb&fit=crop&h=627&w=1200

pricing_type: custom
price_hint: ติดต่อสอบถาม
license_note: Develop to order — พัฒนาตามความต้องการ พร้อมติดตั้งและอบรม
pricing_options:
  - { type: trial,   label: ทดลองใช้ Demo,     price: ฟรี,            desc: บัญชีทดสอบครบ 6 ระดับ }
  - { type: rental,  label: เช่าใช้รายเดือน,    price: ติดต่อสอบถาม,   desc: Cloud server + ซัพพอร์ต }
  - { type: outright,label: ซื้อขาด + ติดตั้ง,  price: ติดต่อสอบถาม,   desc: ติดตั้ง Server เอง ดูแลปีแรกฟรี }

dev_duration: 1–2 วัน (ติดตั้ง + ตั้งค่า + อบรม)
rent_duration: รายเดือน / รายปี (ต่ออายุอัตโนมัติ)

related_costs:
  - { label: ย้ายข้อมูลสินค้า/ลูกค้าจาก Excel, amount: 3,000.-,    note: ฟรีเมื่อเช่ารายปี }
  - { label: เครื่องอ่านบาร์โค้ด/QR,           amount: 2,500.-,    note: ถ้ายังไม่มี }
  - { label: อบรมเพิ่มหน้างาน,                amount: 2,500.-/ครั้ง,note: อบรมออนไลน์ฟรี }

install_specs:
  - { item: คอมพิวเตอร์, spec: Windows/Mac ใดก็ได้ + Chrome เวอร์ชันล่าสุด }
  - { item: เครื่องพิมพ์, spec: A4 หรือ Thermal 58/80mm สำหรับใบเสร็จ }
  - { item: อินเทอร์เน็ต, spec: 10 Mbps ขึ้นไป (ระบบเป็น Cloud) }
  - { item: เครื่องอ่านบาร์โค้ด, spec: USB/Bluetooth (ถ้ามี) }

system_requirements:
  - { category: ที่หน้างาน, items: [คอม/แท็บเล็ต 1 เครื่อง, เน็ต 10 Mbps, เครื่องพิมพ์ใบเสร็จ] }
  - { category: เซิร์ฟเวอร์ (เราดูแล), items: [PHP 8.2+, MySQL 8, Redis 7, สำรองรายวัน] }
  - { category: เอกสาร, items: [โลโก้แบรนด์, รายการสินค้า+ราคา, ข้อมูลสาขา] }

tech_stack:
  - { name: PHP,      version: 8.2+,  desc: Backend หลัก }
  - { name: Laravel,  version: 11.x,  desc: Framework + 6 บทบาท + Gate/Policy }
  - { name: Blade,    version: Native,desc: Template engine + Tailwind CSS }
  - { name: MySQL,    version: 8.0,   desc: 38+ tables (stock, sales, accounting) }
  - { name: Redis,    version: 7.x,   desc: Queue + cache + session }
  - { name: DomPDF,   version: 3.x,   desc: ใบเสร็จ / ใบกำกับภาษี / รายงาน PDF }

modules:
  - { name: POS,        desc: จุดขายหน้าร้าน,        items: [เปิดบิลขาย, ตัดสต๊อก, สะสมแต้ม, พิมพ์ใบเสร็จ] }
  - { name: Stock,      desc: คลังสินค้า,             items: [ล็อต QR, โอน 6 ระดับ, นับสต๊อก, ปรับยอด] }
  - { name: Loyalty,    desc: QR สะสมแต้ม,           items: [QR สินค้า, QR ร้านค้า, แลกรางวัล, วงเงินเดือน] }
  - { name: Accounting, desc: บัญชีและการเงิน,        items: [บิลเรียกเก็บ/รับ/จ่าย, ใบกำกับภาษี, WHT, งบการเงิน] }
  - { name: Admin,      desc: เจ้าของระบบ,            items: [แพ็กเกจ, ความปลอดภัย, ตั้งค่า, แบรนด์] }
  - { name: Shop,       desc: หน้าร้าน,               items: [ตั้งค่าร้าน, QR ร้านค้า, ของรางวัล] }

structure:
  overview: Web App เดียว (Laravel 11 + Blade) แยกสิทธิ์ 6 บทบาท ข้อมูลเข้ารหัส สำรองอัตโนมัติ
  frontend: Blade + Tailwind CSS + Vanilla JS รองรับมือถือ/แท็บเล็ต
  backend: Laravel 11 PHP 8.2 — StockService, SaleService, PointService, AccountingService, DocSequenceService, StockLedgerService
  database: MySQL 8 — products, product_lots, stock_balances, stock_movements, stock_ledger, sales, transfers, invoices, payments, journal_entries, accounts
  cache: Redis queue + session + cache
  queue: Queue — LINE Notify, Email SMTP, SMS, PDF generation
  storage: Local / S3 (รูปภาพ, โลโก้, เอกสาร)
  deployment: Cloud / On-premise + สำรองรายวัน
  integrations: [LINE OA / LINE Notify, Gmail SMTP, Thai Bulk SMS, Twilio]
  layers:
    - { name: POS ขายหน้าร้าน,    desc: เปิดบิล + ตัดสต๊อก + แต้ม,       tech: Blade + JS }
    - { name: คลังสินค้า,        desc: ล็อต QR + โอน + นับ,            tech: Laravel }
    - { name: QR สะสมแต้ม,       desc: สแกน + ให้แต้ม + แลกรางวัล,      tech: Laravel + Queue }
    - { name: บัญชีและการเงิน,   desc: บิล + ใบกำกับภาษี + งบการเงิน,    tech: Laravel + bcmath }
    - { name: Core Services,      desc: Stock/Sale/Point/Accounting,     tech: PHP 8.2 }
    - { name: แจ้งเตือน,          desc: LINE / Email / SMS,              tech: Queue }
  flow:
    - คลังใหญ่รับสินค้า → สร้างล็อต QR → แจกจ่ายให้คลังย่อย/ตัวแทน
    - ตัวแทนโอนให้ร้านค้า → ร้านค้าเปิดบิลขาย POS → ตัดสต๊อกอัตโนมัติ
    - ลูกค้าสแกน QR สินค้า → สะสมแต้มในกระเป๋า → แลกรางวัลที่ร้าน
    - ร้านค้ายื่นใบเบิกแต้ม → Admin อนุมัติ → จ่ายเงินคืน
    - ระบบบัญชี: ออกบิลเรียกเก็บ → รับเงิน → ใบกำกับภาษี → รายงานงบการเงิน
    - Stock Ledger: ทุก movement บันทึก immutable + Journal Entry อัตโนมัติ

workflow_steps:
  - { step: ติดตั้งระบบ,       desc: Clone repo + ตั้ง .env + migrate,            duration: 1 ชั่วโมง }
  - { step: ตั้งค่าแบรนด์,     desc: โลโก้, ชื่อระบบ, สี, LINE/SMTP/SMS,           duration: 1 วัน }
  - { step: สร้างสายงาน,       desc: สร้างหน่วยงาน 6 ระดับ + สมาชิก,               duration: 1–2 วัน }
  - { step: นำเข้าสินค้า,      desc: สร้างสินค้า + ล็อต QR + ราคา,                duration: 1–3 วัน }
  - { step: ตั้งค่าแพ็กเกจ,    desc: แพ็กเกจสมาชิก + อัตราแต้ม + ของรางวัล,          duration: 1 วัน }
  - { step: อบรมทีม,           desc: คลัง/ตัวแทน/ร้านค้า/ผู้ขาย ออนไลน์,          duration: 1 วัน }
  - { step: เปิดใช้จริง,        desc: ใช้คู่ขนาน 3–7 วันแล้วตัดระบบเดิม,           duration: 3–7 วัน }

owner_name: คุณไอเดีย — YourIdea168
owner_role: Product Owner / Solution Architect
owner_phone: 081-168-XXXX
owner_line: "@youridea168"
owner_email: hello@youridea168.com
owner_company: YourIdea168 Co., Ltd. — www.youridea168.com
demo_note: มีระบบ Demo ครบ 6 ระดับ ทดลองเป็นคลัง/ตัวแทน/ร้านค้า/ผู้ขายได้ครบ

is_public: true
---

# RaoMembers — ระบบสต๊อกสินค้าหลายระดับ + QR สะสมแต้ม + บัญชีครบวงจร

> รันบน **PHP 8.2+** | Laravel 11 | MySQL 8 | Redis 7 | Cloud / On-premise

## ระบบทำอะไรได้บ้าง

- **สายงาน 6 ระดับ** — เจ้าของ → คลังใหญ่ → คลังย่อย → ตัวแทน → ร้านค้า → ผู้ขาย
- **POS เปิดบิลขาย** — ตัดสต๊อกอัตโนมัติ ผูกเบอร์ลูกค้าสะสมแต้ม พิมพ์ใบเสร็จ
- **QR สะสมแต้ม** — ติดสินค้า → ลูกค้าสแกน → ได้แต้ม (กันโกง 5 ชั้น)
- **QR ร้านค้า** — ติดหน้าร้าน → ลูกค้าสแกน → แลกของรางวัล
- **ใบโอนสินค้า** — draft → approve → ship → receive (จอง/ตัด/รับครบ)
- **ระบบแต้ม v3** — กระเป๋าแยกตามร้าน, วงเงินรายเดือน, FIFO, หมดอายุ
- **เบิกเงินคืน** — ร้านยื่นใบเบิก → admin อนุมัติ → จ่ายเงิน
- **ระบบบัญชีครบวงจร** — บิลเรียกเก็บ/รับ/จ่าย, ใบกำกับภาษี, หัก ณ ที่จ่าย, ใบส่งของ, ใบลดหนี้, ใบเสนอราคา, ใบสั่งซื้อ, ลงบัญชีแยก
- **งบการเงิน 5 ฉบับ** — General Ledger, งบทดลอง, งบกำไรขาดทุน, งบแสดงฐานะ, AR/AP Aging
- **Stock Ledger (Immutable)** — ทุก movement บันทึกถาวร แก้ไข/ลบไม่ได้ + Journal Entry อัตโนมัติ
- **ศูนย์ความปลอดภัย** — audit trail, rate limit, IP block, security events
- **แจ้งเตือน** — LINE OA / LINE Notify / Gmail SMTP / Thai Bulk SMS / Twilio
- **Sidebar Collapsible** — เมนูย่อ/ขยายได้ จำสถานะข้าม session + sub-groups
- **Workflow Guide** — คู่มือขั้นตอนการทำงานแยกตามบทบาท พร้อม shortcut links
- **169 automated tests** ครอบคลุมทุกกฎธุรกิจ

## เหมาะกับใคร

- ธุรกิจขายตรง / MLM / ตัวแทนจำหน่าย ที่มีสายงานหลายระดับ
- ร้านค้า / แฟรนไชส์ ที่ต้องการ Track & Trace สินค้าตั้งแต่คลังถึงมือลูกค้า
- ธุรกิจที่ต้องการระบบ Loyalty Program (สะสมแต้ม + แลกรางวัล)
- ธุรกิจที่ต้องการระบบบัญชีครบวงจรเชื่อมกับสต๊อกสินค้า

---

## 🚀 ติดตั้งเร็ว (Quick Start)

### Linux / macOS

```bash
git clone https://github.com/danaikointa-maker/stock-system.git
cd stock-system
bash install.sh
```

### Windows

```cmd
git clone https://github.com/danaikointa-maker/stock-system.git
cd stock-system
install.bat
```

สคริปต์ติดตั้งจะทำ 7 ขั้นตอนอัตโนมัติ:
1. ตรวจสอบ dependencies (PHP, Composer, Node.js)
2. ตั้งค่า .env (SQLite สำหรับ dev)
3. สร้าง storage directories
4. `composer install` — PHP dependencies
5. `php artisan key:generate`
6. `php artisan migrate --seed` — สร้างตาราง + ข้อมูลตัวอย่าง
7. Build frontend (ถ้ามี Node.js)

### รันเซิร์ฟเวอร์

```bash
php artisan serve
# เปิด http://localhost:8000/login
```

### บัญชีทดสอบ (รหัสผ่าน: `password` ทุกบัญชี)

| อีเมล | ระดับ | สิทธิ์หลัก |
|---|---|---|
| `admin@demo.test` | เจ้าของระบบ | ทุกอย่าง + บัญชี + งบการเงิน |
| `wh@demo.test` | คลังใหญ่ | จัดการสต๊อก โอนของ |
| `swh@demo.test` | คลังย่อย | รับของ โอนต่อ |
| `agent@demo.test` | ตัวแทนขาย | จัดการสมาชิก โอนของ |
| `shop@demo.test` | ร้านค้า | POS + ตั้งค่าร้าน + QR + บัญชี |
| `seller@demo.test` | ผู้ขาย | POS เท่านั้น |

---

## 📋 ความต้องการของระบบ

| รายการ | ขั้นต่ำ | แนะนำ |
|---|---|---|
| **PHP** | 8.2+ | 8.4 |
| **Composer** | 2.x | 2.7+ |
| **Node.js** | 18+ (ถ้า build frontend) | 20 LTS |
| **Database** | SQLite (dev) | MySQL 8 (production) |

### PHP Extensions ที่ต้องเปิด

```
mbstring, xml, curl, zip, pdo_sqlite, gd, openssl, bcmath
```

<details>
<summary>วิธีติดตั้ง dependencies (ทีละ OS)</summary>

#### Ubuntu / Debian

```bash
sudo apt update
sudo apt install -y php php-cli php-mbstring php-xml php-curl \
    php-zip php-sqlite3 php-mysql php-gd php-bcmath unzip curl

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js (optional)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

#### macOS (Homebrew)

```bash
brew install php composer node
```

#### Windows

1. **PHP**: ดาวน์โหลดจาก https://windows.php.net/download
   - Extract ไปที่ `C:\php`
   - เพิ่ม `C:\php` ใน System PATH
   - Copy `php.ini-development` → `php.ini`
   - เปิด extensions: `extension=mbstring`, `extension=curl`, `extension=openssl`, `extension=pdo_sqlite`, `extension=gd`, `extension=zip`, `extension=fileinfo`

2. **Composer**: ดาวน์โหลดจาก https://getcomposer.org/Composer-Setup.exe

3. **Node.js**: ดาวน์โหลดจาก https://nodejs.org (LTS)

</details>

---

## 🏗️ โครงสร้างโปรเจค

```
stock-system/
├── app/
│   ├── Console/Commands/          # Artisan commands
│   ├── Enums/                     # Role, TransferStatus, MovementType enums
│   ├── Exceptions/                # Custom exceptions
│   ├── Http/
│   │   ├── Controllers/Web/       # Web controllers (20+)
│   │   │   ├── AccountingController.php   # ระบบบัญชี 58 methods
│   │   │   ├── DashboardController.php    # ภาพรวม
│   │   │   ├── SaleController.php         # POS ขาย
│   │   │   ├── TransferController.php     # ใบโอนสินค้า
│   │   │   ├── WorkflowController.php     # คู่มือการทำงาน
│   │   │   └── ...
│   │   └── Middleware/            # Security middleware
│   ├── Models/                    # Eloquent models (44+)
│   │   ├── Account.php            # ผังบัญชี
│   │   ├── CreditNote.php         # ใบลดหนี้
│   │   ├── DeliveryNote.php       # ใบส่งของ
│   │   ├── Invoice.php            # บิลเรียกเก็บ
│   │   ├── JournalEntry.php       # รายการบัญชี
│   │   ├── ManualJournal.php      # ลงบัญชีแยก
│   │   ├── PurchaseOrder.php      # ใบสั่งซื้อ
│   │   ├── Quotation.php          # ใบเสนอราคา
│   │   ├── StockLedger.php        # Stock Ledger (immutable)
│   │   ├── Transfer.php           # ใบโอนสินค้า
│   │   └── ...
│   ├── Services/                  # Business logic (12+)
│   │   ├── DocSequenceService.php # เลขที่เอกสารอัตโนมัติ
│   │   ├── StockLedgerService.php # Stock movement + Journal Entry
│   │   └── ...
│   └── Policies/                  # Authorization
├── database/
│   ├── migrations/                # DB schema (15+)
│   │   ├── 2026_01_01_*           # Core tables (products, users, org)
│   │   ├── 2026_09_04_200000      # Accounting (10 tables)
│   │   ├── 2026_09_04_210000      # Delivery + Credit + Stock Ledger
│   │   └── 2026_09_04_220000      # Quotation + PO + Manual Journal
│   └── seeders/                   # Demo data
├── resources/views/
│   ├── accounting/                # 25+ accounting views
│   │   ├── dashboard.blade.php
│   │   ├── invoices/              # บิลเรียกเก็บ (index/form/show)
│   │   ├── receipts/              # บิลรับ
│   │   ├── payments/              # บิลจ่าย
│   │   ├── delivery/              # ใบส่งของ
│   │   ├── credit/                # ใบลดหนี้
│   │   ├── quotations/            # ใบเสนอราคา
│   │   ├── po/                    # ใบสั่งซื้อ
│   │   ├── journals/              # ลงบัญชีแยก
│   │   ├── stock-ledger.blade.php
│   │   ├── general-ledger.blade.php
│   │   ├── trial-balance.blade.php
│   │   ├── profit-loss.blade.php
│   │   ├── balance-sheet.blade.php
│   │   └── aging-report.blade.php
│   ├── workflow/                  # Workflow Guide
│   ├── partials/sidebar.blade.php # Sidebar collapsible
│   └── layouts/app.blade.php
├── routes/
│   ├── web.php                    # 60+ routes
│   └── api.php
├── tests/                         # 169 tests, 419 assertions
├── install.sh / install.bat       # Auto installer
└── README.md / INSTALL.md / PERMISSIONS.md
```

### ตารางฐานข้อมูลสำคัญ

| หมวด | ตาราง | เก็บอะไร |
|---|---|---|
| **สินค้า** | products | SKU, barcode, ราคา, หมวดหมู่ |
| | product_lots | ล็อต QR, หมดอายุ, สาขา |
| **สต๊อก** | stock_balances | ยอดคงเหลือ per node/product/lot |
| | stock_movements | Movement log (append-only) |
| | stock_ledger | Immutable ledger + journal ref |
| **ขาย** | sales, sale_items | บิลขาย POS |
| **โอน** | transfers, transfer_items | ใบโอน 6 ระดับ |
| **แต้ม** | customer_point_wallets | กระเป๋าแต้ม |
| | point_transactions | รายการได้/ใช้แต้ม |
| **บัญชี** | invoices, invoice_items | บิลเรียกเก็บ |
| | receipts | บิลรับเงิน |
| | payments | บิลจ่ายเงิน |
| | tax_invoices | ใบกำกับภาษี |
| | withholding_taxes | ใบหัก ณ ที่จ่าย |
| | delivery_notes, delivery_items | ใบส่งของ |
| | credit_notes, credit_items | ใบลดหนี้/คืนสินค้า |
| | quotations, quotation_items | ใบเสนอราคา |
| | purchase_orders, purchase_order_items | ใบสั่งซื้อ |
| | accounts | ผังบัญชี (Chart of Accounts) |
| | journal_entries, journal_lines | รายการบัญชี (Double-entry) |
| | manual_journals, manual_journal_lines | ลงบัญชีแยกด้วยมือ |
| | doc_sequences | ตัวเลขเอกสารอัตโนมัติ |

---

## 🔄 ขั้นตอนการทำงาน

### สายงานสินค้า
```
คลังใหญ่รับสินค้า → สร้างล็อต QR → แจกจ่ายคลังย่อย/ตัวแทน
    → ตัวแทนโอนให้ร้านค้า → ร้านค้าขาย POS → ตัดสต๊อกอัตโนมัติ
```

### QR สะสมแต้ม
```
ลูกค้าสแกน QR สินค้า → ได้แต้มในกระเป๋า
    → แลกรางวัลที่ร้าน → ร้านค้ายื่นใบเบิก → Admin อนุมัติ → จ่ายเงิน
```

### ระบบบัญชี
```
เสนอราคา (QT) → ลูกค้าตกลง → บิลเรียกเก็บ (INV) → ใบส่งของ (DLV)
    → ส่งของ: ตัดสต๊อก + Journal (Dr.COGS, Cr.Inventory) อัตโนมัติ
    → รับเงิน: ออกใบเสร็จ (RCP) → อัพเดทลูกหนี้
    → ใบกำกับภาษี (TXI) → หัก ณ ที่จ่าย (WHT)
    → รายงาน: P&L, Balance Sheet, Trial Balance, AR/AP Aging
```

### Stock Ledger (Immutable)
```
ทุก movement → StockLedger (append-only, แก้ไข/ลบไม่ได้)
    → StockBalance อัพเดท
    → JournalEntry (Double-entry: Dr = Cr)
    → Audit ตรวจสอบยอดตรงอัตโนมัติ
```

---

## 📒 ระบบบัญชีและการเงิน

### เอกสาร 13 ประเภท

| # | เอกสาร | เลขที่ | สถานะ |
|---|--------|--------|-------|
| 1 | 📋 ใบเสนอราคา | QT2609-0001 | ✅ |
| 2 | 📄 บิลเรียกเก็บ | INV2609-0001 | ✅ |
| 3 | 💰 บิลรับ | RCP2609-0001 | ✅ |
| 4 | 🚚 ใบส่งของ | DLV2609-0001 | ✅ |
| 5 | 🧾 ใบกำกับภาษี | TXI2609-0001 | ✅ |
| 6 | 🛒 ใบสั่งซื้อ | PO2609-0001 | ✅ |
| 7 | 💸 บิลจ่าย | PAY2609-0001 | ✅ |
| 8 | 📋 หัก ณ ที่จ่าย | WHT2609-0001 | ✅ |
| 9 | ↩️ ใบลดหนี้ | CN2609-0001 | ✅ |
| 10 | 📒 ลงบัญชีแยก | JV2609-0001 | ✅ |
| 11 | 📋 Stock Ledger | — (auto) | ✅ |
| 12 | 🔍 Audit | — | ✅ |
| 13 | 🗂️ ผังบัญชี | — | ✅ |

### งบการเงิน 5 ฉบับ

| # | งบ | ตรวจสอบ |
|---|---|---------|
| 1 | 📒 General Ledger | รายการแยกตามบัญชี + running balance |
| 2 | ⚖️ งบทดลอง | Dr = Cr สมดุล? |
| 3 | 📈 งบกำไรขาดทุน | รายได้ - ค่าใช้จ่าย = กำไร/ขาดทุน |
| 4 | 🏦 งบแสดงฐานะ | สินทรัพย์ = หนี้สิน + ทุน |
| 5 | ⏳ AR/AP Aging | ลูกหนี้/เจ้าหนี้คงค้าง + อายุหนี้ |

---

## 🧪 ทดสอบระบบ

```bash
# รันทุกเทสต์
php artisan test

# รันเฉพาะกลุ่ม
php artisan test --filter=TransferWorkflowTest
php artisan test --filter=SaleAndScanTest
php artisan test --filter=ShopSettingTest
```

**169 tests, 419 assertions** ครอบคลุม:

| ไฟล์ | ครอบคลุม |
|---|---|
| TransferWorkflowTest | วงจรใบโอน 8 เคส |
| SaleAndScanTest | ขาย + QR scan |
| PermissionTest | สิทธิ์ 17 คู่ |
| CatalogAndPointsTest | สินค้า + แต้ม |
| ClaimTest | ใบเบิก 18 เคส |
| RedeemDeskTest | เคาน์เตอร์แลกแต้ม |
| NotificationTest | แจ้งเตือน |
| ShopSettingTest | ตั้งค่าร้าน + QR ร้านค้า |
| SubscriptionTest | สมาชิกร้าน |

---

## 🌐 Deploy ขึ้น Production

### Linux Server

```bash
git clone https://github.com/danaikointa-maker/stock-system.git /var/www/stock
cd /var/www/stock

# ตั้ง .env สำหรับ production
cp .env.example .env
nano .env    # แก้ DB_CONNECTION=mysql, DB_HOST, DB_DATABASE, ฯลฯ

# ติดตั้งแบบ production
bash install.sh prod
```

### ตั้งค่า Web Server (Nginx)

```nginx
server {
    listen 80;
    server_name stock.example.com;
    root /var/www/stock/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### ตั้งค่า Scheduler (Cron)

```bash
# เพิ่มใน crontab -e
* * * * * cd /var/www/stock && php artisan schedule:run >> /dev/null 2>&1
```

| Command | หน้าที่ | ความถี่ |
|---|---|---|
| `roamembers:reset-allowances` | รีเซตวงเงินเดือน + ปิดสมาชิกหมดอายุ | ทุกวันที่ 1 |
| `roamembers:expire-points` | ตัดแต้มหมดอายุ | ทุกวัน 01:00 |
| `roamembers:send-notifications` | ส่งแจ้งเตือนค้าง | ทุกนาที |

### Windows Server (IIS)

1. ติดตั้ง PHP for IIS: https://php.iis.net
2. ชี้ Site ไปที่ `public/` folder
3. Import `web.config` (Laravel สร้างให้อัตโนมัติ)
4. ตั้ง Task Scheduler สำหรับ artisan commands

---

## 📖 เอกสารเพิ่มเติม

- [INSTALL.md](INSTALL.md) — คู่มือติดตั้งละเอียด (step-by-step)
- [PERMISSIONS.md](PERMISSIONS.md) — ตารางสิทธิ์ 6 บทบาท × ทุกหน้า
- Workflow Guide — คู่มือขั้นตอนการทำงาน (เข้าถึงได้จาก topbar ในระบบ)

---

## 📄 License

Private — All rights reserved.

<!-- INTERNAL -->

# ภายใน — RaoMembers Stock & Loyalty System (ไม่เผยแพร่)

## โครงสร้างบัญชี

### หลักการสำคัญ
1. **ห้ามลบ** — ถ้าผิดพลาดต้องสร้าง reversal entry (รายการกลับทาง)
2. **ทุก movement → double-entry** ทันที (Dr/Cr สมดุล)
3. **ยอดต้องตรงเสมอ** — Ledger = Balance, Dr = Cr
4. **Immutable** — StockLedger boot() throw LogicException ถ้าพยายาม update/delete

### DocSequenceService
- รูปแบบ: `{TYPE}{YY}{MM}-{NNNN}` เช่น INV2609-0001
- รองรับ: INV, RCP, PAY, TXI, WHT, JV, DLV, CN, QT, PO
- `next(string $type, ?int $orgNodeId)` — nullable orgNodeId fallback = 0

### resolveNodeId() helper
- Fallback: `user->node_id → visibleNodeIds()[0] → 0`
- แก้ปัญหา SystemAdmin ที่ไม่มี node_id

### StockLedgerService
- `recordDelivery()` — ตัดสต๊อก + Journal (Dr.COGS, Cr.Inventory)
- `recordCreditNote()` — เพิ่มสต๊อกกลับ + Reversal Journal
- `recordTransfer()` — โอนทั้งฝั่งออก+เข้า + Journal
- `recordSale()` — ตัดสต๊อก + Revenue Journal
- `verifyBalances()` + `verifyJournals()` — ตรวจสอบยอดตรง

## Sidebar

- Collapsible groups + nested sub-groups
- localStorage จำสถานะ (key: `sidebar_state`)
- Active auto-open เมื่อมี link `.on` ในกลุ่ม
- บัญชีแบ่งเป็น 4 sub-groups: เอกสาร (6), ส่งของ (3), ตรวจสอบ (3), งบการเงิน (6)

## Routes รวม

- Web routes: 60+ routes
- Accounting routes: 40+ routes
- Accounting controller: 58 methods

## Known Issues (Fixed)

| ปัญหา | แก้ |
|---|---|
| DocSequenceService::next() ไม่รับ null | เปลี่ยน `int` → `?int`, fallback = 0 |
| StockLedger table name ผิด | เพิ่ม `$table = 'stock_ledger'` |
| JournalEntry/JournalLine models ไม่มี | สร้างใหม่ |
| Gate::authorize ไม่มี policy | ลบออก ใช้ inline check |
| user->node_id = null (SystemAdmin) | resolveNodeId() helper |
