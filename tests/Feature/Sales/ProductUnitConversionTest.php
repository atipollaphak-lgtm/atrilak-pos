<?php

namespace Tests\Feature\Sales;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Services\Sales\ProductUnitConversionService;
use App\Services\Sales\SaleDecimalService;
use App\Services\SaleService;
use App\ValueObjects\Sales\ResolvedSaleLine;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesSaleTransactionTestSchema;
use Tests\TestCase;

class ProductUnitConversionTest extends TestCase
{
    use CreatesSaleTransactionTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSaleTransactionTestSchema();
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('base_delivery_fee', 12, 2)->default(0);
            $table->decimal('minimum_profit', 12, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('delivery_zones');
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
        $this->assertEquals([10.00, 120.00, 60.00], $items->pluck('profit')->map(fn ($value) => (float) $value)->all());
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

        $sale = $this->sale([$this->line($product, '5.00', '10.00')]);
        $item = $sale->items()->sole();

        $this->assertNull($item->product_unit_id);
        $this->assertSame('1.0000', $item->conversion_rate_used);
        $this->assertSame('5.0000', $item->base_qty);
        $this->assertSame('5.0000', $product->fresh()->stock_qty);
        $this->assertSame('5.0000', StockMovement::sole()->qty);
    }

    public function test_v2_two_cases_use_sale_quantity_and_forty_eight_base_units(): void
    {
        $product = $this->product('Two cases', '60.0000');
        $case = $this->unit($product, 'case', '24.0000');

        $sale = $this->sale([$this->line($product, '2.00', '180.00', $case)]);
        $item = $sale->items()->sole();

        $this->assertSame('2.00', $item->qty);
        $this->assertSame('24.0000', $item->conversion_rate_used);
        $this->assertSame('48.0000', $item->base_qty);
        $this->assertSame('case', $item->unit_name_snapshot);
        $this->assertSame('case', $item->unit_code_snapshot);
        $this->assertSame('12.0000', $product->fresh()->stock_qty);
        $this->assertSame('48.0000', StockMovement::sole()->qty);
        $this->assertSame('360.00', $item->total);
        $this->assertSame('120.00', $item->profit);
        $this->assertSame(
            '240.00',
            app(SaleDecimalService::class)
                ->storedLineCost($item->total, $item->profit)
        );
    }

    public function test_factor_one_sale_retains_base_unit_profit_behavior(): void
    {
        $product = $this->product('Factor one profit', '10.0000');

        $item = $this->sale([
            $this->line($product, '2.00', '10.00'),
        ])->items()->sole();

        $this->assertSame('20.00', $item->total);
        $this->assertSame('10.00', $item->profit);
    }

    public function test_mixed_units_use_base_cost_per_line_and_preserve_the_invariant(): void
    {
        $product = $this->product('Mixed unit profit', '100.0000');
        $case = $this->unit($product, 'case', '24.0000');

        $items = $this->sale([
            $this->line($product, '2.00', '180.00', $case),
            $this->line($product, '5.00', '10.00'),
        ])->items()->orderBy('id')->get();

        $this->assertSame(['120.00', '25.00'], $items->pluck('profit')->all());
        $this->assertSame(
            '145.00',
            app(SaleDecimalService::class)
                ->sumMoney($items->pluck('profit'))
        );

        foreach ($items as $item) {
            $cost = app(SaleDecimalService::class)
                ->storedLineCost($item->total, $item->profit);
            $this->assertSame(
                $item->total,
                app(SaleDecimalService::class)
                    ->addMoney($cost, $item->profit)
            );
        }
    }

    public function test_fractional_base_cost_rounding_is_deterministic(): void
    {
        $product = $this->product('Fractional cost rounding', '10.0000');
        $fractional = $this->unit($product, 'fractional', '0.3333');

        $item = $this->sale([
            $this->line($product, '1.25', '2.00', $fractional),
        ])->items()->sole();

        $this->assertSame('0.4166', $item->base_qty);
        $this->assertSame('2.50', $item->total);
        $this->assertSame('0.42', $item->profit);
    }

    public function test_negative_product_profit_can_be_offset_by_delivery_fee(): void
    {
        $product = $this->product('Negative line', '100.0000');
        $case = $this->unit($product, 'case', '24.0000');

        $sale = $this->deliverySale(
            [$this->line($product, '2.00', '50.00', $case)],
            '200.00',
            '50.00'
        );

        $this->assertSame('-140.00', $sale->items()->sole()->profit);
    }

    public function test_profit_guard_uses_corrected_base_cost_profit(): void
    {
        $product = $this->product('Guard pass', '100.0000');
        $case = $this->unit($product, 'case', '24.0000');

        $sale = $this->deliverySale(
            [$this->line($product, '2.00', '180.00', $case)],
            '20.00',
            '140.00'
        );

        $this->assertSame('120.00', $sale->items()->sole()->profit);
    }

    public function test_profit_guard_rejects_legacy_false_pass_and_rolls_back_everything(): void
    {
        $product = $this->product('Guard rollback', '100.0000');
        $case = $this->unit($product, 'case', '24.0000');

        $this->expectDomainFailure(fn () => $this->deliverySale(
            [$this->line($product, '2.00', '180.00', $case)],
            '20.00',
            '300.00'
        ));

        $this->assertSame(0, Sale::count());
        $this->assertSame(0, DB::table('sale_items')->count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, DB::table('technician_commissions')->count());
        $this->assertSame('100.0000', $product->fresh()->stock_qty);
        $this->assertSame(0, DB::table('sale_number_counters')->count());
    }

    public function test_resolved_lines_preserve_order_source_and_aggregate_mixed_units(): void
    {
        $product = $this->product('Resolved contract', '100.0000');
        $case = $this->unit($product, 'case', '24.0000');
        $service = app(ProductUnitConversionService::class);

        $lines = $service->resolveLines([
            $this->line($product, '2.00', '180.00', $case),
            $this->line($product, '5.00', '10.00'),
        ], collect([$product->id => $product]));

        $this->assertSame([0, 1], array_map(
            fn (ResolvedSaleLine $line): int => $line->originalIndex,
            $lines
        ));
        $this->assertSame([
            ResolvedSaleLine::SOURCE_CURRENT_PRODUCT_UNIT,
            ResolvedSaleLine::SOURCE_LEGACY_FACTOR_ONE,
        ], array_map(
            fn (ResolvedSaleLine $line): string => $line->resolutionSource,
            $lines
        ));
        $this->assertSame(['2.00', '5.00'], array_map(
            fn (ResolvedSaleLine $line): string => $line->saleQty,
            $lines
        ));
        $this->assertSame([$product->id => '53.0000'], $service
            ->aggregateBaseQuantityByProduct($lines));

        $sale = $this->sale([
            $this->line($product, '2.00', '180.00', $case),
            $this->line($product, '5.00', '10.00'),
        ]);

        $this->assertSame(['2.00', '5.00'], $sale->items()
            ->orderBy('id')->pluck('qty')->all());
        $this->assertSame('47.0000', $product->fresh()->stock_qty);
    }

    public function test_browser_supplied_conversion_values_and_unit_snapshots_are_ignored(): void
    {
        $product = $this->product('Backend authority', '50.0000');
        $case = $this->unit($product, 'case', '24.0000');
        $line = array_merge($this->line($product, '1.00', '130.00', $case), [
            'conversion_rate_used' => '2.0000',
            'base_qty' => '2.0000',
            'unit_name_snapshot' => 'forged',
            'unit_code_snapshot' => 'forged',
        ]);

        $item = $this->sale([$line])->items()->sole();

        $this->assertSame('24.0000', $item->conversion_rate_used);
        $this->assertSame('24.0000', $item->base_qty);
        $this->assertSame('case', $item->unit_name_snapshot);
        $this->assertSame('case', $item->unit_code_snapshot);
        $this->assertSame('26.0000', $product->fresh()->stock_qty);
    }

    public function test_sale_quantity_beyond_two_decimal_places_is_rejected_not_rounded(): void
    {
        $product = $this->product('Strict sale precision', '10.0000');

        $this->expectDomainFailure(fn () => $this->sale([
            $this->line($product, '1.005', '10.00'),
        ]));

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame('10.0000', $product->fresh()->stock_qty);
    }

    public function test_base_quantity_beyond_numeric_precision_is_rejected_not_truncated(): void
    {
        $this->expectException(DomainException::class);

        app(ProductUnitConversionService::class)
            ->calculateBaseQuantity('9999999999999.99', '1000.0000');
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
        $sale = $this->sale([$this->line($product, '1.00', '70.00', $unit->fresh())]);

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
        $sale = $this->sale([$this->line($product, '2.00', '70.00', $unit)]);

        $unit->update(['conversion_rate' => '99.0000', 'conversion_confirmed_at' => null]);
        app(SaleService::class)->deleteSale($sale);

        $this->assertSame('50.0000', $product->fresh()->stock_qty);
        $this->assertSame('24.0000', StockMovement::where('type', 'IN')->sole()->qty);
    }

    public function test_update_restores_old_snapshot_after_confirmation_is_cleared(): void
    {
        $product = $this->product('Snapshot update', '50.0000');
        $unit = $this->unit($product, 'dozen', '12.0000');
        $sale = $this->sale([$this->line($product, '2.00', '70.00', $unit)]);
        $oldItem = $sale->items()->sole();
        $unit->update(['conversion_rate' => '99.0000', 'conversion_confirmed_at' => null]);

        app(SaleService::class)->updateSale($sale, [
            'customer_id' => null,
            'sale_date' => '2026-07-14',
            'items' => [[
                'sale_item_id' => $oldItem->id,
                'product_id' => $product->id,
                'product_unit_id' => null,
                'qty' => '1.00',
                'selling_price' => '10.00',
            ]],
            'delivery_fee' => 0,
            'discount' => 0,
        ], (int) $sale->fresh()->revision);

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
            'items' => [[
                'sale_item_id' => $oldItem->id,
                'product_id' => $product->id,
                'product_unit_id' => $wrongUnit->id,
                'qty' => '1.00',
                'selling_price' => '10.00',
            ]],
            'delivery_fee' => 0,
            'discount' => 0,
        ], (int) $sale->fresh()->revision));

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

    public function test_legacy_item_with_product_unit_but_no_snapshot_blocks_delete(): void
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

        $this->expectDomainFailure(fn () => app(SaleService::class)->deleteSale($sale));

        $this->assertSame('8.0000', $product->fresh()->stock_qty);
        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_update_of_legacy_item_with_unit_and_no_snapshot_is_blocked(): void
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

        $this->expectDomainFailure(fn () => app(SaleService::class)->updateSale($sale, [
            'customer_id' => null,
            'sale_date' => '2026-07-14',
            'items' => [[
                'sale_item_id' => $legacyItem->id,
                'product_id' => $product->id,
                'product_unit_id' => null,
                'qty' => '1.00',
                'selling_price' => '10.00',
            ]],
            'delivery_fee' => 0,
            'discount' => 0,
        ], (int) $sale->fresh()->revision));

        $this->assertSame('8.0000', $product->fresh()->stock_qty);
        $this->assertNull($sale->fresh()->items()->sole()->base_qty);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_converted_quantity_update_preserves_item_identity_cost_and_uses_base_profit(): void
    {
        $product = $this->product('Converted update', '100.0000');
        $case = $this->unit($product, 'case', '24.0000');
        $sale = $this->sale([$this->line($product, '2.00', '180.00', $case)]);
        $item = $sale->items()->sole();
        $product->update(['cost_price' => '9.00']);

        app(SaleService::class)->updateSale($sale, $this->updatePayload($sale, [[
            'sale_item_id' => $item->id,
            'product_id' => $product->id,
            'product_unit_id' => $case->id,
            'qty' => '1.00',
            'selling_price' => '180.00',
        ]]), (int) $sale->fresh()->revision);

        $updated = $sale->fresh()->items()->sole();
        $this->assertSame($item->id, $updated->id);
        $this->assertSame('24.0000', $updated->base_qty);
        $this->assertSame('5.00', $updated->cost_price);
        $this->assertSame('180.00', $updated->total);
        $this->assertSame('60.00', $updated->profit);
        $this->assertSame('76.0000', $product->fresh()->stock_qty);
        $this->assertSame(['48.0000', '24.0000'], StockMovement::where('reference_type', 'sale_edit')->pluck('qty')->all());
    }

    public function test_price_only_update_preserves_original_quantity_and_cost_snapshots(): void
    {
        $product = $this->product('Price-only update', '100.0000');
        $case = $this->unit($product, 'case', '24.0000');
        $sale = $this->sale([$this->line($product, '2.00', '180.00', $case)]);
        $item = $sale->items()->sole();
        $product->update(['cost_price' => '9.00']);
        $case->update(['conversion_rate' => '12.0000']);

        app(SaleService::class)->updateSale($sale, $this->updatePayload($sale, [[
            'sale_item_id' => $item->id,
            'product_id' => $product->id,
            'product_unit_id' => $case->id,
            'qty' => '2.00',
            'selling_price' => '200.00',
        ]]), (int) $sale->fresh()->revision);

        $updated = $sale->fresh()->items()->sole();
        $this->assertSame($item->id, $updated->id);
        $this->assertSame('24.0000', $updated->conversion_rate_used);
        $this->assertSame('48.0000', $updated->base_qty);
        $this->assertSame('5.00', $updated->cost_price);
        $this->assertSame('160.00', $updated->profit);
    }

    public function test_unit_change_preserves_cost_but_refreshes_quantity_and_text_snapshots(): void
    {
        $product = $this->product('Unit update', '100.0000');
        $case = $this->unit($product, 'case', '24.0000');
        $dozen = $this->unit($product, 'dozen', '12.0000');
        $sale = $this->sale([$this->line($product, '1.00', '180.00', $case)]);
        $item = $sale->items()->sole();
        $product->update(['cost_price' => '9.00']);

        app(SaleService::class)->updateSale($sale, $this->updatePayload($sale, [[
            'sale_item_id' => $item->id,
            'product_id' => $product->id,
            'product_unit_id' => $dozen->id,
            'qty' => '1.00',
            'selling_price' => '180.00',
        ]]), (int) $sale->fresh()->revision);

        $updated = $sale->fresh()->items()->sole();
        $this->assertSame($item->id, $updated->id);
        $this->assertSame('5.00', $updated->cost_price);
        $this->assertSame('12.0000', $updated->base_qty);
        $this->assertSame('dozen', $updated->unit_name_snapshot);
        $this->assertSame('120.00', $updated->profit);
    }

    public function test_product_replacement_uses_current_replacement_cost_and_retains_item_id(): void
    {
        $oldProduct = $this->product('Old product', '10.0000');
        $replacement = $this->product('Replacement product', '10.0000');
        $replacement->update(['cost_price' => '7.00']);
        $sale = $this->sale([$this->line($oldProduct, '2.00', '10.00')]);
        $item = $sale->items()->sole();

        app(SaleService::class)->updateSale($sale, $this->updatePayload($sale, [[
            'sale_item_id' => $item->id,
            'product_id' => $replacement->id,
            'product_unit_id' => null,
            'qty' => '2.00',
            'selling_price' => '10.00',
        ]]), (int) $sale->fresh()->revision);

        $updated = $sale->fresh()->items()->sole();
        $this->assertSame($item->id, $updated->id);
        $this->assertSame($replacement->id, $updated->product_id);
        $this->assertSame('7.00', $updated->cost_price);
        $this->assertSame('6.00', $updated->profit);
        $this->assertSame('10.0000', $oldProduct->fresh()->stock_qty);
        $this->assertSame('8.0000', $replacement->fresh()->stock_qty);
    }

    public function test_ambiguous_converted_historical_item_blocks_update_and_delete(): void
    {
        $product = $this->product('Ambiguous legacy', '8.0000');
        $unit = $this->unit($product, 'case', '12.0000');
        $sale = Sale::create([
            'sale_no' => 'SAL-AMBIGUOUS-1',
            'sale_date' => '2026-07-14',
            'total_amount' => '20.00',
            'delivery_type' => 'pickup',
        ]);
        $item = $sale->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'qty' => '2.00',
            'selling_price' => '10.00',
            'cost_price' => '5.00',
            'total' => '20.00',
            'profit' => '10.00',
        ]);

        $this->expectDomainFailure(fn () => app(SaleService::class)->updateSale(
            $sale,
            $this->updatePayload($sale, [[
                'sale_item_id' => $item->id,
                'product_id' => $product->id,
                'product_unit_id' => $unit->id,
                'qty' => '3.00',
                'selling_price' => '10.00',
            ]]),
            (int) $sale->fresh()->revision
        ));
        $this->expectDomainFailure(fn () => app(SaleService::class)->deleteSale($sale));

        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
        $this->assertDatabaseHas('sale_items', ['id' => $item->id]);
        $this->assertSame('8.0000', $product->fresh()->stock_qty);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_update_profit_guard_uses_canonical_base_profit_and_rolls_back(): void
    {
        $product = $this->product('Update guard', '100.0000');
        $case = $this->unit($product, 'case', '24.0000');
        $sale = $this->deliverySale([$this->line($product, '2.00', '180.00', $case)], '20.00', '140.00');
        $item = $sale->items()->sole();
        $beforeMovements = StockMovement::count();
        $beforeRevision = $sale->fresh()->revision;

        $this->expectDomainFailure(fn () => app(SaleService::class)->updateSale(
            $sale,
            $this->updatePayload($sale, [[
                'sale_item_id' => $item->id,
                'product_id' => $product->id,
                'product_unit_id' => $case->id,
                'qty' => '2.00',
                'selling_price' => '100.00',
            ]]),
            (int) $sale->fresh()->revision
        ));

        $this->assertSame('180.00', $item->fresh()->selling_price);
        $this->assertSame('120.00', $item->fresh()->profit);
        $this->assertSame('52.0000', $product->fresh()->stock_qty);
        $this->assertSame($beforeMovements, StockMovement::count());
        $this->assertSame($beforeRevision, $sale->fresh()->revision);
    }

    public function test_update_adds_and_removes_lines_selectively_and_preserves_retained_identity(): void
    {
        $first = $this->product('Retained product', '100.0000');
        $case = $this->unit($first, 'case', '24.0000');
        $removed = $this->product('Removed product', '20.0000');
        $added = $this->product('Added product', '20.0000');
        $added->update(['cost_price' => '7.00']);
        $sale = $this->sale([
            $this->line($first, '1.00', '180.00', $case),
            $this->line($removed, '2.00', '10.00'),
        ]);
        $items = $sale->items()->orderBy('id')->get();

        app(SaleService::class)->updateSale($sale, $this->updatePayload($sale, [[
            'sale_item_id' => $items[0]->id,
            'product_id' => $first->id,
            'product_unit_id' => $case->id,
            'qty' => '2.00',
            'selling_price' => '180.00',
        ], [
            'sale_item_id' => null,
            'product_id' => $added->id,
            'product_unit_id' => null,
            'qty' => '3.00',
            'selling_price' => '10.00',
        ]]), (int) $sale->fresh()->revision);

        $updated = $sale->fresh()->items()->orderBy('id')->get();
        $this->assertCount(2, $updated);
        $this->assertSame($items[0]->id, $updated[0]->id);
        $this->assertDatabaseMissing('sale_items', ['id' => $items[1]->id]);
        $this->assertSame('5.00', $updated[0]->cost_price);
        $this->assertSame('7.00', $updated[1]->cost_price);
        $this->assertSame('52.0000', $first->fresh()->stock_qty);
        $this->assertSame('20.0000', $removed->fresh()->stock_qty);
        $this->assertSame('17.0000', $added->fresh()->stock_qty);
    }

    public function test_update_aggregates_mixed_units_for_the_same_product(): void
    {
        $product = $this->product('Mixed update product', '100.0000');
        $case = $this->unit($product, 'case', '24.0000');
        $sale = $this->sale([
            $this->line($product, '1.00', '180.00', $case),
            $this->line($product, '2.00', '10.00'),
        ]);
        $items = $sale->items()->orderBy('id')->get();

        app(SaleService::class)->updateSale($sale, $this->updatePayload($sale, [[
            'sale_item_id' => $items[0]->id,
            'product_id' => $product->id,
            'product_unit_id' => $case->id,
            'qty' => '2.00',
            'selling_price' => '180.00',
        ], [
            'sale_item_id' => $items[1]->id,
            'product_id' => $product->id,
            'product_unit_id' => null,
            'qty' => '5.00',
            'selling_price' => '10.00',
        ]]), (int) $sale->fresh()->revision);

        $this->assertSame('47.0000', $product->fresh()->stock_qty);
        $this->assertSame(
            ['24.0000', '2.0000', '48.0000', '5.0000'],
            StockMovement::where('reference_type', 'sale_edit')->pluck('qty')->all()
        );
        $this->assertSame(
            $items->pluck('id')->all(),
            $sale->fresh()->items()->orderBy('id')->pluck('id')->all()
        );
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
            'code' => $name,
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
        $total = app(SaleDecimalService::class)->itemsTotal($items);

        return app(SaleService::class)->createSale([
            'sale_date' => '2026-07-14',
            'grand_total' => collect($items)->sum(fn (array $item) => (float) $item['qty'] * (float) $item['selling_price']),
            'delivery_type' => 'pickup',
            'discount' => 0,
            'payment_method' => 'promptpay',
            'cash_amount' => '0.00',
            'promptpay_amount' => $total,
            'received_amount' => '0.00',
            'items' => $items,
        ]);
    }

    private function updatePayload(Sale $sale, array $items): array
    {
        return [
            'customer_id' => $sale->customer_id,
            'sale_date' => $sale->sale_date,
            'delivery_fee' => $sale->delivery_fee,
            'discount' => $sale->discount,
            'items' => $items,
        ];
    }

    private function deliverySale(array $items, string $deliveryFee, string $minimumProfit): Sale
    {
        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Synthetic customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $zoneId = DB::table('delivery_zones')->insertGetId([
            'name' => 'Synthetic zone',
            'base_delivery_fee' => $deliveryFee,
            'minimum_profit' => $minimumProfit,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $addressId = DB::table('customer_delivery_addresses')->insertGetId([
            'customer_id' => $customerId,
            'delivery_zone_id' => $zoneId,
            'name' => 'Synthetic address',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $total = app(SaleDecimalService::class)->netTotal(
            app(SaleDecimalService::class)->itemsTotal($items),
            $deliveryFee,
            '0.00'
        );

        return app(SaleService::class)->createSale([
            'customer_id' => $customerId,
            'customer_delivery_address_id' => $addressId,
            'sale_date' => '2026-07-14',
            'delivery_type' => 'delivery',
            'discount' => 0,
            'payment_method' => 'promptpay',
            'cash_amount' => '0.00',
            'promptpay_amount' => $total,
            'received_amount' => '0.00',
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
