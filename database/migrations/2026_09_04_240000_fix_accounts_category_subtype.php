<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // เพิ่ม category + sub_type + opening_balance columns
            $table->string('category', 20)->nullable()->after('name');
            $table->string('sub_type', 50)->nullable()->after('category');
            $table->decimal('opening_balance', 15, 2)->default(0)->after('sub_type');
        });

        // ย้ายข้อมูลจาก type → category
        DB::statement("UPDATE accounts SET category = type WHERE category IS NULL");

        // ทำให้ code unique per org_node_id (ไม่ใช่ global unique)
        // ลบ unique constraint เดิม
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });

        // เพิ่ม index แทน
        Schema::table('accounts', function (Blueprint $table) {
            $table->index(['code', 'org_node_id']);
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex(['code', 'org_node_id']);
            $table->unique('code');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['category', 'sub_type', 'opening_balance']);
        });
    }
};
