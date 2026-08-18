<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\FrequentProduct;
use App\Models\Product;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleV3FrequentProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_v3_uses_active_category_order_and_frequent_products_as_the_default_virtual_tab(): void
    {
        $firstCategory = Category::query()->create([
            'name' => 'First Category',
            'sort_order' => 20,
            'active' => true,
        ]);
        $secondCategory = Category::query()->create([
            'name' => 'Second Category',
            'sort_order' => 5,
            'active' => true,
        ]);
        $inactiveCategory = Category::query()->create([
            'name' => 'Inactive Category',
            'sort_order' => 1,
            'active' => false,
        ]);

        $firstProduct = $this->product($firstCategory, 'First Product');
        $secondProduct = $this->product($secondCategory, 'Second Product');
        $inactiveProduct = $this->product($inactiveCategory, 'Inactive Product');
        $inactiveProduct->update(['active' => false]);

        FrequentProduct::query()->create(['product_id' => $firstProduct->id, 'sort_order' => 1]);
        FrequentProduct::query()->create(['product_id' => $secondProduct->id, 'sort_order' => 0]);
        FrequentProduct::query()->create(['product_id' => $inactiveProduct->id, 'sort_order' => 2]);

        $html = $this->actingAs(User::factory()->create(['role' => 'cashier']))
            ->get(route('sales.v3'))
            ->assertOk()
            ->getContent();

        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new DOMXPath($document);

        $tabs = $xpath->query('//div[@id="v3-category-tabs"]/button');
        $this->assertSame(
            ['ขายบ่อย', 'ทุกหมวด', 'Second Category', 'First Category'],
            array_map(fn ($node): string => trim($node->textContent), iterator_to_array($tabs)),
        );
        $this->assertSame('frequent', $tabs->item(0)->getAttribute('data-category'));
        $this->assertStringContainsString('active', $tabs->item(0)->getAttribute('class'));

        $cards = $xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), " v3-product-card ")]');
        $this->assertCount(2, $cards);
        $this->assertSame('Second Product', json_decode($cards->item(0)->getAttribute('data-product'), true)['name']);
        $this->assertSame('0', $cards->item(0)->getAttribute('data-frequent-order'));
        $this->assertSame('First Product', json_decode($cards->item(1)->getAttribute('data-product'), true)['name']);
        $this->assertStringNotContainsString('Inactive Product', $html);
        $this->assertStringNotContainsString('Inactive Category', $html);
    }

    private function product(Category $category, string $name): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'unit' => 'ชิ้น',
            'cost_price' => '10.00',
            'selling_price' => '20.00',
            'stock_qty' => '5.0000',
            'minimum_stock' => '0.0000',
            'active' => true,
        ]);
    }
}
