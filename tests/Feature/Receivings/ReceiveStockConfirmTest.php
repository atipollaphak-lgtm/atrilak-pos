<?php

namespace Tests\Feature\Receivings;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Receivings\ReceiveStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiveStockConfirmTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_posts_supplier_receipt_once_and_preserves_selling_price_and_price_lock(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $supplier = Supplier::query()->create(['name' => 'ผู้จำหน่ายทดสอบ', 'active' => true]);
        [$product, $productUnit] = $this->productWithBoxUnit();
        $sellingPriceBefore = (string) $product->selling_price;
        $priceLockBefore = $product->price_lock;
        $key = '11111111-1111-4111-8111-111111111111';

        $service = app(ReceiveStockService::class);
        $preview = $service->preview($user->id, [
            'source' => 'supplier',
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-08-03',
            'supplier_document_number' => 'INV-001',
            'remark' => 'ทดสอบรับเข้า',
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => $productUnit->id,
                'qty' => '2.0000',
                'cost_price' => '50.00',
            ]],
        ]);

        $purchase = $service->confirm($user->id, $preview['token'], $key);
        $repeated = $service->confirm($user->id, $preview['token'], $key);
        $freshProduct = $product->fresh();
        $item = PurchaseItem::query()->firstOrFail();
        $movement = StockMovement::query()->firstOrFail();

        $this->assertSame($purchase->id, $repeated->id);
        $this->assertSame(1, Purchase::query()->count());
        $this->assertSame(1, PurchaseItem::query()->count());
        $this->assertSame(1, StockMovement::query()->count());
        $this->assertSame('supplier', $purchase->source);
        $this->assertSame($supplier->id, $purchase->supplier_id);
        $this->assertSame('100.00', (string) $purchase->total_amount);
        $this->assertSame('20.0000', (string) $item->base_qty);
        $this->assertSame('6.00', (string) $item->average_cost_after);
        $this->assertSame((string) $purchase->id, (string) $movement->reference_id);
        $this->assertSame('20.0000', (string) $movement->qty);
        $this->assertSame('25.0000', (string) $freshProduct->stock_qty);
        $this->assertSame('6.00', (string) $freshProduct->cost_price);
        $this->assertSame($sellingPriceBefore, (string) $freshProduct->selling_price);
        $this->assertEquals($priceLockBefore, $freshProduct->price_lock);
    }

    public function test_production_receipt_does_not_need_supplier(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        [$product, $productUnit] = $this->productWithBoxUnit();

        $preview = app(ReceiveStockService::class)->preview($user->id, [
            'source' => 'production',
            'purchase_date' => '2026-08-03',
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => $productUnit->id,
                'qty' => '1.0000',
                'cost_price' => '50.00',
            ]],
        ]);
        $purchase = app(ReceiveStockService::class)->confirm(
            $user->id,
            $preview['token'],
            '22222222-2222-4222-8222-222222222222'
        );

        $this->assertSame('production', $purchase->source);
        $this->assertNull($purchase->supplier_id);
    }

    private function productWithBoxUnit(): array
    {
        $category = Category::query()->create(['name' => 'วัตถุดิบ']);
        $unit = Unit::query()->create(['code' => 'BOX', 'name' => 'กล่อง', 'short_name' => 'กล่อง']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'สินค้า Confirm',
            'unit' => 'ชิ้น',
            'cost_price' => '10.00',
            'selling_price' => '20.00',
            'price_lock' => true,
            'stock_qty' => '5.0000',
            'active' => true,
        ]);
        $productUnit = ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_rate' => '10.0000',
            'conversion_confirmed_at' => now(),
            'is_base_unit' => false,
            'is_purchase_unit' => true,
            'is_sale_unit' => true,
            'active' => true,
        ]);

        return [$product, $productUnit];
    }
}
