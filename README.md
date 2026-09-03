# ระบบสต๊อกสินค้าหลายระดับ + QR สะสมคะแนน (Laravel + MySQL)

ไฟล์หลัก: `database/schema.sql` (รันได้ทันทีบน MySQL 8)

## 0. สถานะระบบ (พร้อมใช้งาน)

ระบบถูกสร้างจริงครบทั้ง backend และหน้าเว็บ ผ่านชุดทดสอบอัตโนมัติ **47 เคส / 99 assertions**
และทดสอบสิทธิ์ 22 หน้า × 6 บทบาท = 132 คู่ ไม่มี 5xx

| ส่วน | สถานะ |
|---|---|
| Migration 5 ไฟล์ + Seeder ข้อมูลตัวอย่าง | เสร็จ |
| Model 20 ตัว + Service 6 ตัว (Stock/Transfer/Sale/Qr/Point/Report) | เสร็จ |
| Policy 5 ตัว + 9 abilities คุมสิทธิ์ 2 แกน (บทบาท × ขอบเขตสายงาน) | เสร็จ |
| หน้าเว็บผู้ดูแล 22 หน้า (dashboard, POS, ใบโอน, สินค้า/QR, นับสต๊อก, ลูกค้า, ของรางวัล, รายงาน) | เสร็จ |
| **หน้าลูกค้าสแกน QR สะสมคะแนน + แลกของรางวัล** (มือถือ, ไม่ต้องล็อกอิน) | เสร็จ |
| API สำหรับแอป (`routes/api.php`) | เสร็จ |
| ชุดทดสอบอัตโนมัติ `php artisan test` | เสร็จ |

ดูวิธีติดตั้งและตาราง URL ทั้งหมดที่ `INSTALL.md` · ดูรายละเอียดสิทธิ์ที่ `PERMISSIONS.md`

## 1. โครงสร้างสายงาน (6 ระดับ)

```
Lv1 เจ้าของระบบ (HQ)
 └─ Lv2 คลังใหญ่
     └─ Lv3 คลังย่อย
         └─ Lv4 ตัวแทนขาย
             └─ Lv5 ร้านค้า
                 └─ Lv6 ผู้ขาย
```

ใช้ตารางเดียว `org_nodes` (self-referencing) + `org_levels`
- `parent_id` = สังกัดใคร, `level_id` = อยู่ระดับไหน
- `path` เช่น `/1/2/3/` → ดูลูกหลานทั้งสายด้วย `WHERE path LIKE '/1/2/%'` (เร็ว ไม่ต้อง recursive)
- Trigger บังคับว่า parent ต้องเป็นระดับที่สูงกว่า 1 ขั้นเสมอ

**สิทธิ์การมองเห็น (Data Scope):** ผู้ใช้เห็นเฉพาะโหนดตัวเอง + ลูกหลาน
```sql
SELECT * FROM org_nodes
WHERE id = :myNode OR path LIKE CONCAT((SELECT path FROM org_nodes WHERE id=:myNode), :myNode, '/%');
```
ใน Laravel ทำเป็น Global Scope ครอบทุก Model ที่มี `org_node_id`

## 2. หลักการสต๊อก
- **`stock_movements`** = แหล่งความจริง (append-only ห้ามแก้/ลบ)
- **`stock_balances`** = ยอดสรุปต่อ (โหนด × สินค้า × ล็อต) อัปเดตใน DB transaction เดียวกับ movement
- ยอดคงเหลือ 3 ช่อง: `qty_on_hand`, `qty_reserved` (จองในบิล), `qty_in_transit` (กำลังส่งมา)
- Available = on_hand − reserved

### Flow การโอนของลงระดับล่าง (`transfers`)
```
draft → pending_approve → approved → shipped → received
```
| ขั้นตอน | ผลต่อสต๊อก |
|---|---|
| approved | ต้นทาง `qty_reserved +N` |
| shipped  | ต้นทาง on_hand −N, reserved −N (movement `transfer_out`) / ปลายทาง `in_transit +N` |
| received | ปลายทาง in_transit −N, on_hand +N (movement `transfer_in`) |
| ส่วนต่างตอนรับ | ลงเป็น `adjust_out` / `damage` พร้อมหมายเหตุ |

ทิศทางที่อนุญาต: โอนลงหาลูกโดยตรง (`to.parent_id = from.id`) หรือคืนขึ้น (`type='return'`)

### ขาย (`sales`)
ขายที่ Lv5/Lv6 → ตัด `on_hand` + สร้าง movement `sale` + ปั๊ม QR ของชิ้นที่ขายเป็น `sold` (activated)

## 3. ระบบ QR สะสมคะแนน

`product_qrcodes` — 1 แถว = 1 ชิ้น
- `qr_token` (32 ตัวสุ่ม) ใช้ใน URL: `https://app.example.com/s/{qr_token}`
- `secret_hash` = SHA-256 ของรหัสใต้ฟิล์มขูด → กันคนถ่ายรูป QR บนชั้นวางแล้วสแกนชิงคะแนน
- `status`: `created → in_stock → sold → redeemed`

### Flow สแกน
1. ลูกค้าสแกน → เปิดหน้าเว็บ/LIFF → ยืนยันเบอร์ด้วย OTP (สร้าง/ดึง `customers`)
2. กรอกรหัสใต้ฟิล์ม → เทียบ `secret_hash`
3. ตรวจเงื่อนไข → เขียน `qr_scan_logs` ทุกกรณี (สำเร็จ/ไม่สำเร็จ)
4. ถ้าผ่าน: `UPDATE product_qrcodes SET status='redeemed'...` แบบมีเงื่อนไข (กัน race)
   ```sql
   UPDATE product_qrcodes SET status='redeemed', redeemed_at=NOW(), redeemed_by_customer_id=:c
   WHERE id=:id AND status IN ('sold','in_stock');  -- affected_rows=0 แปลว่าโดนใช้ไปแล้ว
   ```
5. `INSERT point_transactions (earn_scan)` + `customers.points_balance += points`

### กันโกง
- 1 QR = 1 ครั้งตลอดกาล (unique + conditional update)
- Rate limit: ต่อเบอร์/ต่อ IP/ต่อวัน → บันทึก `rate_limited` ใน log
- แจ้งเตือนเมื่อมีการสแกนล็อตเดียวกันจำนวนมากจาก IP เดียว หรือสแกน QR ที่ยัง `in_stock` (ยังไม่ถูกขาย = ของหลุด/ปลอม)
- เก็บ lat/lng เทียบกับตำแหน่งร้านที่จ่ายของล็อตนั้นออกไป

### Track & Trace
เพราะ QR ผูก `current_node_id` และมี `stock_movements` ครบ → ตรวจได้ว่าของชิ้นนี้ผ่านคลังไหน ตัวแทนใคร ร้านไหน (ใช้จับของหลุดโซน/สินค้าปลอม)

## 4. คะแนน
- `point_transactions` เป็น ledger (`earn_scan / earn_bonus / redeem / expire / adjust / reverse`)
- `customers.points_balance` เป็นยอด denormalized → มี job กระทบยอดรายวัน
- แลกของ: `rewards` + `reward_redemptions`

## 5. สรุปตาราง
| กลุ่ม | ตาราง |
|---|---|
| องค์กร | org_levels, org_nodes, users, customers |
| สินค้า | categories, units, products, product_level_prices, product_lots |
| สต๊อก | stock_balances, stock_movements |
| เอกสาร | transfers, transfer_items, sales, sale_items |
| QR/คะแนน | product_qrcodes, qr_scan_logs, point_transactions, rewards, reward_redemptions |
| อื่นๆ | commission_rules, commission_entries, audit_logs |

## 6. ข้อแนะนำการ implement (Laravel)
- ทุกการตัด/รับสต๊อก ห่อด้วย `DB::transaction()` + `lockForUpdate()` บนแถว `stock_balances`
- Service ควรมี: `StockService`, `TransferService`, `QrScanService`, `PointService`
- ตาราง log ที่โตเร็ว (`stock_movements`, `qr_scan_logs`) → พิจารณา partition รายเดือน
- generate QR ล่วงหน้าเป็น batch ตอนสร้าง `product_lots` (job แบบ chunk)
