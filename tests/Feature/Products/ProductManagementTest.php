<?php

namespace Tests\Feature\Products;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            Authenticate::class,
            RoleMiddleware::class,
            ValidateCsrfToken::class,
        ]);
    }

    public function test_product_index_supports_search_filters_sort_and_pagination_state(): void
    {
        $hardware = $this->category('Hardware');
        $paint = $this->category('Paint');

        Product::query()->create($this->productPayload($hardware, [
            'name' => 'Blue Roller',
            'product_code' => 'BR-001',
            'barcode' => '8850001',
            'active' => true,
        ]));
        Product::query()->create($this->productPayload($paint, [
            'name' => 'Red Brush',
            'product_code' => 'RB-002',
            'barcode' => '8850002',
            'active' => false,
        ]));

        $response = $this->get(route('products.index', [
            'search' => 'BR-001',
            'category_id' => $hardware->id,
            'status' => 'active',
            'sort' => 'name_desc',
            'per_page' => 10,
        ]));

        $response->assertOk()
            ->assertSee('Blue Roller')
            ->assertDontSee('Red Brush')
            ->assertSee('name="search"', false)
            ->assertSee('name="per_page"', false)
            ->assertSee('Selling Rule')
            ->assertSee('ยังไม่ได้กำหนด')
            ->assertDontSee('value="DELETE"', false);
    }

    public function test_product_without_image_uses_placeholder_and_details_are_read_only_for_price_and_stock(): void
    {
        $category = $this->category('Hardware');
        $product = Product::query()->create($this->productPayload($category, [
            'name' => 'Placeholder Product',
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'stock_qty' => '4.0000',
        ]));

        $response = $this->get(route('products.index'));

        $response->assertOk()
            ->assertSee('product-placeholder.svg')
            ->assertSee('data-product-id="'.$product->id.'"', false)
            ->assertSee('ต้นทุนเฉลี่ย')
            ->assertSee('คงเหลือปัจจุบัน')
            ->assertSee('id="productReadOnlySection"', false)
            ->assertSee('id="productReadOnlySelling"', false)
            ->assertSee('id="productReadOnlyStock"', false)
            ->assertSee('id="productModalCode"', false)
            ->assertSee('id="productModalBarcode"', false)
            ->assertSee('readonly', false);
    }

    public function test_product_image_is_stored_and_path_is_persisted(): void
    {
        Storage::fake('public');
        $category = $this->category('Hardware');

        $response = $this->post(route('products.store'), [
            ...$this->productPayload($category),
            'image' => UploadedFile::fake()->image('product.jpg'),
        ]);

        $response->assertRedirect();

        $product = Product::query()->where('name', 'Test Product')->firstOrFail();

        $this->assertNotNull($product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_product_image_validation_rejects_unsupported_files(): void
    {
        Storage::fake('public');
        $category = $this->category('Hardware');

        $this->from(route('products.index'))
            ->post(route('products.store'), [
                ...$this->productPayload($category),
                'image' => UploadedFile::fake()->create('product.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('products.index'))
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_product_details_update_changes_profile_fields_without_changing_price_or_stock(): void
    {
        $category = $this->category('Hardware');
        $product = Product::query()->create($this->productPayload($category, [
            'name' => 'Existing Product',
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'stock_qty' => '7.0000',
            'active' => true,
        ]));

        $this->put(route('products.update', $product), [
            'name' => 'Renamed Product',
            'product_code' => 'RENAMED-1',
            'category_id' => $category->id,
            'active' => '0',
            'remark' => 'Updated remark',
            'filter_sort' => 'category_name',
            'filter_per_page' => '50',
        ])->assertRedirect(route('products.index', ['sort' => 'category_name', 'per_page' => '50']));

        $product->refresh();
        $this->assertSame('Renamed Product', $product->name);
        $this->assertFalse((bool) $product->active);
        $this->assertSame('10.00', $product->cost_price);
        $this->assertSame('15.00', $product->selling_price);
        $this->assertSame('7.0000', $product->stock_qty);
    }

    public function test_product_edit_fallback_has_no_delete_control(): void
    {
        $category = $this->category('Hardware');
        $product = Product::query()->create($this->productPayload($category));

        $this->get(route('products.edit', $product))
            ->assertOk()
            ->assertDontSee('value="DELETE"', false);
    }

    private function category(string $name): Category
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3));

        return Category::query()->create([
            'name' => $name,
            'code_prefix' => $prefix,
            'barcode_prefix' => str_pad((string) (Category::query()->count() + 1), 3, '0', STR_PAD_LEFT),
            'active' => true,
        ]);
    }

    private function productPayload(Category $category, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'product_code' => Str::upper(Str::random(8)),
            'barcode' => null,
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'stock_qty' => '0.0000',
            'minimum_stock' => '0.0000',
            'vat_enabled' => false,
            'active' => true,
            'remark' => null,
        ], $overrides);
    }
}
