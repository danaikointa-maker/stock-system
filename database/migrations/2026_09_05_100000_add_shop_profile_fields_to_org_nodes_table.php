<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_nodes', function (Blueprint $table) {
            // ข้อมูลติดต่อเพิ่มเติม (phone, address, lat, lng มีอยู่แล้ว)
            $table->string('email', 100)->nullable()->after('phone');
            $table->string('line_id', 50)->nullable()->after('email');
            
            // เวลาทำการ
            $table->string('opening_hours', 100)->nullable()->after('line_id');
            
            // รูปถ่าย (JSON array)
            $table->json('photos')->nullable()->after('opening_hours');
            
            // ข้อมูลเพิ่มเติม
            $table->text('notes')->nullable()->after('photos');
            $table->string('shop_type', 50)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('org_nodes', function (Blueprint $table) {
            $table->dropColumn([
                'email', 'line_id', 'opening_hours', 'photos', 'notes', 'shop_type'
            ]);
        });
    }
};
