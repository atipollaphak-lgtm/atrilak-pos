<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CreatesSaleTransactionTestSchema
{
    protected function createSaleTransactionTestSchema(): void
    {
        $this->dropSaleTransactionTestSchema();

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->string('name');
            $table->string('unit')->nullable();
            $table->string('sku')->nullable();
            $table->string('product_code')->nullable();
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('stock_qty', 19, 4)->default(0);
            $table->decimal('minimum_stock', 19, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('conversion_rate', 15, 4)->default(1);
            $table->boolean('is_base_unit')->default(false);
            $table->boolean('is_purchase_unit')->default(true);
            $table->boolean('is_sale_unit')->default(true);
            $table->decimal('purchase_price', 15, 2)->nullable();
            $table->decimal('selling_price', 15, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamp('conversion_confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('technicians', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('store_name')->nullable();
            $table->text('store_address')->nullable();
            $table->string('store_phone')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('branch_type')->nullable();
            $table->string('branch_number')->nullable();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('branch_type')->nullable();
            $table->string('branch_number')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price_markup_percent', 5, 2)->default(0);
            $table->decimal('rounding_increment', 4, 2)->default(0.25);
            $table->decimal('base_delivery_fee', 12, 2)->default(0);
            $table->decimal('free_delivery_min_amount', 12, 2)->default(0);
            $table->decimal('minimum_profit', 12, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('customer_delivery_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('delivery_zone_id')->nullable();
            $table->string('name')->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('receiver_phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->text('landmark')->nullable();
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_no')->nullable()->unique('sales_sale_no_unique');
            $table->uuid('idempotency_key')->nullable()->unique('sales_idempotency_key_unique');
            $table->char('idempotency_payload_hash', 64)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('customer_delivery_address_id')->nullable();
            $table->unsignedBigInteger('delivery_zone_id')->nullable();
            $table->string('delivery_zone_name_snapshot')->nullable();
            $table->decimal('delivery_zone_markup_percent_snapshot', 5, 2)->nullable();
            $table->decimal('delivery_zone_rounding_increment_snapshot', 4, 2)->nullable();
            $table->decimal('delivery_zone_minimum_profit_snapshot', 12, 2)->nullable();
            $table->foreignId('technician_id')->nullable()->constrained()->nullOnDelete();
            $table->date('sale_date');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->string('delivery_type')->default('delivery');
            $table->decimal('discount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->bigInteger('revision')->default(1);
            $table->string('status', 20)->default('active');
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->text('void_reason')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->decimal('cash_amount', 15, 2)->nullable();
            $table->decimal('promptpay_amount', 15, 2)->nullable();
            $table->decimal('received_amount', 15, 2)->nullable();
            $table->decimal('change_amount', 15, 2)->nullable();
            $table->string('store_name_snapshot')->nullable();
            $table->text('store_address_snapshot')->nullable();
            $table->string('store_phone_snapshot')->nullable();
            $table->string('store_tax_number_snapshot')->nullable();
            $table->string('store_branch_type_snapshot')->nullable();
            $table->string('store_branch_number_snapshot')->nullable();
            $table->string('customer_name_snapshot')->nullable();
            $table->string('customer_phone_snapshot')->nullable();
            $table->text('customer_address_snapshot')->nullable();
            $table->string('customer_tax_number_snapshot')->nullable();
            $table->string('customer_branch_type_snapshot')->nullable();
            $table->string('customer_branch_number_snapshot')->nullable();
            $table->string('technician_name_snapshot')->nullable();
            $table->string('delivery_address_name_snapshot')->nullable();
            $table->string('delivery_receiver_name_snapshot')->nullable();
            $table->string('delivery_receiver_phone_snapshot', 30)->nullable();
            $table->text('delivery_full_address_snapshot')->nullable();
            $table->text('delivery_landmark_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('sale_number_counters', function (Blueprint $table) {
            $table->date('sale_date')->primary();
            $table->integer('last_number');
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_unit_id')->nullable();
            $table->decimal('qty', 15, 2);
            $table->decimal('conversion_rate_used', 15, 4)->nullable();
            $table->decimal('base_qty', 19, 4)->nullable();
            $table->decimal('selling_price', 15, 2);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->decimal('profit', 12, 2)->default(0);
            $table->string('product_name_snapshot')->nullable();
            $table->string('product_sku_snapshot')->nullable();
            $table->string('product_code_snapshot')->nullable();
            $table->string('unit_name_snapshot')->nullable();
            $table->string('unit_code_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->decimal('qty', 19, 4);
            $table->decimal('stock_before', 19, 4);
            $table->decimal('stock_after', 19, 4);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::create('technician_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained()->cascadeOnDelete();
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('payment_batch_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    protected function dropSaleTransactionTestSchema(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::dropAllTables();

            return;
        }

        foreach ([
            'technician_commissions',
            'stock_movements',
            'sale_items',
            'sale_number_counters',
            'sales',
            'customer_delivery_addresses',
            'customers',
            'settings',
            'technicians',
            'product_units',
            'units',
            'products',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
}
