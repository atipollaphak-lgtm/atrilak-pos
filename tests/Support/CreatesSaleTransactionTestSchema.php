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
            $table->string('name');
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('stock_qty', 19, 4)->default(0);
            $table->decimal('minimum_stock', 19, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
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

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_no')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('customer_delivery_address_id')->nullable();
            $table->foreignId('technician_id')->nullable()->constrained()->nullOnDelete();
            $table->date('sale_date');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->string('delivery_type')->default('delivery');
            $table->decimal('discount', 12, 2)->default(0);
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
            $table->timestamps();
        });
    }

    protected function dropSaleTransactionTestSchema(): void
    {
        foreach ([
            'technician_commissions',
            'stock_movements',
            'sale_items',
            'sales',
            'technicians',
            'product_units',
            'units',
            'products',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
}
