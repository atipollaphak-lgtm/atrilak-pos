<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hold_bills', function (Blueprint $table): void {
            $table->id();
            $table->string('hold_no')->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_delivery_address_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('delivery_zone_name_snapshot')->nullable();
            $table->decimal('delivery_zone_markup_percent_snapshot', 8, 2)->nullable();
            $table->decimal('delivery_zone_rounding_increment_snapshot', 8, 2)->nullable();
            $table->decimal('delivery_zone_minimum_profit_snapshot', 15, 2)->nullable();
            $table->date('sale_date');
            $table->string('delivery_type', 20)->default('delivery');
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('delivery_fee', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['sale_date', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hold_bills');
    }
};
