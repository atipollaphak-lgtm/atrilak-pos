<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_barcodes', function (Blueprint $table) {

            $table->id();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_unit_id')
                ->nullable()
                ->constrained('product_units')
                ->nullOnDelete();

            $table->string('barcode', 100)->unique();

            $table->boolean('active')->default(true);

            $table->integer('sort_order')->default(0);

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_barcodes');
    }
};
