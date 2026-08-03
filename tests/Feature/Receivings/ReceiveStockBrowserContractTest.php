<?php

namespace Tests\Feature\Receivings;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiveStockBrowserContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_page_exposes_confirm_form_and_protected_price_warning(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $supplier = Supplier::query()->create(['name' => 'Supplier Browser', 'active' => true]);
        $product = Product::query()->create([
            'category_id' => Category::query()->create(['name' => 'Browser'])->id,
            'name' => 'Browser Product',
            'unit' => 'ชิ้น',
            'cost_price' => '10.00',
            'selling_price' => '20.00',
            'stock_qty' => '0.0000',
            'active' => true,
        ]);

        $preview = $this->actingAs($manager)->post(route('receivings.preview.store'), [
            'source' => 'supplier',
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-08-03',
            'items' => [[
                'product_id' => $product->id,
                'qty' => '2.0000',
                'cost_price' => '12.00',
            ]],
        ]);

        $preview->assertRedirect();
        $this->actingAs($manager)
            ->get($preview->headers->get('Location'))
            ->assertOk()
            ->assertSee('ไม่เปลี่ยน Selling Price')
            ->assertSee('ยืนยันรับสินค้า')
            ->assertSee('idempotency_key');
    }

    public function test_product_search_returns_active_product_identifiers(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $product = Product::query()->create([
            'category_id' => Category::query()->create(['name' => 'Search'])->id,
            'name' => 'ค้นหาด้วยรหัส',
            'product_code' => 'P-SEARCH-01',
            'barcode' => '885000000001',
            'unit' => 'ชิ้น',
            'cost_price' => '10.00',
            'selling_price' => '20.00',
            'stock_qty' => '1.0000',
            'active' => true,
        ]);

        $this->actingAs($manager)
            ->getJson(route('receivings.products.search', ['q' => 'P-SEARCH-01']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.product_code', 'P-SEARCH-01')
            ->assertJsonPath('data.0.selling_price', '20.00');
    }
}
