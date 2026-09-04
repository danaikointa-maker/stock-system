<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ═══ ผังบัญชี (Chart of Accounts) ═══
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->decimal('balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('org_node_id')->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('parent_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('org_node_id')->references('id')->on('org_nodes')->nullOnDelete();
        });

        // ═══ รายการบันทึกบัญชี (Journal Entries) ═══
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 30)->unique();
            $table->date('entry_date');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'posted', 'void'])->default('draft');
            $table->unsignedBigInteger('org_node_id');
            $table->unsignedBigInteger('created_by');
            $table->string('ref_type', 50)->nullable(); // invoice, payment, receipt, etc.
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->timestamps();
            $table->foreign('org_node_id')->references('id')->on('org_nodes');
            $table->foreign('created_by')->references('id')->on('users');
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('account_id');
            $table->enum('dc', ['D', 'C']); // Debit / Credit
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('accounts');
        });

        // ═══ บิลเรียกเก็บ (Invoices) ═══
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 30)->unique();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->unsignedBigInteger('org_node_id');     // ผู้ออกบิล
            $table->unsignedBigInteger('customer_node_id')->nullable(); // ลูกหนี้ (หน่วยงาน)
            $table->string('customer_name');
            $table->string('customer_address')->nullable();
            $table->string('customer_tax_id', 20)->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(7);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->enum('status', ['draft', 'issued', 'partial', 'paid', 'overdue', 'void'])->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->foreign('org_node_id')->references('id')->on('org_nodes');
            $table->foreign('customer_node_id')->references('id')->on('org_nodes')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->string('description');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('qty', 12, 2)->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        // ═══ บิลรับ (Receipts) ═══
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 30)->unique();
            $table->date('receipt_date');
            $table->unsignedBigInteger('org_node_id');
            $table->string('payer_name');
            $table->string('payer_tax_id', 20)->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->enum('method', ['cash', 'bank_transfer', 'promptpay', 'cheque', 'credit_card'])->default('bank_transfer');
            $table->string('bank_ref')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->foreign('org_node_id')->references('id')->on('org_nodes');
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');
        });

        // ═══ บิลจ่าย (Payments/Expense Vouchers) ═══
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no', 30)->unique();
            $table->date('payment_date');
            $table->unsignedBigInteger('org_node_id');
            $table->string('payee_name');
            $table->string('payee_tax_id', 20)->nullable();
            $table->decimal('amount', 15, 2);
            $table->enum('method', ['cash', 'bank_transfer', 'promptpay', 'cheque'])->default('bank_transfer');
            $table->string('bank_ref')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->foreign('org_node_id')->references('id')->on('org_nodes');
            $table->foreign('created_by')->references('id')->on('users');
        });

        // ═══ ใบกำกับภาษี (Tax Invoices) ═══
        Schema::create('tax_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('tax_invoice_no', 30)->unique();
            $table->date('issue_date');
            $table->unsignedBigInteger('org_node_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('buyer_name');
            $table->string('buyer_address')->nullable();
            $table->string('buyer_tax_id', 20)->nullable();
            $table->string('buyer_branch')->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('vat_rate', 5, 2)->default(7);
            $table->decimal('vat_amount', 15, 2);
            $table->decimal('total', 15, 2);
            $table->enum('type', ['full', 'simplified', 'revised'])->default('full');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->foreign('org_node_id')->references('id')->on('org_nodes');
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');
        });

        // ═══ ใบหัก ณ ที่จ่าย (Withholding Tax Certificates) ═══
        Schema::create('withholding_taxes', function (Blueprint $table) {
            $table->id();
            $table->string('wht_no', 30)->unique();
            $table->date('issue_date');
            $table->unsignedBigInteger('org_node_id');       // ผู้ออก (ผู้จ่ายเงิน)
            $table->string('payee_name');                     // ผู้รับเงิน (ถูกหัก)
            $table->string('payee_address')->nullable();
            $table->string('payee_tax_id', 20)->nullable();
            $table->decimal('income_amount', 15, 2);          // จำนวนเงินก่อนหัก
            $table->decimal('wht_rate', 5, 2);                // อัตราหัก % (1, 2, 3, 5, 15)
            $table->decimal('wht_amount', 15, 2);             // จำนวนเงินที่หัก
            $table->decimal('net_amount', 15, 2);              // จำนวนเงินสุทธิที่จ่าย
            $table->string('income_type')->nullable();         // ประเภทเงินได้ (บริการ, ค่าเช่า, etc.)
            $table->string('condition', 50)->default('หัก ณ ที่จ่าย'); // หัก ณ ที่จ่าย / ออกตลอดไป
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->foreign('org_node_id')->references('id')->on('org_nodes');
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');
        });

        // ═══ เลขที่เอกสารอัตโนมัติ (Doc Sequences) ═══
        Schema::create('doc_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);   // INV, RCP, PAY, TXI, WHT, JV
            $table->unsignedBigInteger('org_node_id');
            $table->integer('year');
            $table->integer('month');
            $table->integer('last_number')->default(0);
            $table->timestamps();
            $table->unique(['type', 'org_node_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_sequences');
        Schema::dropIfExists('withholding_taxes');
        Schema::dropIfExists('tax_invoices');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
