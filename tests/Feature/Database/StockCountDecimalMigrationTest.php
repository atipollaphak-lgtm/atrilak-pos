<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class StockCountDecimalMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required for the Stock Count upgrade migration test.');
        }

        if (DB::connection()->getDatabaseName() === 'atrilak_pos') {
            $this->fail('Stock Count migration test refused the application database.');
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

    public function test_upgrade_preserves_existing_values_and_adds_decimal_unique_columns(): void
    {
        $this->insertItem(stockCountId: 1, productId: 1, system: 10, actual: 8, difference: -2);
        $before = DB::table('stock_count_items')->orderBy('id')->get()->toArray();

        $this->migration()->up();

        $this->assertEquals($before, DB::table('stock_count_items')->orderBy('id')->get()->toArray());

        foreach (['system_qty', 'actual_qty', 'difference'] as $column) {
            $metadata = DB::table('information_schema.columns')
                ->where('table_schema', 'public')
                ->where('table_name', 'stock_count_items')
                ->where('column_name', $column)
                ->sole();

            $this->assertSame('numeric', $metadata->data_type);
            $this->assertSame(19, $metadata->numeric_precision);
            $this->assertSame(4, $metadata->numeric_scale);
        }

        try {
            $this->insertItem(stockCountId: 1, productId: 1, system: 8, actual: 7, difference: -1);
            $this->fail('The Stock Count Item unique constraint should reject a duplicate product.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->errorInfo[0] ?? null);
        }
    }

    public function test_upgrade_stops_before_schema_changes_when_duplicate_products_exist(): void
    {
        $this->insertItem(stockCountId: 1, productId: 1, system: 10, actual: 8, difference: -2);
        $this->insertItem(stockCountId: 1, productId: 1, system: 8, actual: 7, difference: -1);

        try {
            $this->migration()->up();
            $this->fail('The migration should stop when duplicate products exist.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('duplicate products exist', $exception->getMessage());
        }

        $metadata = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'stock_count_items')
            ->where('column_name', 'actual_qty')
            ->sole();

        $this->assertSame('integer', $metadata->data_type);
        $this->assertDatabaseCount('stock_count_items', 2);
    }

    public function test_down_refuses_to_truncate_fractional_values(): void
    {
        $this->insertItem(stockCountId: 1, productId: 1, system: 10, actual: 8, difference: -2);
        $migration = $this->migration();
        $migration->up();
        DB::table('stock_count_items')->update(['actual_qty' => '8.2500']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fractional values would be lost');
        $migration->down();
    }

    private function createLegacySchema(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('system_qty')->default(0);
            $table->integer('actual_qty')->default(0);
            $table->integer('difference')->default(0);
        });

        DB::table('products')->insert(['id' => 1]);
        DB::table('stock_counts')->insert(['id' => 1]);
    }

    private function insertItem(
        int $stockCountId,
        int $productId,
        int $system,
        int $actual,
        int $difference
    ): void {
        DB::table('stock_count_items')->insert([
            'stock_count_id' => $stockCountId,
            'product_id' => $productId,
            'system_qty' => $system,
            'actual_qty' => $actual,
            'difference' => $difference,
        ]);
    }

    private function migration()
    {
        return require database_path(
            'migrations/2026_07_15_000001_make_stock_count_items_decimal_and_unique.php'
        );
    }
}
