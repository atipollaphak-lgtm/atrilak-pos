<?php

namespace Tests\Feature\Database;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Unit;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class QuotationQuantitySchemaMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_schema_has_nullable_quantity_contract_columns_and_decimal_casts(): void
    {
        $this->assertTrue(Schema::hasColumns('quotation_items', [
            'product_unit_id',
            'conversion_rate_used',
            'base_qty',
            'unit_name_snapshot',
            'unit_code_snapshot',
        ]));
        $this->assertSame('numeric', Schema::getColumnType('quotation_items', 'qty'));

        $item = new QuotationItem;

        $this->assertSame('decimal:2', $item->getCasts()['qty']);
        $this->assertSame('decimal:4', $item->getCasts()['conversion_rate_used']);
        $this->assertSame('decimal:4', $item->getCasts()['base_qty']);
    }

    public function test_decimal_quantities_and_nullable_snapshots_can_be_stored_without_float_assertions(): void
    {
        $quotation = $this->quotation();

        foreach (['0.50', '1.25'] as $quantity) {
            $item = $quotation->items()->create([
                'qty' => $quantity,
                'selling_price' => '10.00',
                'total' => '10.00',
            ]);

            $this->assertDecimalEquals($quantity, $item->fresh()->qty);
            $this->assertNull($item->product_unit_id);
            $this->assertNull($item->conversion_rate_used);
            $this->assertNull($item->base_qty);
            $this->assertNull($item->unit_name_snapshot);
            $this->assertNull($item->unit_code_snapshot);
        }
    }

    public function test_deleting_product_unit_sets_relation_to_null_without_deleting_quotation_item(): void
    {
        [$product, $productUnit] = $this->productAndUnit();
        $quotation = $this->quotation();
        $item = $quotation->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $productUnit->id,
            'conversion_rate_used' => '12.0000',
            'base_qty' => '24.0000',
            'qty' => '2.00',
            'selling_price' => '100.00',
            'total' => '200.00',
            'unit_name_snapshot' => 'Case',
            'unit_code_snapshot' => 'CASE',
        ]);

        $productUnit->delete();

        $this->assertDatabaseHas('quotation_items', ['id' => $item->id]);
        $this->assertNull($item->fresh()->product_unit_id);
        $this->assertSame('12.0000', $item->fresh()->conversion_rate_used);
        $this->assertSame('24.0000', $item->fresh()->base_qty);
    }

    public function test_upgrade_preserves_legacy_integer_quantity_without_backfilling_contract_columns(): void
    {
        $migration = $this->migration();
        $migration->down();
        $quotation = $this->quotation();
        $itemId = DB::table('quotation_items')->insertGetId([
            'quotation_id' => $quotation->id,
            'qty' => 3,
            'selling_price' => '10.00',
            'total' => '30.00',
        ]);

        $migration->up();

        $item = DB::table('quotation_items')->where('id', $itemId)->sole();
        $this->assertDecimalEquals('3.00', $item->qty);
        $this->assertNull($item->product_unit_id);
        $this->assertNull($item->conversion_rate_used);
        $this->assertNull($item->base_qty);
        $this->assertNull($item->unit_name_snapshot);
        $this->assertNull($item->unit_code_snapshot);
    }

    public function test_up_down_up_is_safe_for_integral_quantities(): void
    {
        $migration = $this->migration();
        $quotation = $this->quotation();
        $quotation->items()->create([
            'qty' => '2.00',
            'selling_price' => '10.00',
            'total' => '20.00',
        ]);

        $migration->down();
        $this->assertContains(
            Schema::getColumnType('quotation_items', 'qty'),
            ['integer', 'int4'],
        );
        $migration->up();

        $this->assertSame('numeric', Schema::getColumnType('quotation_items', 'qty'));
        $this->assertDecimalEquals('2.00', DB::table('quotation_items')->value('qty'));
    }

    public function test_down_refuses_to_truncate_fractional_quantity_before_changing_schema(): void
    {
        $quotation = $this->quotation();
        $quotation->items()->create([
            'qty' => '1.25',
            'selling_price' => '10.00',
            'total' => '12.50',
        ]);

        try {
            $this->migration()->down();
            $this->fail('Expected the down migration to reject fractional quotation quantity.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('fractional values would be lost', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('quotation_items', 'product_unit_id'));
        $this->assertSame('numeric', Schema::getColumnType('quotation_items', 'qty'));
        $this->assertDecimalEquals('1.25', DB::table('quotation_items')->value('qty'));
    }

    public function test_postgresql_columns_have_expected_types_precision_and_nullability(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required for quotation quantity metadata verification.');
        }

        $columns = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'quotation_items')
            ->whereIn('column_name', [
                'product_unit_id',
                'conversion_rate_used',
                'base_qty',
                'qty',
                'unit_name_snapshot',
                'unit_code_snapshot',
            ])
            ->get()
            ->keyBy('column_name');

        $this->assertSame(['15', '2'], [
            (string) $columns['qty']->numeric_precision,
            (string) $columns['qty']->numeric_scale,
        ]);
        $this->assertSame(['15', '4'], [
            (string) $columns['conversion_rate_used']->numeric_precision,
            (string) $columns['conversion_rate_used']->numeric_scale,
        ]);
        $this->assertSame(['19', '4'], [
            (string) $columns['base_qty']->numeric_precision,
            (string) $columns['base_qty']->numeric_scale,
        ]);

        foreach (['product_unit_id', 'conversion_rate_used', 'base_qty', 'unit_name_snapshot', 'unit_code_snapshot'] as $column) {
            $this->assertSame('YES', $columns[$column]->is_nullable);
        }
    }

    private function quotation(): Quotation
    {
        return Quotation::query()->create([
            'quotation_no' => 'QT-SCHEMA-'.Quotation::query()->count(),
            'quotation_date' => '2026-07-15',
            'total_amount' => '0.00',
            'status' => 'draft',
        ]);
    }

    private function productAndUnit(): array
    {
        $category = Category::query()->create(['name' => 'Quantity schema category']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Quantity schema product',
            'cost_price' => '5.00',
            'selling_price' => '10.00',
            'stock_qty' => '100.0000',
            'minimum_stock' => '0.0000',
        ]);
        $unit = Unit::query()->create([
            'name' => 'Case',
            'short_name' => 'Case',
            'code' => 'CASE',
        ]);
        $productUnit = ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_rate' => '12.0000',
            'is_sale_unit' => true,
            'active' => true,
        ]);

        return [$product, $productUnit];
    }

    private function migration(): object
    {
        return require database_path(
            'migrations/2026_07_15_000008_add_quantity_contract_to_quotation_items.php'
        );
    }

    private function assertDecimalEquals(string $expected, mixed $actual): void
    {
        $this->assertTrue(BigDecimal::of($expected)->isEqualTo((string) $actual));
    }
}
