<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ใบส่งของ (Delivery Notes)
        Schema::create('delivery_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('org_node_id')->constrained('org_nodes')->cascadeOnDelete();
            $t->string('doc_no', 30)->unique();
            $t->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->string('customer_name', 150)->nullable();
            $t->string('delivery_address')->nullable();
            $t->string('recipient_name', 100)->nullable();
            $t->string('recipient_phone', 30)->nullable();
            $t->enum('status', ['draft','ready','shipped','delivered','returned','cancelled'])->default('draft');
            $t->integer('total_qty')->default(0);
            $t->decimal('total_amount', 14, 2)->default(0);
            $t->timestamp('shipped_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->string('tracking_no', 50)->nullable();
            $t->string('carrier', 100)->nullable();
            $t->text('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('delivery_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('delivery_note_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('lot_id')->nullable()->constrained('product_lots')->restrictOnDelete();
            $t->integer('qty');
            $t->decimal('unit_cost', 12, 2)->default(0);
            $t->decimal('unit_price', 12, 2)->default(0);
            $t->decimal('line_total', 14, 2)->default(0);
            $t->boolean('returned')->default(false);
            $t->integer('returned_qty')->default(0);
            $t->string('note')->nullable();
            $t->timestamps();
        });

        // ใบลดหนี้ / ใบคืนสินค้า (Credit Notes) - ใช้หักล้าง ไม่ใช่ลบ
        Schema::create('credit_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('org_node_id')->constrained('org_nodes')->cascadeOnDelete();
            $t->string('doc_no', 30)->unique();
            $t->enum('type', ['return','discount','cancel','adjustment'])->default('return');
            $t->string('reason')->nullable();
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('delivery_note_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $t->string('customer_name', 150)->nullable();
            $t->decimal('subtotal', 14, 2)->default(0);
            $t->decimal('vat_amount', 12, 2)->default(0);
            $t->decimal('total_amount', 14, 2)->default(0);
            $t->enum('status', ['draft','confirmed','cancelled'])->default('draft');
            $t->boolean('posted_to_accounting')->default(false);
            $t->text('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('credit_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('lot_id')->nullable()->constrained('product_lots')->restrictOnDelete();
            $t->integer('qty');
            $t->decimal('unit_cost', 12, 2)->default(0);
            $t->decimal('unit_price', 12, 2)->default(0);
            $t->decimal('line_total', 14, 2)->default(0);
            $t->string('note')->nullable();
            $t->timestamps();
        });

        // Stock Ledger (append-only, immutable, audit trail)
        Schema::create('stock_ledger', function (Blueprint $t) {
            $t->id();
            $t->foreignId('org_node_id')->constrained('org_nodes')->restrictOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('lot_id')->nullable()->constrained('product_lots')->restrictOnDelete();
            $t->string('movement_type', 30);  // receipt, sale, transfer_out, transfer_in, return_in, adjust, damage
            $t->string('direction', 5);       // in / out
            $t->integer('qty');
            $t->decimal('unit_cost', 12, 2)->default(0);
            $t->decimal('total_cost', 14, 2)->default(0);
            $t->integer('balance_after');
            $t->string('ref_type')->nullable();
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->string('journal_entry_ref')->nullable();  // ref to journal_entries
            $t->text('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->nullable();
            // ไม่มี updated_at — append only
        });

        // ห้ามแก้ไข/ลบ stock_ledger
        // จะ enforce ใน Model boot
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_items');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('delivery_items');
        Schema::dropIfExists('delivery_notes');
        Schema::dropIfExists('stock_ledger');
    }
};
