<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_pricing_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('pricing_method')->default('percentage');
            $table->decimal('pricing_value', 12, 2);
            $table->string('rounding_direction')->nullable();
            $table->decimal('rounding_unit', 12, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('category_id');
            $table->index(['active', 'category_id']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('pricing_source')->nullable()->after('pricing_method');
            $table->index('pricing_source');
        });

        Schema::table('product_price_histories', function (Blueprint $table): void {
            $table->string('pricing_source')->nullable()->after('pricing_method');
            $table->foreignId('category_pricing_rule_id')->nullable()->after('pricing_source')
                ->constrained('category_pricing_rules')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->after('category_pricing_rule_id')
                ->constrained()->nullOnDelete();
            $table->string('category_name_snapshot')->nullable()->after('category_id');
            $table->decimal('category_rule_value', 12, 2)->nullable()->after('category_name_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('product_price_histories', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_pricing_rule_id');
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn([
                'pricing_source',
                'category_name_snapshot',
                'category_rule_value',
            ]);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['pricing_source']);
            $table->dropColumn('pricing_source');
        });

        Schema::dropIfExists('category_pricing_rules');
    }
};
