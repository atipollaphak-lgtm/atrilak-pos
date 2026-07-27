<?php

namespace Tests\Feature\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class DecimalStockMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required for the decimal upgrade migration test.');
        }

        if (DB::connection()->getDatabaseName() === 'atrilak_pos') {
            $this->fail('Migration test refused the application database.');
        }

        Schema::dropAllTables();
        $this->createLegacySchema();
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql'
            && DB::connection()->getDatabaseName() !== 'atrilak_pos') {
            Schema::dropAllTables();
        }

        parent::tearDown();
    }

    public function test_upgrade_preserves_integer_and_existing_decimal_values(): void
    {
        DB::table('products')->insert([
            'name' => 'Legacy product',
            'stock_qty' => 699,
            'minimum_stock' => 5,
        ]);
        DB::table('stock_movements')->insert([
            'product_id' => 1,
            'type' => 'OUT',
            'qty' => '1.25',
            'stock_before' => '700.00',
            'stock_after' => '698.75',
        ]);
        $before = $this->snapshot();

        $migration = $this->migration();
        $migration->up();

        $this->assertSame($before, $this->snapshot());
        foreach ([
            ['products', 'stock_qty'],
            ['products', 'minimum_stock'],
            ['stock_movements', 'qty'],
            ['stock_movements', 'stock_before'],
            ['stock_movements', 'stock_after'],
        ] as [$table, $column]) {
            $metadata = DB::table('information_schema.columns')
                ->where('table_schema', 'public')
                ->where('table_name', $table)
                ->where('column_name', $column)
                ->first();

            $this->assertSame('numeric', $metadata->data_type);
            $this->assertSame(19, $metadata->numeric_precision);
            $this->assertSame(4, $metadata->numeric_scale);
        }

        $migration->down();

        $productStock = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'products')
            ->where('column_name', 'stock_qty')
            ->sole();
        $movementQty = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'stock_movements')
            ->where('column_name', 'qty')
            ->sole();

        $this->assertSame('integer', $productStock->data_type);
        $this->assertSame(12, $movementQty->numeric_precision);
        $this->assertSame(2, $movementQty->numeric_scale);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_down_refuses_to_truncate_fractional_stock(): void
    {
        DB::table('products')->insert([
            'name' => 'Fractional product',
            'stock_qty' => 1,
            'minimum_stock' => 0,
        ]);
        $migration = $this->migration();
        $migration->up();
        DB::table('products')->update(['stock_qty' => '1.2345']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fractional values would be lost');
        $migration->down();
    }

    public function test_metadata_upgrade_adds_nullable_columns_without_backfill(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->decimal('conversion_rate', 15, 4)->default(1);
        });
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_unit_id')->nullable();
            $table->decimal('qty', 15, 2);
        });
        DB::table('product_units')->insert(['conversion_rate' => '12.0000']);
        DB::table('sale_items')->insert(['product_unit_id' => 1, 'qty' => '3.00']);

        (require database_path('migrations/2026_07_14_000002_add_conversion_snapshot_to_sale_items.php'))->up();
        (require database_path('migrations/2026_07_14_000003_add_conversion_confirmation_to_product_units.php'))->up();

        $this->assertDatabaseCount('product_units', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertNull(DB::table('product_units')->value('conversion_confirmed_at'));
        $item = DB::table('sale_items')->sole();
        $this->assertNull($item->conversion_rate_used);
        $this->assertNull($item->base_qty);
    }

    private function createLegacySchema(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('stock_qty')->default(0);
            $table->integer('minimum_stock')->default(0);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('type');
            $table->decimal('qty', 12, 2);
            $table->decimal('stock_before', 12, 2);
            $table->decimal('stock_after', 12, 2);
        });
    }

    private function migration()
    {
        return require database_path('migrations/2026_07_14_000001_make_stock_quantities_decimal.php');
    }

    private function snapshot(): array
    {
        return [
            'products' => DB::table('products')->orderBy('id')->get()->map(fn ($row) => [
                'id' => $row->id,
                'stock_qty' => (float) $row->stock_qty,
                'minimum_stock' => (float) $row->minimum_stock,
            ])->all(),
            'movements' => DB::table('stock_movements')->orderBy('id')->get()->map(fn ($row) => [
                'id' => $row->id,
                'qty' => (float) $row->qty,
                'stock_before' => (float) $row->stock_before,
                'stock_after' => (float) $row->stock_after,
            ])->all(),
        ];
    }
}
