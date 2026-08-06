<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleV3PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_open_v3_without_changing_v2_route(): void
    {
        Customer::query()->create([
            'code' => 'CUS-TEST-TAX',
            'name' => 'TEST Tax Customer',
            'tax_number' => '0100000000001',
            'active' => true,
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'cashier']))
            ->get('/sales-v3');

        $response->assertOk()
            ->assertSee('id="pos-v3"', false)
            ->assertSee('sale-v3.css', false)
            ->assertSee('sale-v3.js', false)
            ->assertSee('v3-product-search', false)
            ->assertSee('data-document-url-template=', false)
            ->assertSee('id="v3-hold-bill"', false)
            ->assertSee('id="v3-delivery"', false)
            ->assertSee('id="v3-pickup-button"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('data-backdrop="static"', false)
            ->assertSee('data-keyboard="false"', false)
            ->assertSee('data-tax-number="0100000000001"', false)
            ->assertSee('/sales-v3/customers', false)
            ->assertSee('v3-open-customer-create', false)
            ->assertSee('v3-new-customer-branch-number', false)
            ->assertSee('v3-price-zone-select', false);

        $this->get('/sales-v2')->assertOk();
    }

    public function test_guest_cannot_open_v3(): void
    {
        $this->get('/sales-v3')->assertRedirect();
    }

    public function test_pos_v3_uses_the_public_disk_url_for_product_images(): void
    {
        $category = Category::query()->create([
            'name' => 'POS V3 Images',
            'code_prefix' => 'PVI',
            'barcode_prefix' => '009',
            'active' => true,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'POS V3 Image Product',
            'product_code' => 'POS-V3-IMAGE',
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'stock_qty' => '2.0000',
            'minimum_stock' => '0.0000',
            'active' => true,
            'image_path' => 'products/v3-image.jpg',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'cashier']))
            ->get('/sales-v3')
            ->assertOk()
            ->assertSee('http://localhost/storage/products/v3-image.jpg', false)
            ->assertDontSee('src="http://localhost/products/v3-image.jpg"', false);
    }
}
