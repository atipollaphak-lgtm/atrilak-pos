<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_unit_promotions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('product_unit_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name', 150);

            $table->integer('min_qty')->default(1);

            $table->decimal('discount_percent', 8, 2)->default(0);

            $table->decimal('discount_amount', 15, 2)->default(0);

            $table->date('start_date')->nullable();

            $table->date('end_date')->nullable();

            $table->boolean('active')->default(true);

            $table->integer('sort_order')->default(0);

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_unit_promotions');
    }
};
