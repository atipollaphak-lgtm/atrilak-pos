<?php

namespace Tests\Feature\Sales;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\SaleService;
use Tests\Support\CreatesSaleTransactionTestSchema;
use Tests\TestCase;

class SaleSnapshotPreservingUpdateTest extends TestCase
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

    public function test_header_only_update_keeps_sale_items_and_all_snapshots(): void
    {
        [$sale, $product, $productUnit, $item] = $this->existingSale();
        $beforeItem = $item->only([
            'id',
            'product_id',
            'product_unit_id',
            'conversion_rate_used',
            'base_qty',
            'qty',
            'selling_price',
            'cost_price',
            'total',
            'profit',
        ]);
        $beforeMovements = StockMovement::count();
        $beforeStock = $product->stock_qty;

        $product->update(['cost_price' => '50.00']);
        $productUnit->update([
            'conversion_rate' => '99.0000',
            'conversion_confirmed_at' => null,
        ]);

        $updated = app(SaleService::class)->updateSale($sale, $this->updateData($sale, [
            'customer_id' => 321,
            'sale_date' => '2026-07-16',
        ]));

        $afterItem = $updated->fresh()->items()->sole();

        $this->assertSame($beforeItem, $afterItem->only(array_keys($beforeItem)));
        $this->assertSame($beforeMovements, StockMovement::count());
        $this->assertSame($beforeStock, $product->fresh()->stock_qty);
        $this->assertSame(321, $updated->fresh()->customer_id);
        $this->assertSame('2026-07-16', $updated->fresh()->sale_date);
        $this->assertSame('200', (string) $updated->fresh()->total_amount);
    }

    public function test_header_only_fee_or_discount_change_recomputes_only_the_header(): void
    {
        [$sale, $product, , $item] = $this->existingSale();
        $beforeMovements = StockMovement::count();

        $updated = app(SaleService::class)->updateSale($sale, $this->updateData($sale, [
            'delivery_fee' => '25.00',
            'discount' => '5.00',
        ]));

        $this->assertSame($item->id, $updated->fresh()->items()->sole()->id);
        $this->assertSame('210', (string) $updated->fresh()->total_amount);
        $this->assertSame('76.0000', $product->fresh()->stock_qty);
        $this->assertSame($beforeMovements, StockMovement::count());
    }

    public function test_changed_qty_uses_existing_update_flow(): void
    {
        [$sale, $product, , $item] = $this->existingSale();
        $items = $this->submittedItems($sale);
        $items[0]['qty'] = '3.00';

        $this->assertChangedItemFlow($sale, $item, $items);
        $this->assertSame('64.0000', $product->fresh()->stock_qty);
    }

    public function test_changed_price_uses_existing_update_flow(): void
    {
        [$sale, , , $item] = $this->existingSale();
        $items = $this->submittedItems($sale);
        $items[0]['selling_price'] = '96.00';

        $this->assertChangedItemFlow($sale, $item, $items);
    }

    public function test_changed_product_uses_existing_update_flow(): void
    {
        [$sale, $oldProduct, , $item] = $this->existingSale();
        $newProduct = Product::create([
            'name' => 'Replacement snapshot product',
            'cost_price' => '30.00',
            'selling_price' => '95.00',
            'stock_qty' => '100.0000',
        ]);
        $items = $this->submittedItems($sale);
        $items[0]['product_id'] = $newProduct->id;
        $items[0]['product_unit_id'] = null;

        $this->assertChangedItemFlow($sale, $item, $items);
        $this->assertSame('100.0000', $oldProduct->fresh()->stock_qty);
        $this->assertSame('98.0000', $newProduct->fresh()->stock_qty);
    }

    public function test_changed_product_unit_uses_existing_update_flow(): void
    {
        [$sale, $product, , $item] = $this->existingSale();
        $replacementUnit = $this->productUnit($product, 'Replacement unit', '6.0000');
        $items = $this->submittedItems($sale);
        $items[0]['product_unit_id'] = $replacementUnit->id;

        $this->assertChangedItemFlow($sale, $item, $items);
        $this->assertSame('88.0000', $product->fresh()->stock_qty);
    }

    public function test_changed_item_order_preserves_existing_item_ids(): void
    {
        [$sale, $product, $productUnit, $firstItem] = $this->existingSale();
        $secondItem = $sale->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $productUnit->id,
            'conversion_rate_used' => '12.0000',
            'base_qty' => '12.0000',
            'qty' => '1.00',
            'selling_price' => '80.00',
            'cost_price' => '7.00',
            'total' => '80.00',
            'profit' => '73.00',
        ]);
        $product->update(['stock_qty' => '64.0000']);
        StockMovement::create([
            'product_id' => $product->id,
            'type' => 'OUT',
            'qty' => '12.0000',
            'stock_before' => '76.0000',
            'stock_after' => '64.0000',
            'reference_type' => 'sale',
            'reference_id' => $sale->id,
        ]);
        $sale->update(['total_amount' => '280.00']);
        $items = array_reverse($this->submittedItems($sale));
        $beforeMovements = StockMovement::count();

        app(SaleService::class)->updateSale($sale, $this->updateData($sale, [
            'items' => $items,
        ]));

        $this->assertDatabaseHas('sale_items', ['id' => $firstItem->id]);
        $this->assertDatabaseHas('sale_items', ['id' => $secondItem->id]);
        $this->assertSame($beforeMovements + 4, StockMovement::count());
        $this->assertSame(
            ['95.00', '80.00'],
            $sale->fresh()->items()->orderBy('id')->pluck('selling_price')->all()
        );
    }

    private function assertChangedItemFlow(Sale $sale, SaleItem $oldItem, array $items): void
    {
        $beforeMovements = StockMovement::count();

        app(SaleService::class)->updateSale($sale, $this->updateData($sale, [
            'items' => $items,
        ]));

        $this->assertDatabaseHas('sale_items', ['id' => $oldItem->id]);
        $this->assertSame($beforeMovements + 2, StockMovement::count());
    }

    private function existingSale(): array
    {
        $product = Product::create([
            'name' => 'Historical snapshot product',
            'cost_price' => '7.00',
            'selling_price' => '95.00',
            'stock_qty' => '76.0000',
        ]);
        $productUnit = $this->productUnit($product, 'Historical unit', '12.0000');
        $sale = Sale::create([
            'sale_no' => 'SAL-SNAPSHOT-0001',
            'customer_id' => 123,
            'sale_date' => '2026-07-15',
            'total_amount' => '200.00',
            'delivery_fee' => '20.00',
            'delivery_type' => 'delivery',
            'discount' => '10.00',
        ]);
        $item = $sale->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $productUnit->id,
            'conversion_rate_used' => '12.0000',
            'base_qty' => '24.0000',
            'qty' => '2.00',
            'selling_price' => '95.00',
            'cost_price' => '7.00',
            'total' => '190.00',
            'profit' => '176.00',
        ]);
        StockMovement::create([
            'product_id' => $product->id,
            'type' => 'OUT',
            'qty' => '24.0000',
            'stock_before' => '100.0000',
            'stock_after' => '76.0000',
            'reference_type' => 'sale',
            'reference_id' => $sale->id,
        ]);

        return [$sale, $product, $productUnit, $item];
    }

    private function productUnit(Product $product, string $name, string $rate): ProductUnit
    {
        $unit = Unit::create(['name' => $name]);

        return ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_rate' => $rate,
            'conversion_confirmed_at' => now(),
            'is_base_unit' => false,
            'is_purchase_unit' => false,
            'is_sale_unit' => true,
            'active' => true,
        ]);
    }

    private function submittedItems(Sale $sale): array
    {
        return $sale->items()->orderBy('id')->get()->map(fn (SaleItem $item): array => [
            'sale_item_id' => $item->id,
            'product_id' => $item->product_id,
            'product_unit_id' => $item->product_unit_id,
            'qty' => $item->qty,
            'selling_price' => $item->selling_price,
        ])->all();
    }

    private function updateData(Sale $sale, array $overrides = []): array
    {
        return array_replace([
            'customer_id' => $sale->customer_id,
            'sale_date' => $sale->sale_date,
            'items' => $this->submittedItems($sale),
            'delivery_fee' => $sale->delivery_fee,
            'discount' => $sale->discount,
        ], $overrides);
    }
}
