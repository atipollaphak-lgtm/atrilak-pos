<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hold_bill_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hold_bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('qty', 15, 2);
            $table->decimal('selling_price', 15, 2);
            $table->string('product_name_snapshot');
            $table->string('product_sku_snapshot')->nullable();
            $table->string('product_code_snapshot')->nullable();
            $table->string('unit_name_snapshot')->nullable();
            $table->string('unit_code_snapshot')->nullable();
            $table->timestamps();

            $table->index(['hold_bill_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hold_bill_items');
    }
};
