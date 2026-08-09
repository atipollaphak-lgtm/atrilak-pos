<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['sales', 'hold_bills'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'pricing_zone_id')) {
                    $table->foreignId('pricing_zone_id')
                        ->nullable()
                        ->after('delivery_zone_id')
                        ->constrained('delivery_zones')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn($tableName, 'pricing_zone_name_snapshot')) {
                    $table->string('pricing_zone_name_snapshot')
                        ->nullable()
                        ->after('pricing_zone_id');
                }

                if (! Schema::hasColumn($tableName, 'pricing_zone_markup_percent_snapshot')) {
                    $table->decimal('pricing_zone_markup_percent_snapshot', 8, 2)
                        ->nullable()
                        ->after('pricing_zone_name_snapshot');
                }

                if (! Schema::hasColumn($tableName, 'pricing_zone_rounding_increment_snapshot')) {
                    $table->decimal('pricing_zone_rounding_increment_snapshot', 8, 2)
                        ->nullable()
                        ->after('pricing_zone_markup_percent_snapshot');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['sales', 'hold_bills'] as $tableName) {
            if (Schema::hasColumn($tableName, 'pricing_zone_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropForeign(['pricing_zone_id']);
                });
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                foreach ([
                    'pricing_zone_rounding_increment_snapshot',
                    'pricing_zone_markup_percent_snapshot',
                    'pricing_zone_name_snapshot',
                    'pricing_zone_id',
                ] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
