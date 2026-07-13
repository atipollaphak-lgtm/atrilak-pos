<?php

namespace Tests\Feature\BusinessRules;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\TechnicianCommissionRule;
use App\Services\TechnicianCommissionService;
use Tests\Support\CreatesBusinessRuleTestSchema;
use Tests\TestCase;

class TechnicianCommissionCharacterizationTest extends TestCase
{
    use CreatesBusinessRuleTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBusinessRuleTestSchema();
    }

    protected function tearDown(): void
    {
        $this->dropBusinessRuleTestSchema();
        parent::tearDown();
    }

    public function test_product_percent_rule_currently_takes_priority_over_category_rule(): void
    {
        $category = Category::create(['name' => 'Commission category']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Commission product',
            'cost_price' => 50,
            'selling_price' => 100,
            'stock_qty' => 10,
        ]);
        TechnicianCommissionRule::create([
            'category_id' => $category->id,
            'name' => 'Category 5 percent',
            'rule_type' => 'percent',
            'rule_value' => 5,
            'active' => true,
        ]);
        TechnicianCommissionRule::create([
            'product_id' => $product->id,
            'name' => 'Product 10 percent',
            'rule_type' => 'percent',
            'rule_value' => 10,
            'active' => true,
        ]);
        $sale = $this->createSaleWithItem($product, 2, 100);

        $commission = (new TechnicianCommissionService)->createFromSale($sale);

        $this->assertNotNull($commission);
        $this->assertEquals(20.00, $commission->commission_amount);
        $detail = json_decode($commission->calculation_detail, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Product 10 percent', $detail[0]['rule_name']);
    }

    public function test_amount_rule_is_currently_applied_per_item_quantity(): void
    {
        $product = Product::create([
            'name' => 'Fixed commission product',
            'cost_price' => 50,
            'selling_price' => 100,
            'stock_qty' => 10,
        ]);
        TechnicianCommissionRule::create([
            'product_id' => $product->id,
            'name' => '15 per item',
            'rule_type' => 'amount',
            'rule_value' => 15,
            'active' => true,
        ]);
        $sale = $this->createSaleWithItem($product, 3, 100);

        $commission = (new TechnicianCommissionService)->createFromSale($sale);

        $this->assertNotNull($commission);
        $this->assertEquals(45.00, $commission->commission_amount);
        $this->assertEquals(300.00, $commission->sale_total);
        $this->assertSame('pending', $commission->status);
    }

    private function createSaleWithItem(Product $product, float $qty, float $price): Sale
    {
        $sale = Sale::create([
            'sale_no' => 'SAL-COMMISSION-'.$product->id,
            'technician_id' => 99,
            'sale_date' => '2026-07-13',
            'total_amount' => $qty * $price,
            'delivery_type' => 'pickup',
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'qty' => $qty,
            'selling_price' => $price,
            'cost_price' => 50,
            'total' => $qty * $price,
            'profit' => ($price - 50) * $qty,
        ]);

        return $sale;
    }
}
