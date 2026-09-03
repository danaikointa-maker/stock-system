<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_levels', function (Blueprint $t) {
            $t->tinyInteger('id')->unsigned()->primary();
            $t->string('code', 30)->unique();
            $t->string('name_th', 100);
            $t->boolean('can_hold_stock')->default(true);
            $t->timestamps();
        });

        DB::table('org_levels')->insert([
            ['id' => 1, 'code' => 'system_owner',   'name_th' => 'เจ้าของระบบ',  'can_hold_stock' => true],
            ['id' => 2, 'code' => 'main_warehouse', 'name_th' => 'คลังใหญ่',     'can_hold_stock' => true],
            ['id' => 3, 'code' => 'sub_warehouse',  'name_th' => 'คลังย่อย',     'can_hold_stock' => true],
            ['id' => 4, 'code' => 'agent',          'name_th' => 'ตัวแทนขาย',    'can_hold_stock' => true],
            ['id' => 5, 'code' => 'shop',           'name_th' => 'ร้านค้า',      'can_hold_stock' => true],
            ['id' => 6, 'code' => 'seller',         'name_th' => 'ผู้ขาย',       'can_hold_stock' => true],
        ]);

        Schema::create('org_nodes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('org_nodes');
            $t->tinyInteger('level_id')->unsigned();
            $t->string('code', 50)->unique();
            $t->string('name', 150);
            $t->string('path', 500)->default('/');   // '/1/2/' = บรรพบุรุษทั้งหมด
            $t->tinyInteger('depth')->unsigned()->default(0);
            $t->string('phone', 30)->nullable();
            $t->text('address')->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->decimal('credit_limit', 14, 2)->default(0);
            $t->enum('status', ['active', 'suspended', 'closed'])->default('active');
            $t->timestamps();
            $t->softDeletes();

            $t->foreign('level_id')->references('id')->on('org_levels');
            $t->index('level_id');
            $t->index('path');
        });

        // Laravel มีตาราง users อยู่แล้ว (พร้อม sessions / password_reset_tokens)
        // จึงใช้วิธี "เพิ่มคอลัมน์" แทนการสร้างใหม่ เพื่อไม่ให้ระบบ session พัง
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('org_node_id')->nullable()->after('id')->constrained('org_nodes');
            $t->string('phone', 30)->nullable()->unique()->after('email');
            $t->enum('role', [
                'system_admin', 'warehouse_admin', 'agent_user',
                'shop_user', 'seller_user', 'viewer',
            ])->default('viewer')->after('password');
            $t->boolean('is_active')->default(true)->after('role');
            $t->softDeletes();
        });

        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('phone', 30)->unique();
            $t->string('name', 150)->nullable();
            $t->string('line_user_id', 100)->nullable()->unique();
            $t->integer('points_balance')->default(0);
            $t->enum('tier', ['bronze', 'silver', 'gold', 'platinum'])->default('bronze');
            $t->foreignId('referred_by_node_id')->nullable()->constrained('org_nodes');
            $t->enum('status', ['active', 'blocked'])->default('active');
            $t->timestamps();
        });

        // บังคับลำดับชั้นระดับฐานข้อมูล (MySQL เท่านั้น) — เป็นด่านสุดท้ายกันข้อมูลเสียจาก raw SQL
        // ตรรกะหลักอยู่ใน OrgNode::booted() ซึ่งทำงานได้ทุก database driver
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared(<<<'SQL'
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
            END
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_org_nodes_bi');
        }

        Schema::dropIfExists('customers');

        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('org_node_id');
            $t->dropColumn(['phone', 'role', 'is_active', 'deleted_at']);
        });

        Schema::dropIfExists('org_nodes');
        Schema::dropIfExists('org_levels');
    }
};
