<?php

namespace Tests\Feature\Products;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductMinimumStockTest extends TestCase
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

    public function test_create_defaults_a_blank_minimum_stock_to_zero(): void
    {
        $category = $this->category();

        $this->post(route('products.store'), $this->payload($category->id, ''))
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'name' => 'Blank minimum stock product',
            'minimum_stock' => 0,
        ]);
    }

    public function test_create_preserves_explicit_zero_and_positive_minimum_stock_values(): void
    {
        $category = $this->category();

        foreach ([0, 5] as $minimumStock) {
            $name = "Explicit minimum stock {$minimumStock} product";

            $this->post(route('products.store'), $this->payload($category->id, $minimumStock, $name))
                ->assertRedirect();

            $this->assertDatabaseHas('products', [
                'name' => $name,
                'minimum_stock' => $minimumStock,
            ]);
        }
    }

    public function test_create_rejects_invalid_minimum_stock_values(): void
    {
        $category = $this->category();

        foreach (['-1', 'not-a-number'] as $minimumStock) {
            $this->post(route('products.store'), $this->payload($category->id, $minimumStock))
                ->assertSessionHasErrors('minimum_stock');
        }

        $this->assertDatabaseCount('products', 0);
    }

    public function test_update_defaults_a_blank_minimum_stock_to_zero(): void
    {
        $category = $this->category();
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Existing minimum stock product',
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'stock_qty' => '0.0000',
            'minimum_stock' => '5.0000',
        ]);

        $this->put(route('products.update', $product), $this->payload($category->id, ''))
            ->assertRedirect(route('products.index'));

        $this->assertSame('5.0000', $product->fresh()->minimum_stock);
    }

    private function category(): Category
    {
        return Category::query()->create([
            'name' => 'Product minimum stock category',
            'active' => true,
        ]);
    }

    private function payload(int $categoryId, mixed $minimumStock, string $name = 'Blank minimum stock product'): array
    {
        return [
            'category_id' => $categoryId,
            'name' => $name,
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'stock_qty' => '0.0000',
            'minimum_stock' => $minimumStock,
        ];
    }
}
