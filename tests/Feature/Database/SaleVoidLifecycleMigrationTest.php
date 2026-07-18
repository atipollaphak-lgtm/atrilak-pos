<?php

namespace Tests\Feature\Database;

use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SaleVoidLifecycleMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS = ['status', 'voided_at', 'voided_by', 'void_reason'];

    public function test_fresh_schema_defaults_sales_to_active_and_has_nullable_void_metadata(): void
    {
        $this->assertTrue(Schema::hasColumns('sales', self::COLUMNS));

        $sale = Sale::query()->create([
            'sale_no' => 'SAL-VOID-SCHEMA',
            'sale_date' => '2026-07-18',
            'total_amount' => '10.00',
        ])->fresh();

        $this->assertSame(Sale::STATUS_ACTIVE, $sale->status);
        $this->assertNull($sale->voided_at);
        $this->assertNull($sale->voided_by);
        $this->assertNull($sale->void_reason);
    }

    public function test_upgrade_marks_existing_sales_active_without_changing_business_columns(): void
    {
        $migration = $this->migration();
        $migration->down();

        $saleId = DB::table('sales')->insertGetId([
            'sale_no' => 'SAL-VOID-UPGRADE',
            'sale_date' => '2026-07-18',
            'total_amount' => '123.45',
            'delivery_fee' => '10.00',
            'discount' => '5.00',
            'revision' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $before = (array) DB::table('sales')->where('id', $saleId)->sole();

        $migration->up();

        $after = (array) DB::table('sales')->where('id', $saleId)->sole();
        $this->assertSame(Sale::STATUS_ACTIVE, $after['status']);
        $this->assertNull($after['voided_at']);
        $this->assertNull($after['voided_by']);
        $this->assertNull($after['void_reason']);
        unset($after['status'], $after['voided_at'], $after['voided_by'], $after['void_reason']);
        $this->assertEquals($before, $after);
    }

    public function test_down_removes_only_void_lifecycle_columns_and_up_can_run_again(): void
    {
        $migration = $this->migration();
        $migration->down();

        foreach (self::COLUMNS as $column) {
            $this->assertFalse(Schema::hasColumn('sales', $column));
        }
        $this->assertTrue(Schema::hasColumns('sales', ['sale_no', 'sale_date', 'total_amount']));

        $migration->up();

        $this->assertTrue(Schema::hasColumns('sales', self::COLUMNS));
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_07_18_000001_add_void_lifecycle_to_sales_table.php');
    }
}
