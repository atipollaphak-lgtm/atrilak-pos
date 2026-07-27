<?php

namespace Tests\Feature\Sales;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Sales\SaleDecimalService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleRequestShapeTest extends TestCase
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

    public function test_v1_rejects_required_array_length_mismatch(): void
    {
        $product = $this->product('V1 required mismatch');
        $payload = $this->v1Payload($product);
        $payload['product_id'][] = $product->id;

        $this->post(route('sales.store'), $payload)
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_v1_rejects_optional_array_misalignment(): void
    {
        $product = $this->product('V1 optional mismatch');
        $payload = $this->v1Payload($product);
        $payload['product_id'][] = $product->id;
        $payload['qty'][] = '1.00';
        $payload['selling_price'][] = '10.00';
        $payload['product_unit_id'] = [null];

        $this->post(route('sales.store'), $payload)
            ->assertSessionHasErrors('product_unit_id');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_v1_rejects_partial_row(): void
    {
        $product = $this->product('V1 partial row');
        $payload = $this->v1Payload($product);
        $payload['product_id'][] = $product->id;
        $payload['qty'][] = '';
        $payload['selling_price'][] = '10.00';

        $this->post(route('sales.store'), $payload)
            ->assertSessionHasErrors('normalized_items.1.qty');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_v1_allows_only_fully_blank_trailing_rows(): void
    {
        $product = $this->product('V1 trailing rows');
        $payload = $this->v1Payload($product);
        $payload['product_id'] = [$product->id, '', ''];
        $payload['qty'] = ['2.00', '', ''];
        $payload['selling_price'] = ['10.00', '', ''];
        $payload['promptpay_amount'] = '20.00';

        $this->post(route('sales.store'), $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(1, Sale::query()->sole()->items);
    }

    public function test_v1_aligned_optional_product_unit_keeps_legacy_factor_one(): void
    {
        $product = $this->product('V1 legacy unit shape');
        $payload = $this->v1Payload($product);
        $payload['product_unit_id'] = [999999];

        $this->post(route('sales.store'), $payload)->assertOk();

        $item = Sale::query()->sole()->items()->sole();
        $this->assertNull($item->product_unit_id);
        $this->assertSame('1.0000', $item->conversion_rate_used);
        $this->assertSame('1.0000', $item->base_qty);
    }

    public function test_v1_rejects_internal_blank_row(): void
    {
        $first = $this->product('V1 first item');
        $third = $this->product('V1 third item');
        $payload = $this->v1Payload($first);
        $payload['product_id'] = [$first->id, '', $third->id];
        $payload['qty'] = ['1.00', '', '1.00'];
        $payload['selling_price'] = ['10.00', '', '10.00'];

        $this->post(route('sales.store'), $payload)
            ->assertSessionHasErrors([
                'normalized_items.1.product_id' => 'กรุณาเลือกสินค้ารายการที่ 2',
            ]);

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_update_rejects_required_array_length_mismatch(): void
    {
        [$sale, $product] = $this->existingSale();
        $payload = $this->updatePayload($sale, $product);
        $payload['qty'][] = '1.00';

        $this->put(route('sales.update', $sale), $payload)
            ->assertSessionHasErrors('items');

        $this->assertSame('1.00', $sale->fresh()->items()->sole()->qty);
    }

    public function test_update_rejects_sale_item_id_misalignment(): void
    {
        [$sale, $product] = $this->existingSale();
        $payload = $this->updatePayload($sale, $product);
        $payload['product_id'][] = $product->id;
        $payload['product_unit_id'][] = null;
        $payload['qty'][] = '1.00';
        $payload['selling_price'][] = '10.00';

        $this->put(route('sales.update', $sale), $payload)
            ->assertSessionHasErrors('sale_item_id');

        $this->assertCount(1, $sale->fresh()->items);
    }

    public function test_update_rejects_partial_row(): void
    {
        [$sale, $product] = $this->existingSale();
        $payload = $this->updatePayload($sale, $product);
        $payload['sale_item_id'][] = null;
        $payload['product_unit_id'][] = null;
        $payload['product_id'][] = $product->id;
        $payload['qty'][] = '';
        $payload['selling_price'][] = '10.00';

        $this->put(route('sales.update', $sale), $payload)
            ->assertSessionHasErrors('normalized_items.1.qty');

        $this->assertCount(1, $sale->fresh()->items);
    }

    public function test_update_allows_only_fully_blank_trailing_rows(): void
    {
        [$sale, $product] = $this->existingSale();
        $payload = $this->updatePayload($sale, $product);
        $payload['sale_item_id'] = [$sale->items()->sole()->id, null, null];
        $payload['product_unit_id'] = [null, null, null];
        $payload['product_id'] = [$product->id, '', ''];
        $payload['qty'] = ['2.00', '', ''];
        $payload['selling_price'] = ['10.00', '', ''];
        $payload['promptpay_amount'] = '20.00';

        $this->put(route('sales.update', $sale), $payload)
            ->assertRedirect(route('sales.show', $sale));

        $this->assertSame('2.00', $sale->fresh()->items()->sole()->qty);
    }

    public function test_update_rejects_internal_blank_row(): void
    {
        [$sale, $product] = $this->existingSale();
        $third = $this->product('Update third item');
        $payload = $this->updatePayload($sale, $product);
        $payload['sale_item_id'] = [$sale->items()->sole()->id, null, null];
        $payload['product_unit_id'] = [null, null, null];
        $payload['product_id'] = [$product->id, '', $third->id];
        $payload['qty'] = ['1.00', '', '1.00'];
        $payload['selling_price'] = ['10.00', '', '10.00'];

        $this->put(route('sales.update', $sale), $payload)
            ->assertSessionHasErrors([
                'normalized_items.1.product_id' => 'กรุณาเลือกสินค้ารายการที่ 2',
            ]);

        $this->assertCount(1, $sale->fresh()->items);
    }

    public function test_v2_rejects_items_when_not_array(): void
    {
        $this->postJson(route('sales.v2.store'), [
            'idempotency_key' => $this->key(11),
            'items' => 'invalid',
        ])->assertStatus(422)->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_v2_rejects_scalar_item(): void
    {
        $this->postJson(route('sales.v2.store'), [
            'idempotency_key' => $this->key(12),
            'items' => ['invalid'],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_v2_rejects_partial_nested_item(): void
    {
        $product = $this->product('V2 partial item');

        $this->postJson(route('sales.v2.store'), [
            'idempotency_key' => $this->key(13),
            'items' => [[
                'product_id' => $product->id,
                'selling_price' => '10.00',
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.qty');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_v2_preserves_original_item_order(): void
    {
        $first = $this->product('Order first');
        $second = $this->product('Order second');

        $this->postJson(route('sales.v2.store'), $this->v2Payload([
            $this->line($second, '1.00'),
            $this->line($first, '2.00'),
        ], 14))->assertOk();

        $this->assertSame(
            [$second->id, $first->id],
            Sale::query()->sole()->items()->orderBy('id')->pluck('product_id')->all()
        );
    }

    public function test_validation_errors_use_human_readable_item_numbers(): void
    {
        $first = $this->product('Readable first');
        $second = $this->product('Readable second');
        $payload = $this->v1Payload($first);
        $payload['product_id'][] = $second->id;
        $payload['qty'][] = 'invalid';
        $payload['selling_price'][] = '10.00';

        $this->post(route('sales.store'), $payload)->assertSessionHasErrors([
            'normalized_items.1.qty' => 'จำนวนสินค้ารายการที่ 2 ไม่ถูกต้อง',
        ]);

        $response = $this->postJson(route('sales.v2.store'), $this->v2Payload([
            $this->line($first, '1.00'),
            $this->line($second, 'invalid'),
        ], 15));

        $response->assertStatus(422)->assertJsonValidationErrors('items.1.qty');
        $this->assertSame(
            'จำนวนสินค้ารายการที่ 2 ไม่ถูกต้อง',
            $response->json('errors')['items.1.qty'][0] ?? null
        );
    }

    public function test_duplicate_lines_remain_allowed(): void
    {
        $product = $this->product('Duplicate item');

        $this->postJson(route('sales.v2.store'), $this->v2Payload([
            $this->line($product, '1.00'),
            $this->line($product, '2.00'),
        ], 16))->assertOk();

        $this->assertCount(2, Sale::query()->sole()->items);
    }

    public function test_normalized_payload_does_not_shift_indexes(): void
    {
        $first = $this->product('Index first');
        $third = $this->product('Index third');
        $payload = $this->v1Payload($first);
        $payload['product_id'] = [$first->id, '', $third->id];
        $payload['qty'] = ['1.00', '', ''];
        $payload['selling_price'] = ['10.00', '', '10.00'];

        $this->post(route('sales.store'), $payload)->assertSessionHasErrors([
            'normalized_items.1.product_id',
            'normalized_items.2.qty',
        ]);

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_parallel_arrays_reject_equal_counts_with_different_indexes(): void
    {
        $product = $this->product('Shifted array indexes');
        $payload = $this->v1Payload($product);
        $payload['product_id'] = [0 => $product->id, 1 => $product->id];
        $payload['qty'] = [0 => '1.00', 2 => '1.00'];
        $payload['selling_price'] = [0 => '10.00', 1 => '10.00'];

        $this->post(route('sales.store'), $payload)
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_validation_failure_preserves_all_rows(): void
    {
        $first = $this->product('Restore first');
        $third = $this->product('Restore third');
        $payload = $this->v1Payload($first);
        $payload['product_id'] = [$first->id, '', $third->id];
        $payload['qty'] = ['1.00', '', 'invalid'];
        $payload['selling_price'] = ['10.00', '', '30.00'];

        $this->post(route('sales.store'), $payload)
            ->assertSessionHasErrors()
            ->assertSessionHasInput('product_id', $payload['product_id'])
            ->assertSessionHasInput('qty', $payload['qty'])
            ->assertSessionHasInput('selling_price', $payload['selling_price']);
    }

    private function v1Payload(Product $product): array
    {
        return [
            'idempotency_key' => $this->key(1),
            'sale_date' => '2026-07-15',
            'delivery_type' => 'pickup',
            'product_id' => [$product->id],
            'qty' => ['1.00'],
            'selling_price' => ['10.00'],
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'payment_method' => 'promptpay',
            'cash_amount' => '0.00',
            'promptpay_amount' => '10.00',
            'received_amount' => '0.00',
        ];
    }

    private function v2Payload(array $items, int $key): array
    {
        return array_merge([
            'idempotency_key' => $this->key($key),
            'sale_date' => '2026-07-15',
            'delivery_type' => 'pickup',
            'items' => $items,
            'delivery_fee' => '0.00',
            'discount' => '0.00',
        ], $this->paymentForItems($items));
    }

    private function updatePayload(Sale $sale, Product $product): array
    {
        return [
            'revision' => 1,
            'sale_date' => '2026-07-15',
            'sale_item_id' => [$sale->items()->sole()->id],
            'product_unit_id' => [null],
            'product_id' => [$product->id],
            'qty' => ['1.00'],
            'selling_price' => ['10.00'],
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'payment_method' => 'promptpay',
            'cash_amount' => '0.00',
            'promptpay_amount' => '10.00',
            'received_amount' => '0.00',
        ];
    }

    private function line(Product $product, string $qty): array
    {
        return [
            'product_id' => $product->id,
            'product_unit_id' => null,
            'qty' => $qty,
            'selling_price' => '10.00',
        ];
    }

    private function paymentForItems(array $items): array
    {
        try {
            $total = app(SaleDecimalService::class)->itemsTotal($items);
        } catch (\Throwable) {
            $total = '0.00';
        }

        return [
            'payment_method' => 'promptpay',
            'cash_amount' => '0.00',
            'promptpay_amount' => $total,
            'received_amount' => '0.00',
        ];
    }

    private function existingSale(): array
    {
        $product = $this->product('Existing request-shape product', '9.0000');
        $sale = Sale::query()->create([
            'sale_no' => 'SAL-SHAPE-1',
            'sale_date' => '2026-07-15',
            'total_amount' => '10.00',
            'delivery_fee' => '0.00',
            'delivery_type' => 'pickup',
            'discount' => '0.00',
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => null,
            'conversion_rate_used' => '1.0000',
            'base_qty' => '1.0000',
            'qty' => '1.00',
            'selling_price' => '10.00',
            'cost_price' => '5.00',
            'total' => '10.00',
            'profit' => '5.00',
        ]);

        return [$sale, $product];
    }

    private function product(
        string $name,
        string $stock = '100.0000'
    ): Product {
        $category = Category::query()->firstOrCreate(['name' => 'Sale request-shape category']);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'cost_price' => '5.00',
            'selling_price' => '10.00',
            'stock_qty' => $stock,
            'minimum_stock' => '0.0000',
            'active' => true,
        ]);
    }

    private function key(int $suffix): string
    {
        return '12000000-0000-4000-8000-'.str_pad((string) $suffix, 12, '0', STR_PAD_LEFT);
    }
}
