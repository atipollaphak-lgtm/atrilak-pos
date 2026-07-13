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
        Schema::create('technician_commissions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('sale_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('technician_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('commission_amount', 12, 2)->default(0);

            $table->decimal('manual_adjust', 12, 2)->default(0);

            $table->decimal('payable_amount', 12, 2)->default(0);

            $table->text('adjust_remark')->nullable();

            $table->string('rule_name')->nullable();

            $table->json('calculation_detail')->nullable();

            $table->string('status')->default('unpaid');

            $table->timestamp('paid_at')->nullable();

            $table->foreignId('paid_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('payment_batch_id')
                ->nullable()
                ->constrained('technician_payment_batches')
                ->nullOnDelete();

            $table->text('remark')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technician_commissions');
    }
};
