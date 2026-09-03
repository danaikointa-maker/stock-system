-- =====================================================================
-- RoaMembers — ระบบแต้ม (v3 : แพ็กเกจรายเดือน ไม่มีโควต้าไหลตามสาย)
--
-- ── สรุปโมเดล ────────────────────────────────────────────────────
--   1) เจ้าของระบบ/แอดมิน สร้าง "แพ็กเกจ" ไว้ล่วงหน้า
--      เช่น Silver 12 เดือน / รับแลกได้ 10,000 แต้มต่อเดือน / 5,000 บาท
--   2) ตัวแทนกรอกใบสมัครให้ร้าน แค่เลือกแพ็กเกจ ระบบคำนวณให้ทันที
--   3) ร้านมี "วงเงินแลกรายเดือน" รีเซตทุกวันที่ 1 ใช้ไม่หมดไม่ทบ
--   4) หมดวงเงินเดือนนั้น = หยุดรับแลก รอเดือนถัดไป (หรือซื้อ top-up)
--   5) หมดอายุสมาชิก / ยกเลิก / ไม่ต่ออายุ = หยุดรับแลกถาวร
--   6) ไม่มีโควต้าไหลลงตามสาย ไม่มีหนี้ระหว่างร้าน
--   7) สายงานเห็นรายงานของลูกสาย สรุปจบที่เจ้าของระบบ
--   8) เจ้าของระบบเป็นผู้จ่ายเงินคืนให้ร้านโดยตรง
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 0) ค่าคงที่ของระบบ — แก้ได้เฉพาะเจ้าของระบบ / แอดมิน
-- ---------------------------------------------------------------------
CREATE TABLE system_settings (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  skey        VARCHAR(80) NOT NULL UNIQUE,
  svalue      VARCHAR(255) NOT NULL,
  value_type  ENUM('int','decimal','string','bool','json') NOT NULL DEFAULT 'string',
  description VARCHAR(255) NULL,
  owner_only  TINYINT(1) NOT NULL DEFAULT 1,
  updated_by  BIGINT UNSIGNED NULL,
  created_at  TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  CONSTRAINT fk_ss_user FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (skey,svalue,value_type,description,owner_only,created_at,updated_at) VALUES
 ('point_value_baht','0.25','decimal','มูลค่าเงินของ 1 แต้ม (บาท) — เจ้าของระบบเท่านั้นที่แก้ได้',1,NOW(),NOW()),
 ('point_expire_months','12','int','อายุแต้มของลูกค้า (เดือน) แบบ FIFO',1,NOW(),NOW()),
 ('monthly_reset_day','1','int','วันที่รีเซตวงเงินรายเดือนของร้าน',1,NOW(),NOW()),
 ('allow_topup','1','bool','อนุญาตให้ร้านซื้อวงเงินเพิ่มกลางเดือนหรือไม่',1,NOW(),NOW()),
 ('low_balance_percent','20','int','แจ้งเตือนเมื่อวงเงินเหลือต่ำกว่ากี่ %',1,NOW(),NOW()),
 ('scan_daily_limit','20','int','จำกัดจำนวนสแกนต่อลูกค้าต่อวัน (กันโกง)',1,NOW(),NOW()),
 ('claim_min_points','400','int','แต้มขั้นต่ำที่ร้านจะยื่นเบิกเงินได้',1,NOW(),NOW()),
 ('max_social_links_default','5','int','จำนวน LINE/Google ที่ผู้ใช้ระบบผูกได้',1,NOW(),NOW());

CREATE TABLE point_value_history (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  old_value    DECIMAL(10,4) NULL,
  new_value    DECIMAL(10,4) NOT NULL,
  reason       VARCHAR(255) NULL,
  effective_at DATETIME NOT NULL,
  changed_by   BIGINT UNSIGNED NOT NULL,
  created_at   TIMESTAMP NULL,
  CONSTRAINT fk_pvh_user FOREIGN KEY (changed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 1) แพ็กเกจ — แอดมินตั้งไว้ ตัวแทนแค่เลือก
-- =====================================================================
CREATE TABLE shop_packages (
  id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  code                VARCHAR(30) NOT NULL UNIQUE,      -- PKG-SILVER
  name                VARCHAR(120) NOT NULL,            -- แพ็กเกจเงิน
  tagline             VARCHAR(200) NULL,
  duration_months     SMALLINT UNSIGNED NOT NULL,       -- อายุสมาชิก (เดือน)
  monthly_point_limit BIGINT NOT NULL,                  -- รับแลกได้กี่แต้ม/เดือน
  price               DECIMAL(12,2) NOT NULL,           -- ค่าสมัคร
  allow_rollover      TINYINT(1) NOT NULL DEFAULT 0,    -- ใช้ไม่หมดทบเดือนหน้าไหม
  allow_cash_redeem   TINYINT(1) NOT NULL DEFAULT 0,    -- ให้ลูกค้าแลกเป็นเงินสดได้ไหม
  agent_commission_pct DECIMAL(5,2) NOT NULL DEFAULT 0, -- คอมฯ ตัวแทน (%)
  sort_order          SMALLINT NOT NULL DEFAULT 0,
  is_active           TINYINT(1) NOT NULL DEFAULT 1,
  note                TEXT NULL,
  created_by          BIGINT UNSIGNED NULL,
  created_at          TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  INDEX idx_pkg_active (is_active, sort_order),
  CONSTRAINT fk_pkg_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO shop_packages
 (code,name,tagline,duration_months,monthly_point_limit,price,allow_rollover,
  allow_cash_redeem,agent_commission_pct,sort_order,is_active,created_at,updated_at) VALUES
 ('PKG-BRONZE','แพ็กเกจทองแดง','เหมาะกับร้านเล็ก เริ่มต้นง่าย',
   6,  4000,  2500.00, 0, 0, 10.00, 1, 1, NOW(), NOW()),
 ('PKG-SILVER','แพ็กเกจเงิน','ยอดนิยม คุ้มที่สุด',
   12, 10000, 5000.00, 0, 0, 12.00, 2, 1, NOW(), NOW()),
 ('PKG-GOLD','แพ็กเกจทอง','ร้านใหญ่ ลูกค้าเยอะ',
   12, 25000, 10000.00, 1, 1, 15.00, 3, 1, NOW(), NOW()),
 ('PKG-TRIAL','ทดลองใช้ฟรี','ทดลอง 1 เดือน',
   1,  1000,  0.00, 0, 0, 0.00, 0, 1, NOW(), NOW());

-- =====================================================================
-- 2) การสมัครของร้าน (ตัวแทนเป็นคนกรอก)
-- =====================================================================
CREATE TABLE shop_subscriptions (
  id                BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  code              VARCHAR(30) NOT NULL UNIQUE,     -- SUB-2569-0001
  shop_node_id      BIGINT UNSIGNED NOT NULL,
  package_id        BIGINT UNSIGNED NOT NULL,
  recruiter_node_id BIGINT UNSIGNED NOT NULL,        -- ตัวแทนที่พาเข้ามา
  -- ค่าที่ล็อกไว้ตอนสมัคร (ถ้าแอดมินแก้แพ็กเกจภายหลัง สัญญาเดิมไม่เปลี่ยน)
  monthly_point_limit BIGINT NOT NULL,
  price_paid        DECIMAL(12,2) NOT NULL,
  allow_rollover    TINYINT(1) NOT NULL DEFAULT 0,
  allow_cash_redeem TINYINT(1) NOT NULL DEFAULT 0,
  commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  starts_on         DATE NOT NULL,
  ends_on           DATE NOT NULL,
  status            ENUM('pending_payment','active','expired','cancelled','suspended')
                    NOT NULL DEFAULT 'pending_payment',
  auto_renew        TINYINT(1) NOT NULL DEFAULT 0,
  paid_at           DATETIME NULL,
  payment_ref       VARCHAR(120) NULL,
  approved_by       BIGINT UNSIGNED NULL,
  cancelled_at      DATETIME NULL,
  cancel_reason     VARCHAR(255) NULL,
  note              TEXT NULL,
  created_at        TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  INDEX idx_sub_shop (shop_node_id, status),
  INDEX idx_sub_recruiter (recruiter_node_id),
  INDEX idx_sub_ends (ends_on, status),
  CONSTRAINT fk_sub_shop      FOREIGN KEY (shop_node_id)      REFERENCES org_nodes(id),
  CONSTRAINT fk_sub_pkg       FOREIGN KEY (package_id)        REFERENCES shop_packages(id),
  CONSTRAINT fk_sub_recruiter FOREIGN KEY (recruiter_node_id) REFERENCES org_nodes(id),
  CONSTRAINT fk_sub_user      FOREIGN KEY (approved_by)       REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 3) วงเงินรายเดือน — รีเซตทุกเดือน (หัวใจของระบบใหม่)
--    1 ร้าน 1 เดือน = 1 แถว
-- =====================================================================
CREATE TABLE shop_monthly_allowances (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  subscription_id BIGINT UNSIGNED NOT NULL,
  shop_node_id    BIGINT UNSIGNED NOT NULL,
  period_ym       CHAR(7) NOT NULL,                  -- '2569-09'
  limit_points    BIGINT NOT NULL,                   -- วงเงินเดือนนี้
  rollover_points BIGINT NOT NULL DEFAULT 0,         -- ทบมาจากเดือนก่อน
  topup_points    BIGINT NOT NULL DEFAULT 0,         -- ซื้อเพิ่มกลางเดือน
  used_points     BIGINT NOT NULL DEFAULT 0,         -- ใช้ไปแล้ว
  remaining_points BIGINT NOT NULL DEFAULT 0,        -- คงเหลือ
  redemption_count INT UNSIGNED NOT NULL DEFAULT 0,
  low_alerted_at  DATETIME NULL,
  exhausted_at    DATETIME NULL,                     -- วงเงินหมดเมื่อไร
  created_at      TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_shop_period (shop_node_id, period_ym),
  INDEX idx_alw_sub (subscription_id),
  INDEX idx_alw_period (period_ym),
  CONSTRAINT fk_alw_sub  FOREIGN KEY (subscription_id) REFERENCES shop_subscriptions(id),
  CONSTRAINT fk_alw_shop FOREIGN KEY (shop_node_id)    REFERENCES org_nodes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ซื้อวงเงินเพิ่มกลางเดือน (ทางเลือก — เป็นรายได้เพิ่มของเจ้าของระบบ)
CREATE TABLE allowance_topups (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  allowance_id  BIGINT UNSIGNED NOT NULL,
  points        BIGINT NOT NULL,
  price         DECIMAL(12,2) NOT NULL,
  status        ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  paid_at       DATETIME NULL,
  payment_ref   VARCHAR(120) NULL,
  approved_by   BIGINT UNSIGNED NULL,
  created_at    TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  INDEX idx_top_alw (allowance_id),
  CONSTRAINT fk_top_alw  FOREIGN KEY (allowance_id) REFERENCES shop_monthly_allowances(id),
  CONSTRAINT fk_top_user FOREIGN KEY (approved_by)  REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 4) กระเป๋าแต้มลูกค้า (แยกตามร้านผู้ออกแต้ม) + ล็อต FIFO 12 เดือน
-- =====================================================================
CREATE TABLE customer_point_wallets (
  id               BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  customer_id      BIGINT UNSIGNED NOT NULL,
  issuer_node_id   BIGINT UNSIGNED NOT NULL,
  balance          BIGINT NOT NULL DEFAULT 0,
  lifetime_earned  BIGINT NOT NULL DEFAULT 0,
  lifetime_used    BIGINT NOT NULL DEFAULT 0,
  last_activity_at DATETIME NULL,
  created_at       TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_wallet (customer_id, issuer_node_id),
  INDEX idx_wallet_issuer (issuer_node_id),
  CONSTRAINT fk_cpw_cust FOREIGN KEY (customer_id)    REFERENCES customers(id),
  CONSTRAINT fk_cpw_node FOREIGN KEY (issuer_node_id) REFERENCES org_nodes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE point_lots (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  wallet_id   BIGINT UNSIGNED NOT NULL,
  points_in   BIGINT NOT NULL,
  points_left BIGINT NOT NULL,
  earned_at   DATETIME NOT NULL,
  expires_at  DATETIME NOT NULL,
  source_type ENUM('scan','manual','promo','refund') NOT NULL DEFAULT 'scan',
  source_id   BIGINT UNSIGNED NULL,
  is_expired  TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  INDEX idx_lot_fifo (wallet_id, is_expired, expires_at),
  CONSTRAINT fk_plot_wallet FOREIGN KEY (wallet_id) REFERENCES customer_point_wallets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 5) การแลกแต้ม — ตัดจากวงเงินรายเดือนของร้าน
-- =====================================================================
CREATE TABLE point_redemptions (
  id                BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  code              VARCHAR(30) NOT NULL UNIQUE,
  customer_id       BIGINT UNSIGNED NOT NULL,
  issuer_node_id    BIGINT UNSIGNED NOT NULL,     -- แต้มมาจากร้านไหน (สถิติ)
  accepting_node_id BIGINT UNSIGNED NOT NULL,     -- ร้านที่รับแลก
  allowance_id      BIGINT UNSIGNED NULL,         -- ตัดจากวงเงินเดือนไหน
  redeem_type       ENUM('cash','goods','service','discount') NOT NULL DEFAULT 'goods',
  reward_id         BIGINT UNSIGNED NULL,
  reward_name       VARCHAR(200) NOT NULL,
  points_used       BIGINT NOT NULL,
  point_value       DECIMAL(10,4) NOT NULL,
  cash_value        DECIMAL(12,2) NOT NULL,
  status            ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  claim_id          BIGINT UNSIGNED NULL,
  redeemed_at       DATETIME NULL,
  confirmed_by      BIGINT UNSIGNED NULL,
  note              VARCHAR(255) NULL,
  created_at        TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  INDEX idx_red_accept (accepting_node_id, status),
  INDEX idx_red_issuer (issuer_node_id),
  INDEX idx_red_cust (customer_id),
  INDEX idx_red_alw (allowance_id),
  INDEX idx_red_claim (claim_id),
  CONSTRAINT fk_red_cust   FOREIGN KEY (customer_id)       REFERENCES customers(id),
  CONSTRAINT fk_red_issuer FOREIGN KEY (issuer_node_id)    REFERENCES org_nodes(id),
  CONSTRAINT fk_red_accept FOREIGN KEY (accepting_node_id) REFERENCES org_nodes(id),
  CONSTRAINT fk_red_alw    FOREIGN KEY (allowance_id)      REFERENCES shop_monthly_allowances(id),
  CONSTRAINT fk_red_user   FOREIGN KEY (confirmed_by)      REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 6) ใบเบิกเงิน — ร้านเบิกจากเจ้าของระบบโดยตรง
-- =====================================================================
CREATE TABLE reimbursement_claims (
  id               BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  code             VARCHAR(30) NOT NULL UNIQUE,
  claimant_node_id BIGINT UNSIGNED NOT NULL,
  period_ym        CHAR(7) NOT NULL,
  total_points     BIGINT NOT NULL DEFAULT 0,
  point_value      DECIMAL(10,4) NOT NULL,
  total_amount     DECIMAL(12,2) NOT NULL DEFAULT 0,
  entry_count      INT UNSIGNED NOT NULL DEFAULT 0,
  status           ENUM('draft','submitted','approved','rejected','paid')
                   NOT NULL DEFAULT 'draft',
  submitted_at     DATETIME NULL,
  approved_at      DATETIME NULL,
  approved_by      BIGINT UNSIGNED NULL,
  paid_at          DATETIME NULL,
  payment_method   ENUM('transfer','cash','credit') NULL,
  payment_ref      VARCHAR(120) NULL,
  reject_reason    VARCHAR(255) NULL,
  note             TEXT NULL,
  created_at       TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_claim_period (claimant_node_id, period_ym),
  INDEX idx_clm_status (status, submitted_at),
  CONSTRAINT fk_clm_claimant FOREIGN KEY (claimant_node_id) REFERENCES org_nodes(id),
  CONSTRAINT fk_clm_user     FOREIGN KEY (approved_by)      REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 7) หน้าร้าน (storefront)
-- =====================================================================
CREATE TABLE shop_profiles (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  node_id         BIGINT UNSIGNED NOT NULL UNIQUE,
  slug            VARCHAR(80) NOT NULL UNIQUE,
  display_name    VARCHAR(150) NOT NULL,
  tagline         VARCHAR(255) NULL,
  description     TEXT NULL,
  business_type   ENUM('cafe','restaurant','carwash','beauty','pharmacy','retail','other')
                  NOT NULL DEFAULT 'retail',
  template_key    VARCHAR(40) NOT NULL DEFAULT 'retail',
  logo_path       VARCHAR(255) NULL,
  cover_path      VARCHAR(255) NULL,
  color_primary   CHAR(7) NULL,
  color_secondary CHAR(7) NULL,
  phone           VARCHAR(30) NULL,
  line_id         VARCHAR(80) NULL,
  address         TEXT NULL,
  lat             DECIMAL(10,7) NULL,
  lng             DECIMAL(10,7) NULL,
  open_hours      JSON NULL,
  blocks          JSON NULL,
  gallery         JSON NULL,
  status          ENUM('draft','active','suspended') NOT NULL DEFAULT 'draft',
  created_at      TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  INDEX idx_shop_type (business_type, status),
  CONSTRAINT fk_shop_node FOREIGN KEY (node_id) REFERENCES org_nodes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 8) LINE / Google + 9) GPS + 10) QR
-- =====================================================================
CREATE TABLE social_links (
  id             BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  owner_type     ENUM('customer','user') NOT NULL,
  owner_id       BIGINT UNSIGNED NOT NULL,
  provider       ENUM('line','google') NOT NULL,
  provider_uid   VARCHAR(191) NOT NULL,
  display_name   VARCHAR(150) NULL,
  picture_url    VARCHAR(255) NULL,
  email          VARCHAR(191) NULL,
  is_primary     TINYINT(1) NOT NULL DEFAULT 0,
  notify_enabled TINYINT(1) NOT NULL DEFAULT 1,
  linked_at      DATETIME NOT NULL,
  created_at     TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_provider_uid (provider, provider_uid),
  INDEX idx_social_owner (owner_type, owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users ADD COLUMN max_social_links TINYINT UNSIGNED NOT NULL DEFAULT 5;

CREATE TABLE scan_geo_logs (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  scan_log_id     BIGINT UNSIGNED NOT NULL,
  customer_id     BIGINT UNSIGNED NULL,
  lat             DECIMAL(10,7) NULL,
  lng             DECIMAL(10,7) NULL,
  accuracy_m      INT UNSIGNED NULL,
  permission      ENUM('granted','denied','unavailable') NOT NULL DEFAULT 'denied',
  nearest_node_id BIGINT UNSIGNED NULL,
  distance_m      INT UNSIGNED NULL,
  ip_address      VARCHAR(45) NULL,
  user_agent      VARCHAR(255) NULL,
  risk_flag       ENUM('none','far_from_shop','impossible_travel','rate_limit')
                  NOT NULL DEFAULT 'none',
  created_at      TIMESTAMP NULL,
  INDEX idx_geo_scan (scan_log_id),
  INDEX idx_geo_risk (risk_flag, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE product_qrcodes
  ADD COLUMN activated_node_id BIGINT UNSIGNED NULL COMMENT 'เปิดใช้ที่ร้านไหน',
  ADD COLUMN issuer_node_id BIGINT UNSIGNED NULL COMMENT 'แต้มจาก QR นี้เป็นของร้านไหน';

ALTER TABLE product_qrcodes
  MODIFY COLUMN status ENUM('created','printed','in_stock','sold','redeemed','void')
  NOT NULL DEFAULT 'created';

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- TRIGGER : กันวงเงินรายเดือนติดลบ (ด่านสุดท้ายระดับฐานข้อมูล)
-- =====================================================================
DELIMITER $$

DROP TRIGGER IF EXISTS trg_alw_guard_ins $$
CREATE TRIGGER trg_alw_guard_ins BEFORE INSERT ON shop_monthly_allowances
FOR EACH ROW
BEGIN
  IF NEW.remaining_points < 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Monthly allowance exceeded: remaining cannot be negative';
  END IF;
END $$

DROP TRIGGER IF EXISTS trg_alw_guard_upd $$
CREATE TRIGGER trg_alw_guard_upd BEFORE UPDATE ON shop_monthly_allowances
FOR EACH ROW
BEGIN
  IF NEW.remaining_points < 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Monthly allowance exceeded: remaining cannot be negative';
  END IF;
  -- บันทึกเวลาที่วงเงินหมดพอดี
  IF NEW.remaining_points = 0 AND OLD.remaining_points > 0 THEN
    SET NEW.exhausted_at = NOW();
  END IF;
END $$

DELIMITER ;

-- =====================================================================
-- VIEW : สถานะร้าน — เหลือวงเงินเท่าไร สมาชิกหมดเมื่อไร
-- =====================================================================
CREATE OR REPLACE VIEW v_shop_status AS
SELECT
  n.id                AS shop_node_id,
  n.code              AS shop_code,
  n.name              AS shop_name,
  p.name              AS package_name,
  s.status            AS sub_status,
  s.starts_on, s.ends_on,
  DATEDIFF(s.ends_on, CURDATE())            AS days_left,
  a.period_ym,
  a.limit_points + a.rollover_points + a.topup_points AS total_allowance,
  a.used_points,
  a.remaining_points,
  CASE WHEN (a.limit_points + a.rollover_points + a.topup_points) > 0
       THEN ROUND(a.used_points * 100.0 /
            (a.limit_points + a.rollover_points + a.topup_points), 1)
       ELSE 0 END                            AS used_percent,
  CASE
    WHEN s.status <> 'active' THEN 'สมาชิกไม่พร้อมใช้งาน'
    WHEN s.ends_on < CURDATE() THEN 'สมาชิกหมดอายุ'
    WHEN a.remaining_points <= 0 THEN 'วงเงินเดือนนี้หมดแล้ว'
    ELSE 'พร้อมรับแลก'
  END                                        AS redeem_state
FROM org_nodes n
JOIN shop_subscriptions s ON s.shop_node_id = n.id AND s.status = 'active'
JOIN shop_packages p ON p.id = s.package_id
LEFT JOIN shop_monthly_allowances a
       ON a.shop_node_id = n.id
      AND a.period_ym = DATE_FORMAT(CURDATE(), '%Y-%m');

-- =====================================================================
-- VIEW : เงินที่ร้านเบิกได้ในเดือนนั้น
-- =====================================================================
CREATE OR REPLACE VIEW v_shop_claimable AS
SELECT
  r.accepting_node_id                AS shop_node_id,
  n.code                             AS shop_code,
  n.name                             AS shop_name,
  DATE_FORMAT(r.redeemed_at,'%Y-%m') AS period_ym,
  COUNT(*)                           AS redemption_count,
  SUM(r.points_used)                 AS total_points,
  SUM(r.cash_value)                  AS total_amount,
  SUM(CASE WHEN r.claim_id IS NULL THEN r.points_used ELSE 0 END) AS unclaimed_points,
  SUM(CASE WHEN r.claim_id IS NULL THEN r.cash_value  ELSE 0 END) AS unclaimed_amount
FROM point_redemptions r
JOIN org_nodes n ON n.id = r.accepting_node_id
WHERE r.status = 'confirmed'
GROUP BY r.accepting_node_id, n.code, n.name, DATE_FORMAT(r.redeemed_at,'%Y-%m');

-- =====================================================================
-- VIEW : รายงานสายงาน — สายบนเห็นยอดของลูกสายทั้งหมด
--   หมายเหตุ: org_nodes.path เก็บเส้นทางของ "พ่อแม่" ไม่รวม id ตัวเอง
--   เส้นทางเต็มจึงเป็น CONCAT(path, id, '/')
--   ถ้าเทียบด้วย path เฉย ๆ ร้านพี่น้องจะถูกนับรวมผิด
-- =====================================================================
CREATE OR REPLACE VIEW v_branch_rollup AS
SELECT
  anc.id                       AS branch_node_id,
  anc.name                     AS branch_name,
  anc.level_id                 AS branch_level,
  COUNT(DISTINCT des.id)       AS downline_shops,
  COUNT(DISTINCT r.id)         AS redemptions,
  COALESCE(SUM(r.points_used),0) AS points_redeemed,
  COALESCE(SUM(r.cash_value),0)  AS cash_to_pay
FROM org_nodes anc
JOIN org_nodes des
  ON des.id <> anc.id
 AND des.path LIKE CONCAT(anc.path, anc.id, '/%')
LEFT JOIN point_redemptions r
  ON r.accepting_node_id = des.id AND r.status = 'confirmed'
GROUP BY anc.id, anc.name, anc.level_id;
