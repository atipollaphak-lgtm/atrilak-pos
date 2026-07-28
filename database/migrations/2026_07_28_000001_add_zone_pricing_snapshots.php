<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('delivery_zones', 'price_markup_percent')) {
            Schema::table('delivery_zones', function (Blueprint $table): void {
                $table->decimal('price_markup_percent', 5, 2)
                    ->default(0)
                    ->after('name');
            });
        }

        if (! Schema::hasColumn('sales', 'delivery_zone_id')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->foreignId('delivery_zone_id')
                    ->nullable()
                    ->after('customer_delivery_address_id')
                    ->constrained('delivery_zones')
                    ->nullOnDelete();
            });
        }

        Schema::table('sales', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales', 'delivery_zone_name_snapshot')) {
                $table->string('delivery_zone_name_snapshot')->nullable()->after('delivery_zone_id');
                $table->decimal('delivery_zone_markup_percent_snapshot', 5, 2)->nullable()->after('delivery_zone_name_snapshot');
                $table->decimal('delivery_zone_minimum_profit_snapshot', 12, 2)->nullable()->after('delivery_zone_markup_percent_snapshot');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales', 'delivery_zone_id')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->dropForeign(['delivery_zone_id']);
                $table->dropColumn('delivery_zone_id');
            });
        }

        foreach ([
            'delivery_zone_name_snapshot',
            'delivery_zone_markup_percent_snapshot',
            'delivery_zone_minimum_profit_snapshot',
        ] as $column) {
            if (Schema::hasColumn('sales', $column)) {
                Schema::table('sales', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }

        if (Schema::hasColumn('delivery_zones', 'price_markup_percent')) {
            Schema::table('delivery_zones', fn (Blueprint $table) => $table->dropColumn('price_markup_percent'));
        }
    }
};
