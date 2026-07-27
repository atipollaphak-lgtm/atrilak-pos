<?php

namespace Tests\Feature\Database;

use Brick\Math\BigDecimal;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PurchaseItemQuantityMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required for the Purchase Item quantity upgrade migration test.');
        }

        if (DB::connection()->getDatabaseName() === 'atrilak_pos') {
            $this->fail('Purchase Item quantity migration test refused the application database.');
        }

        Schema::dropAllTables();
        Schema::create('purchase_items', function (Blueprint $table): void {
            $table->id();
            $table->decimal('qty', 12, 2);
        });
    }

    protected function tearDown(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql'
            && DB::connection()->getDatabaseName() !== 'atrilak_pos') {
            Schema::dropAllTables();
        }

        parent::tearDown();
    }

    public function test_upgrade_preserves_values_and_changes_quantity_to_numeric_19_4(): void
    {
        DB::table('purchase_items')->insert([
            ['qty' => '1.00'],
            ['qty' => '9999999999.99'],
        ]);
        $before = DB::table('purchase_items')->orderBy('id')->pluck('qty');

        $this->migration()->up();

        $after = DB::table('purchase_items')->orderBy('id')->pluck('qty');
        $this->assertCount($before->count(), $after);

        foreach ($before as $index => $quantity) {
            $this->assertTrue(BigDecimal::of($quantity)->isEqualTo($after[$index]));
        }

        $this->assertSame(['1.0000', '9999999999.9900'], $after->all());
        $metadata = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'purchase_items')
            ->where('column_name', 'qty')
            ->sole();
        $this->assertSame('numeric', $metadata->data_type);
        $this->assertSame(19, $metadata->numeric_precision);
        $this->assertSame(4, $metadata->numeric_scale);
    }

    public function test_down_refuses_to_lose_fractional_values_beyond_two_decimal_places(): void
    {
        $migration = $this->migration();
        $migration->up();
        DB::table('purchase_items')->insert(['qty' => '1.2345']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fractional values beyond 2 decimal places would be lost');
        $migration->down();
    }

    private function migration()
    {
        return require database_path(
            'migrations/2026_07_15_000003_make_purchase_item_quantity_decimal.php'
        );
    }
}
