<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_price_histories')) {
            Schema::create('product_price_histories', function (Blueprint $table) {
                $table->id();

                $table->foreignId('product_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->decimal('old_price', 12, 2);
                $table->decimal('new_price', 12, 2);

                $table->decimal('average_cost', 12, 2)->nullable();
                $table->decimal('profit_percent', 8, 2)->nullable();
                $table->decimal('price_before_round', 12, 2)->nullable();
                $table->decimal('satang_rounded_price', 12, 2)->nullable();

                $table->decimal('final_price', 12, 2);

                $table->string('created_from')->default('manual');

                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained()
                    ->nullOnDelete();

                $table->text('remark')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_histories');
    }
};
