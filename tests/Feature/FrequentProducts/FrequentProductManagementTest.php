<?php

namespace Tests\Feature\FrequentProducts;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrequentProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            Authenticate::class,
            RoleMiddleware::class,
            ValidateCsrfToken::class,
        ]);
    }

    public function test_pin_is_idempotent_and_unpin_preserves_the_product_category_and_identity(): void
    {
        [$product, $category] = $this->product('Pinned Product');

        $first = $this->postJson(route('frequent-products.pin', $product));
        $first->assertCreated()->assertJsonPath('frequent_product.product_id', $product->id);

        $this->postJson(route('frequent-products.pin', $product))->assertOk();
        $this->assertDatabaseCount('pos_v3_frequent_products', 1);

        $mappingId = $first->json('frequent_product.id');
        $this->deleteJson(route('frequent-products.unpin', $mappingId))->assertOk();

        $this->assertDatabaseMissing('pos_v3_frequent_products', ['id' => $mappingId]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $category->id]);
    }

    public function test_reorder_persists_mapping_order(): void
    {
        $products = collect(['First', 'Second', 'Third'])
            ->map(fn (string $name): Product => $this->product($name)[0]);

        $mappingIds = $products
            ->map(fn (Product $product): int => $this->postJson(route('frequent-products.pin', $product))
                ->json('frequent_product.id'))
            ->all();

        $this->putJson(route('frequent-products.order'), [
            'frequent_product_ids' => [$mappingIds[2], $mappingIds[0], $mappingIds[1]],
        ])->assertOk();

        $this->assertSame(
            [$mappingIds[2], $mappingIds[0], $mappingIds[1]],
            \DB::table('pos_v3_frequent_products')->orderBy('sort_order')->orderBy('id')->pluck('id')->all(),
        );
    }

    /** @return array{0: Product, 1: Category} */
    private function product(string $name): array
    {
        $category = Category::query()->firstOrCreate(['name' => 'Frequent Category']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'unit' => 'ชิ้น',
            'cost_price' => '10.00',
            'selling_price' => '20.00',
            'stock_qty' => '5.0000',
            'minimum_stock' => '0.0000',
            'active' => true,
        ]);

        return [$product, $category];
    }
}
