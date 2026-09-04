#!/usr/bin/env bash
# ============================================================
#  RoaMembers Stock System — Install Script
# ============================================================
#  ใช้ติดตั้งทั้งตอน develop และ deploy ขึ้น production
#
#  วิธีใช้:
#    bash install.sh          # ติดตั้งแบบ dev (SQLite)
#    bash install.sh prod     # ติดตั้งแบบ production (MySQL ตาม .env)
#
#  ความต้องการ:
#    - PHP 8.2+ พร้อม extensions: mbstring, xml, curl, zip, sqlite3, gd
#    - Composer 2.x
#    - Node.js 18+ และ npm (สำหรับ build frontend assets)
# ============================================================
set -e

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'

step()  { echo -e "\n${BLUE}▸ $1${NC}"; }
ok()    { echo -e "  ${GREEN}✓${NC} $1"; }
warn()  { echo -e "  ${YELLOW}⚠${NC} $1"; }
fail()  { echo -e "  ${RED}✗${NC} $1"; exit 1; }

MODE="${1:-dev}"
echo -e "${GREEN}╔══════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║  RoaMembers Stock System — Installer         ║${NC}"
echo -e "${GREEN}║  Mode: ${MODE}                                  ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════╝${NC}"

# ── 1. ตรวจสอบ dependencies ──────────────────────────────
step "1/7 ตรวจสอบ dependencies"

command -v php >/dev/null 2>&1 || fail "ไม่พบ PHP — ติดตั้ง: sudo apt install php php-cli php-mbstring php-xml php-curl php-zip php-sqlite3 php-gd"
command -v composer >/dev/null 2>&1 || fail "ไม่พบ Composer — ติดตั้ง: https://getcomposer.org"

PHP_VER=$(php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;")
ok "PHP ${PHP_VER}"

# ตรวจ extensions ที่จำเป็น
for ext in mbstring xml curl zip pdo_sqlite gd; do
    if php -m 2>/dev/null | grep -qi "^${ext}$"; then
        ok "ext-${ext}"
    else
        warn "ขาด ext-${ext} — ลอง: sudo apt install php-${ext}"
    fi
done

HAS_NODE=false
if command -v node >/dev/null 2>&1; then
    ok "Node.js $(node -v)"
    HAS_NODE=true
else
    warn "ไม่พบ Node.js — ข้าม frontend build (ไม่จำเป็นสำหรับ API-only)"
fi

# ── 2. ตั้งค่า .env ──────────────────────────────────────
step "2/7 ตั้งค่า .env"

if [ ! -f .env ]; then
    cp .env.example .env
    ok "สร้าง .env จาก .env.example"
else
    ok ".env มีอยู่แล้ว (ไม่ทับ)"
fi

if [ "$MODE" = "dev" ]; then
    # ใช้ SQLite สำหรับ development
    sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
    sed -i 's/^DB_HOST=.*/# DB_HOST=/' .env
    sed -i 's/^DB_PORT=.*/# DB_PORT=/' .env
    sed -i 's/^DB_DATABASE=.*/# DB_DATABASE=/' .env
    sed -i 's/^DB_USERNAME=.*/# DB_USERNAME=/' .env
    sed -i 's/^DB_PASSWORD=.*/# DB_PASSWORD=/' .env
    sed -i 's/^APP_URL=.*/APP_URL=http:\/\/localhost:8000/' .env

    # สร้างไฟล์ SQLite
    mkdir -p database
    touch database/database.sqlite
    ok "ตั้งค่า SQLite สำหรับ development"
else
    warn "Production mode — ตรวจสอบ .env ว่าตั้งค่า DB_CONNECTION=mysql ถูกต้อง"
fi

# ── 3. สร้าง storage directories ─────────────────────────
step "3/7 สร้าง storage directories"

mkdir -p storage/framework/{views,sessions,cache,data}
mkdir -p storage/logs
mkdir -p storage/app/public
chmod -R 775 storage/
ok "storage directories พร้อมใช้งาน"

# สร้าง placeholder logo ถ้ายังไม่มี
mkdir -p public/brand
if [ ! -f public/brand/logo.svg ]; then
    cat > public/brand/logo.svg << 'LOGOSVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192">
  <rect width="192" height="192" rx="32" fill="#1A1A1A"/>
  <g transform="translate(96,96)">
    <polygon points="0,-52 12,-16 50,-16 20,6 32,42 0,22 -32,42 -20,6 -50,-16 -12,-16" fill="#D4A84B"/>
    <circle r="14" fill="#1A1A1A"/>
    <circle r="8" fill="#D4A84B" opacity="0.6"/>
  </g>
  <text x="96" y="170" text-anchor="middle" font-family="Arial,sans-serif" font-weight="800" font-size="28" fill="#D4A84B" letter-spacing="4">RM</text>
</svg>
LOGOSVG
    ok "สร้าง placeholder logo.svg"
fi

# ── 4. ติดตั้ง PHP dependencies ──────────────────────────
step "4/7 ติดตั้ง PHP dependencies (Composer)"

if [ "$MODE" = "prod" ]; then
    composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -3
    ok "Composer install (production)"
else
    composer install --no-interaction 2>&1 | tail -3
    ok "Composer install (development)"
fi

# ── 5. ตั้งค่า Laravel ───────────────────────────────────
step "5/7 ตั้งค่า Laravel"

php artisan key:generate --force 2>&1 | grep -v "^$" | head -2
ok "APP_KEY generated"

php artisan config:clear >/dev/null 2>&1
php artisan route:clear >/dev/null 2>&1
php artisan view:clear >/dev/null 2>&1

php artisan migrate:fresh --seed --force 2>&1 | tail -5
ok "Database migrated + seeded"

# ── 6. Build frontend assets (ถ้ามี Node.js) ─────────────
step "6/7 Frontend assets"

if [ "$HAS_NODE" = true ]; then
    npm install --silent 2>/dev/null
    if [ "$MODE" = "prod" ]; then
        npm run build 2>/dev/null || warn "npm run build failed (อาจไม่จำเป็น)"
    fi
    ok "Frontend assets built"
else
    warn "ข้าม — ไม่มี Node.js (ใช้ pre-built assets หรือ API-only)"
fi

# ── 7. Storage link ──────────────────────────────────────
step "7/7 สร้าง symlink สำหรับไฟล์สาธารณะ"

php artisan storage:link --force 2>/dev/null || true
ok "storage:link สร้างแล้ว"

# ── สรุป ─────────────────────────────────────────────────
echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║  ติดตั้งเสร็จสมบูรณ์!                        ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════╝${NC}"
echo ""

if [ "$MODE" = "dev" ]; then
    echo -e "  ${YELLOW}รันเซิร์ฟเวอร์:${NC}"
    echo "    php artisan serve"
    echo ""
    echo -e "  ${YELLOW}รันเทสต์:${NC}"
    echo "    php artisan test"
    echo ""
    echo -e "  ${YELLOW}บัญชีทดสอบ (รหัสผ่าน: password):${NC}"
    echo "    admin@demo.test   — เจ้าของระบบ"
    echo "    wh@demo.test      — คลังใหญ่"
    echo "    swh@demo.test     — คลังย่อย"
    echo "    agent@demo.test   — ตัวแทนขาย"
    echo "    shop@demo.test    — ร้านค้า (POS + ตั้งค่าร้าน)"
    echo "    seller@demo.test  — ผู้ขาย"
    echo ""
    echo -e "  ${YELLOW}URL สำคัญ:${NC}"
    echo "    http://localhost:8000/login     — เข้าสู่ระบบ"
    echo "    http://localhost:8000/scan      — หน้าสแกน QR (ไม่ต้อง login)"
    echo ""
else
    echo -e "  ${YELLOW}ขั้นตอนต่อไป:${NC}"
    echo "    1. ตั้ง APP_URL ใน .env ให้ตรงกับโดเมนจริง"
    echo "    2. ตั้งค่า web server ชี้ไปที่ public/"
    echo "    3. ตั้งค่า cron: * * * * * php /path/artisan schedule:run"
    echo ""
    echo -e "  ${YELLOW}Commands ที่ต้องตั้ง scheduler:${NC}"
    echo "    roamembers:reset-allowances   — รีเซตวงเงินเดือน (ทุกวันที่ 1)"
    echo "    roamembers:expire-points      — ตัดแต้มหมดอายุ (ทุกวัน)"
    echo "    roamembers:send-notifications — ส่งแจ้งเตือน (ทุกนาที)"
    echo ""
fi
