<?php

namespace Tests\Feature\DailyPaymentClosings;

use App\Models\DailyPaymentClosing;
use App\Models\DailyPaymentClosingSale;
use App\Models\Sale;
use App\Services\Sales\DailyPaymentSummaryService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DailyPaymentClosingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_payment_closing_foundation_classes_and_tables_exist(): void
    {
        $this->assertTrue(class_exists(DailyPaymentClosing::class));
        $this->assertTrue(class_exists(DailyPaymentClosingSale::class));
        $this->assertTrue(class_exists(DailyPaymentSummaryService::class));
        $this->assertTrue(Schema::hasTable('daily_payment_closings'));
        $this->assertTrue(Schema::hasTable('daily_payment_closing_sales'));
    }

    public function test_closing_schema_has_required_constraints_precise_amounts_and_foreign_keys(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->assertPostgresqlSchema();

            return;
        }

        $closingColumns = collect(DB::select("PRAGMA table_info('daily_payment_closings')"))
            ->keyBy('name');
        $salesColumns = collect(DB::select("PRAGMA table_info('daily_payment_closing_sales')"))
            ->keyBy('name');

        $this->assertSame('numeric', strtolower($closingColumns['expected_cash_amount']->type));
        $this->assertSame('numeric', strtolower($closingColumns['actual_promptpay_amount']->type));
        $this->assertSame('1', trim((string) $closingColumns['revision']->dflt_value, "'\""));
        $this->assertSame('open', trim((string) $closingColumns['status']->dflt_value, "'\""));
        $this->assertSame('numeric', strtolower($salesColumns['cash_amount']->type));
        $this->assertSame('numeric', strtolower($salesColumns['change_amount']->type));
        $this->assertTrue($this->hasIndexForColumns('daily_payment_closings', ['business_date'], true));
        $this->assertTrue($this->hasIndexForColumns('daily_payment_closing_sales', ['sale_id']));
        $this->assertTrue($this->hasIndexForColumns(
            'daily_payment_closing_sales',
            ['daily_payment_closing_id', 'sale_id'],
            true
        ));

        $closingForeignKeys = collect(DB::select("PRAGMA foreign_key_list('daily_payment_closings')"))
            ->keyBy('from');
        $salesForeignKeys = collect(DB::select("PRAGMA foreign_key_list('daily_payment_closing_sales')"))
            ->keyBy('from');

        $this->assertSame('SET NULL', $closingForeignKeys['opened_by']->on_delete);
        $this->assertSame('SET NULL', $closingForeignKeys['finalized_by']->on_delete);
        $this->assertSame('RESTRICT', $salesForeignKeys['sale_id']->on_delete);
        $this->assertSame('CASCADE', $salesForeignKeys['daily_payment_closing_id']->on_delete);
    }

    public function test_closing_and_snapshot_uniqueness_are_enforced(): void
    {
        $closing = DailyPaymentClosing::query()->create(['business_date' => '2026-07-18']);

        try {
            DB::transaction(function (): void {
                DailyPaymentClosing::query()->create(['business_date' => '2026-07-18']);
            });
            $this->fail('Expected business date uniqueness violation.');
        } catch (QueryException) {
            // Expected.
        }

        $this->assertDatabaseCount('daily_payment_closings', 1);

        $sale = Sale::query()->create([
            'sale_no' => 'CLOSE-SNAPSHOT-SALE',
            'sale_date' => '2026-07-18',
            'total_amount' => '0.00',
        ]);
        $attributes = [
            'daily_payment_closing_id' => $closing->id,
            'sale_id' => $sale->id,
            'sale_status' => Sale::STATUS_ACTIVE,
        ];
        DailyPaymentClosingSale::query()->create($attributes);

        try {
            DB::transaction(function () use ($attributes): void {
                DailyPaymentClosingSale::query()->create($attributes);
            });
            $this->fail('Expected closing-sale uniqueness violation.');
        } catch (QueryException) {
            // Expected.
        }

        $this->assertDatabaseCount('daily_payment_closing_sales', 1);
    }

    private function assertPostgresqlSchema(): void
    {
        $columns = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->whereIn('table_name', [
                'daily_payment_closings',
                'daily_payment_closing_sales',
            ])
            ->whereIn('column_name', [
                'expected_cash_amount',
                'actual_promptpay_amount',
                'cash_amount',
                'change_amount',
                'revision',
                'status',
            ])
            ->get()
            ->keyBy(fn (object $column): string => $column->table_name.'.'.$column->column_name);

        foreach ([
            'daily_payment_closings.expected_cash_amount',
            'daily_payment_closings.actual_promptpay_amount',
            'daily_payment_closing_sales.cash_amount',
            'daily_payment_closing_sales.change_amount',
        ] as $columnName) {
            $column = $columns[$columnName];

            $this->assertSame('numeric', $column->data_type);
            $this->assertSame('15', (string) $column->numeric_precision);
            $this->assertSame('2', (string) $column->numeric_scale);
        }

        $this->assertStringContainsString('1', $columns['daily_payment_closings.revision']->column_default);
        $this->assertStringContainsString('open', $columns['daily_payment_closings.status']->column_default);

        $indexes = DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->whereIn('tablename', ['daily_payment_closings', 'daily_payment_closing_sales'])
            ->get();

        $this->assertTrue($indexes->contains(
            fn (object $index): bool => str_contains($index->indexdef, 'UNIQUE')
                && str_contains($index->indexdef, '(business_date)')
        ));
        $this->assertTrue($indexes->contains(
            fn (object $index): bool => ! str_contains($index->indexdef, 'UNIQUE')
                && str_contains($index->indexdef, '(sale_id)')
        ));
        $this->assertTrue($indexes->contains(
            fn (object $index): bool => str_contains($index->indexdef, 'UNIQUE')
                && str_contains($index->indexdef, '(daily_payment_closing_id, sale_id)')
        ));

        $foreignKeys = DB::select(
            "SELECT conrelid::regclass::text AS table_name, conname, confdeltype
             FROM pg_constraint
             WHERE conrelid IN ('daily_payment_closings'::regclass, 'daily_payment_closing_sales'::regclass)
               AND contype = 'f'"
        );
        $foreignKeys = collect($foreignKeys)->keyBy('conname');

        $this->assertSame('n', $foreignKeys['daily_payment_closings_opened_by_foreign']->confdeltype);
        $this->assertSame('n', $foreignKeys['daily_payment_closings_finalized_by_foreign']->confdeltype);
        $this->assertSame('r', $foreignKeys['daily_payment_closing_sales_sale_id_foreign']->confdeltype);
        $this->assertSame('c', $foreignKeys['daily_payment_closing_sales_daily_payment_closing_id_foreign']->confdeltype);
    }

    public function test_postgresql_amount_columns_use_numeric_fifteen_two(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required for numeric precision verification.');
        }

        $columns = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->whereIn('table_name', [
                'daily_payment_closings',
                'daily_payment_closing_sales',
            ])
            ->whereIn('column_name', [
                'expected_cash_amount',
                'actual_promptpay_amount',
                'cash_amount',
                'change_amount',
            ])
            ->get();

        $this->assertCount(4, $columns);

        foreach ($columns as $column) {
            $this->assertSame('numeric', $column->data_type);
            $this->assertSame('15', (string) $column->numeric_precision);
            $this->assertSame('2', (string) $column->numeric_scale);
        }
    }

    private function hasIndexForColumns(string $table, array $expectedColumns, bool $unique = false): bool
    {
        foreach (DB::select("PRAGMA index_list('{$table}')") as $index) {
            if ((int) $index->unique !== (int) $unique) {
                continue;
            }

            $columns = collect(DB::select("PRAGMA index_info('{$index->name}')"))
                ->sortBy('seqno')
                ->pluck('name')
                ->all();

            if ($columns === $expectedColumns) {
                return true;
            }
        }

        return false;
    }
}
