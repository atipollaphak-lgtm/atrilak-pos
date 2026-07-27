<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_payment_closing_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_payment_closing_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sale_revision')->default(1);
            $table->string('sale_status', 20);
            $table->decimal('sale_total_amount', 15, 2)->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->decimal('cash_amount', 15, 2)->nullable();
            $table->decimal('promptpay_amount', 15, 2)->nullable();
            $table->decimal('received_amount', 15, 2)->nullable();
            $table->decimal('change_amount', 15, 2)->nullable();
            $table->timestamps();

            $table->index('sale_id');
            $table->unique(['daily_payment_closing_id', 'sale_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_payment_closing_sales');
    }
};
