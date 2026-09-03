<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('categories');
            $t->string('name', 150);
            $t->timestamps();
        });

        Schema::create('units', function (Blueprint $t) {
            $t->id();
            $t->string('name', 50)->unique();
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('sku', 64)->unique();
            $t->string('barcode', 64)->nullable()->unique();
            $t->string('name', 200);
            $t->foreignId('category_id')->nullable()->constrained('categories');
            $t->foreignId('unit_id')->nullable()->constrained('units');
            $t->unsignedInteger('pack_size')->default(1);
            $t->decimal('cost_price', 12, 2)->default(0);
            $t->decimal('retail_price', 12, 2)->default(0);
            $t->integer('points_per_unit')->default(0);
            $t->boolean('track_serial')->default(true);
            $t->boolean('has_expiry')->default(false);
            $t->string('image_url')->nullable();
            $t->enum('status', ['active', 'inactive'])->default('active');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('product_level_prices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->tinyInteger('level_id')->unsigned();
            $t->decimal('price', 12, 2);
            $t->unsignedInteger('min_qty')->default(1);
            $t->date('effective_from')->nullable();
            $t->date('effective_to')->nullable();
            $t->timestamps();

            $t->foreign('level_id')->references('id')->on('org_levels');
            $t->unique(['product_id', 'level_id', 'min_qty', 'effective_from'], 'uq_plp');
        });

        Schema::create('product_lots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained();
            $t->string('lot_no', 64);
            $t->date('mfg_date')->nullable();
            $t->date('expiry_date')->nullable();
            $t->unsignedInteger('qty_produced')->default(0);
            $t->timestamps();

            $t->unique(['product_id', 'lot_no']);
            $t->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_lots');
        Schema::dropIfExists('product_level_prices');
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
        Schema::dropIfExists('categories');
    }
};
