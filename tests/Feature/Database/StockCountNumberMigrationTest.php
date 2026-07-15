<?php

namespace Tests\Feature\Database;

use App\Services\StockCountNumberService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class StockCountNumberMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required for the Stock Count number upgrade migration test.');
        }

        if (DB::connection()->getDatabaseName() === 'atrilak_pos') {
            $this->fail('Stock Count number migration test refused the application database.');
        }

        Schema::dropAllTables();
        $this->createLegacyStockCountsTable();
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql'
            && DB::connection()->getDatabaseName() !== 'atrilak_pos') {
            Schema::dropAllTables();
        }

        parent::tearDown();
    }

    public function test_upgrade_initializes_from_maximum_prefix_suffix_without_changing_old_documents(): void
    {
        DB::table('stock_counts')->insert([
            ['count_no' => 'SC-20260714-0001', 'count_date' => '2026-07-14'],
            ['count_no' => 'SC-20260714-0003', 'count_date' => '2026-07-15'],
            ['count_no' => 'SC-20260715-10000', 'count_date' => '2026-07-15'],
            ['count_no' => 'legacy-number', 'count_date' => '2026-07-15'],
            ['count_no' => null, 'count_date' => '2026-07-15'],
        ]);
        $before = DB::table('stock_counts')->orderBy('id')->get()->toArray();

        $migration = $this->migration();
        $migration->up();

        $this->assertEquals($before, DB::table('stock_counts')->orderBy('id')->get()->toArray());
        $this->assertSame(3, DB::table('stock_count_number_counters')->where('count_date', '2026-07-14')->value('last_number'));
        $this->assertSame(10000, DB::table('stock_count_number_counters')->where('count_date', '2026-07-15')->value('last_number'));
        $this->assertSame(
            'SC-20260714-0004',
            app(StockCountNumberService::class)->generate('2026-07-14')
        );

        DB::table('stock_counts')->insert([
            ['count_no' => null, 'count_date' => '2026-07-15'],
            ['count_no' => null, 'count_date' => '2026-07-15'],
        ]);

        try {
            DB::table('stock_counts')->insert([
                'count_no' => 'SC-20260714-0003',
                'count_date' => '2026-07-14',
            ]);
            $this->fail('The Stock Count number unique constraint should reject duplicates.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->errorInfo[0] ?? null);
        }

        DB::table('stock_count_number_counters')
            ->where('count_date', '2026-07-14')
            ->update(['last_number' => 9]);
        $this->runCounterInitialization($migration);
        $this->assertSame(9, DB::table('stock_count_number_counters')->where('count_date', '2026-07-14')->value('last_number'));
    }

    public function test_upgrade_stops_before_schema_changes_when_duplicate_numbers_exist(): void
    {
        DB::table('stock_counts')->insert([
            ['count_no' => 'SC-20260715-0001', 'count_date' => '2026-07-15'],
            ['count_no' => 'SC-20260715-0001', 'count_date' => '2026-07-15'],
        ]);

        try {
            $this->migration()->up();
            $this->fail('Migration should reject duplicate Stock Count numbers.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('duplicate Stock Count number', $exception->getMessage());
            $this->assertStringContainsString('No Stock Count numbers were changed', $exception->getMessage());
        }

        $this->assertFalse(Schema::hasTable('stock_count_number_counters'));
        $this->assertDatabaseCount('stock_counts', 2);
    }

    public function test_upgrade_stops_when_a_valid_number_exceeds_the_counter_integer_limit(): void
    {
        DB::table('stock_counts')->insert([
            'count_no' => 'SC-20260715-2147483648',
            'count_date' => '2026-07-15',
        ]);

        try {
            $this->migration()->up();
            $this->fail('Migration should reject a suffix beyond the counter integer limit.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('greater than the integer counter limit', $exception->getMessage());
            $this->assertStringContainsString('No Stock Count numbers were changed', $exception->getMessage());
        }

        $this->assertFalse(Schema::hasTable('stock_count_number_counters'));
        $this->assertDatabaseCount('stock_counts', 1);
    }

    private function createLegacyStockCountsTable(): void
    {
        Schema::create('stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->string('count_no')->nullable();
            $table->date('count_date');
            $table->timestamps();
        });
    }

    private function migration()
    {
        return require database_path(
            'migrations/2026_07_15_000002_add_stock_count_number_counter.php'
        );
    }

    private function runCounterInitialization(object $migration): void
    {
        $method = new ReflectionMethod($migration, 'initializeCounters');
        $method->invoke($migration);
    }
}
