<?php

namespace Tests\Feature\Categories;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
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

    public function test_category_index_contains_single_page_management_controls_and_product_count(): void
    {
        $category = Category::query()->create([
            'name' => 'Hardware',
            'code_prefix' => 'HWD',
            'barcode_prefix' => '101',
            'description' => 'Construction hardware',
            'active' => true,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Hammer',
        ]);

        $this->get(route('categories.index'))
            ->assertOk()
            ->assertSee('categoryModal', false)
            ->assertSee('category-search', false)
            ->assertSee('Product Count')
            ->assertSee('Construction hardware')
            ->assertSee('data-product-count="1"', false);
    }

    public function test_category_can_be_created_and_updated_through_json_endpoints(): void
    {
        $response = $this->postJson(route('categories.store'), [
            'name' => 'Hardware',
            'code_prefix' => 'HWD',
            'barcode_prefix' => '101',
            'description' => 'Tools',
            'active' => true,
        ]);

        $response->assertCreated()->assertJsonPath('category.name', 'Hardware');
        $category = Category::query()->firstOrFail();

        $this->putJson(route('categories.update', $category), [
            'name' => 'Updated Hardware',
            'code_prefix' => 'UHW',
            'barcode_prefix' => '102',
            'description' => 'Updated tools',
            'active' => false,
        ])->assertOk()->assertJsonPath('category.active', false);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Hardware',
            'description' => 'Updated tools',
            'active' => false,
        ]);
    }

    public function test_duplicate_prefixes_are_rejected_for_create_and_update(): void
    {
        $existing = Category::query()->create([
            'name' => 'Existing',
            'code_prefix' => 'EXT',
            'barcode_prefix' => '103',
        ]);

        $this->post(route('categories.store'), [
            'name' => 'Duplicate',
            'code_prefix' => 'EXT',
            'barcode_prefix' => '103',
        ])->assertSessionHasErrors(['code_prefix', 'barcode_prefix']);

        $other = Category::query()->create(['name' => 'Other']);

        $this->put(route('categories.update', $other), [
            'name' => 'Other',
            'code_prefix' => $existing->code_prefix,
            'barcode_prefix' => $existing->barcode_prefix,
        ])->assertSessionHasErrors(['code_prefix', 'barcode_prefix']);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $category = Category::query()->create(['name' => 'Used']);
        Product::query()->create(['category_id' => $category->id, 'name' => 'Product']);

        $this->deleteJson(route('categories.destroy', $category))
            ->assertStatus(422)
            ->assertJsonPath('message', 'ไม่สามารถลบหมวดหมู่ที่มีสินค้าได้');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_empty_category_can_be_deleted(): void
    {
        $category = Category::query()->create(['name' => 'Unused']);

        $this->deleteJson(route('categories.destroy', $category))->assertOk();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
