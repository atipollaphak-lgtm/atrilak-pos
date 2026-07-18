<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_payment_closings', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->unique();
            $table->string('status', 20)->default('open')->index();
            $table->decimal('expected_cash_amount', 15, 2)->default(0);
            $table->decimal('expected_promptpay_amount', 15, 2)->default(0);
            $table->decimal('expected_recorded_sales_amount', 15, 2)->default(0);
            $table->decimal('expected_received_cash_amount', 15, 2)->default(0);
            $table->decimal('expected_change_amount', 15, 2)->default(0);
            $table->unsignedInteger('cash_sales_count')->default(0);
            $table->unsignedInteger('promptpay_sales_count')->default(0);
            $table->unsignedInteger('mixed_sales_count')->default(0);
            $table->unsignedInteger('unrecorded_payment_count')->default(0);
            $table->decimal('actual_cash_amount', 15, 2)->nullable();
            $table->decimal('actual_promptpay_amount', 15, 2)->nullable();
            $table->decimal('cash_variance', 15, 2)->nullable();
            $table->decimal('promptpay_variance', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable()->index();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_payment_closings');
    }
};
