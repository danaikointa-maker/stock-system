<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * คิวแจ้งเตือน LINE / Email
 *
 * ทำไมต้องมีคิวแทนที่จะส่งทันที
 *   1) การส่งต้องเรียก API ภายนอก ถ้าปลายทางล่มจะทำให้ธุรกรรมหลักช้าหรือพัง
 *      เช่น ลูกค้าสแกน QR แล้ว LINE ล่ม -> ต้องไม่ทำให้แต้มไม่เข้า
 *   2) ส่งไม่สำเร็จต้องลองใหม่ได้ พร้อมนับจำนวนครั้ง
 *   3) เก็บหลักฐานว่าส่งอะไรไปหาใคร เมื่อไร ผลเป็นอย่างไร
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_queue', function (Blueprint $t) {
            $t->id();

            // ส่งหาใคร
            $t->enum('channel', ['line', 'email']);
            $t->enum('recipient_type', ['customer', 'user', 'admin'])->default('customer');
            $t->unsignedBigInteger('recipient_id')->nullable();
            $t->string('destination', 191);        // LINE userId หรืออีเมล

            // เนื้อหา
            $t->string('template', 60);            // points_earned, redeem_confirmed, ...
            $t->string('subject', 200)->nullable();
            $t->text('body');
            $t->json('payload')->nullable();       // ข้อมูลดิบไว้สร้างข้อความใหม่ถ้าต้องการ

            // สถานะ
            $t->enum('status', ['pending', 'sending', 'sent', 'failed', 'cancelled'])
                ->default('pending');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->unsignedTinyInteger('max_attempts')->default(3);
            $t->dateTime('scheduled_at')->nullable();   // เลื่อนส่งได้
            $t->dateTime('sent_at')->nullable();
            $t->string('error_message', 500)->nullable();
            $t->string('provider_message_id', 191)->nullable();

            // อ้างอิงต้นทาง เผื่อตรวจย้อนกลับ
            $t->string('ref_type', 60)->nullable();
            $t->unsignedBigInteger('ref_id')->nullable();

            $t->timestamps();

            $t->index(['status', 'scheduled_at']);
            $t->index(['recipient_type', 'recipient_id']);
            $t->index(['ref_type', 'ref_id']);
            $t->index('channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_queue');
    }
};
