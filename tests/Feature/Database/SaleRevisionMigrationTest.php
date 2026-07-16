<?php

namespace Tests\Feature\Database;

use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SaleRevisionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_schema_has_non_nullable_bigint_revision_defaulting_to_one(): void
    {
        $this->assertTrue(Schema::hasColumn('sales', 'revision'));

        $sale = Sale::query()->create([
            'sale_no' => 'SAL-REVISION-FRESH',
            'sale_date' => '2026-07-16',
            'total_amount' => '10.00',
        ]);

        $this->assertSame(1, $sale->fresh()->revision);
        $this->assertSame('integer', $sale->getCasts()['revision']);
    }

    public function test_upgrade_initializes_revision_without_changing_business_columns(): void
    {
        $migration = $this->migration();
        $migration->down();

        $saleId = DB::table('sales')->insertGetId([
            'sale_no' => 'SAL-REVISION-UPGRADE',
            'sale_date' => '2026-07-16',
            'total_amount' => '123.45',
            'delivery_fee' => '10.00',
            'discount' => '5.00',
            'created_at' => '2026-07-16 10:00:00',
            'updated_at' => '2026-07-16 10:00:00',
        ]);
        $before = (array) DB::table('sales')->where('id', $saleId)->sole();

        $migration->up();

        $after = (array) DB::table('sales')->where('id', $saleId)->sole();
        $this->assertSame(1, $after['revision']);
        unset($after['revision']);
        $this->assertEquals($before, $after);
    }

    public function test_up_down_up_is_safe(): void
    {
        $migration = $this->migration();

        $migration->down();
        $this->assertFalse(Schema::hasColumn('sales', 'revision'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('sales', 'revision'));
        $this->assertSame(1, DB::table('sales')->insertGetId([
            'sale_no' => 'SAL-REVISION-UP-DOWN-UP',
            'sale_date' => '2026-07-16',
            'total_amount' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]) > 0 ? DB::table('sales')->where('sale_no', 'SAL-REVISION-UP-DOWN-UP')->value('revision') : null);
    }

    public function test_postgresql_revision_metadata_is_bigint_non_nullable_with_default_one(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required for Sale revision metadata verification.');
        }

        $column = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'sales')
            ->where('column_name', 'revision')
            ->sole();

        $this->assertSame('bigint', $column->data_type);
        $this->assertSame('NO', $column->is_nullable);
        $this->assertStringContainsString('1', (string) $column->column_default);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_07_16_000001_add_revision_to_sales_table.php');
    }
}
