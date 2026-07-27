<?php

namespace Tests\Feature\Database;

use App\Models\Category;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocumentSnapshotMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_schema_contains_all_nullable_snapshot_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('sales', $this->saleColumns()));
        $this->assertTrue(Schema::hasColumns('sale_items', $this->saleItemColumns()));
        $this->assertTrue(Schema::hasColumns('quotations', $this->quotationColumns()));
        $this->assertTrue(Schema::hasColumns('quotation_items', $this->quotationItemColumns()));

        $category = Category::query()->create(['name' => 'Legacy Category']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Legacy Product',
            'cost_price' => 1,
            'selling_price' => 2,
            'stock_qty' => 0,
            'minimum_stock' => 0,
        ]);
        $sale = Sale::query()->create([
            'sale_no' => 'SAL-LEGACY-SNAPSHOT',
            'sale_date' => '2026-07-15',
            'total_amount' => 2,
        ]);
        $saleItem = $sale->items()->create([
            'product_id' => $product->id,
            'qty' => 1,
            'selling_price' => 2,
            'total' => 2,
            'cost_price' => 1,
            'profit' => 1,
        ]);
        $quotation = Quotation::query()->create([
            'quotation_no' => 'QT-LEGACY-SNAPSHOT',
            'quotation_date' => '2026-07-15',
            'total_amount' => 2,
        ]);
        $quotationItem = $quotation->items()->create([
            'product_id' => $product->id,
            'qty' => 1,
            'selling_price' => 2,
            'total' => 2,
        ]);

        foreach ($this->saleColumns() as $column) {
            $this->assertNull($sale->getAttribute($column));
        }
        foreach ($this->saleItemColumns() as $column) {
            $this->assertNull($saleItem->getAttribute($column));
        }
        foreach ($this->quotationColumns() as $column) {
            $this->assertNull($quotation->getAttribute($column));
        }
        foreach ($this->quotationItemColumns() as $column) {
            $this->assertNull($quotationItem->getAttribute($column));
        }
    }

    public function test_migrations_contain_no_historical_data_writes(): void
    {
        $files = [
            database_path('migrations/2026_07_15_000004_add_header_snapshots_to_sales_table.php'),
            database_path('migrations/2026_07_15_000005_add_document_snapshots_to_sale_items_table.php'),
            database_path('migrations/2026_07_15_000006_add_header_snapshots_to_quotations_table.php'),
            database_path('migrations/2026_07_15_000007_add_document_snapshots_to_quotation_items_table.php'),
        ];

        foreach ($files as $file) {
            $source = file_get_contents($file);

            $this->assertStringNotContainsString('DB::', $source);
            $this->assertStringNotContainsString('->update(', $source);
            $this->assertStringNotContainsString('->insert(', $source);
        }
    }

    public function test_upgrade_preserves_legacy_rows_and_numeric_values_without_backfill(): void
    {
        $migrations = $this->migrations();

        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }

        $category = Category::query()->create(['name' => 'Upgrade Category']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Upgrade Product',
            'cost_price' => '1.25',
            'selling_price' => '2.50',
            'stock_qty' => '4.0000',
            'minimum_stock' => '0.0000',
        ]);
        $sale = Sale::query()->create([
            'sale_no' => 'SAL-UPGRADE-SNAPSHOT',
            'sale_date' => '2026-07-15',
            'total_amount' => '7.50',
        ]);
        $saleItem = $sale->items()->create([
            'product_id' => $product->id,
            'qty' => '3.00',
            'selling_price' => '2.50',
            'total' => '7.50',
            'cost_price' => '1.25',
            'profit' => '3.75',
        ]);
        $quotation = Quotation::query()->create([
            'quotation_no' => 'QT-UPGRADE-SNAPSHOT',
            'quotation_date' => '2026-07-15',
            'total_amount' => '7.50',
        ]);
        $quotationItem = $quotation->items()->create([
            'product_id' => $product->id,
            'qty' => 3,
            'selling_price' => '2.50',
            'total' => '7.50',
        ]);

        foreach ($migrations as $migration) {
            $migration->up();
        }

        $this->assertDecimalEquals('7.50', $sale->fresh()->total_amount);
        $this->assertDecimalEquals('3.00', $saleItem->fresh()->qty);
        $this->assertDecimalEquals('2.50', $saleItem->fresh()->selling_price);
        $this->assertDecimalEquals('7.50', $quotation->fresh()->total_amount);
        $this->assertDecimalEquals('3', $quotationItem->fresh()->qty);

        foreach ($this->saleColumns() as $column) {
            $this->assertNull($sale->fresh()->getAttribute($column));
        }
        foreach ($this->saleItemColumns() as $column) {
            $this->assertNull($saleItem->fresh()->getAttribute($column));
        }
        foreach ($this->quotationColumns() as $column) {
            $this->assertNull($quotation->fresh()->getAttribute($column));
        }
        foreach ($this->quotationItemColumns() as $column) {
            $this->assertNull($quotationItem->fresh()->getAttribute($column));
        }
    }

    public function test_postgresql_snapshot_columns_have_expected_types_and_are_nullable(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required for snapshot type verification.');
        }

        $expected = [
            'sales' => $this->typedColumns($this->saleColumns(), [
                'store_address_snapshot',
                'customer_address_snapshot',
                'delivery_full_address_snapshot',
                'delivery_landmark_snapshot',
            ]),
            'sale_items' => $this->typedColumns($this->saleItemColumns()),
            'quotations' => $this->typedColumns($this->quotationColumns(), [
                'store_address_snapshot',
                'customer_address_snapshot',
            ]),
            'quotation_items' => $this->typedColumns($this->quotationItemColumns()),
        ];

        foreach ($expected as $table => $columns) {
            $metadata = DB::table('information_schema.columns')
                ->where('table_schema', 'public')
                ->where('table_name', $table)
                ->whereIn('column_name', array_keys($columns))
                ->get()
                ->keyBy('column_name');

            $this->assertCount(count($columns), $metadata);

            foreach ($columns as $column => $type) {
                $this->assertSame($type, $metadata[$column]->data_type);
                $this->assertSame('YES', $metadata[$column]->is_nullable);
                if ($column === 'delivery_receiver_phone_snapshot') {
                    $this->assertSame(30, $metadata[$column]->character_maximum_length);
                }
            }
        }
    }

    private function saleColumns(): array
    {
        return [
            'store_name_snapshot', 'store_address_snapshot', 'store_phone_snapshot',
            'store_tax_number_snapshot', 'store_branch_type_snapshot', 'store_branch_number_snapshot',
            'customer_name_snapshot', 'customer_phone_snapshot', 'customer_address_snapshot',
            'customer_tax_number_snapshot', 'customer_branch_type_snapshot', 'customer_branch_number_snapshot',
            'technician_name_snapshot', 'delivery_address_name_snapshot', 'delivery_receiver_name_snapshot',
            'delivery_receiver_phone_snapshot', 'delivery_full_address_snapshot', 'delivery_landmark_snapshot',
        ];
    }

    private function saleItemColumns(): array
    {
        return [
            'product_name_snapshot', 'product_sku_snapshot', 'product_code_snapshot',
            'unit_name_snapshot', 'unit_code_snapshot',
        ];
    }

    private function quotationColumns(): array
    {
        return array_slice($this->saleColumns(), 0, 12);
    }

    private function quotationItemColumns(): array
    {
        return array_slice($this->saleItemColumns(), 0, 3);
    }

    private function migrations(): array
    {
        return [
            require database_path('migrations/2026_07_15_000004_add_header_snapshots_to_sales_table.php'),
            require database_path('migrations/2026_07_15_000005_add_document_snapshots_to_sale_items_table.php'),
            require database_path('migrations/2026_07_15_000006_add_header_snapshots_to_quotations_table.php'),
            require database_path('migrations/2026_07_15_000007_add_document_snapshots_to_quotation_items_table.php'),
        ];
    }

    private function typedColumns(array $columns, array $textColumns = []): array
    {
        return collect($columns)->mapWithKeys(fn (string $column): array => [
            $column => in_array($column, $textColumns, true)
                ? 'text'
                : 'character varying',
        ])->all();
    }

    private function assertDecimalEquals(string $expected, mixed $actual): void
    {
        $this->assertTrue(BigDecimal::of($expected)->isEqualTo((string) $actual));
    }
}
