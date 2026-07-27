<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONSTRAINT = 'product_price_tiers_product_unit_id_deferred_foreign';

    public function up(): void
    {
        if (! Schema::hasTable('product_price_tiers')
            || ! Schema::hasTable('product_units')
            || ! Schema::hasColumn('product_price_tiers', 'product_unit_id')) {
            return;
        }

        if ($this->hasProductUnitForeignKey()) {
            return;
        }

        Schema::table('product_price_tiers', function (Blueprint $table) {
            $table->foreign('product_unit_id', self::CONSTRAINT)
                ->references('id')
                ->on('product_units')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_price_tiers')) {
            return;
        }

        $hasDeferredConstraint = collect(Schema::getForeignKeys('product_price_tiers'))
            ->contains(fn (array $foreignKey): bool => $foreignKey['name'] === self::CONSTRAINT);

        if (! $hasDeferredConstraint) {
            return;
        }

        Schema::table('product_price_tiers', function (Blueprint $table) {
            $table->dropForeign(self::CONSTRAINT);
        });
    }

    private function hasProductUnitForeignKey(): bool
    {
        return collect(Schema::getForeignKeys('product_price_tiers'))
            ->contains(function (array $foreignKey): bool {
                return $foreignKey['columns'] === ['product_unit_id']
                    && $foreignKey['foreign_table'] === 'product_units'
                    && $foreignKey['foreign_columns'] === ['id'];
            });
    }
};
