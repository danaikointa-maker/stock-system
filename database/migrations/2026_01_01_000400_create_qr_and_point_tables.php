<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_qrcodes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained();
            $t->foreignId('lot_id')->nullable()->constrained('product_lots');
            $t->string('serial_no', 40)->unique();
            $t->char('qr_token', 32)->unique();       // ใช้ใน URL
            $t->char('secret_hash', 64)->nullable();  // hash รหัสใต้ฟิล์มขูด
            $t->integer('points')->default(0);
            $t->foreignId('current_node_id')->nullable()->constrained('org_nodes');
            $t->enum('status', ['created', 'in_stock', 'sold', 'redeemed', 'void'])->default('created');
            $t->dateTime('activated_at')->nullable();
            $t->dateTime('redeemed_at')->nullable();
            $t->foreignId('redeemed_by_customer_id')->nullable()->constrained('customers');
            $t->dateTime('expires_at')->nullable();
            $t->timestamps();

            $t->index('status');
            $t->index('lot_id');
        });

        Schema::create('qr_scan_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('qrcode_id')->nullable()->constrained('product_qrcodes');
            $t->string('raw_token', 64)->nullable();
            $t->foreignId('customer_id')->nullable()->constrained('customers');
            $t->foreignId('org_node_id')->nullable()->constrained('org_nodes');
            $t->enum('result', [
                'success', 'already_used', 'invalid', 'expired', 'rate_limited', 'blocked',
            ]);
            $t->integer('points_awarded')->default(0);
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent')->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->timestamp('scanned_at')->useCurrent();

            $t->index(['customer_id', 'scanned_at']);
            $t->index(['result', 'scanned_at']);
        });

        Schema::create('point_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained();
            $t->enum('type', ['earn_scan', 'earn_bonus', 'redeem', 'expire', 'adjust', 'reverse']);
            $t->integer('points');            // + ได้รับ / - ใช้ไป
            $t->integer('balance_after');
            $t->string('ref_type', 50)->nullable();
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->date('expires_at')->nullable();
            $t->string('note')->nullable();
            $t->timestamp('created_at')->useCurrent();

            $t->index(['customer_id', 'created_at']);
        });

        Schema::create('rewards', function (Blueprint $t) {
            $t->id();
            $t->string('name', 200);
            $t->unsignedInteger('points_cost');
            $t->integer('stock_qty')->default(0);
            $t->string('image_url')->nullable();
            $t->enum('status', ['active', 'inactive'])->default('active');
            $t->timestamps();
        });

        Schema::create('reward_redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained();
            $t->foreignId('reward_id')->constrained();
            $t->unsignedInteger('points_used');
            $t->enum('status', ['pending', 'approved', 'shipped', 'completed', 'rejected'])
                ->default('pending');
            $t->text('address')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_redemptions');
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('point_transactions');
        Schema::dropIfExists('qr_scan_logs');
        Schema::dropIfExists('product_qrcodes');
    }
};
