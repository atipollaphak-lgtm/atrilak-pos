<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('barcode')->nullable();

            $table->string('name');

            $table->string('unit')->default('ชิ้น');

            $table->decimal('cost_price', 12, 2)->default(0);

            $table->decimal('selling_price', 12, 2)->default(0);

            $table->integer('stock_qty')->default(0);

            $table->integer('minimum_stock')->default(0);

            $table->boolean('vat_enabled')->default(false);

            $table->boolean('active')->default(true);

            $table->text('remark')->nullable();

            $table->timestamps();

            $table->string('sku')->nullable();
$table->string('product_code')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
