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
        Schema::create('technician_payment_batches', function (Blueprint $table) {
            $table->id();

            $table->string('batch_no')->unique();

            $table->foreignId('technician_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('total_amount', 12, 2);

            $table->string('payment_method')->default('cash');

            $table->string('reference_no')->nullable();

            $table->timestamp('paid_at');

            $table->foreignId('paid_by')
                ->nullable()
                ->constrained('users')
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
        Schema::dropIfExists('technician_payment_batches');
    }
};
