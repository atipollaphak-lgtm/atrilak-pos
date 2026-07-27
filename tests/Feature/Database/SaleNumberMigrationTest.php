<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class SaleNumberMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required for the sale-number upgrade migration test.');
        }

        if (DB::connection()->getDatabaseName() === 'atrilak_pos') {
            $this->fail('Sale-number migration test refused the application database.');
        }

        Schema::dropAllTables();
        $this->createLegacySalesTable();
    }

    protected function tearDown(): void
    {
        if (DB::getDriverName() === 'pgsql'
            && DB::connection()->getDatabaseName() !== 'atrilak_pos') {
            Schema::dropAllTables();
        }

        parent::tearDown();
    }

    public function test_upgrade_initializes_daily_counters_without_changing_old_sales(): void
    {
        DB::table('sales')->insert([
            ['sale_no' => 'SAL-20260713-0002', 'sale_date' => '2026-07-13'],
            ['sale_no' => 'SAL-20260713-0012', 'sale_date' => '2026-07-13'],
            ['sale_no' => 'SAL-20260714-0004', 'sale_date' => '2026-07-14'],
            ['sale_no' => null, 'sale_date' => '2026-07-14'],
        ]);
        $before = DB::table('sales')->orderBy('id')->get(['id', 'sale_no', 'sale_date'])->toArray();

        $this->migration()->up();

        $this->assertEquals(
            $before,
            DB::table('sales')->orderBy('id')->get(['id', 'sale_no', 'sale_date'])->toArray()
        );
        $this->assertSame(12, DB::table('sale_number_counters')->where('sale_date', '2026-07-13')->value('last_number'));
        $this->assertSame(4, DB::table('sale_number_counters')->where('sale_date', '2026-07-14')->value('last_number'));
        $this->assertNull(DB::table('sales')->whereNull('sale_no')->value('idempotency_key'));
        $this->assertNull(DB::table('sales')->whereNull('sale_no')->value('idempotency_payload_hash'));

        $this->expectException(QueryException::class);
        DB::table('sales')->insert([
            'sale_no' => 'SAL-20260713-0012',
            'sale_date' => '2026-07-13',
        ]);
    }

    public function test_upgrade_stops_before_schema_changes_when_duplicate_sale_numbers_exist(): void
    {
        DB::table('sales')->insert([
            ['sale_no' => 'SAL-20260714-0001', 'sale_date' => '2026-07-14'],
            ['sale_no' => 'SAL-20260714-0001', 'sale_date' => '2026-07-14'],
        ]);

        try {
            $this->migration()->up();
            $this->fail('Migration should reject duplicate sale numbers.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('duplicate sale number', $exception->getMessage());
            $this->assertStringContainsString('No sale numbers were changed', $exception->getMessage());
        }

        $this->assertFalse(Schema::hasTable('sale_number_counters'));
        $this->assertFalse(Schema::hasColumn('sales', 'idempotency_key'));
        $this->assertDatabaseCount('sales', 2);
    }

    private function createLegacySalesTable(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_no')->nullable();
            $table->date('sale_date');
        });
    }

    private function migration()
    {
        return require database_path('migrations/2026_07_14_000004_add_sale_number_counters_and_idempotency.php');
    }
}
