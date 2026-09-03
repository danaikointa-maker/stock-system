<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $t) {
            $t->id();
            $t->tinyInteger('level_id')->unsigned();
            $t->foreignId('product_id')->nullable()->constrained(); // null = ทุกสินค้า
            $t->enum('calc_type', ['percent', 'fixed'])->default('percent');
            $t->decimal('value', 10, 2);
            $t->boolean('active')->default(true);
            $t->timestamps();

            $t->foreign('level_id')->references('id')->on('org_levels');
        });

        Schema::create('commission_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('org_node_id')->constrained('org_nodes');
            $t->foreignId('sale_id')->nullable()->constrained();
            $t->decimal('amount', 14, 2);
            $t->string('period', 7);   // 2026-09
            $t->enum('status', ['pending', 'paid', 'void'])->default('pending');
            $t->timestamps();

            $t->index(['period', 'org_node_id']);
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->foreignId('org_node_id')->nullable()->constrained('org_nodes');
            $t->string('action', 100);
            $t->string('auditable_type', 100)->nullable();
            $t->unsignedBigInteger('auditable_id')->nullable();
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();

            $t->index(['auditable_type', 'auditable_id']);
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('commission_entries');
        Schema::dropIfExists('commission_rules');
    }
};
