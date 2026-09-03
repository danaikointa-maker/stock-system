<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RoaMembers — ระบบความปลอดภัยและการตรวจสอบ
 *
 * เก็บหลักฐานทุกอย่างที่แอดมินต้องใช้ตรวจสอบ:
 *   security_events   เหตุการณ์ด้านความปลอดภัย (พยายามแฮค/สิทธิ์ไม่พอ/ผิดปกติ)
 *   audit_trails      ใครแก้อะไร ค่าเดิม -> ค่าใหม่
 *   login_attempts    ประวัติการเข้าสู่ระบบทั้งสำเร็จและล้มเหลว
 *   error_logs        ข้อผิดพลาดของระบบ
 *   admin_alerts      แจ้งเตือนที่ต้องให้แอดมินดู
 *   blocked_entities  IP / บัญชี ที่ถูกระงับชั่วคราว
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── เหตุการณ์ด้านความปลอดภัย ──────────────────────────────
        Schema::create('security_events', function (Blueprint $t) {
            $t->id();
            $t->string('event_type', 60);          // permission_denied, rate_limit_hit, ...
            $t->enum('severity', ['info', 'low', 'medium', 'high', 'critical'])->default('low');
            // ใครเป็นคนทำ (อาจไม่ล็อกอินก็ได้)
            $t->enum('actor_type', ['guest', 'customer', 'user', 'system'])->default('guest');
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('actor_label', 150)->nullable();   // เก็บชื่อ/อีเมลไว้ตอนนั้น
            // ทำอะไร ที่ไหน
            $t->string('route', 191)->nullable();
            $t->string('method', 10)->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->string('target_type', 60)->nullable();    // Model ที่เกี่ยวข้อง
            $t->unsignedBigInteger('target_id')->nullable();
            // รายละเอียด
            $t->string('message', 255);
            $t->json('context')->nullable();
            $t->boolean('is_reviewed')->default(false);
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->dateTime('reviewed_at')->nullable();
            $t->string('review_note', 255)->nullable();
            $t->timestamp('created_at')->nullable();

            $t->index(['event_type', 'created_at']);
            $t->index(['severity', 'is_reviewed']);
            $t->index(['actor_type', 'actor_id']);
            $t->index('ip_address');
        });

        // ── ร่องรอยการแก้ไขข้อมูล (ใครแก้อะไร) ────────────────────
        Schema::create('audit_trails', function (Blueprint $t) {
            $t->id();
            $t->string('auditable_type', 100);
            $t->unsignedBigInteger('auditable_id');
            $t->enum('action', ['created', 'updated', 'deleted', 'restored']);
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->json('changed_fields')->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users');
            $t->string('user_label', 150)->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('route', 191)->nullable();
            $t->string('reason', 255)->nullable();     // เหตุผล (บังคับกับบางรายการ)
            $t->timestamp('created_at')->nullable();

            $t->index(['auditable_type', 'auditable_id']);
            $t->index(['user_id', 'created_at']);
            $t->index('created_at');
        });

        // ── ประวัติการเข้าสู่ระบบ ─────────────────────────────────
        Schema::create('login_attempts', function (Blueprint $t) {
            $t->id();
            $t->string('login_input', 191);            // อีเมล/เบอร์ที่กรอกมา
            $t->enum('guard', ['web', 'customer', 'api'])->default('web');
            $t->boolean('succeeded')->default(false);
            $t->string('failure_reason', 100)->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users');
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->string('country', 2)->nullable();
            $t->boolean('is_suspicious')->default(false);
            $t->timestamp('created_at')->nullable();

            $t->index(['login_input', 'created_at']);
            $t->index(['ip_address', 'created_at']);
            $t->index(['succeeded', 'created_at']);
        });

        // ── ข้อผิดพลาดของระบบ ────────────────────────────────────
        Schema::create('error_logs', function (Blueprint $t) {
            $t->id();
            $t->string('fingerprint', 64)->nullable();  // hash กันบันทึกซ้ำ
            $t->enum('level', ['debug', 'info', 'warning', 'error', 'critical'])->default('error');
            $t->string('exception_class', 191)->nullable();
            $t->text('message');
            $t->string('file', 255)->nullable();
            $t->unsignedInteger('line')->nullable();
            $t->text('stack_trace')->nullable();
            $t->string('route', 191)->nullable();
            $t->string('method', 10)->nullable();
            $t->json('input')->nullable();              // ตัดข้อมูลอ่อนไหวออกแล้ว
            $t->foreignId('user_id')->nullable()->constrained('users');
            $t->string('ip_address', 45)->nullable();
            $t->unsignedInteger('occurrence_count')->default(1);
            $t->dateTime('first_seen_at')->nullable();
            $t->dateTime('last_seen_at')->nullable();
            $t->boolean('is_resolved')->default(false);
            $t->timestamps();

            $t->index('fingerprint');
            $t->index(['level', 'is_resolved']);
            $t->index('last_seen_at');
        });

        // ── แจ้งเตือนแอดมิน ──────────────────────────────────────
        Schema::create('admin_alerts', function (Blueprint $t) {
            $t->id();
            $t->string('alert_type', 60);
            $t->enum('severity', ['info', 'warning', 'danger', 'critical'])->default('warning');
            $t->string('title', 200);
            $t->text('body')->nullable();
            $t->json('data')->nullable();
            $t->string('link', 255)->nullable();
            // ส่งไปช่องทางไหนแล้วบ้าง
            $t->boolean('sent_line')->default(false);
            $t->boolean('sent_email')->default(false);
            $t->dateTime('sent_at')->nullable();
            // สถานะการจัดการ
            $t->enum('status', ['new', 'acknowledged', 'resolved', 'ignored'])->default('new');
            $t->foreignId('handled_by')->nullable()->constrained('users');
            $t->dateTime('handled_at')->nullable();
            $t->string('handle_note', 255)->nullable();
            $t->timestamps();

            $t->index(['status', 'severity']);
            $t->index(['alert_type', 'created_at']);
        });

        // ── IP / บัญชี ที่ถูกระงับ ────────────────────────────────
        Schema::create('blocked_entities', function (Blueprint $t) {
            $t->id();
            $t->enum('entity_type', ['ip', 'user', 'customer', 'phone', 'device']);
            $t->string('entity_value', 191);
            $t->string('reason', 255);
            $t->enum('block_type', ['temporary', 'permanent'])->default('temporary');
            $t->dateTime('blocked_until')->nullable();
            $t->unsignedInteger('hit_count')->default(0);
            $t->foreignId('blocked_by')->nullable()->constrained('users');
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->unique(['entity_type', 'entity_value']);
            $t->index(['is_active', 'blocked_until']);
        });

        // ── กฎตรวจจับพฤติกรรมผิดปกติ (แอดมินปรับได้) ──────────────
        Schema::create('security_rules', function (Blueprint $t) {
            $t->id();
            $t->string('code', 60)->unique();
            $t->string('name', 150);
            $t->string('description', 255)->nullable();
            $t->unsignedInteger('threshold')->default(5);      // เกินกี่ครั้ง
            $t->unsignedInteger('window_minutes')->default(10); // ในกี่นาที
            $t->enum('action', ['log', 'alert', 'block_temp', 'block_perm'])->default('alert');
            $t->unsignedInteger('block_minutes')->default(30);
            $t->enum('severity', ['info', 'low', 'medium', 'high', 'critical'])->default('medium');
            $t->boolean('is_enabled')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'security_rules', 'blocked_entities', 'admin_alerts',
            'error_logs', 'login_attempts', 'audit_trails', 'security_events',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
