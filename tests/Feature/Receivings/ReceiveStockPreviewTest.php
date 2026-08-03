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

class ReceiveStockPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_calculates_base_values_and_does_not_write_inventory(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $supplier = Supplier::query()->create(['name' => 'ผู้จำหน่ายทดสอบ', 'active' => true]);
        [$product, $productUnit] = $this->productWithBoxUnit();

        $result = app(ReceiveStockService::class)->preview($user->id, [
            'source' => 'supplier',
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-08-03',
            'supplier_document_number' => 'INV-001',
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => $productUnit->id,
                'qty' => '2.0000',
                'cost_price' => '50.00',
            ]],
        ]);

        $line = $result['preview']->lines[0];
        $this->assertSame('20.0000', $line['base_qty']);
        $this->assertSame('5.00', $line['base_cost_price']);
        $this->assertSame('100.00', $line['line_total']);
        $this->assertSame('25.0000', $line['stock_after']);
        $this->assertSame('6', $line['average_cost_after']);
        $this->assertSame(0, Purchase::query()->count());
        $this->assertSame(0, PurchaseItem::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame('10.00', (string) $product->fresh()->cost_price);
        $this->assertSame('20.00', (string) $product->fresh()->selling_price);
    }

    private function productWithBoxUnit(): array
    {
        $category = Category::query()->create(['name' => 'วัตถุดิบ']);
        $unit = Unit::query()->create(['code' => 'BOX', 'name' => 'กล่อง', 'short_name' => 'กล่อง']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'สินค้า Preview',
            'unit' => 'ชิ้น',
            'cost_price' => '10.00',
            'selling_price' => '20.00',
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
