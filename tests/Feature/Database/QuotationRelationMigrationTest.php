<?php

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QuotationRelationMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required for the quotation-relation upgrade migration test.');
        }

        if (DB::connection()->getDatabaseName() === 'atrilak_pos') {
            $this->fail('Quotation relation migration test refused the application database.');
        }

        Schema::dropAllTables();
        $this->createLegacySchema();
    }

    protected function tearDown(): void
    {
        if (DB::getDriverName() === 'pgsql'
            && DB::connection()->getDatabaseName() !== 'atrilak_pos') {
            Schema::dropAllTables();
        }

        parent::tearDown();
    }

    public function test_upgrade_adds_nullable_relation_without_backfilling_old_records(): void
    {
        $quotationId = DB::table('quotations')->insertGetId([
            'quotation_no' => 'QT-LEGACY',
            'status' => 'converted',
        ]);
        $saleId = DB::table('sales')->insertGetId([
            'sale_no' => 'SAL-LEGACY',
        ]);

        $this->migration()->up();

        $this->assertTrue(Schema::hasColumn('sales', 'quotation_id'));
        $this->assertNull(DB::table('sales')->where('id', $saleId)->value('quotation_id'));
        $this->assertSame('converted', DB::table('quotations')->where('id', $quotationId)->value('status'));
    }

    public function test_unique_constraint_and_restrict_foreign_key_enforce_integrity(): void
    {
        $firstQuotationId = DB::table('quotations')->insertGetId([
            'quotation_no' => 'QT-ONE',
            'status' => 'draft',
        ]);
        $secondQuotationId = DB::table('quotations')->insertGetId([
            'quotation_no' => 'QT-TWO',
            'status' => 'draft',
        ]);
        $this->migration()->up();

        DB::table('sales')->insert([
            'sale_no' => 'SAL-ONE',
            'quotation_id' => $firstQuotationId,
        ]);

        try {
            DB::table('sales')->insert([
                'sale_no' => 'SAL-TWO',
                'quotation_id' => $firstQuotationId,
            ]);
            $this->fail('Unique quotation relation should reject a second sale.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->errorInfo[0] ?? null);
        }

        try {
            DB::table('quotations')->where('id', $firstQuotationId)->delete();
            $this->fail('Restrict foreign key should reject deleting a linked quotation.');
        } catch (QueryException $exception) {
            $this->assertSame('23503', $exception->errorInfo[0] ?? null);
        }

        $this->assertSame(1, DB::table('sales')->where('quotation_id', $firstQuotationId)->count());
        $this->assertSame(1, DB::table('quotations')->where('id', $secondQuotationId)->count());
    }

    private function createLegacySchema(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_no')->nullable();
            $table->string('status')->default('draft');
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_no')->nullable();
        });
    }

    private function migration()
    {
        return require database_path('migrations/2026_07_14_000005_add_quotation_relation_to_sales.php');
    }
}
