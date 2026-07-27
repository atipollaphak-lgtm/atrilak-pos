<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FreshMigrationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_schema_contains_the_repaired_columns_and_foreign_keys(): void
    {
        $this->assertTrue(Schema::hasColumn('delivery_zones', 'sort_order'));
        $this->assertTrue(Schema::hasColumn('pricing_settings', 'default_satang_rounding_mode'));
        $this->assertTrue(Schema::hasColumn('pricing_settings', 'default_baht_rounding_mode'));
        $this->assertFalse(Schema::hasColumn('pricing_settings', 'default_rounding_mode'));
        $this->assertTrue(Schema::hasColumn('sales', 'quotation_id'));

        $this->assertTrue($this->hasForeignKey(
            'product_price_tiers',
            'product_unit_id',
            'product_units'
        ));
        $this->assertTrue($this->hasForeignKey(
            'technician_commissions',
            'payment_batch_id',
            'technician_payment_batches'
        ));
        $this->assertTrue($this->hasForeignKey(
            'sales',
            'quotation_id',
            'quotations'
        ));
    }

    private function hasForeignKey(string $table, string $column, string $foreignTable): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(function (array $foreignKey) use ($column, $foreignTable): bool {
                return $foreignKey['columns'] === [$column]
                    && $foreignKey['foreign_table'] === $foreignTable
                    && $foreignKey['foreign_columns'] === ['id'];
            });
    }
}
