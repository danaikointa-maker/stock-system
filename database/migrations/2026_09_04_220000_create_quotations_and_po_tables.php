<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ใบเสนอราคา (Quotation)
        Schema::create('quotations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('org_node_id')->constrained('org_nodes')->cascadeOnDelete();
            $t->string('doc_no', 30)->unique();
            $t->string('customer_name', 200);
            $t->string('customer_address')->nullable();
            $t->string('customer_tax_id', 20)->nullable();
            $t->string('customer_contact', 100)->nullable();
            $t->date('issue_date');
            $t->date('valid_until');
            $t->decimal('subtotal', 14, 2)->default(0);
            $t->decimal('discount', 12, 2)->default(0);
            $t->decimal('vat_rate', 5, 2)->default(7);
            $t->decimal('vat_amount', 12, 2)->default(0);
            $t->decimal('total', 14, 2)->default(0);
            $t->enum('status', ['draft','sent','accepted','rejected','expired','converted'])->default('draft');
            $t->text('notes')->nullable();
            $t->text('terms')->nullable();
            $t->foreignId('converted_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('quotation_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $t->string('description');
            $t->decimal('qty', 12, 2)->default(1);
            $t->decimal('unit_price', 12, 2)->default(0);
            $t->decimal('line_total', 14, 2)->default(0);
            $t->timestamps();
        });

        // ใบสั่งซื้อ (Purchase Order)
        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('org_node_id')->constrained('org_nodes')->cascadeOnDelete();
            $t->string('po_no', 30)->unique();
            $t->string('vendor_name', 200);
            $t->string('vendor_address')->nullable();
            $t->string('vendor_tax_id', 20)->nullable();
            $t->string('vendor_contact', 100)->nullable();
            $t->date('order_date');
            $t->date('expected_date')->nullable();
            $t->decimal('subtotal', 14, 2)->default(0);
            $t->decimal('discount', 12, 2)->default(0);
            $t->decimal('vat_rate', 5, 2)->default(7);
            $t->decimal('vat_amount', 12, 2)->default(0);
            $t->decimal('wht_rate', 5, 2)->default(0);
            $t->decimal('wht_amount', 12, 2)->default(0);
            $t->decimal('total', 14, 2)->default(0);
            $t->decimal('net_total', 14, 2)->default(0);
            $t->enum('status', ['draft','approved','ordered','partial_received','received','cancelled'])->default('draft');
            $t->text('notes')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('description');
            $t->decimal('qty', 12, 2)->default(1);
            $t->decimal('unit_price', 12, 2)->default(0);
            $t->decimal('line_total', 14, 2)->default(0);
            $t->decimal('received_qty', 12, 2)->default(0);
            $t->timestamps();
        });

        // Manual Journal Entries (ลงบัญชีแยกด้วยมือ)
        Schema::create('manual_journals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('org_node_id')->constrained('org_nodes')->cascadeOnDelete();
            $t->string('doc_no', 30)->unique();
            $t->date('entry_date');
            $t->string('description');
            $t->enum('status', ['draft','posted','reversed'])->default('draft');
            $t->text('notes')->nullable();
            $t->foreignId('reversed_by_id')->nullable()->constrained('manual_journals')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('manual_journal_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('manual_journal_id')->constrained()->cascadeOnDelete();
            $t->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $t->decimal('debit', 14, 2)->default(0);
            $t->decimal('credit', 14, 2)->default(0);
            $t->string('description')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_journal_lines');
        Schema::dropIfExists('manual_journals');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
