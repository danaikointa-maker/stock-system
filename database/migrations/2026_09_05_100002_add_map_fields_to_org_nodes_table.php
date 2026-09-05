<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_nodes', function (Blueprint $table) {
            // แสดงบนแผนที่สาธารณะ (ลูกค้าเห็น)
            $table->boolean('show_on_map')->default(false)->after('shop_type');
            
            // รูป cover สำหรับแสดงบนแผนที่
            $table->string('map_cover_photo', 500)->nullable()->after('show_on_map');
            
            // คำอธิบายสั้นๆ สำหรับแสดงบนแผนที่
            $table->string('map_description', 255)->nullable()->after('map_cover_photo');
        });
    }

    public function down(): void
    {
        Schema::table('org_nodes', function (Blueprint $table) {
            $table->dropColumn(['show_on_map', 'map_cover_photo', 'map_description']);
        });
    }
};
