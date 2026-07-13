<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {

            $table->id();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('unit_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('conversion_rate', 15, 4)->default(1);
            // จำนวนหน่วยฐาน เช่น 1 พาเลท = 50 ถุง

            $table->boolean('is_base_unit')->default(false);

            $table->boolean('is_purchase_unit')->default(true);

            $table->boolean('is_sale_unit')->default(true);

            $table->decimal('purchase_price', 15, 2)->nullable();

            $table->decimal('selling_price', 15, 2)->nullable();

            $table->boolean('active')->default(true);

            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['product_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
