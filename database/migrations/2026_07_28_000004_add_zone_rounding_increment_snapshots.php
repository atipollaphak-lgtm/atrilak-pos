<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONSTRAINT = 'delivery_zones_rounding_increment_allowed';

    public function up(): void
    {
        if (! Schema::hasColumn('delivery_zones', 'rounding_increment')) {
            Schema::table('delivery_zones', function (Blueprint $table): void {
                $table->decimal('rounding_increment', 4, 2)
                    ->default('0.25')
                    ->after('price_markup_percent');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(sprintf(
                'ALTER TABLE delivery_zones ADD CONSTRAINT %s CHECK (rounding_increment IN (0.25, 0.50, 1.00, 5.00, 10.00))',
                self::CONSTRAINT
            ));
        }

        if (! Schema::hasColumn('sales', 'delivery_zone_rounding_increment_snapshot')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->decimal('delivery_zone_rounding_increment_snapshot', 4, 2)
                    ->nullable()
                    ->after('delivery_zone_markup_percent_snapshot');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(sprintf(
                'ALTER TABLE delivery_zones DROP CONSTRAINT IF EXISTS %s',
                self::CONSTRAINT
            ));
        }

        if (Schema::hasColumn('sales', 'delivery_zone_rounding_increment_snapshot')) {
            Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('delivery_zone_rounding_increment_snapshot'));
        }

        if (Schema::hasColumn('delivery_zones', 'rounding_increment')) {
            Schema::table('delivery_zones', fn (Blueprint $table) => $table->dropColumn('rounding_increment'));
        }
    }
};
