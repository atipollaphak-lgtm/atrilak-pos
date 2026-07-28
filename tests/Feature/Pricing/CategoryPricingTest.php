<?php

namespace Tests\Feature\Pricing;

use App\Models\Category;
use App\Models\CategoryPricingRule;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Pricing\CategoryPricingService;
use App\Services\Pricing\PricingService;
use App\Services\ProductUpdateService;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_uses_selected_category_source_without_persisting_it(): void
    {
        $user = User::factory()->create(['role' => 'owner']);
        $category = Category::query()->create(['name' => 'Preview category']);
        $rule = CategoryPricingRule::query()->create([
            'category_id' => $category->id,
            'pricing_method' => 'percentage',
            'pricing_value' => '10.00',
            'rounding_direction' => 'up',
            'rounding_unit' => '1.00',
            'active' => true,
        ]);
        $product = $this->product($category, 'Preview product', 'product', '50.00', '15.00');

        $this->actingAs($user)
            ->getJson(route('pricing-management.show', $product).'?pricing_source=category')
            ->assertOk()
            ->assertJsonPath('pricing_source', 'category')
            ->assertJsonPath('category_rule.id', $rule->id)
            ->assertJsonPath('suggested_price', '11.00');

        $fresh = $product->fresh();
        $this->assertSame('product', $fresh->pricing_source);
        $this->assertSame('15.00', $fresh->selling_price);
        $this->assertDatabaseCount('product_price_histories', 0);
    }

    public function test_preview_rejects_category_source_when_the_product_category_has_no_active_rule(): void
    {
        $user = User::factory()->create(['role' => 'owner']);
        $category = Category::query()->create(['name' => 'Unconfigured preview category']);
        $product = $this->product($category, 'Unconfigured preview product', 'product', '50.00', '15.00');

        $this->actingAs($user)
            ->getJson(route('pricing-management.show', $product).'?pricing_source=category')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'หมวดนี้ยังไม่ได้ตั้งค่าราคา');

        $this->assertSame('product', $product->fresh()->pricing_source);
        $this->assertDatabaseCount('product_price_histories', 0);
    }

    public function test_preview_uses_temporary_product_specific_values_without_persisting_them(): void
    {
        $user = User::factory()->create(['role' => 'owner']);
        $category = Category::query()->create(['name' => 'Product preview category']);
        $product = $this->product($category, 'Product preview', 'product', '50.00', '15.00');

        $this->actingAs($user)
            ->getJson(route('pricing-management.show', $product).'?'.http_build_query([
                'pricing_source' => 'product',
                'pricing_method' => 'percentage',
                'pricing_value' => '30.00',
                'rounding_direction' => 'up',
                'rounding_unit' => '1.00',
            ]))
            ->assertOk()
            ->assertJsonPath('pricing_source', 'product')
            ->assertJsonPath('suggested_price', '13.00');

        $this->assertSame('50.00', $product->fresh()->pricing_value);
        $this->assertSame('15.00', $product->fresh()->selling_price);
        $this->assertDatabaseCount('product_price_histories', 0);
    }

    public function test_preview_uses_temporary_fixed_price_without_persisting_it(): void
    {
        $user = User::factory()->create(['role' => 'owner']);
        $category = Category::query()->create(['name' => 'Fixed preview category']);
        $product = $this->product($category, 'Fixed preview', 'product', '50.00', '15.00');

        $this->actingAs($user)
            ->getJson(route('pricing-management.show', $product).'?'.http_build_query([
                'pricing_source' => 'fixed',
                'pricing_method' => 'manual',
                'pricing_value' => '18.00',
            ]))
            ->assertOk()
            ->assertJsonPath('pricing_source', 'fixed')
            ->assertJsonPath('pricing_method', 'manual')
            ->assertJsonPath('suggested_price', '18.00');

        $this->assertSame('product', $product->fresh()->pricing_source);
        $this->assertSame('15.00', $product->fresh()->selling_price);
        $this->assertDatabaseCount('product_price_histories', 0);
    }

    public function test_preview_rejects_a_browser_category_that_does_not_match_the_product(): void
    {
        $user = User::factory()->create(['role' => 'owner']);
        $productCategory = Category::query()->create(['name' => 'Product category']);
        $browserCategory = Category::query()->create(['name' => 'Browser category']);
        CategoryPricingRule::query()->create([
            'category_id' => $browserCategory->id,
            'pricing_method' => 'percentage',
            'pricing_value' => '99.00',
            'rounding_direction' => 'up',
            'rounding_unit' => '1.00',
            'active' => true,
        ]);
        $product = $this->product($productCategory, 'Cross category preview', 'product', '50.00', '15.00');

        $this->actingAs($user)
            ->getJson(route('pricing-management.show', $product).'?'.http_build_query([
                'pricing_source' => 'category',
                'category_id' => $browserCategory->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'หมวดสินค้าที่ส่งมาไม่ตรงกับสินค้า');
    }

    public function test_preview_requires_pricing_permission(): void
    {
        $category = Category::query()->create(['name' => 'Restricted preview category']);
        $product = $this->product($category, 'Restricted preview', 'product', '50.00', '15.00');

        $this->actingAs(User::factory()->create(['role' => 'manager']))
            ->getJson(route('pricing-management.show', $product).'?pricing_source=category')
            ->assertForbidden();
    }

    public function test_category_source_resolves_category_rule_without_affecting_product_source(): void
    {
        $category = Category::query()->create(['name' => 'Category pricing']);
        $rule = CategoryPricingRule::query()->create([
            'category_id' => $category->id,
            'pricing_method' => 'percentage',
            'pricing_value' => '30.00',
            'rounding_direction' => 'up',
            'rounding_unit' => '1.00',
            'active' => true,
        ]);
        $categoryProduct = $this->product($category, 'Category product', 'category', '5.00', '15.00');
        $productOverride = $this->product($category, 'Override product', 'product', '10.00', '11.00');

        $service = app(PricingService::class);
        $categoryPreview = $service->calculate($categoryProduct->fresh('category.categoryPricingRule'));
        $overridePreview = $service->calculate($productOverride->fresh('category.categoryPricingRule'));

        $this->assertSame($rule->id, $categoryPreview['category_rule']['id']);
        $this->assertSame('category', $categoryPreview['pricing_source']);
        $this->assertSame('13.00', $categoryPreview['suggested_price']);
        $this->assertSame('11.00', $overridePreview['suggested_price']);
    }

    public function test_owner_can_manage_rules_and_counts_are_derived_from_product_source(): void
    {
        $user = User::factory()->create(['role' => 'owner']);
        $category = Category::query()->create(['name' => 'Managed category']);
        $this->actingAs($user);

        $response = $this->postJson(route('pricing-management.category-rules.store'), [
            'category_id' => $category->id,
            'pricing_method' => 'percentage',
            'pricing_value' => '25.00',
            'rounding_direction' => 'up',
            'rounding_unit' => '1.00',
            'active' => true,
        ]);

        $response->assertCreated();
        $this->product($category, 'Category user', 'category', '10.00', '15.00');
        $this->product($category, 'Product user', 'product', '10.00', '15.00');

        $this->getJson(route('pricing-management.category-rules'))
            ->assertOk()
            ->assertJsonFragment(['total_products' => 2, 'category_products' => 1]);
    }

    public function test_manager_cannot_manage_category_pricing_rules(): void
    {
        $category = Category::query()->create(['name' => 'Restricted category']);

        $this->actingAs(User::factory()->create(['role' => 'manager']))
            ->postJson(route('pricing-management.category-rules.store'), [
                'category_id' => $category->id,
                'pricing_method' => 'percentage',
                'pricing_value' => '25.00',
            ])
            ->assertForbidden();
    }

    public function test_existing_pricing_management_page_keeps_table_and_adds_only_category_entry_point(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get(route('pricing-management.index'))
            ->assertOk()
            ->assertSee('ตั้งราคาตามหมวด')
            ->assertSee('id="pricingTable"', false)
            ->assertSee('id="categoryRulesModal"', false)
            ->assertDontSee('Scheduled Price');
    }

    public function test_category_rule_cannot_be_disabled_while_products_use_it(): void
    {
        $category = Category::query()->create(['name' => 'Protected category']);
        $rule = CategoryPricingRule::query()->create([
            'category_id' => $category->id,
            'pricing_method' => 'percentage',
            'pricing_value' => '10.00',
            'active' => true,
        ]);
        $this->product($category, 'Protected category product', 'category', '10.00', '15.00');

        $this->expectException(\DomainException::class);
        app(CategoryPricingService::class)->disable($rule);
    }

    public function test_category_review_updates_price_and_records_category_history_metadata(): void
    {
        $user = User::factory()->create(['role' => 'owner']);
        $category = Category::query()->create(['name' => 'History category']);
        $rule = CategoryPricingRule::query()->create([
            'category_id' => $category->id,
            'pricing_method' => 'percentage',
            'pricing_value' => '25.00',
            'rounding_direction' => 'up',
            'rounding_unit' => '1.00',
            'active' => true,
        ]);
        $product = $this->product($category, 'History category product', 'product', '10.00', '15.00');

        $this->actingAs($user)
            ->putJson(route('pricing-management.update', $product), [
                'pricing_method' => 'category',
                'pricing_value' => '25.00',
                'rounding_direction' => 'up',
                'rounding_unit' => '1.00',
                'suggested_price' => '999.00',
            ])
            ->assertOk()
            ->assertJsonPath('final_price', '13.00');

        $history = $product->priceHistories()->latest('id')->first();

        $this->assertNotNull($history);
        $this->assertSame('category', $history->pricing_source);
        $this->assertSame($rule->id, $history->category_pricing_rule_id);
        $this->assertSame($category->id, $history->category_id);
        $this->assertSame('History category', $history->category_name_snapshot);
        $this->assertEquals('25.00', $history->category_rule_value);
        $this->assertEquals('13.00', $history->new_price);
    }

    public function test_purchase_changes_cost_and_pending_review_without_changing_selling_price_for_category_source(): void
    {
        $category = Category::query()->create(['name' => 'Purchase category']);
        CategoryPricingRule::query()->create([
            'category_id' => $category->id,
            'pricing_method' => 'percentage',
            'pricing_value' => '30.00',
            'rounding_direction' => 'up',
            'rounding_unit' => '1.00',
            'active' => true,
        ]);
        $product = $this->product($category, 'Purchase category product', 'category', '10.00', '15.00');
        $product->update(['stock_qty' => '10.0000', 'pricing_reviewed_cost' => '10.00']);
        $supplier = Supplier::query()->create(['name' => 'Category pricing supplier', 'active' => true]);

        app(PurchaseService::class)->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-07-28',
            'items' => [[
                'product_id' => $product->id,
                'qty' => '10.0000',
                'cost_price' => '20.00',
            ]],
        ]);

        $fresh = $product->fresh('category.categoryPricingRule');
        $preview = app(PricingService::class)->calculate($fresh);

        $this->assertSame('15.00', $fresh->selling_price);
        $this->assertSame('15.00', $fresh->cost_price);
        $this->assertSame('pending_review', $preview['status']);
        $this->assertSame('20.00', $preview['suggested_price']);
    }

    public function test_category_source_cannot_move_to_a_category_without_an_active_rule(): void
    {
        $oldCategory = Category::query()->create(['name' => 'Old category']);
        $newCategory = Category::query()->create(['name' => 'New category']);
        CategoryPricingRule::query()->create([
            'category_id' => $oldCategory->id,
            'pricing_method' => 'percentage',
            'pricing_value' => '10.00',
            'active' => true,
        ]);
        $product = $this->product($oldCategory, 'Category move product', 'category', '10.00', '15.00');

        $this->expectException(\DomainException::class);
        app(ProductUpdateService::class)->update($product, [
            'category_id' => $newCategory->id,
            'unit_id' => null,
            'name' => $product->name,
            'cost_price' => $product->cost_price,
            'selling_price' => $product->selling_price,
            'stock_qty' => $product->stock_qty,
            'minimum_stock' => $product->minimum_stock,
            'vat_enabled' => false,
            'active' => true,
        ]);
    }

    private function product(Category $category, string $name, string $source, string $value, string $sellingPrice): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'cost_price' => '10.00',
            'selling_price' => $sellingPrice,
            'pricing_source' => $source,
            'pricing_method' => 'percentage',
            'pricing_value' => $value,
            'rounding_direction' => 'up',
            'rounding_unit' => '1.00',
            'pricing_reviewed_cost' => '10.00',
            'stock_qty' => '10.0000',
            'minimum_stock' => '0.0000',
            'active' => true,
        ]);
    }
}
