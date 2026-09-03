<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RoaMembers — ระบบแต้ม v3
 *
 * โมเดล: แพ็กเกจรายเดือน (ไม่มีโควต้าไหลตามสาย)
 *   - แอดมินสร้างแพ็กเกจ -> ตัวแทนเลือกให้ร้าน -> ร้านได้วงเงินรายเดือน
 *   - วงเงินรีเซตทุกเดือน ใช้ไม่หมดไม่ทบ (ยกเว้นแพ็กเกจที่เปิด rollover)
 *   - เจ้าของระบบเป็นผู้จ่ายเงินคืนให้ร้านโดยตรง
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── ค่าคงที่ของระบบ (แก้ได้เฉพาะเจ้าของระบบ/แอดมิน) ──────────
        Schema::create('system_settings', function (Blueprint $t) {
            $t->id();
            $t->string('skey', 80)->unique();
            $t->string('svalue', 255);
            $t->enum('value_type', ['int', 'decimal', 'string', 'bool', 'json'])->default('string');
            $t->string('description', 255)->nullable();
            $t->boolean('owner_only')->default(true);
            $t->foreignId('updated_by')->nullable()->constrained('users');
            $t->timestamps();
        });

        // ประวัติการเปลี่ยนค่าแต้ม — ต้องย้อนตรวจได้เสมอ
        Schema::create('point_value_history', function (Blueprint $t) {
            $t->id();
            $t->decimal('old_value', 10, 4)->nullable();
            $t->decimal('new_value', 10, 4);
            $t->string('reason', 255)->nullable();
            $t->dateTime('effective_at');
            $t->foreignId('changed_by')->constrained('users');
            $t->timestamp('created_at')->nullable();
        });

        // ── แพ็กเกจที่แอดมินตั้งไว้ ────────────────────────────────
        Schema::create('shop_packages', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name', 120);
            $t->string('tagline', 200)->nullable();
            $t->unsignedSmallInteger('duration_months');
            $t->bigInteger('monthly_point_limit');
            $t->decimal('price', 12, 2);
            $t->boolean('allow_rollover')->default(false);
            $t->boolean('allow_cash_redeem')->default(false);
            $t->decimal('agent_commission_pct', 5, 2)->default(0);
            $t->smallInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->text('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamps();
            $t->index(['is_active', 'sort_order']);
        });

        // ── การสมัครของร้าน (ตัวแทนกรอก) ──────────────────────────
        Schema::create('shop_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->foreignId('shop_node_id')->constrained('org_nodes');
            $t->foreignId('package_id')->constrained('shop_packages');
            $t->foreignId('recruiter_node_id')->constrained('org_nodes');
            // ล็อกค่าไว้ตอนสมัคร ถ้าแอดมินแก้แพ็กเกจภายหลัง สัญญาเดิมไม่เปลี่ยน
            $t->bigInteger('monthly_point_limit');
            $t->decimal('price_paid', 12, 2);
            $t->boolean('allow_rollover')->default(false);
            $t->boolean('allow_cash_redeem')->default(false);
            $t->decimal('commission_amount', 12, 2)->default(0);
            $t->date('starts_on');
            $t->date('ends_on');
            $t->enum('status', ['pending_payment', 'active', 'expired', 'cancelled', 'suspended'])
                ->default('pending_payment');
            $t->boolean('auto_renew')->default(false);
            $t->dateTime('paid_at')->nullable();
            $t->string('payment_ref', 120)->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->dateTime('cancelled_at')->nullable();
            $t->string('cancel_reason', 255)->nullable();
            $t->text('note')->nullable();
            $t->timestamps();
            $t->index(['shop_node_id', 'status']);
            $t->index(['ends_on', 'status']);
        });

        // ── วงเงินรายเดือน (หัวใจของระบบ) ─────────────────────────
        Schema::create('shop_monthly_allowances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('subscription_id')->constrained('shop_subscriptions');
            $t->foreignId('shop_node_id')->constrained('org_nodes');
            $t->char('period_ym', 7);              // 2569-09
            $t->bigInteger('limit_points');
            $t->bigInteger('rollover_points')->default(0);
            $t->bigInteger('topup_points')->default(0);
            $t->bigInteger('used_points')->default(0);
            $t->bigInteger('remaining_points')->default(0);
            $t->unsignedInteger('redemption_count')->default(0);
            $t->dateTime('low_alerted_at')->nullable();
            $t->dateTime('exhausted_at')->nullable();
            $t->timestamps();
            $t->unique(['shop_node_id', 'period_ym']);
            $t->index('period_ym');
        });

        Schema::create('allowance_topups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('allowance_id')->constrained('shop_monthly_allowances');
            $t->bigInteger('points');
            $t->decimal('price', 12, 2);
            $t->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $t->dateTime('paid_at')->nullable();
            $t->string('payment_ref', 120)->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->timestamps();
        });

        // ── กระเป๋าแต้มลูกค้า (แยกตามร้านผู้ออกแต้ม) ───────────────
        Schema::create('customer_point_wallets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained('customers');
            $t->foreignId('issuer_node_id')->constrained('org_nodes');
            $t->bigInteger('balance')->default(0);
            $t->bigInteger('lifetime_earned')->default(0);
            $t->bigInteger('lifetime_used')->default(0);
            $t->dateTime('last_activity_at')->nullable();
            $t->timestamps();
            $t->unique(['customer_id', 'issuer_node_id']);
            $t->index('issuer_node_id');
        });

        // ล็อตแต้ม FIFO — ใช้ของเก่าก่อน หมดอายุ 12 เดือน
        Schema::create('point_lots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wallet_id')->constrained('customer_point_wallets');
            $t->bigInteger('points_in');
            $t->bigInteger('points_left');
            $t->dateTime('earned_at');
            $t->dateTime('expires_at');
            $t->enum('source_type', ['scan', 'manual', 'promo', 'refund'])->default('scan');
            $t->unsignedBigInteger('source_id')->nullable();
            $t->boolean('is_expired')->default(false);
            $t->timestamps();
            $t->index(['wallet_id', 'is_expired', 'expires_at']);
        });

        // ── การแลกแต้ม ───────────────────────────────────────────
        Schema::create('point_redemptions', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->foreignId('customer_id')->constrained('customers');
            $t->foreignId('issuer_node_id')->constrained('org_nodes');
            $t->foreignId('accepting_node_id')->constrained('org_nodes');
            $t->foreignId('allowance_id')->nullable()->constrained('shop_monthly_allowances');
            $t->enum('redeem_type', ['cash', 'goods', 'service', 'discount'])->default('goods');
            $t->unsignedBigInteger('reward_id')->nullable();
            $t->string('reward_name', 200);
            $t->bigInteger('points_used');
            $t->decimal('point_value', 10, 4);
            $t->decimal('cash_value', 12, 2);
            $t->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');
            $t->unsignedBigInteger('claim_id')->nullable();
            $t->dateTime('redeemed_at')->nullable();
            $t->foreignId('confirmed_by')->nullable()->constrained('users');
            $t->string('note', 255)->nullable();
            $t->timestamps();
            $t->index(['accepting_node_id', 'status']);
            $t->index('issuer_node_id');
            $t->index('customer_id');
            $t->index('claim_id');
        });

        // รายการสินค้าที่แลก — ผูกกับล็อตจริง ตรวจย้อนกลับได้
        Schema::create('redemption_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('redemption_id')->constrained('point_redemptions');
            $t->foreignId('product_id')->constrained('products');
            $t->foreignId('lot_id')->nullable()->constrained('product_lots');
            $t->foreignId('qrcode_id')->nullable()->constrained('product_qrcodes');
            $t->foreignId('from_node_id')->constrained('org_nodes');
            $t->unsignedInteger('qty');
            // snapshot กันข้อมูลต้นทางเปลี่ยนภายหลัง
            $t->string('sku_snapshot', 64);
            $t->string('name_snapshot', 200);
            $t->string('lot_no_snapshot', 64)->nullable();
            $t->date('expiry_snapshot')->nullable();
            $t->decimal('unit_cost', 12, 2)->default(0);
            $t->bigInteger('points_each')->default(0);
            $t->bigInteger('points_total')->default(0);
            $t->unsignedBigInteger('movement_id')->nullable();
            $t->timestamps();
            $t->index('redemption_id');
            $t->index('lot_id');
        });

        // บันทึกความพยายามแลกที่ล้มเหลว
        Schema::create('redemption_attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->nullable()->constrained('customers');
            $t->foreignId('shop_node_id')->constrained('org_nodes');
            $t->bigInteger('points_requested');
            $t->string('reward_name', 200)->nullable();
            $t->enum('result', [
                'ok', 'insufficient_customer_points', 'insufficient_shop_allowance',
                'subscription_inactive', 'out_of_stock', 'lot_expired', 'blocked',
            ]);
            $t->string('detail', 255)->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['shop_node_id', 'created_at']);
            $t->index(['result', 'created_at']);
        });

        // ── ใบเบิกเงิน (ร้าน -> เจ้าของระบบ) ──────────────────────
        Schema::create('reimbursement_claims', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->foreignId('claimant_node_id')->constrained('org_nodes');
            $t->char('period_ym', 7);
            $t->bigInteger('total_points')->default(0);
            $t->decimal('point_value', 10, 4);
            $t->decimal('total_amount', 12, 2)->default(0);
            $t->unsignedInteger('entry_count')->default(0);
            $t->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'paid'])->default('draft');
            $t->dateTime('submitted_at')->nullable();
            $t->dateTime('approved_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->dateTime('paid_at')->nullable();
            $t->enum('payment_method', ['transfer', 'cash', 'credit'])->nullable();
            $t->string('payment_ref', 120)->nullable();
            $t->string('reject_reason', 255)->nullable();
            $t->text('note')->nullable();
            $t->timestamps();
            $t->unique(['claimant_node_id', 'period_ym']);
            $t->index(['status', 'submitted_at']);
        });

        // ── หน้าร้าน (โลโก้/ชื่อ/ธีมของร้านเอง) ────────────────────
        Schema::create('shop_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('node_id')->unique()->constrained('org_nodes');
            $t->string('slug', 80)->unique();
            $t->string('display_name', 150);
            $t->string('tagline', 255)->nullable();
            $t->text('description')->nullable();
            $t->enum('business_type', ['cafe', 'restaurant', 'carwash', 'beauty', 'pharmacy', 'retail', 'other'])
                ->default('retail');
            $t->string('template_key', 40)->default('retail');
            $t->string('logo_path', 255)->nullable();
            $t->string('cover_path', 255)->nullable();
            $t->char('color_primary', 7)->nullable();
            $t->char('color_secondary', 7)->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('line_id', 80)->nullable();
            $t->text('address')->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->json('open_hours')->nullable();
            $t->json('blocks')->nullable();
            $t->json('gallery')->nullable();
            $t->enum('status', ['draft', 'active', 'suspended'])->default('draft');
            $t->timestamps();
            $t->index(['business_type', 'status']);
        });

        // ── ผูก LINE / Google ────────────────────────────────────
        Schema::create('social_links', function (Blueprint $t) {
            $t->id();
            $t->enum('owner_type', ['customer', 'user']);
            $t->unsignedBigInteger('owner_id');
            $t->enum('provider', ['line', 'google']);
            $t->string('provider_uid', 191);
            $t->string('display_name', 150)->nullable();
            $t->string('picture_url', 255)->nullable();
            $t->string('email', 191)->nullable();
            $t->boolean('is_primary')->default(false);
            $t->boolean('notify_enabled')->default(true);
            $t->dateTime('linked_at');
            $t->timestamps();
            $t->unique(['provider', 'provider_uid']);
            $t->index(['owner_type', 'owner_id']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->unsignedTinyInteger('max_social_links')->default(5);
        });

        // ── บันทึกตำแหน่งตอนสแกน (กันโกง) ─────────────────────────
        Schema::create('scan_geo_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('scan_log_id');
            $t->foreignId('customer_id')->nullable()->constrained('customers');
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->unsignedInteger('accuracy_m')->nullable();
            $t->enum('permission', ['granted', 'denied', 'unavailable'])->default('denied');
            $t->unsignedBigInteger('nearest_node_id')->nullable();
            $t->unsignedInteger('distance_m')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->enum('risk_flag', ['none', 'far_from_shop', 'impossible_travel', 'rate_limit'])->default('none');
            $t->timestamp('created_at')->nullable();
            $t->index('scan_log_id');
            $t->index(['risk_flag', 'created_at']);
        });

        // ── QR: แยก "พิมพ์แล้ว" ออกจาก "เปิดใช้แล้ว" ──────────────
        Schema::table('product_qrcodes', function (Blueprint $t) {
            $t->unsignedBigInteger('activated_node_id')->nullable();
            $t->unsignedBigInteger('issuer_node_id')->nullable();
        });

        $this->seedSettings();
        $this->seedPackages();
        $this->createGuardTriggers();
    }

    /** ค่าตั้งต้นของระบบ */
    private function seedSettings(): void
    {
        $now = now();
        DB::table('system_settings')->insert([
            ['skey' => 'point_value_baht', 'svalue' => '0.25', 'value_type' => 'decimal',
             'description' => 'มูลค่าเงินของ 1 แต้ม (บาท) — เจ้าของระบบเท่านั้นที่แก้ได้',
             'owner_only' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['skey' => 'point_expire_months', 'svalue' => '12', 'value_type' => 'int',
             'description' => 'อายุแต้มของลูกค้า (เดือน) แบบ FIFO',
             'owner_only' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['skey' => 'monthly_reset_day', 'svalue' => '1', 'value_type' => 'int',
             'description' => 'วันที่รีเซตวงเงินรายเดือนของร้าน',
             'owner_only' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['skey' => 'allow_topup', 'svalue' => '1', 'value_type' => 'bool',
             'description' => 'อนุญาตให้ร้านซื้อวงเงินเพิ่มกลางเดือน',
             'owner_only' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['skey' => 'low_balance_percent', 'svalue' => '20', 'value_type' => 'int',
             'description' => 'แจ้งเตือนเมื่อวงเงินเหลือต่ำกว่ากี่ %',
             'owner_only' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['skey' => 'scan_daily_limit', 'svalue' => '20', 'value_type' => 'int',
             'description' => 'จำกัดจำนวนสแกนต่อลูกค้าต่อวัน (กันโกง)',
             'owner_only' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['skey' => 'claim_min_points', 'svalue' => '400', 'value_type' => 'int',
             'description' => 'แต้มขั้นต่ำที่ร้านจะยื่นเบิกเงินได้',
             'owner_only' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['skey' => 'max_social_links_default', 'svalue' => '5', 'value_type' => 'int',
             'description' => 'จำนวน LINE/Google ที่ผู้ใช้ระบบผูกได้',
             'owner_only' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /** แพ็กเกจตั้งต้น */
    private function seedPackages(): void
    {
        $now = now();
        DB::table('shop_packages')->insert([
            ['code' => 'PKG-TRIAL', 'name' => 'ทดลองใช้ฟรี', 'tagline' => 'ทดลอง 1 เดือน',
             'duration_months' => 1, 'monthly_point_limit' => 1000, 'price' => 0,
             'allow_rollover' => 0, 'allow_cash_redeem' => 0, 'agent_commission_pct' => 0,
             'sort_order' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PKG-BRONZE', 'name' => 'แพ็กเกจทองแดง', 'tagline' => 'เหมาะกับร้านเล็ก เริ่มต้นง่าย',
             'duration_months' => 6, 'monthly_point_limit' => 4000, 'price' => 2500,
             'allow_rollover' => 0, 'allow_cash_redeem' => 0, 'agent_commission_pct' => 10,
             'sort_order' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PKG-SILVER', 'name' => 'แพ็กเกจเงิน', 'tagline' => 'ยอดนิยม คุ้มที่สุด',
             'duration_months' => 12, 'monthly_point_limit' => 10000, 'price' => 5000,
             'allow_rollover' => 0, 'allow_cash_redeem' => 0, 'agent_commission_pct' => 12,
             'sort_order' => 2, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PKG-GOLD', 'name' => 'แพ็กเกจทอง', 'tagline' => 'ร้านใหญ่ ลูกค้าเยอะ',
             'duration_months' => 12, 'monthly_point_limit' => 25000, 'price' => 10000,
             'allow_rollover' => 1, 'allow_cash_redeem' => 1, 'agent_commission_pct' => 15,
             'sort_order' => 3, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * ด่านป้องกันระดับฐานข้อมูล
     *
     * เป็นด่านสุดท้าย ต่อให้โค้ดฝั่งแอปมีบั๊ก หรือมีคนแก้ข้อมูลตรง ๆ
     * ผ่าน phpMyAdmin ก็ยังกันไม่ให้ยอดติดลบได้
     *
     * SQLite ไม่รองรับ SIGNAL จึงข้ามไป (ใช้ตอน dev/test เท่านั้น)
     */
    private function createGuardTriggers(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // แต้มลูกค้าห้ามติดลบ
        DB::unprepared("
            CREATE TRIGGER trg_wallet_guard BEFORE UPDATE ON customer_point_wallets
            FOR EACH ROW
            BEGIN
              IF NEW.balance < 0 THEN
                SIGNAL SQLSTATE '45000'
                  SET MESSAGE_TEXT = 'Customer points not enough: balance cannot be negative';
              END IF;
            END
        ");

        // ล็อตแต้มห้ามติดลบ และห้ามเหลือเกินที่เคยได้
        DB::unprepared("
            CREATE TRIGGER trg_pointlot_guard BEFORE UPDATE ON point_lots
            FOR EACH ROW
            BEGIN
              IF NEW.points_left < 0 THEN
                SIGNAL SQLSTATE '45000'
                  SET MESSAGE_TEXT = 'Point lot cannot go negative';
              END IF;
              IF NEW.points_left > NEW.points_in THEN
                SIGNAL SQLSTATE '45000'
                  SET MESSAGE_TEXT = 'Point lot remaining cannot exceed original amount';
              END IF;
            END
        ");

        // วงเงินรายเดือนของร้านห้ามติดลบ
        DB::unprepared("
            CREATE TRIGGER trg_alw_guard_ins BEFORE INSERT ON shop_monthly_allowances
            FOR EACH ROW
            BEGIN
              IF NEW.remaining_points < 0 THEN
                SIGNAL SQLSTATE '45000'
                  SET MESSAGE_TEXT = 'Monthly allowance exceeded: remaining cannot be negative';
              END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_alw_guard_upd BEFORE UPDATE ON shop_monthly_allowances
            FOR EACH ROW
            BEGIN
              IF NEW.remaining_points < 0 THEN
                SIGNAL SQLSTATE '45000'
                  SET MESSAGE_TEXT = 'Monthly allowance exceeded: remaining cannot be negative';
              END IF;
              IF NEW.remaining_points = 0 AND OLD.remaining_points > 0 THEN
                SET NEW.exhausted_at = NOW();
              END IF;
            END
        ");

        // สต๊อกห้ามติดลบ
        DB::unprepared("
            CREATE TRIGGER trg_stock_guard BEFORE UPDATE ON stock_balances
            FOR EACH ROW
            BEGIN
              IF NEW.qty_on_hand < 0 THEN
                SIGNAL SQLSTATE '45000'
                  SET MESSAGE_TEXT = 'Stock not enough: qty_on_hand cannot be negative';
              END IF;
            END
        ");

        // แลกสินค้าต้องระบุล็อต / ห้ามล็อตหมดอายุ / ห้ามผูกกับการแลกแบบอื่น
        DB::unprepared("
            CREATE TRIGGER trg_ri_require_lot BEFORE INSERT ON redemption_items
            FOR EACH ROW
            BEGIN
              DECLARE v_type VARCHAR(20);
              DECLARE v_exp DATE;
              DECLARE v_has_expiry TINYINT;

              SELECT redeem_type INTO v_type FROM point_redemptions WHERE id = NEW.redemption_id;
              IF v_type <> 'goods' THEN
                SIGNAL SQLSTATE '45000'
                  SET MESSAGE_TEXT = 'Only goods redemption can have items';
              END IF;

              SELECT has_expiry INTO v_has_expiry FROM products WHERE id = NEW.product_id;
              IF v_has_expiry = 1 AND NEW.lot_id IS NULL THEN
                SIGNAL SQLSTATE '45000'
                  SET MESSAGE_TEXT = 'This product requires a lot number';
              END IF;

              IF NEW.lot_id IS NOT NULL THEN
                SELECT expiry_date INTO v_exp FROM product_lots WHERE id = NEW.lot_id;
                IF v_exp IS NOT NULL AND v_exp < CURDATE() THEN
                  SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Cannot redeem an expired lot';
                END IF;
              END IF;
            END
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach ([
                'trg_wallet_guard', 'trg_pointlot_guard', 'trg_alw_guard_ins',
                'trg_alw_guard_upd', 'trg_stock_guard', 'trg_ri_require_lot',
            ] as $trg) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$trg}");
            }
        }

        Schema::table('product_qrcodes', function (Blueprint $t) {
            $t->dropColumn(['activated_node_id', 'issuer_node_id']);
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('max_social_links'));

        foreach ([
            'scan_geo_logs', 'social_links', 'shop_profiles', 'reimbursement_claims',
            'redemption_attempts', 'redemption_items', 'point_redemptions', 'point_lots',
            'customer_point_wallets', 'allowance_topups', 'shop_monthly_allowances',
            'shop_subscriptions', 'shop_packages', 'point_value_history', 'system_settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
