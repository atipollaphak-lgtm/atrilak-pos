<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_price_tiers', function (Blueprint $table) {
            if (Schema::hasColumn('product_price_tiers', 'product_id')) {
                $table->dropColumn('product_id');
            }

            if (!Schema::hasColumn('product_price_tiers', 'product_unit_id')) {
                $table->foreignId('product_unit_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->cascadeOnDelete();
            }

            if (Schema::hasColumn('product_price_tiers', 'discount_type')) {
                $table->dropColumn('discount_type');
            }

            if (Schema::hasColumn('product_price_tiers', 'discount_value')) {
                $table->dropColumn('discount_value');
            }

            if (!Schema::hasColumn('product_price_tiers', 'discount_percent')) {
                $table->decimal('discount_percent', 8, 2)
                    ->default(0)
                    ->after('min_qty');
            }

            if (!Schema::hasColumn('product_price_tiers', 'fixed_price')) {
                $table->decimal('fixed_price', 15, 2)
                    ->nullable()
                    ->after('discount_percent');
            }

            if (!Schema::hasColumn('product_price_tiers', 'active')) {
                $table->boolean('active')
                    ->default(true)
                    ->after('fixed_price');
            }

            if (!Schema::hasColumn('product_price_tiers', 'sort_order')) {
                $table->integer('sort_order')
                    ->default(0)
                    ->after('active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_price_tiers', function (Blueprint $table) {
            //
        });
    }
};
