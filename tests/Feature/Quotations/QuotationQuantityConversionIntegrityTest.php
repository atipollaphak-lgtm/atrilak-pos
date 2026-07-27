<?php

namespace Tests\Feature\Quotations;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Quotation;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\SaleService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationQuantityConversionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_converted_contract_uses_stored_base_quantity_and_snapshots(): void
    {
        [$product, $productUnit] = $this->productAndUnit('24.0000', stock: '100.0000');
        $quotation = $this->quotation($product, [
            'product_unit_id' => $productUnit->id,
            'qty' => '2.00',
            'conversion_rate_used' => '24.0000',
            'base_qty' => '48.0000',
            'selling_price' => '180.00',
            'total' => '360.00',
            'unit_name_snapshot' => 'Historical Case',
            'unit_code_snapshot' => 'HCASE',
        ], '360.00');

        $service = app(SaleService::class);
        $sale = $service->createSaleFromQuotation($quotation);
        $replayed = $service->createSaleFromQuotation($quotation);
        $item = $sale->items()->sole();

        $this->assertSame($sale->id, $replayed->id);
        $this->assertSame('2.00', $item->qty);
        $this->assertSame('24.0000', $item->conversion_rate_used);
        $this->assertSame('48.0000', $item->base_qty);
        $this->assertSame('180.00', $item->selling_price);
        $this->assertSame('5.00', $item->cost_price);
        $this->assertSame('Historical Case', $item->unit_name_snapshot);
        $this->assertSame('HCASE', $item->unit_code_snapshot);
        $this->assertSame('52.0000', $product->fresh()->stock_qty);
        $this->assertSame('48.0000', StockMovement::query()->sole()->qty);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('technician_commissions', 0);
        $this->assertDatabaseHas('quotation_items', [
            'id' => $quotation->items()->sole()->id,
            'conversion_rate_used' => '24.0000',
            'base_qty' => '48.0000',
            'unit_name_snapshot' => 'Historical Case',
            'unit_code_snapshot' => 'HCASE',
        ]);
    }

    public function test_current_unit_rate_and_inactive_state_do_not_change_complete_stored_contract(): void
    {
        [$product, $productUnit] = $this->productAndUnit('30.0000', stock: '100.0000', activeUnit: false);
        $quotation = $this->quotation($product, [
            'product_unit_id' => $productUnit->id,
            'qty' => '2.00',
            'conversion_rate_used' => '24.0000',
            'base_qty' => '48.0000',
            'selling_price' => '180.00',
            'total' => '360.00',
            'unit_name_snapshot' => 'Stored Case',
            'unit_code_snapshot' => 'STORED',
        ], '360.00');

        $item = app(SaleService::class)
            ->createSaleFromQuotation($quotation)
            ->items()->sole();

        $this->assertSame('24.0000', $item->conversion_rate_used);
        $this->assertSame('48.0000', $item->base_qty);
        $this->assertSame('Stored Case', $item->unit_name_snapshot);
        $this->assertSame('52.0000', $product->fresh()->stock_qty);
    }

    public function test_deleted_unit_with_complete_contract_still_converts_from_stored_snapshots(): void
    {
        [$product, $productUnit] = $this->productAndUnit('24.0000', stock: '100.0000');
        $quotation = $this->quotation($product, [
            'product_unit_id' => $productUnit->id,
            'qty' => '2.00',
            'conversion_rate_used' => '24.0000',
            'base_qty' => '48.0000',
            'selling_price' => '180.00',
            'total' => '360.00',
            'unit_name_snapshot' => 'Deleted Case',
            'unit_code_snapshot' => 'DEL',
        ], '360.00');
        $productUnit->delete();

        $item = app(SaleService::class)
            ->createSaleFromQuotation($quotation)
            ->items()->sole();

        $this->assertNull($item->product_unit_id);
        $this->assertSame('24.0000', $item->conversion_rate_used);
        $this->assertSame('48.0000', $item->base_qty);
        $this->assertSame('Deleted Case', $item->unit_name_snapshot);
        $this->assertSame('DEL', $item->unit_code_snapshot);
    }

    public function test_missing_rate_is_recovered_only_when_it_exactly_reproduces_stored_base_quantity(): void
    {
        [$product, $productUnit] = $this->productAndUnit('99.0000', stock: '100.0000');
        $quotation = $this->quotation($product, [
            'product_unit_id' => $productUnit->id,
            'qty' => '2.00',
            'conversion_rate_used' => null,
            'base_qty' => '48.0000',
            'selling_price' => '180.00',
            'total' => '360.00',
        ], '360.00');

        $item = app(SaleService::class)
            ->createSaleFromQuotation($quotation)
            ->items()->sole();

        $this->assertSame('24.0000', $item->conversion_rate_used);
        $this->assertSame('48.0000', $item->base_qty);
    }

    public function test_non_reproducible_implied_rate_blocks_without_writes(): void
    {
        [$product, $productUnit] = $this->productAndUnit('24.0000');
        $quotation = $this->quotation($product, [
            'product_unit_id' => $productUnit->id,
            'qty' => '3.00',
            'conversion_rate_used' => null,
            'base_qty' => '1.0000',
            'selling_price' => '10.00',
            'total' => '30.00',
        ], '30.00');

        $this->assertConversionBlocked($quotation);
    }

    public function test_ambiguous_and_inconsistent_contracts_are_blocked(): void
    {
        [$product, $productUnit] = $this->productAndUnit('24.0000');
        [, $otherUnit] = $this->productAndUnit('12.0000');

        $cases = [
            ['product_unit_id' => $productUnit->id, 'conversion_rate_used' => null, 'base_qty' => null],
            ['product_unit_id' => null, 'conversion_rate_used' => '24.0000', 'base_qty' => null],
            ['product_unit_id' => $productUnit->id, 'conversion_rate_used' => '24.0000', 'base_qty' => '47.0000'],
            ['product_unit_id' => $otherUnit->id, 'conversion_rate_used' => '12.0000', 'base_qty' => '24.0000'],
        ];

        foreach ($cases as $index => $contract) {
            $quotation = $this->quotation($product, array_merge([
                'qty' => '2.00',
                'selling_price' => '10.00',
                'total' => '20.00',
            ], $contract), '20.00', 'QT-BLOCK-'.$index);

            $this->assertConversionBlocked($quotation);
        }
    }

    public function test_legacy_all_null_and_explicit_rate_one_rows_convert_as_factor_one(): void
    {
        foreach ([null, '1.0000'] as $index => $rate) {
            [$product] = $this->productAndUnit('24.0000', stock: '10.0000');
            $quotation = $this->quotation($product, [
                'product_unit_id' => null,
                'qty' => '2.00',
                'conversion_rate_used' => $rate,
                'base_qty' => null,
                'selling_price' => '10.00',
                'total' => '20.00',
            ], '20.00', 'QT-LEGACY-'.$index);

            $item = app(SaleService::class)
                ->createSaleFromQuotation($quotation)
                ->items()->sole();

            $this->assertSame('1.0000', $item->conversion_rate_used);
            $this->assertSame('2.0000', $item->base_qty);
            $this->assertSame('piece', $item->unit_name_snapshot);
            $this->assertSame('8.0000', $product->fresh()->stock_qty);
        }
    }

    public function test_missing_or_inactive_product_blocks_atomically(): void
    {
        [$inactive] = $this->productAndUnit('1.0000', activeProduct: false);
        $inactiveQuotation = $this->quotation($inactive, [
            'qty' => '1.00',
            'selling_price' => '10.00',
            'total' => '10.00',
        ], '10.00', 'QT-INACTIVE');
        $this->assertConversionBlocked($inactiveQuotation);

        [$deleted] = $this->productAndUnit('1.0000');
        $missingQuotation = $this->quotation($deleted, [
            'qty' => '1.00',
            'selling_price' => '10.00',
            'total' => '10.00',
        ], '10.00', 'QT-MISSING');
        $deleted->delete();
        $this->assertConversionBlocked($missingQuotation);
    }

    public function test_header_total_mismatch_blocks_without_mutating_quotation_or_stock(): void
    {
        [$product] = $this->productAndUnit('1.0000', stock: '10.0000');
        $quotation = $this->quotation($product, [
            'qty' => '2.00',
            'selling_price' => '30.00',
            'total' => '60.00',
        ], '99.00');

        $this->assertConversionBlocked($quotation);
        $this->assertSame('10.0000', $product->fresh()->stock_qty);
    }

    public function test_stored_item_total_mismatch_blocks_without_silent_repair(): void
    {
        [$product] = $this->productAndUnit('1.0000');
        $quotation = $this->quotation($product, [
            'qty' => '2.00',
            'selling_price' => '30.00',
            'total' => '59.00',
        ], '60.00');

        $this->assertConversionBlocked($quotation);
        $this->assertSame('59.00', $quotation->items()->sole()->total);
        $this->assertEquals('60.00', $quotation->fresh()->total_amount);
    }

    public function test_mixed_contract_lines_aggregate_base_stock_and_preserve_id_order(): void
    {
        [$product, $productUnit] = $this->productAndUnit('24.0000', stock: '60.0000');
        $quotation = Quotation::query()->create([
            'quotation_no' => 'QT-MIXED',
            'quotation_date' => '2026-07-16',
            'total_amount' => '410.00',
            'status' => 'draft',
        ]);
        $quotation->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $productUnit->id,
            'qty' => '2.00',
            'conversion_rate_used' => '24.0000',
            'base_qty' => '48.0000',
            'selling_price' => '180.00',
            'total' => '360.00',
            'unit_name_snapshot' => 'Case',
        ]);
        $quotation->items()->create([
            'product_id' => $product->id,
            'qty' => '5.00',
            'selling_price' => '10.00',
            'total' => '50.00',
        ]);

        $sale = app(SaleService::class)->createSaleFromQuotation($quotation);

        $this->assertSame(['2.00', '5.00'], $sale->items()->orderBy('id')->pluck('qty')->all());
        $this->assertSame(['48.0000', '5.0000'], $sale->items()->orderBy('id')->pluck('base_qty')->all());
        $this->assertSame('7.0000', $product->fresh()->stock_qty);
        $this->assertSame(['48.0000', '5.0000'], StockMovement::query()->orderBy('id')->pluck('qty')->all());
    }

    public function test_insufficient_aggregate_base_stock_rolls_back_every_conversion_write(): void
    {
        [$product, $productUnit] = $this->productAndUnit('24.0000', stock: '52.0000');
        $quotation = Quotation::query()->create([
            'quotation_no' => 'QT-INSUFFICIENT-AGGREGATE',
            'quotation_date' => '2026-07-16',
            'total_amount' => '410.00',
            'status' => 'draft',
        ]);
        $quotation->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $productUnit->id,
            'qty' => '2.00',
            'conversion_rate_used' => '24.0000',
            'base_qty' => '48.0000',
            'selling_price' => '180.00',
            'total' => '360.00',
        ]);
        $quotation->items()->create([
            'product_id' => $product->id,
            'qty' => '5.00',
            'selling_price' => '10.00',
            'total' => '50.00',
        ]);

        $this->assertConversionBlocked($quotation);
        $this->assertSame('52.0000', $product->fresh()->stock_qty);
    }

    private function assertConversionBlocked(Quotation $quotation): void
    {
        try {
            app(SaleService::class)->createSaleFromQuotation($quotation);
            $this->fail('Expected quotation conversion to be rejected.');
        } catch (DomainException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }

        $this->assertSame('draft', $quotation->fresh()->status);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('technician_commissions', 0);
        $this->assertDatabaseCount('sale_number_counters', 0);
    }

    private function quotation(
        Product $product,
        array $item,
        string $headerTotal,
        ?string $number = null
    ): Quotation {
        $quotation = Quotation::query()->create([
            'quotation_no' => $number ?? 'QT-'.uniqid(),
            'quotation_date' => '2026-07-16',
            'total_amount' => $headerTotal,
            'status' => 'draft',
        ]);
        $quotation->items()->create(array_merge([
            'product_id' => $product->id,
            'product_unit_id' => null,
            'conversion_rate_used' => null,
            'base_qty' => null,
            'unit_name_snapshot' => null,
            'unit_code_snapshot' => null,
        ], $item));

        return $quotation;
    }

    private function productAndUnit(
        string $currentRate,
        string $stock = '100.0000',
        bool $activeProduct = true,
        bool $activeUnit = true
    ): array {
        $category = Category::query()->create([
            'name' => 'Quotation quantity category '.uniqid(),
            'active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Quotation quantity product '.uniqid(),
            'unit' => 'piece',
            'cost_price' => '5.00',
            'selling_price' => '10.00',
            'stock_qty' => $stock,
            'minimum_stock' => '0.0000',
            'active' => $activeProduct,
        ]);
        $unitCode = 'CASE-'.uniqid();
        $unit = Unit::query()->create([
            'name' => 'Current Case',
            'short_name' => 'Case',
            'code' => $unitCode,
        ]);
        $productUnit = ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_rate' => $currentRate,
            'is_sale_unit' => true,
            'active' => $activeUnit,
            'conversion_confirmed_at' => now(),
        ]);

        return [$product, $productUnit];
    }
}
