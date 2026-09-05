<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('org_node_id')->constrained('org_nodes')->cascadeOnDelete();
            
            // พิกัดตอน check-in
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            
            // ประเภท check-in
            $table->enum('type', ['visit', 'delivery', 'pickup', 'other'])->default('visit');
            
            // รายละเอียด
            $table->text('notes')->nullable();
            
            // รูปถ่าย (JSON array)
            $table->json('photos')->nullable();
            
            // ระยะห่างจากร้าน (เมตร) — คำนวณจาก GPS
            $table->integer('distance_meters')->nullable();
            
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['org_node_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_checkins');
    }
};
