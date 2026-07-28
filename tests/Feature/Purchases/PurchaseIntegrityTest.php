<?php

namespace Tests\Feature\Purchases;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_preserves_weighted_average_cost_with_decimal_stock_and_movement(): void
    {
        $product = $this->product('Decimal receipt', '10.0000', '20.00');
        $supplier = $this->supplier('Active supplier');

        $purchase = app(PurchaseService::class)->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-07-15',
            'items' => [[
                'product_id' => $product->id,
                'qty' => '2.5000',
                'cost_price' => '30.00',
            ]],
        ]);

        $item = $purchase->items()->sole();
        $movement = StockMovement::query()->where('product_id', $product->id)->sole();

        $this->assertSame('2.5000', $item->qty);
        $this->assertSame('75.00', $item->total);
        $this->assertSame('12.5000', $product->fresh()->stock_qty);
        $this->assertSame('22.00', $product->fresh()->cost_price);
        $this->assertSame('50.00', $product->fresh()->selling_price);
        $this->assertSame('20.00', $product->fresh()->pricing_reviewed_cost);
        $this->assertSame('2.5000', $movement->qty);
        $this->assertSame('10.0000', $movement->stock_before);
        $this->assertSame('12.5000', $movement->stock_after);
    }

    public function test_update_and_delete_keep_the_existing_cost_policy(): void
    {
        $product = $this->product('Cost policy', '10.0000', '20.00');
        $supplier = $this->supplier('Cost supplier');
        $service = app(PurchaseService::class);
        $purchase = $service->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-07-15',
            'items' => [[
                'product_id' => $product->id,
                'qty' => '2.0000',
                'cost_price' => '30.00',
            ]],
        ]);

        $this->assertSame('21.67', $product->fresh()->cost_price);

        $service->update($purchase, [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-07-16',
            'items' => [[
                'product_id' => $product->id,
                'qty' => '3.0000',
                'cost_price' => '40.00',
            ]],
        ]);

        $this->assertSame('13.0000', $product->fresh()->stock_qty);
        $this->assertSame('40.00', $product->fresh()->cost_price);

        $movements = StockMovement::query()->where('product_id', $product->id)->orderBy('id')->get();
        $this->assertSame(
            [
                ['IN', '2.0000', '10.0000', '12.0000'],
                ['OUT', '2.0000', '12.0000', '10.0000'],
                ['IN', '3.0000', '10.0000', '13.0000'],
            ],
            $movements->map(fn (StockMovement $movement): array => [
                $movement->type,
                $movement->qty,
                $movement->stock_before,
                $movement->stock_after,
            ])->all()
        );

        $service->delete($purchase->fresh());

        $this->assertSame('10.0000', $product->fresh()->stock_qty);
        $this->assertSame('40.00', $product->fresh()->cost_price);
        $lastMovement = StockMovement::query()->where('product_id', $product->id)->latest('id')->firstOrFail();
        $this->assertSame('OUT', $lastMovement->type);
        $this->assertSame('3.0000', $lastMovement->qty);
        $this->assertSame('13.0000', $lastMovement->stock_before);
        $this->assertSame('10.0000', $lastMovement->stock_after);
    }

    public function test_service_rechecks_active_references_inside_the_transaction(): void
    {
        $inactiveProduct = $this->product('Inactive direct service product', '5.0000', '20.00', false);
        $inactiveSupplier = $this->supplier('Inactive direct service supplier', false);
        $activeSupplier = $this->supplier('Active direct service supplier');
        $service = app(PurchaseService::class);

        foreach ([
            [$inactiveSupplier->id, $this->product('Active direct service product', '5.0000', '20.00')->id],
            [$activeSupplier->id, $inactiveProduct->id],
        ] as [$supplierId, $productId]) {
            try {
                $service->create([
                    'supplier_id' => $supplierId,
                    'purchase_date' => '2026-07-15',
                    'items' => [[
                        'product_id' => $productId,
                        'qty' => '1.2500',
                        'cost_price' => '30.00',
                    ]],
                ]);
                $this->fail('Inactive references should be rejected by the service.');
            } catch (\DomainException) {
                $this->assertDatabaseCount('purchases', 0);
                $this->assertDatabaseCount('purchase_items', 0);
                $this->assertDatabaseCount('stock_movements', 0);
            }
        }
    }

    private function product(
        string $name,
        string $stock,
        string $cost,
        bool $active = true
    ): Product {
        $category = Category::query()->firstOrCreate(['name' => 'Purchase integrity category']);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'cost_price' => $cost,
            'selling_price' => '50.00',
            'stock_qty' => $stock,
            'minimum_stock' => '0.0000',
            'active' => $active,
            'auto_price_enabled' => false,
        ]);
    }

    private function supplier(string $name, bool $active = true): Supplier
    {
        return Supplier::query()->create([
            'name' => $name,
            'active' => $active,
        ]);
    }
}
