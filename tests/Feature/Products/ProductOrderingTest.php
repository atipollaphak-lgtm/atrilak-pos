<?php

namespace Tests\Feature\Products;

use App\Http\Middleware\ValidateCsrfToken;
use App\Models\Category;
use App\Models\FrequentProduct;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductCreationService;
use App\Services\ProductUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductOrderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_products_have_a_forward_safe_sort_order_column_with_a_zero_default(): void
    {
        $this->assertTrue(Schema::hasColumn('products', 'sort_order'));

        $product = $this->product($this->category('Hardware'));

        $this->assertSame(0, (int) $product->fresh()->sort_order);
    }

    public function test_product_creation_appends_after_the_existing_category_order(): void
    {
        $category = $this->category('Hardware');
        $this->product($category, 'First', 0);
        $this->product($category, 'Last', 4);

        $created = app(ProductCreationService::class)->create([
            'category_id' => $category->id,
            'name' => 'Appended Product',
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'stock_qty' => '0.0000',
            'minimum_stock' => '0.0000',
            'vat_enabled' => false,
            'active' => true,
        ]);

        $this->assertSame(5, (int) $created->fresh()->sort_order);
    }

    public function test_category_move_appends_without_changing_protected_product_fields(): void
    {
        $source = $this->category('Source');
        $destination = $this->category('Destination');
        $this->product($destination, 'Existing Destination Product', 0);
        $this->product($destination, 'Last Destination Product', 3);
        $moving = $this->product($source, 'Moving Product', 1, [
            'cost_price' => '12.34',
            'selling_price' => '18.76',
            'stock_qty' => '7.5000',
            'barcode' => '8850000000001',
        ]);

        $updated = app(ProductUpdateService::class)->update($moving, [
            'name' => $moving->name,
            'category_id' => $destination->id,
            'unit_id' => null,
            'cost_price' => $moving->cost_price,
            'selling_price' => $moving->selling_price,
            'stock_qty' => $moving->stock_qty,
            'minimum_stock' => $moving->minimum_stock,
            'vat_enabled' => $moving->vat_enabled,
            'active' => $moving->active,
            'remark' => $moving->remark,
            'sku' => $moving->sku,
        ]);

        $updated->refresh();

        $this->assertSame($destination->id, $updated->category_id);
        $this->assertSame(4, (int) $updated->sort_order);
        $this->assertSame('12.34', (string) $updated->cost_price);
        $this->assertSame('18.76', (string) $updated->selling_price);
        $this->assertSame('7.5000', (string) $updated->stock_qty);
        $this->assertSame('8850000000001', $updated->barcode);
    }

    public function test_manager_can_reorder_the_complete_active_category_set_without_touching_frequent_or_protected_data(): void
    {
        $category = $this->category('Hardware');
        $first = $this->product($category, 'First', 0, ['stock_qty' => '8.0000']);
        $second = $this->product($category, 'Second', 1, ['selling_price' => '31.25']);
        $third = $this->product($category, 'Third', 2, ['barcode' => '8850000000002']);
        $inactive = $this->product($category, 'Inactive', 9, ['active' => false]);
        FrequentProduct::query()->create(['product_id' => $second->id, 'sort_order' => 0]);

        $before = [
            'first' => $first->fresh()->only(['cost_price', 'selling_price', 'stock_qty', 'barcode']),
            'second' => $second->fresh()->only(['cost_price', 'selling_price', 'stock_qty', 'barcode']),
            'third' => $third->fresh()->only(['cost_price', 'selling_price', 'stock_qty', 'barcode']),
            'inactive_sort_order' => (int) $inactive->fresh()->sort_order,
        ];

        $this->actingAs(User::factory()->create(['role' => 'manager']))
            ->putJson(route('categories.products.order', $category), [
                'product_ids' => [$third->id, $first->id, $second->id],
            ])
            ->assertOk();

        $this->assertSame(
            [$third->id, $first->id, $second->id],
            Product::query()
                ->where('category_id', $category->id)
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('id')
                ->all(),
        );
        $this->assertSame($before['first'], $first->fresh()->only(['cost_price', 'selling_price', 'stock_qty', 'barcode']));
        $this->assertSame($before['second'], $second->fresh()->only(['cost_price', 'selling_price', 'stock_qty', 'barcode']));
        $this->assertSame($before['third'], $third->fresh()->only(['cost_price', 'selling_price', 'stock_qty', 'barcode']));
        $this->assertSame($before['inactive_sort_order'], (int) $inactive->fresh()->sort_order);
        $this->assertSame(0, (int) $second->frequentProduct()->firstOrFail()->sort_order);
    }

    public function test_reorder_rejects_an_incomplete_or_injected_product_set_without_partial_updates(): void
    {
        $category = $this->category('Hardware');
        $first = $this->product($category, 'First', 0);
        $second = $this->product($category, 'Second', 1);
        $foreign = $this->product($this->category('Paint'), 'Foreign', 0);

        $this->actingAs(User::factory()->create(['role' => 'manager']))
            ->putJson(route('categories.products.order', $category), [
                'product_ids' => [$second->id, $foreign->id],
            ])
            ->assertUnprocessable();

        $this->assertSame(0, (int) $first->fresh()->sort_order);
        $this->assertSame(1, (int) $second->fresh()->sort_order);
    }

    public function test_cashier_cannot_reorder_products(): void
    {
        $category = $this->category('Hardware');
        $first = $this->product($category, 'First');

        $this->actingAs(User::factory()->create(['role' => 'cashier']))
            ->putJson(route('categories.products.order', $category), [
                'product_ids' => [$first->id],
            ])
            ->assertForbidden();
    }

    private function category(string $name, int $sortOrder = 0): Category
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name) ?: 'CAT', 0, 3));

        return Category::query()->create([
            'name' => $name,
            'code_prefix' => $prefix,
            'barcode_prefix' => str_pad((string) (Category::query()->count() + 1), 3, '0', STR_PAD_LEFT),
            'sort_order' => $sortOrder,
            'active' => true,
        ]);
    }

    private function product(Category $category, string $name = 'Product', int $sortOrder = 0, array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'category_id' => $category->id,
            'name' => $name,
            'unit' => 'ชิ้น',
            'cost_price' => '10.00',
            'selling_price' => '20.00',
            'stock_qty' => '0.0000',
            'minimum_stock' => '0.0000',
            'barcode' => null,
            'active' => true,
            'sort_order' => $sortOrder,
        ], $overrides));
    }
}
