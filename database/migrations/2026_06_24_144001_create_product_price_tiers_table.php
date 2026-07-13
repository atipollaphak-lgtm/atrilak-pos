<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_tiers', function (Blueprint $table) {

            $table->id();

            $table->foreignId('product_unit_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->integer('min_qty');

            $table->decimal('discount_percent', 8, 2)
                ->default(0);

            $table->decimal('fixed_price', 15, 2)
                ->nullable();

            $table->boolean('active')
                ->default(true);

            $table->integer('sort_order')
                ->default(0);

            $table->timestamps();

            $table->unique([
                'product_unit_id',
                'min_qty'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_tiers');
    }
};
