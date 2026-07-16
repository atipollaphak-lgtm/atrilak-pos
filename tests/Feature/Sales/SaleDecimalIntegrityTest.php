<?php

namespace Tests\Feature\Sales;

use App\Models\Product;
use App\Models\StockMovement;
use App\Services\SaleService;
use Brick\Math\BigDecimal;
use Tests\Support\CreatesSaleTransactionTestSchema;
use Tests\TestCase;

class SaleDecimalIntegrityTest extends TestCase
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

    public function test_persisted_header_equals_rounded_duplicate_and_mixed_line_totals(): void
    {
        $product = Product::create([
            'name' => 'Decimal sale product',
            'cost_price' => '0.10',
            'selling_price' => '0.50',
            'stock_qty' => '1.0000',
        ]);

        $sale = app(SaleService::class)->createSale([
            'sale_date' => '2026-07-15',
            'delivery_type' => 'pickup',
            'discount' => '0.01',
            'payment_method' => 'promptpay',
            'cash_amount' => '0.00',
            'promptpay_amount' => '0.02',
            'received_amount' => '0.00',
            'items' => [
                [
                    'product_id' => $product->id,
                    'qty' => '0.01',
                    'selling_price' => '0.50',
                ],
                [
                    'product_id' => $product->id,
                    'qty' => '0.03',
                    'selling_price' => '0.50',
                ],
            ],
        ])->fresh('items');

        $this->assertSame(['0.01', '0.02'], $sale->items->pluck('total')->all());
        $lineTotal = $sale->items->reduce(
            fn (BigDecimal $carry, $item): BigDecimal => $carry->plus($item->total),
            BigDecimal::zero()->toScale(2)
        );
        $this->assertSame('0.03', (string) $lineTotal);
        $this->assertSame('0.02', (string) $sale->total_amount);
        $this->assertSame(['0.01', '0.02'], $sale->items->pluck('profit')->all());
    }

    public function test_decimal_base_quantity_and_movement_chain_remain_at_scale_four(): void
    {
        $product = Product::create([
            'name' => 'Decimal movement product',
            'cost_price' => '1.00',
            'selling_price' => '2.00',
            'stock_qty' => '1.0000',
        ]);

        $sale = app(SaleService::class)->createSale([
            'sale_date' => '2026-07-15',
            'delivery_type' => 'pickup',
            'payment_method' => 'promptpay',
            'cash_amount' => '0.00',
            'promptpay_amount' => '0.06',
            'received_amount' => '0.00',
            'items' => [
                [
                    'product_id' => $product->id,
                    'qty' => '0.01',
                    'selling_price' => '2.00',
                ],
                [
                    'product_id' => $product->id,
                    'qty' => '0.02',
                    'selling_price' => '2.00',
                ],
            ],
        ]);

        $this->assertSame(['0.0100', '0.0200'], $sale->items()->pluck('base_qty')->all());

        $movements = StockMovement::query()->orderBy('id')->get();
        $this->assertSame(['0.0100', '0.0200'], $movements->pluck('qty')->all());
        $this->assertSame(['1.0000', '0.9900'], $movements->pluck('stock_before')->all());
        $this->assertSame(['0.9900', '0.9700'], $movements->pluck('stock_after')->all());
        $this->assertSame('0.9700', $product->fresh()->stock_qty);
    }

    public function test_decimal_update_and_delete_keep_totals_and_stock_reversible(): void
    {
        $product = Product::create([
            'name' => 'Decimal update product',
            'cost_price' => '0.10',
            'selling_price' => '0.50',
            'stock_qty' => '1.0000',
        ]);
        $service = app(SaleService::class);
        $sale = $service->createSale([
            'sale_date' => '2026-07-15',
            'delivery_type' => 'pickup',
            'payment_method' => 'promptpay',
            'cash_amount' => '0.00',
            'promptpay_amount' => '0.05',
            'received_amount' => '0.00',
            'items' => [[
                'product_id' => $product->id,
                'qty' => '0.10',
                'selling_price' => '0.50',
            ]],
        ]);

        $service->updateSale($sale, [
            'customer_id' => null,
            'sale_date' => '2026-07-15',
            'delivery_fee' => '0.02',
            'discount' => '0.01',
            'items' => [
                [
                    'product_id' => $product->id,
                    'qty' => '0.01',
                    'selling_price' => '0.50',
                ],
                [
                    'product_id' => $product->id,
                    'qty' => '0.03',
                    'selling_price' => '0.50',
                ],
            ],
        ], (int) $sale->fresh()->revision);

        $updated = $sale->fresh('items');
        $this->assertSame(['0.01', '0.02'], $updated->items->pluck('total')->all());
        $this->assertSame('0.04', (string) $updated->total_amount);
        $this->assertSame('0.9600', $product->fresh()->stock_qty);

        $service->deleteSale($updated);

        $this->assertSame('1.0000', $product->fresh()->stock_qty);
        $this->assertSame('1.0000', StockMovement::query()->latest('id')->value('stock_after'));
    }
}
