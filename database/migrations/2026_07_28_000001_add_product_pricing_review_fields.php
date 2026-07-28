<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('pricing_method')->nullable()->after('selling_price');
            $table->decimal('pricing_value', 12, 2)->nullable()->after('pricing_method');
            $table->string('rounding_direction')->nullable()->after('pricing_value');
            $table->decimal('rounding_unit', 12, 2)->nullable()->after('rounding_direction');
            $table->decimal('pricing_reviewed_cost', 12, 2)->nullable()->after('rounding_unit');
            $table->timestamp('pricing_reviewed_at')->nullable()->after('pricing_reviewed_cost');
            $table->foreignId('pricing_reviewed_by')->nullable()->after('pricing_reviewed_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('product_price_histories', function (Blueprint $table): void {
            $table->decimal('old_average_cost', 12, 2)->nullable()->after('new_price');
            $table->string('pricing_method')->nullable()->after('old_average_cost');
            $table->decimal('pricing_value', 12, 2)->nullable()->after('pricing_method');
            $table->string('rounding_direction')->nullable()->after('pricing_value');
            $table->decimal('rounding_unit', 12, 2)->nullable()->after('rounding_direction');
            $table->decimal('profit_amount', 12, 2)->nullable()->after('final_price');
        });
    }

    public function down(): void
    {
        Schema::table('product_price_histories', function (Blueprint $table): void {
            $table->dropColumn([
                'old_average_cost',
                'pricing_method',
                'pricing_value',
                'rounding_direction',
                'rounding_unit',
                'profit_amount',
            ]);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pricing_reviewed_by');
            $table->dropColumn([
                'pricing_method',
                'pricing_value',
                'rounding_direction',
                'rounding_unit',
                'pricing_reviewed_cost',
                'pricing_reviewed_at',
            ]);
        });
    }
};
