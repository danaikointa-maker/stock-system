<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            // เพิ่ม debit + credit columns
            $table->decimal('debit', 15, 2)->default(0)->after('account_id');
            $table->decimal('credit', 15, 2)->default(0)->after('debit');
        });

        // ย้ายข้อมูลจาก dc/amount → debit/credit (ถ้ามีข้อมูลเก่า)
        DB::statement("UPDATE journal_lines SET debit = amount WHERE dc = 'D' AND debit = 0");
        DB::statement("UPDATE journal_lines SET credit = amount WHERE dc = 'C' AND credit = 0");

        Schema::table('journal_lines', function (Blueprint $table) {
            // ลบ dc + amount columns
            $table->dropColumn(['dc', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->enum('dc', ['D', 'C'])->after('account_id');
            $table->decimal('amount', 15, 2)->after('dc');
        });

        DB::statement("UPDATE journal_lines SET dc = 'D', amount = debit WHERE debit > 0");
        DB::statement("UPDATE journal_lines SET dc = 'C', amount = credit WHERE credit > 0");

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropColumn(['debit', 'credit']);
        });
    }
};
