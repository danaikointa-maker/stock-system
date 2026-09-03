<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * รายการของรางวัล/บริการที่ "แต่ละร้าน" ตั้งเอง
 *
 * ต่างจากตาราง rewards เดิมที่เป็นของส่วนกลาง
 * ตารางนี้ผูกกับร้าน เจ้าของร้านเพิ่ม/แก้/ปิดได้เอง
 * และแสดงบนหน้าร้านสาธารณะให้ลูกค้าเห็นว่าแลกอะไรได้บ้าง
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_rewards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shop_node_id')->constrained('org_nodes');
            $t->string('name', 200);
            $t->string('description', 500)->nullable();
            $t->enum('reward_type', ['goods', 'service', 'discount', 'cash'])->default('service');
            $t->unsignedBigInteger('points_cost');
            $t->decimal('cash_value', 12, 2)->default(0);   // มูลค่าโดยประมาณ ใช้แสดงผล
            $t->string('image_path', 255)->nullable();
            $t->string('icon', 10)->nullable();             // อีโมจิ ใช้เมื่อไม่มีรูป
            // ผูกกับสินค้าจริง (เฉพาะของรางวัลประเภทสินค้า)
            $t->foreignId('product_id')->nullable()->constrained('products');
            $t->unsignedInteger('qty_per_redeem')->default(1);
            // จำกัดจำนวน
            $t->integer('stock_limit')->nullable();         // null = ไม่จำกัด
            $t->unsignedInteger('redeemed_count')->default(0);
            $t->unsignedInteger('limit_per_customer')->nullable();
            $t->smallInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->timestamps();

            $t->index(['shop_node_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_rewards');
    }
};
