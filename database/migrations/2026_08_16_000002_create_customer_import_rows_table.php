<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')
                ->constrained('customer_import_batches')
                ->cascadeOnDelete();
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('external_id')->nullable();
            $table->string('name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('tax_number')->nullable();
            $table->string('branch_type')->nullable();
            $table->string('branch_number', 10)->nullable();
            $table->text('address')->nullable();
            $table->text('remark')->nullable();
            $table->string('status', 32);
            $table->json('reasons')->nullable();
            $table->json('warnings')->nullable();
            $table->json('original_values')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'status']);
            $table->index(['batch_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_import_rows');
    }
};
