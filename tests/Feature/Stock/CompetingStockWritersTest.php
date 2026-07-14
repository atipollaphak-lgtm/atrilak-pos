<?php

namespace Tests\Feature\Stock;

use App\Models\Category;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\ProductUpdateService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Services\StockCountService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompetingStockWritersTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_store_preserves_weighted_average_cost_and_movement_chain(): void
    {
        $product = $this->product(stock: 10, cost: 20);
        $supplier = Supplier::query()->create(['name' => 'Test supplier', 'active' => true]);

        $purchase = app(PurchaseService::class)->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-07-14',
            'items' => [
                ['product_id' => $product->id, 'qty' => 10, 'cost_price' => 30],
            ],
        ]);

        $this->assertEquals(20, $product->fresh()->stock_qty);
        $this->assertEquals(25, $product->fresh()->cost_price);
        $this->assertEquals(300, $purchase->total_amount);
        $this->assertMovement($product, 10, 20, 'purchase');
    }

    public function test_purchase_update_and_delete_preserve_existing_reversal_behavior(): void
    {
        $product = $this->product(stock: 10, cost: 20);
        $supplier = Supplier::query()->create(['name' => 'Test supplier', 'active' => true]);
        $service = app(PurchaseService::class);
        $purchase = $service->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-07-14',
            'items' => [['product_id' => $product->id, 'qty' => 2, 'cost_price' => 30]],
        ]);

        $updated = $service->update($purchase, [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-07-15',
            'items' => [['product_id' => $product->id, 'qty' => 3, 'cost_price' => 40]],
        ]);

        $this->assertEquals(13, $product->fresh()->stock_qty);
        $this->assertEquals(40, $product->fresh()->cost_price);
        $this->assertEquals(120, $updated->total_amount);

        $service->delete($updated);

        $this->assertEquals(10, $product->fresh()->stock_qty);
        $this->assertDatabaseMissing('purchases', ['id' => $purchase->id]);
        $this->assertDatabaseCount('purchase_items', 0);
    }

    public function test_duplicate_purchase_lines_share_one_working_stock_balance(): void
    {
        $product = $this->product(stock: 10, cost: 5);
        $supplier = Supplier::query()->create(['name' => 'Test supplier', 'active' => true]);

        app(PurchaseService::class)->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-07-14',
            'items' => [
                ['product_id' => $product->id, 'qty' => 2, 'cost_price' => 10],
                ['product_id' => $product->id, 'qty' => 3, 'cost_price' => 20],
            ],
        ]);

        $movements = StockMovement::query()->where('product_id', $product->id)->orderBy('id')->get();
        $this->assertCount(2, $movements);
        $this->assertEquals(10, $movements[0]->stock_before);
        $this->assertEquals(12, $movements[0]->stock_after);
        $this->assertEquals(12, $movements[1]->stock_before);
        $this->assertEquals(15, $movements[1]->stock_after);
        $this->assertEquals(15, $product->fresh()->stock_qty);
        $this->assertEquals(8.66, $product->fresh()->cost_price);
    }

    public function test_failed_purchase_update_rolls_back_document_stock_and_movements(): void
    {
        $product = $this->product(stock: 0, cost: 5);
        $supplier = Supplier::query()->create(['name' => 'Test supplier', 'active' => true]);
        $service = app(PurchaseService::class);
        $purchase = $service->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-07-14',
            'items' => [['product_id' => $product->id, 'qty' => 5, 'cost_price' => 5]],
        ]);
        $product->update(['stock_qty' => 2]);

        try {
            $service->update($purchase, [
                'supplier_id' => $supplier->id,
                'purchase_date' => '2026-07-15',
                'items' => [['product_id' => $product->id, 'qty' => 1, 'cost_price' => 7]],
            ]);
            $this->fail('The purchase reversal should fail.');
        } catch (DomainException) {
            $this->assertEquals(2, $product->fresh()->stock_qty);
            $this->assertEquals(5, $purchase->fresh()->items()->sole()->qty);
            $this->assertEquals('2026-07-14', $purchase->fresh()->purchase_date);
            $this->assertDatabaseCount('stock_movements', 1);
        }
    }

    public function test_failed_purchase_delete_rolls_back_document_stock_and_movements(): void
    {
        $product = $this->product(stock: 0, cost: 5);
        $supplier = Supplier::query()->create(['name' => 'Test supplier', 'active' => true]);
        $service = app(PurchaseService::class);
        $purchase = $service->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-07-14',
            'items' => [['product_id' => $product->id, 'qty' => 5, 'cost_price' => 5]],
        ]);
        $product->update(['stock_qty' => 2]);

        try {
            $service->delete($purchase);
            $this->fail('The purchase reversal should fail.');
        } catch (DomainException) {
            $this->assertEquals(2, $product->fresh()->stock_qty);
            $this->assertDatabaseHas('purchases', ['id' => $purchase->id]);
            $this->assertDatabaseHas('purchase_items', ['purchase_id' => $purchase->id, 'qty' => 5]);
            $this->assertDatabaseCount('stock_movements', 1);
        }
    }

    public function test_stock_count_rejects_duplicate_products_without_writes(): void
    {
        $product = $this->product(stock: 10);

        try {
            app(StockCountService::class)->create([
                'count_date' => '2026-07-14',
                'items' => [
                    ['product_id' => $product->id, 'actual_qty' => 8],
                    ['product_id' => $product->id, 'actual_qty' => 7],
                ],
            ]);
            $this->fail('Duplicate products should be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('stock_counts', 0);
            $this->assertDatabaseCount('stock_count_items', 0);
            $this->assertDatabaseCount('stock_movements', 0);
            $this->assertEquals(10, $product->fresh()->stock_qty);
        }
    }

    public function test_manual_product_update_uses_locked_stock_for_adjustment_movement(): void
    {
        $product = $this->product(stock: 10, cost: 20);

        app(ProductUpdateService::class)->update($product, [
            'barcode' => $product->barcode,
            'name' => $product->name,
            'category_id' => $product->category_id,
            'unit_id' => null,
            'cost_price' => 20,
            'selling_price' => 30,
            'stock_qty' => 7,
            'minimum_stock' => 0,
            'vat_enabled' => 0,
            'active' => 1,
            'remark' => null,
        ]);

        $this->assertEquals(7, $product->fresh()->stock_qty);
        $this->assertMovement($product, 10, 7, 'adjust');
    }

    public function test_quotation_can_create_at_most_one_sale(): void
    {
        $product = $this->product(stock: 10, cost: 20);
        $quotation = Quotation::query()->create([
            'quotation_no' => 'QT-TEST-1',
            'quotation_date' => '2026-07-14',
            'total_amount' => 60,
            'status' => 'draft',
        ]);
        $quotation->items()->create([
            'product_id' => $product->id,
            'qty' => 2,
            'selling_price' => 30,
            'total' => 60,
        ]);

        $sale = app(SaleService::class)->createSaleFromQuotation($quotation);

        $this->assertEquals(8, $product->fresh()->stock_qty);
        $this->assertEquals('converted', $quotation->fresh()->status);
        $this->assertEquals(60, $sale->total_amount);
        $this->assertNull($sale->items()->sole()->product_unit_id);
        $this->assertSame('1.0000', $sale->items()->sole()->conversion_rate_used);
        $this->assertSame('2.0000', $sale->items()->sole()->base_qty);

        $replayedSale = app(SaleService::class)->createSaleFromQuotation($quotation);

        $this->assertSame($sale->id, $replayedSale->id);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    private function product(float $stock, float $cost = 10): Product
    {
        $category = Category::query()->firstOrCreate(
            ['name' => 'Test category'],
            ['active' => true]
        );

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Product '.uniqid(),
            'unit' => 'piece',
            'cost_price' => $cost,
            'selling_price' => 30,
            'stock_qty' => $stock,
            'minimum_stock' => 0,
            'vat_enabled' => false,
            'active' => true,
            'auto_price_enabled' => false,
        ]);
    }

    private function assertMovement(Product $product, float $before, float $after, string $referenceType): void
    {
        $movement = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('reference_type', $referenceType)
            ->latest('id')
            ->firstOrFail();

        $this->assertEquals($before, $movement->stock_before);
        $this->assertEquals($after, $movement->stock_after);
    }
}
