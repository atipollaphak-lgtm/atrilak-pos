<?php

namespace Tests\Feature\Database;

use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalePaymentMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const PAYMENT_COLUMNS = [
        'payment_method',
        'cash_amount',
        'promptpay_amount',
        'received_amount',
        'change_amount',
    ];

    public function test_fresh_schema_has_nullable_payment_columns_and_decimal_casts(): void
    {
        $this->assertTrue(Schema::hasColumns('sales', self::PAYMENT_COLUMNS));

        $sale = Sale::query()->create([
            'sale_no' => 'SAL-PAYMENT-SCHEMA',
            'sale_date' => '2026-07-16',
            'total_amount' => '100.00',
        ])->fresh();

        foreach (self::PAYMENT_COLUMNS as $column) {
            $this->assertNull($sale->{$column});
        }

        foreach (['cash_amount', 'promptpay_amount', 'received_amount', 'change_amount'] as $column) {
            $this->assertSame('decimal:2', $sale->getCasts()[$column]);
        }
    }

    public function test_existing_sale_remains_null_after_upgrade_without_business_backfill(): void
    {
        $migration = $this->migration();
        $migration->down();

        $saleId = DB::table('sales')->insertGetId([
            'sale_no' => 'SAL-PAYMENT-LEGACY',
            'sale_date' => '2026-07-16',
            'total_amount' => '123.45',
            'revision' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();
        $sale = DB::table('sales')->where('id', $saleId)->sole();

        $this->assertSame('123.45', (string) $sale->total_amount);

        foreach (self::PAYMENT_COLUMNS as $column) {
            $this->assertNull($sale->{$column});
        }
    }

    public function test_sale_model_persists_canonical_payment_as_two_decimal_strings(): void
    {
        $sale = Sale::query()->create([
            'sale_no' => 'SAL-PAYMENT-MODEL',
            'sale_date' => '2026-07-16',
            'total_amount' => '850.00',
            'payment_method' => 'mixed',
            'cash_amount' => '300',
            'promptpay_amount' => '550',
            'received_amount' => '500',
            'change_amount' => '200',
        ])->fresh();

        $this->assertSame('mixed', $sale->payment_method);
        $this->assertSame('300.00', $sale->cash_amount);
        $this->assertSame('550.00', $sale->promptpay_amount);
        $this->assertSame('500.00', $sale->received_amount);
        $this->assertSame('200.00', $sale->change_amount);
    }

    public function test_down_removes_only_payment_columns_and_up_can_run_again(): void
    {
        $migration = $this->migration();
        $businessColumns = ['id', 'sale_no', 'sale_date', 'total_amount', 'revision'];

        $migration->down();

        foreach (self::PAYMENT_COLUMNS as $column) {
            $this->assertFalse(Schema::hasColumn('sales', $column));
        }

        foreach ($businessColumns as $column) {
            $this->assertTrue(Schema::hasColumn('sales', $column));
        }

        $migration->up();

        $this->assertTrue(Schema::hasColumns('sales', self::PAYMENT_COLUMNS));
    }

    public function test_postgresql_payment_columns_have_expected_types_precision_and_nullability(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required for Sale payment metadata verification.');
        }

        $columns = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'sales')
            ->whereIn('column_name', self::PAYMENT_COLUMNS)
            ->get()
            ->keyBy('column_name');

        $this->assertSame('character varying', $columns['payment_method']->data_type);
        $this->assertSame('20', (string) $columns['payment_method']->character_maximum_length);

        foreach (['cash_amount', 'promptpay_amount', 'received_amount', 'change_amount'] as $column) {
            $this->assertSame('numeric', $columns[$column]->data_type);
            $this->assertSame('15', (string) $columns[$column]->numeric_precision);
            $this->assertSame('2', (string) $columns[$column]->numeric_scale);
        }

        foreach (self::PAYMENT_COLUMNS as $column) {
            $this->assertSame('YES', $columns[$column]->is_nullable);
            $this->assertNull($columns[$column]->column_default);
        }
    }

    private function migration(): object
    {
        return require database_path(
            'migrations/2026_07_16_000002_add_payment_recording_to_sales_table.php'
        );
    }
}
