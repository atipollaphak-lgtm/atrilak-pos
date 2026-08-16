<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_external_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();
            $table->foreignId('batch_id')
                ->nullable()
                ->constrained('customer_import_batches')
                ->nullOnDelete();
            $table->string('source_system', 50);
            $table->string('external_id');
            $table->timestamps();

            $table->unique(['source_system', 'external_id']);
            $table->index('customer_id');
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_external_references');
    }
};
