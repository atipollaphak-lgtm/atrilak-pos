<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_price_histories', function (Blueprint $table) {

            $table->decimal('old_price', 12, 2)->nullable()->after('product_id');
            $table->decimal('new_price', 12, 2)->nullable()->after('old_price');

            $table->decimal('average_cost', 12, 2)->nullable()->after('new_price');
            $table->decimal('profit_percent', 8, 2)->nullable()->after('average_cost');

            $table->decimal('price_before_round', 12, 2)->nullable()->after('profit_percent');
            $table->decimal('satang_rounded_price', 12, 2)->nullable()->after('price_before_round');

            $table->decimal('final_price', 12, 2)->nullable()->after('satang_rounded_price');

            $table->string('created_from')->default('manual')->after('final_price');

            $table->foreignId('user_id')
                ->nullable()
                ->after('created_from')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_price_histories', function (Blueprint $table) {

            $table->dropConstrainedForeignId('user_id');

            $table->dropColumn([
                'old_price',
                'new_price',
                'average_cost',
                'profit_percent',
                'price_before_round',
                'satang_rounded_price',
                'final_price',
                'created_from',
            ]);
        });
    }
};
