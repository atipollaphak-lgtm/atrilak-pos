<?php

namespace Tests\Unit\Receivings;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Services\Receivings\ReceiveStockValidationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiveStockValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_source_requires_supplier(): void
    {
        $service = app(ReceiveStockValidationService::class);
        $base = [
            'purchase_date' => '2026-08-03',
            'items' => [['product_id' => 1, 'qty' => '1', 'cost_price' => '10']],
        ];

        $this->expectException(DomainException::class);
        $service->normalize($base + ['source' => 'supplier']);

    }

    public function test_production_source_rejects_supplier(): void
    {
        $service = app(ReceiveStockValidationService::class);

        $this->expectException(DomainException::class);
        $service->normalize([
            'source' => 'production',
            'supplier_id' => 2,
            'purchase_date' => '2026-08-03',
            'items' => [['product_id' => 1, 'qty' => '1', 'cost_price' => '10']],
        ]);
    }

    public function test_duplicate_product_lines_are_rejected(): void
    {
        $service = app(ReceiveStockValidationService::class);

        $this->expectException(DomainException::class);
        $service->normalize([
            'source' => 'production',
            'purchase_date' => '2026-08-03',
            'items' => [
                ['product_id' => 1, 'qty' => '1', 'cost_price' => '10'],
                ['product_id' => 1, 'qty' => '2', 'cost_price' => '12'],
            ],
        ]);
    }

    public function test_non_base_purchase_unit_resolves_to_base_quantity_and_cost(): void
    {
        $category = Category::query()->create(['name' => 'วัตถุดิบ']);
        $unit = Unit::query()->create(['code' => 'BOX', 'name' => 'กล่อง', 'short_name' => 'กล่อง']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'สินค้าแปลงหน่วย',
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

        $service = app(ReceiveStockValidationService::class);
        $normalized = $service->normalize([
            'source' => 'production',
            'purchase_date' => '2026-08-03',
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => $productUnit->id,
                'qty' => '2.0000',
                'cost_price' => '50.00',
            ]],
        ]);
        $resolved = $service->resolveItems($normalized['items'], collect([$product->load('productUnits.unit')])->keyBy('id'));

        $this->assertSame('20.0000', $resolved[0]['base_qty']);
        $this->assertSame('5.00', $resolved[0]['base_cost_price']);
        $this->assertSame($productUnit->id, $resolved[0]['unit']->id);
    }
}
