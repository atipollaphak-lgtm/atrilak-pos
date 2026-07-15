<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CreatesCompetingStockWriterTestSchema
{
    use CreatesSaleTransactionTestSchema;

    protected function createCompetingStockWriterTestSchema(): void
    {
        $this->createSaleTransactionTestSchema();

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('profit_percent', 8, 2)->nullable();
            $table->string('satang_rounding_mode')->nullable();
            $table->string('baht_rounding_mode')->nullable();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->string('barcode')->nullable();
            $table->string('unit')->default('piece');
            $table->boolean('vat_enabled')->default(false);
            $table->boolean('active')->default(true);
            $table->text('remark')->nullable();
            $table->boolean('auto_price_enabled')->default(false);
            $table->boolean('price_lock')->default(false);
            $table->decimal('profit_percent', 8, 2)->nullable();
            $table->string('satang_rounding_mode')->nullable();
            $table->string('baht_rounding_mode')->nullable();
        });

        Schema::create('pricing_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('default_profit_percent', 8, 2)->default(20);
            $table->string('default_satang_rounding_mode')->default('ceil_satang_50');
            $table->string('default_baht_rounding_mode')->default('ceil_5');
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->date('purchase_date');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 12, 2);
            $table->decimal('cost_price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('count_no')->nullable();
            $table->date('count_date');
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->unique('count_no', 'stock_counts_count_no_unique');
        });

        Schema::create('stock_count_number_counters', function (Blueprint $table) {
            $table->date('count_date')->primary();
            $table->integer('last_number');
            $table->timestamps();
        });

        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('system_qty', 19, 4)->default(0);
            $table->decimal('actual_qty', 19, 4)->default(0);
            $table->decimal('difference', 19, 4)->default(0);
            $table->unique(
                ['stock_count_id', 'product_id'],
                'stock_count_items_stock_count_product_unique'
            );
            $table->timestamps();
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_no')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->date('quotation_date');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('remark')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('qty');
            $table->decimal('selling_price', 15, 2);
            $table->decimal('total', 15, 2);
            $table->timestamps();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('quotation_id')
                ->nullable()
                ->constrained('quotations')
                ->restrictOnDelete();
            $table->unique('quotation_id', 'sales_quotation_id_unique');
        });
    }

    protected function dropCompetingStockWriterTestSchema(): void
    {
        foreach ([
            'quotation_items',
            'stock_count_items',
            'stock_count_number_counters',
            'stock_counts',
            'purchase_items',
            'purchases',
            'suppliers',
            'pricing_settings',
            'categories',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        $this->dropSaleTransactionTestSchema();
        Schema::dropIfExists('quotations');
    }
}
