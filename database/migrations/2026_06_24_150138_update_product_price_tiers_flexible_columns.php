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
        Schema::table('product_price_tiers', function (Blueprint $table) {
            $table->string('discount_type')
                ->default('percent')
                ->after('min_qty');

            $table->decimal('discount_value', 10, 2)
                ->default(0)
                ->after('discount_type');
        });

        Schema::table('product_price_tiers', function (Blueprint $table) {
            $table->dropColumn('discount_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_price_tiers', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)
                ->default(0);
        });

        Schema::table('product_price_tiers', function (Blueprint $table) {
            $table->dropColumn([
                'discount_type',
                'discount_value',
            ]);
        });
    }
};
