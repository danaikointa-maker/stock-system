-- =====================================================================
-- ระบบสต๊อกสินค้าแบบหลายระดับ (Multi-level Inventory) + QR สะสมคะแนน
-- MySQL 8.0 / MariaDB 10.6+  | charset utf8mb4
-- โครงสร้างสายงาน: เจ้าของระบบ -> คลังใหญ่ -> คลังย่อย -> ตัวแทนขาย -> ร้านค้า -> ผู้ขาย
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1) โครงสร้างองค์กร / ระดับชั้น (Hierarchy)
-- ---------------------------------------------------------------------

-- ระดับชั้นในสายงาน (กำหนดเป็นข้อมูล ไม่ hard-code)
CREATE TABLE org_levels (
  id            TINYINT UNSIGNED PRIMARY KEY,          -- 1..6
  code          VARCHAR(30)  NOT NULL UNIQUE,          -- system_owner, main_warehouse, ...
  name_th       VARCHAR(100) NOT NULL,
  can_hold_stock TINYINT(1)  NOT NULL DEFAULT 1,       -- ระดับนี้ถือสต๊อกได้ไหม
  created_at    TIMESTAMP NULL, updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO org_levels (id, code, name_th, can_hold_stock) VALUES
 (1,'system_owner','เจ้าของระบบ',1),
 (2,'main_warehouse','คลังใหญ่',1),
 (3,'sub_warehouse','คลังย่อย',1),
 (4,'agent','ตัวแทนขาย',1),
 (5,'shop','ร้านค้า',1),
 (6,'seller','ผู้ขาย',1);

-- หน่วยงาน/โหนดในสายงาน (ใช้ adjacency list + materialized path เพื่อ query ลูกหลานเร็ว)
CREATE TABLE org_nodes (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id     BIGINT UNSIGNED NULL,
  level_id      TINYINT UNSIGNED NOT NULL,
  code          VARCHAR(50)  NOT NULL UNIQUE,          -- WH-BKK-01, AG-0012
  name          VARCHAR(150) NOT NULL,
  path          VARCHAR(500) NOT NULL,                 -- '/1/3/17/' รวม id ตัวเอง
  depth         TINYINT UNSIGNED NOT NULL DEFAULT 0,
  phone         VARCHAR(30) NULL,
  address       TEXT NULL,
  lat DECIMAL(10,7) NULL, lng DECIMAL(10,7) NULL,
  credit_limit  DECIMAL(14,2) NOT NULL DEFAULT 0,
  status        ENUM('active','suspended','closed') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  CONSTRAINT fk_node_parent FOREIGN KEY (parent_id) REFERENCES org_nodes(id),
  CONSTRAINT fk_node_level  FOREIGN KEY (level_id)  REFERENCES org_levels(id),
  INDEX idx_node_parent (parent_id),
  INDEX idx_node_level  (level_id),
  INDEX idx_node_path   (path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- กติกา: parent ต้องมี level_id = ลูก - 1 (บังคับใน Application Layer / Trigger ด้านล่าง)

-- ---------------------------------------------------------------------
-- 2) ผู้ใช้ / สิทธิ์
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_node_id   BIGINT UNSIGNED NULL,                  -- ผู้ใช้สังกัดโหนดไหน
  name          VARCHAR(150) NOT NULL,
  email         VARCHAR(150) NULL UNIQUE,
  phone         VARCHAR(30)  NULL UNIQUE,
  password      VARCHAR(255) NOT NULL,
  role          ENUM('system_admin','warehouse_admin','agent_user','shop_user','seller_user','viewer')
                NOT NULL DEFAULT 'viewer',
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  remember_token VARCHAR(100) NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  CONSTRAINT fk_user_node FOREIGN KEY (org_node_id) REFERENCES org_nodes(id),
  INDEX idx_user_node (org_node_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ลูกค้าปลายทาง (คนสแกน QR รับคะแนน) แยกจาก users ของระบบหลังบ้าน
CREATE TABLE customers (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  phone         VARCHAR(30) NOT NULL UNIQUE,
  name          VARCHAR(150) NULL,
  line_user_id  VARCHAR(100) NULL UNIQUE,
  points_balance INT NOT NULL DEFAULT 0,               -- ยอดคงเหลือ (denormalized)
  tier          ENUM('bronze','silver','gold','platinum') NOT NULL DEFAULT 'bronze',
  referred_by_node_id BIGINT UNSIGNED NULL,            -- ผูกกับร้าน/ผู้ขายที่แนะนำ
  status        ENUM('active','blocked') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT fk_cust_node FOREIGN KEY (referred_by_node_id) REFERENCES org_nodes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3) สินค้า
-- ---------------------------------------------------------------------
CREATE TABLE categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT fk_cat_parent FOREIGN KEY (parent_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE units (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE                     -- ชิ้น, กล่อง, ลัง
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku           VARCHAR(64)  NOT NULL UNIQUE,
  barcode       VARCHAR(64)  NULL UNIQUE,
  name          VARCHAR(200) NOT NULL,
  category_id   BIGINT UNSIGNED NULL,
  unit_id       BIGINT UNSIGNED NULL,
  pack_size     INT UNSIGNED NOT NULL DEFAULT 1,       -- 1 ลัง = กี่ชิ้น
  cost_price    DECIMAL(12,2) NOT NULL DEFAULT 0,
  retail_price  DECIMAL(12,2) NOT NULL DEFAULT 0,
  points_per_unit INT NOT NULL DEFAULT 0,              -- คะแนนต่อ 1 ชิ้นเมื่อสแกน QR
  track_serial  TINYINT(1) NOT NULL DEFAULT 1,         -- ใช้ QR ต่อชิ้นหรือไม่
  has_expiry    TINYINT(1) NOT NULL DEFAULT 0,
  image_url     VARCHAR(255) NULL,
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  CONSTRAINT fk_prod_cat  FOREIGN KEY (category_id) REFERENCES categories(id),
  CONSTRAINT fk_prod_unit FOREIGN KEY (unit_id) REFERENCES units(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ราคาขายต่อระดับชั้น (คลังใหญ่ขายให้คลังย่อยราคาหนึ่ง, ตัวแทนขายให้ร้านอีกราคา)
CREATE TABLE product_level_prices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  level_id   TINYINT UNSIGNED NOT NULL,                -- ราคาที่ "ระดับนี้" ซื้อเข้า
  price      DECIMAL(12,2) NOT NULL,
  min_qty    INT UNSIGNED NOT NULL DEFAULT 1,
  effective_from DATE NULL, effective_to DATE NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT fk_plp_prod  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_plp_level FOREIGN KEY (level_id) REFERENCES org_levels(id),
  UNIQUE KEY uq_plp (product_id, level_id, min_qty, effective_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ล็อตสินค้า (ผลิต/นำเข้า)
CREATE TABLE product_lots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id  BIGINT UNSIGNED NOT NULL,
  lot_no      VARCHAR(64) NOT NULL,
  mfg_date    DATE NULL,
  expiry_date DATE NULL,
  qty_produced INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT fk_lot_prod FOREIGN KEY (product_id) REFERENCES products(id),
  UNIQUE KEY uq_lot (product_id, lot_no),
  INDEX idx_lot_exp (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4) สต๊อกคงเหลือ + Ledger (แหล่งความจริง)
-- ---------------------------------------------------------------------

-- ยอดคงเหลือรายโหนด/รายล็อต  (สรุป, อัปเดตพร้อม ledger ในทรานแซกชันเดียว)
CREATE TABLE stock_balances (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_node_id BIGINT UNSIGNED NOT NULL,
  product_id  BIGINT UNSIGNED NOT NULL,
  lot_id      BIGINT UNSIGNED NULL,
  qty_on_hand  INT NOT NULL DEFAULT 0,
  qty_reserved INT NOT NULL DEFAULT 0,                 -- จองไว้ในบิลที่ยังไม่ส่ง
  qty_in_transit INT NOT NULL DEFAULT 0,               -- กำลังส่งมาหาโหนดนี้
  reorder_point INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NULL,
  CONSTRAINT fk_bal_node FOREIGN KEY (org_node_id) REFERENCES org_nodes(id),
  CONSTRAINT fk_bal_prod FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_bal_lot  FOREIGN KEY (lot_id) REFERENCES product_lots(id),
  UNIQUE KEY uq_balance (org_node_id, product_id, lot_id),
  INDEX idx_bal_prod (product_id),
  CONSTRAINT chk_bal_nonneg CHECK (qty_on_hand >= 0 AND qty_reserved >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- บันทึกการเคลื่อนไหวทุกชิ้น (append-only, ห้ามแก้ย้อนหลัง)
CREATE TABLE stock_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_node_id BIGINT UNSIGNED NOT NULL,
  product_id  BIGINT UNSIGNED NOT NULL,
  lot_id      BIGINT UNSIGNED NULL,
  direction   ENUM('in','out') NOT NULL,
  qty         INT UNSIGNED NOT NULL,
  balance_after INT NOT NULL,
  type        ENUM('receipt','transfer_out','transfer_in','sale','return_in','return_out',
                   'adjust_in','adjust_out','damage','expired') NOT NULL,
  ref_type    VARCHAR(50) NULL,                        -- App\Models\Transfer, Sale...
  ref_id      BIGINT UNSIGNED NULL,
  unit_cost   DECIMAL(12,2) NULL,
  note        VARCHAR(255) NULL,
  created_by  BIGINT UNSIGNED NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mv_node FOREIGN KEY (org_node_id) REFERENCES org_nodes(id),
  CONSTRAINT fk_mv_prod FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_mv_lot  FOREIGN KEY (lot_id) REFERENCES product_lots(id),
  CONSTRAINT fk_mv_user FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_mv_node_prod (org_node_id, product_id, created_at),
  INDEX idx_mv_ref (ref_type, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5) การโอนสินค้าระหว่างระดับชั้น (Transfer / Requisition)
-- ---------------------------------------------------------------------
CREATE TABLE transfers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doc_no      VARCHAR(40) NOT NULL UNIQUE,             -- TRF-2026-000123
  from_node_id BIGINT UNSIGNED NOT NULL,
  to_node_id   BIGINT UNSIGNED NOT NULL,
  type        ENUM('allocation','requisition','return') NOT NULL DEFAULT 'allocation',
  status      ENUM('draft','pending_approve','approved','rejected','shipped','received','cancelled')
              NOT NULL DEFAULT 'draft',
  total_qty   INT NOT NULL DEFAULT 0,
  total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  requested_by BIGINT UNSIGNED NULL,
  approved_by  BIGINT UNSIGNED NULL, approved_at DATETIME NULL,
  shipped_at   DATETIME NULL,
  received_by  BIGINT UNSIGNED NULL, received_at DATETIME NULL,
  note        TEXT NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT fk_trf_from FOREIGN KEY (from_node_id) REFERENCES org_nodes(id),
  CONSTRAINT fk_trf_to   FOREIGN KEY (to_node_id)   REFERENCES org_nodes(id),
  CONSTRAINT fk_trf_req  FOREIGN KEY (requested_by) REFERENCES users(id),
  CONSTRAINT fk_trf_apv  FOREIGN KEY (approved_by)  REFERENCES users(id),
  CONSTRAINT fk_trf_rcv  FOREIGN KEY (received_by)  REFERENCES users(id),
  INDEX idx_trf_from (from_node_id, status),
  INDEX idx_trf_to   (to_node_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transfer_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transfer_id BIGINT UNSIGNED NOT NULL,
  product_id  BIGINT UNSIGNED NOT NULL,
  lot_id      BIGINT UNSIGNED NULL,
  qty_requested INT UNSIGNED NOT NULL,
  qty_shipped   INT UNSIGNED NOT NULL DEFAULT 0,
  qty_received  INT UNSIGNED NOT NULL DEFAULT 0,
  unit_price  DECIMAL(12,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_ti_trf  FOREIGN KEY (transfer_id) REFERENCES transfers(id) ON DELETE CASCADE,
  CONSTRAINT fk_ti_prod FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_ti_lot  FOREIGN KEY (lot_id) REFERENCES product_lots(id),
  INDEX idx_ti_trf (transfer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6) การขายที่หน้าร้าน / ผู้ขาย
-- ---------------------------------------------------------------------
CREATE TABLE sales (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doc_no      VARCHAR(40) NOT NULL UNIQUE,
  org_node_id BIGINT UNSIGNED NOT NULL,                -- ร้านค้า/ผู้ขายที่ขาย
  seller_user_id BIGINT UNSIGNED NULL,
  customer_id BIGINT UNSIGNED NULL,
  sold_at     DATETIME NOT NULL,
  subtotal    DECIMAL(14,2) NOT NULL DEFAULT 0,
  discount    DECIMAL(14,2) NOT NULL DEFAULT 0,
  total       DECIMAL(14,2) NOT NULL DEFAULT 0,
  payment_method ENUM('cash','transfer','qr','credit') NOT NULL DEFAULT 'cash',
  status      ENUM('completed','voided') NOT NULL DEFAULT 'completed',
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT fk_sale_node FOREIGN KEY (org_node_id) REFERENCES org_nodes(id),
  CONSTRAINT fk_sale_user FOREIGN KEY (seller_user_id) REFERENCES users(id),
  CONSTRAINT fk_sale_cust FOREIGN KEY (customer_id) REFERENCES customers(id),
  INDEX idx_sale_node_date (org_node_id, sold_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sale_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id    BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  lot_id     BIGINT UNSIGNED NULL,
  qty        INT UNSIGNED NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  discount   DECIMAL(12,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(14,2) NOT NULL,
  CONSTRAINT fk_si_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  CONSTRAINT fk_si_prod FOREIGN KEY (product_id) REFERENCES products(id),
  INDEX idx_si_sale (sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7) QR Code รายชิ้น + คะแนนสะสม
-- ---------------------------------------------------------------------

-- QR 1 แถว = สินค้า 1 ชิ้น (serialized) ใช้ทั้ง track & trace และรับคะแนน
CREATE TABLE product_qrcodes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id  BIGINT UNSIGNED NOT NULL,
  lot_id      BIGINT UNSIGNED NULL,
  serial_no   VARCHAR(40) NOT NULL UNIQUE,             -- รหัสอ่านได้ เช่น SN-000001
  qr_token    CHAR(32) NOT NULL UNIQUE,                -- token สุ่มใน URL (กันเดา)
  secret_hash CHAR(64) NULL,                           -- hash ของรหัสใต้ฟิล์มขูด (กันปลอม)
  points      INT NOT NULL DEFAULT 0,                  -- คะแนนของชิ้นนี้ (snapshot)
  current_node_id BIGINT UNSIGNED NULL,                -- อยู่ที่โหนดไหนตอนนี้
  status      ENUM('created','in_stock','sold','redeemed','void') NOT NULL DEFAULT 'created',
  activated_at DATETIME NULL,                          -- เปิดใช้งานตอนขายออกจากร้าน
  redeemed_at  DATETIME NULL,
  redeemed_by_customer_id BIGINT UNSIGNED NULL,
  expires_at   DATETIME NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT fk_qr_prod FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_qr_lot  FOREIGN KEY (lot_id) REFERENCES product_lots(id),
  CONSTRAINT fk_qr_node FOREIGN KEY (current_node_id) REFERENCES org_nodes(id),
  CONSTRAINT fk_qr_cust FOREIGN KEY (redeemed_by_customer_id) REFERENCES customers(id),
  INDEX idx_qr_status (status),
  INDEX idx_qr_lot (lot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- log ทุกครั้งที่มีการสแกน (สำเร็จ/ไม่สำเร็จ) ใช้ตรวจของปลอม & พฤติกรรมโกง
CREATE TABLE qr_scan_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  qrcode_id   BIGINT UNSIGNED NULL,
  raw_token   VARCHAR(64) NULL,
  customer_id BIGINT UNSIGNED NULL,
  org_node_id BIGINT UNSIGNED NULL,                    -- สแกนที่ร้านไหน (ถ้าทราบ)
  result      ENUM('success','already_used','invalid','expired','rate_limited','blocked')
              NOT NULL,
  points_awarded INT NOT NULL DEFAULT 0,
  ip_address  VARCHAR(45) NULL,
  user_agent  VARCHAR(255) NULL,
  lat DECIMAL(10,7) NULL, lng DECIMAL(10,7) NULL,
  scanned_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_scan_qr   FOREIGN KEY (qrcode_id) REFERENCES product_qrcodes(id),
  CONSTRAINT fk_scan_cust FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_scan_node FOREIGN KEY (org_node_id) REFERENCES org_nodes(id),
  INDEX idx_scan_cust_time (customer_id, scanned_at),
  INDEX idx_scan_result (result, scanned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- บัญชีแยกประเภทคะแนน (append-only) -> customers.points_balance คือผลรวม
CREATE TABLE point_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  type        ENUM('earn_scan','earn_bonus','redeem','expire','adjust','reverse') NOT NULL,
  points      INT NOT NULL,                            -- + ได้รับ / - ใช้ไป
  balance_after INT NOT NULL,
  ref_type    VARCHAR(50) NULL, ref_id BIGINT UNSIGNED NULL,
  expires_at  DATE NULL,
  note        VARCHAR(255) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pt_cust FOREIGN KEY (customer_id) REFERENCES customers(id),
  INDEX idx_pt_cust (customer_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ของรางวัล / แลกคะแนน
CREATE TABLE rewards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  points_cost INT UNSIGNED NOT NULL,
  stock_qty   INT NOT NULL DEFAULT 0,
  image_url   VARCHAR(255) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reward_redemptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  reward_id   BIGINT UNSIGNED NOT NULL,
  points_used INT UNSIGNED NOT NULL,
  status ENUM('pending','approved','shipped','completed','rejected') NOT NULL DEFAULT 'pending',
  address TEXT NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT fk_rr_cust FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_rr_rw   FOREIGN KEY (reward_id) REFERENCES rewards(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8) คอมมิชชัน & Audit
-- ---------------------------------------------------------------------
CREATE TABLE commission_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  level_id TINYINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,                     -- NULL = ทุกสินค้า
  calc_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  value DECIMAL(10,2) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_cr_level FOREIGN KEY (level_id) REFERENCES org_levels(id),
  CONSTRAINT fk_cr_prod  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE commission_entries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_node_id BIGINT UNSIGNED NOT NULL,
  sale_id BIGINT UNSIGNED NULL,
  amount DECIMAL(14,2) NOT NULL,
  period VARCHAR(7) NOT NULL,                          -- 2026-09
  status ENUM('pending','paid','void') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NULL,
  CONSTRAINT fk_ce_node FOREIGN KEY (org_node_id) REFERENCES org_nodes(id),
  CONSTRAINT fk_ce_sale FOREIGN KEY (sale_id) REFERENCES sales(id),
  INDEX idx_ce_period (period, org_node_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  org_node_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  auditable_type VARCHAR(100) NULL, auditable_id BIGINT UNSIGNED NULL,
  old_values JSON NULL, new_values JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_obj (auditable_type, auditable_id),
  INDEX idx_audit_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- TRIGGER: บังคับให้ parent มีระดับสูงกว่าลูก 1 ขั้น + สร้าง path อัตโนมัติ
-- =====================================================================
DELIMITER //
CREATE TRIGGER trg_org_nodes_bi BEFORE INSERT ON org_nodes
FOR EACH ROW
BEGIN
  DECLARE p_level TINYINT UNSIGNED;
  DECLARE p_path  VARCHAR(500);
  IF NEW.parent_id IS NULL THEN
    IF NEW.level_id <> 1 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'เฉพาะเจ้าของระบบเท่านั้นที่ไม่มี parent';
    END IF;
    SET NEW.depth = 0; SET NEW.path = '/';
  ELSE
    SELECT level_id, path INTO p_level, p_path FROM org_nodes WHERE id = NEW.parent_id;
    IF p_level + 1 <> NEW.level_id THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ระดับชั้นของ parent ไม่ถูกต้อง (ต้องสูงกว่า 1 ขั้น)';
    END IF;
    SET NEW.depth = p_level;
    SET NEW.path = CONCAT(p_path, NEW.parent_id, '/');
  END IF;
END//
DELIMITER ;

-- =====================================================================
-- VIEW ช่วยรายงาน
-- =====================================================================
CREATE OR REPLACE VIEW v_stock_summary AS
SELECT n.id AS node_id, n.code AS node_code, n.name AS node_name,
       l.name_th AS level_name, p.id AS product_id, p.sku, p.name AS product_name,
       SUM(b.qty_on_hand) AS on_hand,
       SUM(b.qty_reserved) AS reserved,
       SUM(b.qty_on_hand - b.qty_reserved) AS available,
       SUM(b.qty_in_transit) AS in_transit
FROM stock_balances b
JOIN org_nodes n ON n.id = b.org_node_id
JOIN org_levels l ON l.id = n.level_id
JOIN products p ON p.id = b.product_id
GROUP BY n.id, n.code, n.name, l.name_th, p.id, p.sku, p.name;

CREATE OR REPLACE VIEW v_low_stock AS
SELECT * FROM stock_balances WHERE qty_on_hand <= reorder_point AND reorder_point > 0;

-- =====================================================================
-- ตัวอย่างข้อมูล (Demo)
-- =====================================================================
INSERT INTO org_nodes (parent_id, level_id, code, name) VALUES (NULL,1,'HQ','เจ้าของระบบ (HQ)');
INSERT INTO org_nodes (parent_id, level_id, code, name) VALUES (1,2,'WH-BKK','คลังใหญ่ กรุงเทพฯ');
INSERT INTO org_nodes (parent_id, level_id, code, name) VALUES (2,3,'SWH-NT','คลังย่อย นนทบุรี');
INSERT INTO org_nodes (parent_id, level_id, code, name) VALUES (3,4,'AG-001','ตัวแทนขาย สมชาย');
INSERT INTO org_nodes (parent_id, level_id, code, name) VALUES (4,5,'SH-001','ร้านค้า ป้าแดง');
INSERT INTO org_nodes (parent_id, level_id, code, name) VALUES (5,6,'SL-001','ผู้ขาย นายเอ');

INSERT INTO units (name) VALUES ('ชิ้น'),('กล่อง'),('ลัง');
INSERT INTO categories (name) VALUES ('เครื่องดื่ม'),('ของใช้');
INSERT INTO products (sku,name,category_id,unit_id,pack_size,cost_price,retail_price,points_per_unit,track_serial)
VALUES ('SKU-001','น้ำดื่มวิตามิน 500ml',1,1,12,8.00,15.00,5,1);

INSERT INTO product_level_prices (product_id, level_id, price) VALUES
 (1,2,8.50),(1,3,9.50),(1,4,10.50),(1,5,12.00),(1,6,13.00);
