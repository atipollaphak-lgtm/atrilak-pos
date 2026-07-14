<?php

namespace Tests\Feature\Sales;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Services\Sales\ProductUnitConversionService;
use App\Services\SaleService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesSaleTransactionTestSchema;
use Tests\TestCase;

class ProductUnitConversionTest extends TestCase
{
    use CreatesSaleTransactionTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSaleTransactionTestSchema();
    }

    protected function tearDown(): void
    {
        $this->dropSaleTransactionTestSchema();
        parent::tearDown();
    }

    public function test_rate_one_twelve_and_twenty_four_use_base_quantities_without_combining_lines(): void
    {
        $product = $this->product('Mixed units', '100.0000');
        $piece = $this->unit($product, 'piece', '1.0000', true);
        $dozen = $this->unit($product, 'dozen', '12.0000');
        $case = $this->unit($product, 'case', '24.0000');

        $sale = $this->sale([
            $this->line($product, '2.00', '10.00', $piece),
            $this->line($product, '3.00', '100.00', $dozen),
            $this->line($product, '1.00', '180.00', $case),
        ]);

        $items = $sale->items()->orderBy('id')->get();
        $this->assertCount(3, $items);
        $this->assertSame(['2.0000', '36.0000', '24.0000'], $items->pluck('base_qty')->all());
        $this->assertSame(['1.0000', '12.0000', '24.0000'], $items->pluck('conversion_rate_used')->all());
        $this->assertEquals([10.00, 285.00, 175.00], $items->pluck('profit')->map(fn ($value) => (float) $value)->all());
        $this->assertSame('38.0000', $product->fresh()->stock_qty);
        $this->assertSame(['2.0000', '36.0000', '24.0000'], StockMovement::orderBy('id')->pluck('qty')->all());
        $this->assertEquals(500.00, $sale->total_amount);
    }

    public function test_fractional_rate_and_deterministic_decimal_precision_are_preserved(): void
    {
        $product = $this->product('Fractional unit', '10.0000');
        $bucket = $this->unit($product, 'bucket', '0.0200');
        $third = $this->unit($product, 'third', '0.3333');

        $sale = $this->sale([
            $this->line($product, '1.00', '5.00', $bucket),
            $this->line($product, '3.00', '7.00', $third),
        ]);

        $this->assertSame(['0.0200', '0.9999'], $sale->items()->orderBy('id')->pluck('base_qty')->all());
        $this->assertSame('8.9801', $product->fresh()->stock_qty);
        $this->assertSame('8.9801', StockMovement::latest('id')->value('stock_after'));
    }

    public function test_one_case_plus_two_pieces_deducts_twenty_six_base_units(): void
    {
        $product = $this->product('Case and pieces', '100.0000');
        $case = $this->unit($product, 'case', '24.0000');

        $sale = $this->sale([
            $this->line($product, '1.00', '180.00', $case),
            $this->line($product, '2.00', '10.00'),
        ]);

        $this->assertSame(['24.0000', '2.0000'], $sale->items()->orderBy('id')->pluck('base_qty')->all());
        $this->assertSame('74.0000', $product->fresh()->stock_qty);
        $this->assertEquals(200.00, $sale->items()->sum('total'));
    }

    public function test_v1_without_product_unit_uses_factor_one_snapshot(): void
    {
        $product = $this->product('Legacy base flow', '10.0000');

        $sale = $this->sale([$this->line($product, '2.50', '10.00')]);
        $item = $sale->items()->sole();

        $this->assertNull($item->product_unit_id);
        $this->assertSame('1.0000', $item->conversion_rate_used);
        $this->assertSame('2.5000', $item->base_qty);
        $this->assertSame('7.5000', $product->fresh()->stock_qty);
    }

    public function test_rejects_invalid_product_unit_configuration_and_rolls_back(): void
    {
        $product = $this->product('Validated product', '20.0000');
        $other = $this->product('Other product', '20.0000');

        $cases = [
            'wrong product' => $this->unit($other, 'other-unit', '12.0000'),
            'inactive' => $this->unit($product, 'inactive', '12.0000', false, false),
            'not sale unit' => $this->unit($product, 'purchase-only', '12.0000', false, true, false),
            'unconfirmed' => $this->unit($product, 'unconfirmed', '12.0000', false, true, true, null),
            'bad base rate' => $this->unit($product, 'bad-base', '2.0000', true),
        ];

        foreach ($cases as $label => $unit) {
            try {
                $this->sale([$this->line($product, '1.00', '10.00', $unit)]);
                $this->fail("Expected {$label} to be rejected.");
            } catch (DomainException) {
                $this->assertDatabaseCount('sales', 0);
                $this->assertSame('20.0000', $product->fresh()->stock_qty, $label);
            }
        }
    }

    public function test_unconfirmed_non_base_unit_has_understandable_message_then_succeeds_after_confirmation(): void
    {
        $product = $this->product('Confirmation product', '20.0000');
        $unit = $this->unit($product, 'case', '12.0000', false, true, true, null);

        try {
            $this->sale([$this->line($product, '1.00', '10.00', $unit)]);
            $this->fail('Expected unconfirmed conversion failure.');
        } catch (DomainException $exception) {
            $this->assertSame('หน่วยขายนี้ยังไม่ได้ยืนยันอัตราแปลงสต๊อก', $exception->getMessage());
        }

        $unit->update(['conversion_confirmed_at' => now()]);
        $sale = $this->sale([$this->line($product, '1.00', '10.00', $unit->fresh())]);

        $this->assertSame('12.0000', $sale->items()->sole()->base_qty);
        $this->assertSame('8.0000', $product->fresh()->stock_qty);
    }

    public function test_non_positive_rate_and_base_quantity_that_rounds_to_zero_are_rejected(): void
    {
        $product = $this->product('Invalid decimal product', '20.0000');

        foreach (['0.0000', '-1.0000'] as $rate) {
            $unit = $this->unit($product, 'rate-'.$rate, $rate);
            $this->expectDomainFailure(fn () => $this->sale([$this->line($product, '1.00', '10.00', $unit)]));
        }

        $tiny = $this->unit($product, 'tiny', '0.0001');
        $this->expectDomainFailure(fn () => $this->sale([$this->line($product, '0.01', '10.00', $tiny)]));
        $this->expectDomainFailure(
            fn () => app(ProductUnitConversionService::class)->calculateBaseQuantity('1.00', null)
        );
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_combined_base_quantity_is_checked_before_any_stock_write(): void
    {
        $product = $this->product('Combined product', '25.0000');
        $dozen = $this->unit($product, 'dozen', '12.0000');

        $this->expectDomainFailure(fn () => $this->sale([
            $this->line($product, '2.00', '10.00', $dozen),
            $this->line($product, '2.00', '10.00'),
        ]));

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame('25.0000', $product->fresh()->stock_qty);
    }

    public function test_delete_uses_snapshot_even_after_rate_and_confirmation_change(): void
    {
        $product = $this->product('Snapshot delete', '50.0000');
        $unit = $this->unit($product, 'dozen', '12.0000');
        $sale = $this->sale([$this->line($product, '2.00', '10.00', $unit)]);

        $unit->update(['conversion_rate' => '99.0000', 'conversion_confirmed_at' => null]);
        app(SaleService::class)->deleteSale($sale);

        $this->assertSame('50.0000', $product->fresh()->stock_qty);
        $this->assertSame('24.0000', StockMovement::where('type', 'IN')->sole()->qty);
    }

    public function test_update_restores_old_snapshot_after_confirmation_is_cleared(): void
    {
        $product = $this->product('Snapshot update', '50.0000');
        $unit = $this->unit($product, 'dozen', '12.0000');
        $sale = $this->sale([$this->line($product, '2.00', '10.00', $unit)]);
        $oldItem = $sale->items()->sole();
        $unit->update(['conversion_rate' => '99.0000', 'conversion_confirmed_at' => null]);

        app(SaleService::class)->updateSale($sale, [
            'customer_id' => null,
            'sale_date' => '2026-07-14',
            'sale_item_id' => [$oldItem->id],
            'product_id' => [$product->id],
            'product_unit_id' => [null],
            'qty' => ['1.00'],
            'selling_price' => ['10.00'],
            'delivery_fee' => 0,
            'discount' => 0,
        ]);

        $this->assertSame('49.0000', $product->fresh()->stock_qty);
        $this->assertSame('24.0000', StockMovement::where('type', 'IN')->sole()->qty);
        $this->assertSame('1.0000', $sale->fresh()->items()->sole()->base_qty);
    }

    public function test_update_rejects_hidden_product_unit_owned_by_another_product(): void
    {
        $product = $this->product('Edited product', '10.0000');
        $other = $this->product('Other unit owner', '10.0000');
        $wrongUnit = $this->unit($other, 'wrong unit', '12.0000');
        $sale = $this->sale([$this->line($product, '1.00', '10.00')]);
        $oldItem = $sale->items()->sole();
        $before = DB::table('stock_movements')->count();

        $this->expectDomainFailure(fn () => app(SaleService::class)->updateSale($sale, [
            'customer_id' => null,
            'sale_date' => '2026-07-14',
            'sale_item_id' => [$oldItem->id],
            'product_id' => [$product->id],
            'product_unit_id' => [$wrongUnit->id],
            'qty' => ['1.00'],
            'selling_price' => ['10.00'],
            'delivery_fee' => 0,
            'discount' => 0,
        ]));

        $this->assertSame('9.0000', $product->fresh()->stock_qty);
        $this->assertSame($before, DB::table('stock_movements')->count());
        $this->assertSame($oldItem->id, $sale->fresh()->items()->sole()->id);
    }

    public function test_decimal_model_casts_do_not_truncate_fractional_stock(): void
    {
        $product = $this->product('Decimal cast', '1.2345');
        $movement = StockMovement::create([
            'product_id' => $product->id,
            'type' => 'OUT',
            'qty' => '0.0200',
            'stock_before' => '1.2345',
            'stock_after' => '1.2145',
        ]);

        $this->assertSame('1.2345', $product->stock_qty);
        $this->assertSame('0.0200', $movement->qty);
        $this->assertSame('1.2145', $movement->stock_after);
    }

    public function test_legacy_item_with_product_unit_but_no_snapshot_restores_sale_quantity_only(): void
    {
        $product = $this->product('Legacy restore', '8.0000');
        $unit = $this->unit($product, 'case', '12.0000');
        $sale = Sale::create([
            'sale_no' => 'SAL-LEGACY-1',
            'sale_date' => '2026-07-14',
            'total_amount' => 20,
            'delivery_type' => 'pickup',
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'qty' => '2.00',
            'selling_price' => '10.00',
            'cost_price' => '5.00',
            'total' => '20.00',
            'profit' => '10.00',
        ]);

        app(SaleService::class)->deleteSale($sale);

        $this->assertSame('10.0000', $product->fresh()->stock_qty);
        $this->assertSame('2.0000', StockMovement::sole()->qty);
    }

    public function test_update_of_legacy_item_with_unit_restores_sale_quantity_only(): void
    {
        $product = $this->product('Legacy update restore', '8.0000');
        $unit = $this->unit($product, 'case', '12.0000');
        $sale = Sale::create([
            'sale_no' => 'SAL-LEGACY-UPDATE-1',
            'sale_date' => '2026-07-14',
            'total_amount' => 20,
            'delivery_type' => 'pickup',
        ]);
        $legacyItem = $sale->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'qty' => '2.00',
            'selling_price' => '10.00',
            'cost_price' => '5.00',
            'total' => '20.00',
            'profit' => '10.00',
        ]);

        app(SaleService::class)->updateSale($sale, [
            'customer_id' => null,
            'sale_date' => '2026-07-14',
            'sale_item_id' => [$legacyItem->id],
            'product_id' => [$product->id],
            'product_unit_id' => [null],
            'qty' => ['1.00'],
            'selling_price' => ['10.00'],
            'delivery_fee' => 0,
            'discount' => 0,
        ]);

        $this->assertSame('9.0000', $product->fresh()->stock_qty);
        $this->assertSame('2.0000', StockMovement::where('type', 'IN')->sole()->qty);
        $this->assertSame('1.0000', $sale->fresh()->items()->sole()->base_qty);
    }

    private function product(string $name, string $stock): Product
    {
        return Product::create([
            'name' => $name,
            'cost_price' => '5.00',
            'selling_price' => '10.00',
            'stock_qty' => $stock,
        ]);
    }

    private function unit(
        Product $product,
        string $name,
        ?string $rate,
        bool $base = false,
        bool $active = true,
        bool $saleUnit = true,
        mixed $confirmedAt = 'confirmed'
    ): ProductUnit {
        $unitId = DB::table('units')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $unitId,
            'conversion_rate' => $rate,
            'is_base_unit' => $base,
            'is_sale_unit' => $saleUnit,
            'active' => $active,
            'conversion_confirmed_at' => $confirmedAt === 'confirmed' ? now() : $confirmedAt,
        ]);
    }

    private function line(Product $product, string $qty, string $price, ?ProductUnit $unit = null): array
    {
        return [
            'product_id' => $product->id,
            'product_unit_id' => $unit?->id,
            'qty' => $qty,
            'selling_price' => $price,
        ];
    }

    private function sale(array $items): Sale
    {
        return app(SaleService::class)->createSale([
            'sale_date' => '2026-07-14',
            'grand_total' => collect($items)->sum(fn (array $item) => (float) $item['qty'] * (float) $item['selling_price']),
            'delivery_type' => 'pickup',
            'discount' => 0,
            'items' => $items,
        ]);
    }

    private function expectDomainFailure(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a domain failure.');
        } catch (DomainException) {
            // Expected.
        }
    }
}
