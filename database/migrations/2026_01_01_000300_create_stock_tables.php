<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('org_node_id')->constrained('org_nodes');
            $t->foreignId('product_id')->constrained();
            $t->foreignId('lot_id')->nullable()->constrained('product_lots');
            $t->integer('qty_on_hand')->default(0);
            $t->integer('qty_reserved')->default(0);
            $t->integer('qty_in_transit')->default(0);
            $t->integer('reorder_point')->default(0);
            $t->timestamp('updated_at')->nullable();

            $t->unique(['org_node_id', 'product_id', 'lot_id'], 'uq_balance');
            $t->index('product_id');
        });

        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('org_node_id')->constrained('org_nodes');
            $t->foreignId('product_id')->constrained();
            $t->foreignId('lot_id')->nullable()->constrained('product_lots');
            $t->enum('direction', ['in', 'out']);
            $t->unsignedInteger('qty');
            $t->integer('balance_after');
            $t->enum('type', [
                'receipt', 'transfer_out', 'transfer_in', 'sale',
                'return_in', 'return_out', 'adjust_in', 'adjust_out',
                'damage', 'expired',
            ]);
            $t->string('ref_type', 50)->nullable();
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->decimal('unit_cost', 12, 2)->nullable();
            $t->string('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamp('created_at')->useCurrent();

            $t->index(['org_node_id', 'product_id', 'created_at'], 'idx_mv_node_prod');
            $t->index(['ref_type', 'ref_id'], 'idx_mv_ref');
        });

        Schema::create('transfers', function (Blueprint $t) {
            $t->id();
            $t->string('doc_no', 40)->unique();
            $t->foreignId('from_node_id')->constrained('org_nodes');
            $t->foreignId('to_node_id')->constrained('org_nodes');
            $t->enum('type', ['allocation', 'requisition', 'return'])->default('allocation');
            $t->enum('status', [
                'draft', 'pending_approve', 'approved', 'rejected',
                'shipped', 'received', 'cancelled',
            ])->default('draft');
            $t->integer('total_qty')->default(0);
            $t->decimal('total_amount', 14, 2)->default(0);
            $t->foreignId('requested_by')->nullable()->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->dateTime('approved_at')->nullable();
            $t->dateTime('shipped_at')->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users');
            $t->dateTime('received_at')->nullable();
            $t->text('note')->nullable();
            $t->timestamps();

            $t->index(['from_node_id', 'status']);
            $t->index(['to_node_id', 'status']);
        });

        Schema::create('transfer_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('transfer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained();
            $t->foreignId('lot_id')->nullable()->constrained('product_lots');
            $t->unsignedInteger('qty_requested');
            $t->unsignedInteger('qty_shipped')->default(0);
            $t->unsignedInteger('qty_received')->default(0);
            $t->decimal('unit_price', 12, 2)->default(0);
        });

        Schema::create('sales', function (Blueprint $t) {
            $t->id();
            $t->string('doc_no', 40)->unique();
            $t->foreignId('org_node_id')->constrained('org_nodes');
            $t->foreignId('seller_user_id')->nullable()->constrained('users');
            $t->foreignId('customer_id')->nullable()->constrained('customers');
            $t->dateTime('sold_at');
            $t->decimal('subtotal', 14, 2)->default(0);
            $t->decimal('discount', 14, 2)->default(0);
            $t->decimal('total', 14, 2)->default(0);
            $t->enum('payment_method', ['cash', 'transfer', 'qr', 'credit'])->default('cash');
            $t->enum('status', ['completed', 'voided'])->default('completed');
            $t->timestamps();

            $t->index(['org_node_id', 'sold_at']);
        });

        Schema::create('sale_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained();
            $t->foreignId('lot_id')->nullable()->constrained('product_lots');
            $t->unsignedInteger('qty');
            $t->decimal('unit_price', 12, 2);
            $t->decimal('discount', 12, 2)->default(0);
            $t->decimal('line_total', 14, 2);
        });

        DB::statement('DROP VIEW IF EXISTS v_stock_summary');
        DB::statement(<<<'SQL'
            CREATE VIEW v_stock_summary AS
            SELECT n.id AS node_id, n.code AS node_code, n.name AS node_name,
                   l.name_th AS level_name, p.id AS product_id, p.sku, p.name AS product_name,
                   SUM(b.qty_on_hand) AS on_hand,
                   SUM(b.qty_reserved) AS reserved,
                   SUM(b.qty_on_hand - b.qty_reserved) AS available,
                   SUM(b.qty_in_transit) AS in_transit
            FROM stock_balances b
            JOIN org_nodes n ON n.id = b.org_node_id
            JOIN org_levels l ON l.id = n.level_id
            JOIN products p ON p.id = b.product_id
            GROUP BY n.id, n.code, n.name, l.name_th, p.id, p.sku, p.name
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_stock_summary');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('transfer_items');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_balances');
    }
};
