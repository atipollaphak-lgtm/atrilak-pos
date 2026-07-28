<?php

namespace Tests\Feature\Sales;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleNotesTest extends TestCase
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

    public function test_v3_sale_creation_persists_notes_through_the_sale_service(): void
    {
        $product = $this->product('Sale notes product');

        $this->postJson(route('sales.v3.store'), $this->v2Payload($product, ['notes' => 'Call before delivery']))
            ->assertOk();

        $this->assertSame('Call before delivery', Sale::query()->sole()->notes);
    }

    public function test_blank_notes_are_normalized_to_null_and_missing_notes_remain_valid(): void
    {
        $product = $this->product('Blank notes product');

        $this->postJson(route('sales.v3.store'), $this->v2Payload($product, ['notes' => '   ']))
            ->assertOk();

        $this->assertNull(Sale::query()->sole()->notes);
    }

    public function test_existing_v1_and_v2_payloads_without_notes_remain_valid(): void
    {
        $v1Product = $this->product('V1 no notes product');
        $this->postJson(route('sales.store'), $this->v1Payload($v1Product))->assertOk();

        $v2Product = $this->product('V2 no notes product');
        $this->postJson(route('sales.v2.store'), $this->v2Payload($v2Product))->assertOk();

        $this->assertDatabaseCount('sales', 2);
        $this->assertDatabaseMissing('sales', ['notes' => '']);
    }

    public function test_notes_have_a_reasonable_maximum_length(): void
    {
        $product = $this->product('Long notes product');

        $this->postJson(route('sales.v3.store'), $this->v2Payload($product, ['notes' => str_repeat('x', 2001)]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('notes');
    }

    private function product(string $name): Product
    {
        $category = Category::query()->firstOrCreate(['name' => 'Sale notes category']);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'cost_price' => '50.00',
            'selling_price' => '100.00',
            'stock_qty' => '10.0000',
            'minimum_stock' => '0.0000',
            'active' => true,
        ]);
    }

    private function v1Payload(Product $product): array
    {
        return [
            'idempotency_key' => str()->uuid()->toString(),
            'sale_date' => '2026-07-28',
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
            'cash_amount' => '100.00',
            'promptpay_amount' => '0.00',
            'received_amount' => '100.00',
            'product_id' => [$product->id],
            'product_unit_id' => [''],
            'qty' => ['1.00'],
            'selling_price' => ['100.00'],
        ];
    }

    private function v2Payload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'idempotency_key' => str()->uuid()->toString(),
            'sale_date' => '2026-07-28',
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
            'cash_amount' => '100.00',
            'promptpay_amount' => '0.00',
            'received_amount' => '100.00',
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => null,
                'qty' => '1.00',
                'selling_price' => '100.00',
            ]],
        ], $overrides);
    }
}
