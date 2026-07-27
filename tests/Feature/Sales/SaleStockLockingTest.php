<?php

namespace Tests\Feature\Sales;

use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Sales\SaleDecimalService;
use App\Services\SaleService;
use DomainException;
use Tests\Support\CreatesSaleTransactionTestSchema;
use Tests\TestCase;

class SaleStockLockingTest extends TestCase
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

    public function test_duplicate_product_lines_with_insufficient_combined_stock_roll_back(): void
    {
        $product = $this->createProduct(5);

        try {
            app(SaleService::class)->createSale($this->createData([
                $this->line($product, 3, 10),
                $this->line($product, 3, 20),
            ]));
            $this->fail('Expected insufficient combined stock failure.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString($product->name, $exception->getMessage());
        }

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertEquals(5.0000, $product->fresh()->stock_qty);
    }

    public function test_duplicate_product_lines_keep_prices_items_totals_and_continuous_movements(): void
    {
        $product = $this->createProduct(10);

        $sale = app(SaleService::class)->createSale($this->createData([
            $this->line($product, 3, 10),
            $this->line($product, 2, 20),
        ]));

        $items = $sale->items()->orderBy('id')->get();
        $movements = StockMovement::orderBy('id')->get();

        $this->assertCount(2, $items);
        $this->assertEquals([10.00, 20.00], $items->pluck('selling_price')->map(fn ($value) => (float) $value)->all());
        $this->assertEquals([30.00, 40.00], $items->pluck('total')->map(fn ($value) => (float) $value)->all());
        $this->assertEquals(70.00, $sale->total_amount);
        $this->assertEquals(5.0000, $product->fresh()->stock_qty);
        $this->assertCount(2, $movements);
        $this->assertEquals([[10, 7], [7, 5]], $movements->map(fn ($movement) => [
            (float) $movement->stock_before,
            (float) $movement->stock_after,
        ])->all());
    }

    private function createProduct(float $stock): Product
    {
        return Product::create([
            'name' => 'Locking product',
            'cost_price' => 5,
            'selling_price' => 10,
            'stock_qty' => $stock,
        ]);
    }

    private function line(Product $product, float $qty, float $price): array
    {
        return [
            'product_id' => $product->id,
            'qty' => $qty,
            'selling_price' => $price,
        ];
    }

    private function createData(array $items): array
    {
        $total = app(SaleDecimalService::class)->itemsTotal($items);

        return [
            'sale_date' => '2026-07-13',
            'grand_total' => collect($items)->sum(fn (array $item) => $item['qty'] * $item['selling_price']),
            'delivery_type' => 'pickup',
            'discount' => 0,
            'payment_method' => 'promptpay',
            'cash_amount' => '0.00',
            'promptpay_amount' => $total,
            'received_amount' => '0.00',
            'items' => $items,
        ];
    }
}
