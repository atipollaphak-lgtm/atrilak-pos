<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CreatesBusinessRuleTestSchema
{
    protected function createBusinessRuleTestSchema(): void
    {
        $this->dropBusinessRuleTestSchema();

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('product_code')->nullable();
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('stock_qty', 12, 4)->default(0);
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
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('unit_id')->nullable();
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

        Schema::create('technicians', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_no')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('customer_delivery_address_id')->nullable();
            $table->unsignedBigInteger('delivery_zone_id')->nullable();
            $table->string('delivery_zone_name_snapshot')->nullable();
            $table->decimal('delivery_zone_markup_percent_snapshot', 5, 2)->nullable();
            $table->decimal('delivery_zone_rounding_increment_snapshot', 4, 2)->nullable();
            $table->decimal('delivery_zone_minimum_profit_snapshot', 12, 2)->nullable();
            $table->unsignedBigInteger('technician_id')->nullable();
            $table->date('sale_date');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->string('delivery_type')->default('delivery');
            $table->decimal('discount', 12, 2)->default(0);
            $table->text('notes')->nullable();
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

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_unit_id')->nullable();
            $table->decimal('qty', 12, 4);
            $table->decimal('conversion_rate_used', 15, 4)->nullable();
            $table->decimal('base_qty', 19, 4)->nullable();
            $table->decimal('selling_price', 12, 2);
            $table->decimal('cost_price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->decimal('profit', 12, 2);
            $table->string('product_name_snapshot')->nullable();
            $table->string('product_sku_snapshot')->nullable();
            $table->string('product_code_snapshot')->nullable();
            $table->string('unit_name_snapshot')->nullable();
            $table->string('unit_code_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('type');
            $table->decimal('qty', 12, 4);
            $table->decimal('stock_before', 12, 4);
            $table->decimal('stock_after', 12, 4);
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price_markup_percent', 5, 2)->default(0);
            $table->decimal('base_delivery_fee', 12, 2)->default(0);
            $table->decimal('free_delivery_min_amount', 12, 2)->default(0);
            $table->decimal('minimum_profit', 12, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('customer_delivery_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('delivery_zone_id')->nullable();
            $table->string('name')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });

        Schema::create('technician_commission_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('name');
            $table->string('rule_type');
            $table->decimal('rule_value', 12, 2);
            $table->boolean('active')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::create('technician_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('technician_id');
            $table->date('commission_date');
            $table->decimal('sale_total', 12, 2);
            $table->decimal('commission_rate', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2);
            $table->string('status');
            $table->string('rule_name')->nullable();
            $table->text('calculation_detail')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();
        });
    }

    protected function dropBusinessRuleTestSchema(): void
    {
        foreach ([
            'daily_payment_closing_sales',
            'daily_payment_closings',
            'technician_payment_batches',
            'technician_commissions',
            'technician_commission_rules',
            'quotation_items',
            'quotations',
            'stock_movements',
            'sale_items',
            'sales',
            'customer_delivery_addresses',
            'delivery_zones',
            'technicians',
            'customers',
            'settings',
            'product_units',
            'units',
            'products',
            'categories',
        ] as $table) {
            DB::statement('DROP TABLE IF EXISTS "'.$table.'" CASCADE');
        }
    }
}
