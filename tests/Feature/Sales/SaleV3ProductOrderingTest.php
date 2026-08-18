<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\FrequentProduct;
use App\Models\Product;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SaleV3ProductOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_v3_renders_separate_order_metadata_and_manager_only_reorder_controls(): void
    {
        $highCategory = $this->category('High Category', 20);
        $lowCategory = $this->category('Low Category', 5);
        $highProduct = $this->product($highCategory, 'High Product', 1);
        $lowProduct = $this->product($lowCategory, 'Low Product', 4);
        FrequentProduct::query()->create(['product_id' => $highProduct->id, 'sort_order' => 0]);
        FrequentProduct::query()->create(['product_id' => $lowProduct->id, 'sort_order' => 1]);

        $cashierHtml = $this->actingAs(User::factory()->create(['role' => 'cashier']))
            ->get(route('sales.v3'))
            ->assertOk()
            ->assertDontSee('id="v3-product-order-toggle"', false)
            ->assertDontSee('F2 ค้นหา', false)
            ->assertDontSee('F8 Barcode', false)
            ->assertSee('id="v3-pos-brand"', false)
            ->assertSee('href="'.route('dashboard').'"', false)
            ->getContent();

        $managerHtml = $this->actingAs(User::factory()->create(['role' => 'manager']))
            ->get(route('sales.v3'))
            ->assertOk()
            ->assertSee('id="v3-product-order-toggle"', false)
            ->assertSee('จัดลำดับสินค้า', false)
            ->getContent();

        $this->assertSame(
            [$highProduct->id, $lowProduct->id],
            $this->productMetadataById($cashierHtml)->keys()->all(),
        );
        $metadata = $this->productMetadataById($managerHtml);
        $this->assertSame(1, $metadata[$highProduct->id]['product_sort_order']);
        $this->assertSame(20, $metadata[$highProduct->id]['category_sort_order']);
        $this->assertSame(0, $metadata[$highProduct->id]['frequent_order']);
        $this->assertSame(4, $metadata[$lowProduct->id]['product_sort_order']);
        $this->assertSame(5, $metadata[$lowProduct->id]['category_sort_order']);
        $this->assertSame(1, $metadata[$lowProduct->id]['frequent_order']);
    }

    private function productMetadataById(string $html): Collection
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new DOMXPath($document);
        $cards = $xpath->query('//button[contains(concat(" ", normalize-space(@class), " "), " v3-product-card ")]');

        return collect(iterator_to_array($cards))->mapWithKeys(function ($card): array {
            $data = json_decode($card->getAttribute('data-product'), true, 512, JSON_THROW_ON_ERROR);

            return [(int) $data['id'] => $data];
        });
    }

    private function category(string $name, int $sortOrder): Category
    {
        return Category::query()->create([
            'name' => $name,
            'code_prefix' => strtoupper(substr($name, 0, 3)),
            'barcode_prefix' => str_pad((string) (Category::query()->count() + 1), 3, '0', STR_PAD_LEFT),
            'sort_order' => $sortOrder,
            'active' => true,
        ]);
    }

    private function product(Category $category, string $name, int $sortOrder): Product
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
            'sort_order' => $sortOrder,
        ]);
    }
}
