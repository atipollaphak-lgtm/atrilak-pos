<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->foreignId('supplier_id')->nullable()->change();
        });

        Schema::table('purchases', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchases', 'source')) {
                $table->string('source', 20)->nullable();
            }
            if (! Schema::hasColumn('purchases', 'supplier_document_number')) {
                $table->string('supplier_document_number', 100)->nullable();
            }
            if (! Schema::hasColumn('purchases', 'status')) {
                $table->string('status', 20)->nullable();
            }
            if (! Schema::hasColumn('purchases', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('purchases', 'idempotency_key')) {
                $table->uuid('idempotency_key')->nullable()->unique();
            }
        });

        Schema::table('purchase_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_items', 'product_unit_id')) {
                $table->foreignId('product_unit_id')->nullable()->constrained('product_units')->nullOnDelete();
            }
            if (! Schema::hasColumn('purchase_items', 'conversion_rate_used')) {
                $table->decimal('conversion_rate_used', 19, 4)->nullable();
            }
            if (! Schema::hasColumn('purchase_items', 'base_qty')) {
                $table->decimal('base_qty', 19, 4)->nullable();
            }
            if (! Schema::hasColumn('purchase_items', 'unit_name_snapshot')) {
                $table->string('unit_name_snapshot')->nullable();
            }
            if (! Schema::hasColumn('purchase_items', 'unit_code_snapshot')) {
                $table->string('unit_code_snapshot')->nullable();
            }
            if (! Schema::hasColumn('purchase_items', 'average_cost_before')) {
                $table->decimal('average_cost_before', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('purchase_items', 'average_cost_after')) {
                $table->decimal('average_cost_after', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('purchase_items', 'stock_before')) {
                $table->decimal('stock_before', 19, 4)->nullable();
            }
            if (! Schema::hasColumn('purchase_items', 'stock_after')) {
                $table->decimal('stock_after', 19, 4)->nullable();
            }
            if (! Schema::hasColumn('purchase_items', 'stock_movement_id')) {
                $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('purchase_items', 'product_unit_id') ? 'product_unit_id' : null,
                Schema::hasColumn('purchase_items', 'conversion_rate_used') ? 'conversion_rate_used' : null,
                Schema::hasColumn('purchase_items', 'base_qty') ? 'base_qty' : null,
                Schema::hasColumn('purchase_items', 'unit_name_snapshot') ? 'unit_name_snapshot' : null,
                Schema::hasColumn('purchase_items', 'unit_code_snapshot') ? 'unit_code_snapshot' : null,
                Schema::hasColumn('purchase_items', 'average_cost_before') ? 'average_cost_before' : null,
                Schema::hasColumn('purchase_items', 'average_cost_after') ? 'average_cost_after' : null,
                Schema::hasColumn('purchase_items', 'stock_before') ? 'stock_before' : null,
                Schema::hasColumn('purchase_items', 'stock_after') ? 'stock_after' : null,
                Schema::hasColumn('purchase_items', 'stock_movement_id') ? 'stock_movement_id' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('purchases', 'source') ? 'source' : null,
                Schema::hasColumn('purchases', 'supplier_document_number') ? 'supplier_document_number' : null,
                Schema::hasColumn('purchases', 'status') ? 'status' : null,
                Schema::hasColumn('purchases', 'created_by') ? 'created_by' : null,
                Schema::hasColumn('purchases', 'idempotency_key') ? 'idempotency_key' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
