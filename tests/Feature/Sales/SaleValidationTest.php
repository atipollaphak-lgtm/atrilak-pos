<?php

namespace Tests\Feature\Sales;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerDeliveryAddress;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\Unit;
use App\Services\SaleService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SaleValidationTest extends TestCase
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

    public function test_v2_rejects_missing_product_customer_and_technician_without_writes(): void
    {
        $product = $this->product();
        $payload = $this->v2Payload($product);

        foreach ([
            ['path' => 'items.0.product_id', 'payload' => array_replace_recursive($payload, [
                'items' => [['product_id' => 999999]],
            ])],
            ['path' => 'customer_id', 'payload' => array_replace($payload, [
                'customer_id' => 999999,
            ])],
            ['path' => 'technician_id', 'payload' => array_replace($payload, [
                'technician_id' => 999999,
            ])],
        ] as $case) {
            $response = $this->postJson(route('sales.v2.store'), $case['payload']);

            $response->assertStatus(422)
                ->assertJsonPath('message', 'ข้อมูลการขายไม่ถูกต้อง')
                ->assertJsonValidationErrors($case['path']);
            $this->assertNoSaleWrites();
        }
    }

    #[DataProvider('invalidItemValues')]
    public function test_v2_rejects_invalid_quantity_and_price_values(
        string $field,
        mixed $value
    ): void {
        $product = $this->product();
        $payload = $this->v2Payload($product);
        $payload['items'][0][$field] = $value;

        $this->postJson(route('sales.v2.store'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.'.$field);

        $this->assertNoSaleWrites();
    }

    public static function invalidItemValues(): array
    {
        return [
            'zero quantity' => ['qty', 0],
            'negative quantity' => ['qty', -1],
            'non numeric quantity' => ['qty', 'not-a-number'],
            'quantity over precision' => ['qty', '1.234'],
            'zero price' => ['selling_price', 0],
            'negative price' => ['selling_price', -1],
            'non numeric price' => ['selling_price', 'not-a-number'],
            'price over precision' => ['selling_price', '10.999'],
        ];
    }

    public function test_v2_rejects_invalid_header_values_empty_items_and_snapshot_fields(): void
    {
        $product = $this->product();

        foreach ([
            ['field' => 'discount', 'value' => -1],
            ['field' => 'delivery_fee', 'value' => -1],
            ['field' => 'sale_date', 'value' => '14/07/2026'],
        ] as $case) {
            $payload = $this->v2Payload($product);
            $payload[$case['field']] = $case['value'];

            $this->postJson(route('sales.v2.store'), $payload)
                ->assertStatus(422)
                ->assertJsonValidationErrors($case['field']);
        }

        $this->postJson(route('sales.v2.store'), array_replace(
            $this->v2Payload($product),
            ['items' => []]
        ))->assertStatus(422)->assertJsonValidationErrors('items');

        $payload = $this->v2Payload($product);
        $payload['items'][0]['base_qty'] = '99.0000';
        $payload['items'][0]['conversion_rate_used'] = '99.0000';

        $this->postJson(route('sales.v2.store'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'items.0.base_qty',
                'items.0.conversion_rate_used',
            ]);

        $this->assertNoSaleWrites();
    }

    public function test_v2_returns_json_400_for_malformed_json(): void
    {
        $response = $this->call(
            'POST',
            route('sales.v2.store'),
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: '{"items": ['
        );

        $response->assertStatus(400)
            ->assertJsonPath('message', 'รูปแบบ JSON ไม่ถูกต้อง')
            ->assertJsonStructure(['message', 'errors']);
        $this->assertNoSaleWrites();
    }

    public function test_delivery_address_must_belong_to_selected_customer(): void
    {
        $product = $this->product();
        $firstCustomer = Customer::create(['name' => 'First customer']);
        $secondCustomer = Customer::create(['name' => 'Second customer']);
        $address = CustomerDeliveryAddress::create([
            'customer_id' => $firstCustomer->id,
            'name' => 'First address',
            'address' => 'Test address',
        ]);
        $payload = array_replace($this->v2Payload($product), [
            'customer_id' => $secondCustomer->id,
            'customer_delivery_address_id' => $address->id,
        ]);

        $this->postJson(route('sales.v2.store'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('customer_delivery_address_id');

        $this->assertNoSaleWrites();
    }

    public function test_v1_discards_fully_blank_trailing_row_and_uses_legacy_factor_one(): void
    {
        $product = $this->product('V1 product', '10.0000');

        $response = $this->post(route('sales.store'), [
            'idempotency_key' => '10000000-0000-4000-8000-000000000001',
            'sale_date' => '2026-07-14',
            'delivery_type' => 'pickup',
            'product_id' => [$product->id, ''],
            'qty' => ['2.00', ''],
            'selling_price' => ['25.00', ''],
            'payment_method' => 'promptpay',
            'cash_amount' => '0.00',
            'promptpay_amount' => '50.00',
            'received_amount' => '0.00',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $item = Sale::query()->sole()->items()->sole();

        $this->assertNull($item->product_unit_id);
        $this->assertSame('1.0000', $item->conversion_rate_used);
        $this->assertSame('2.0000', $item->base_qty);
        $this->assertSame('8.0000', $product->fresh()->stock_qty);
    }

    public function test_v1_rejects_mismatched_partial_and_all_blank_rows(): void
    {
        $product = $this->product();

        foreach ([
            [
                'product_id' => [$product->id, $product->id],
                'qty' => [1],
                'selling_price' => [10, 10],
            ],
            [
                'product_id' => [$product->id],
                'qty' => [1, 1],
                'selling_price' => [10, 10],
            ],
            [
                'product_id' => [$product->id, $product->id],
                'qty' => [1, 1],
                'selling_price' => [10],
            ],
            [
                'product_id' => [$product->id],
                'qty' => [''],
                'selling_price' => [10],
            ],
            [
                'product_id' => [''],
                'qty' => [''],
                'selling_price' => [''],
            ],
        ] as $payload) {
            $this->from(route('sales.index'))
                ->post(route('sales.store'), $payload)
                ->assertRedirect(route('sales.index'))
                ->assertSessionHasErrors();
            $this->assertNoSaleWrites();
        }
    }

    public function test_update_rejects_array_mismatches_before_restoring_stock(): void
    {
        [$sale, $product] = $this->existingSale();
        $before = $this->writeSnapshot();

        $payloads = [
            [
                'sale_date' => '2026-07-14',
                'product_id' => [$product->id, $product->id],
                'qty' => [1],
                'selling_price' => [10, 10],
            ],
            [
                'sale_date' => '2026-07-14',
                'product_id' => [$product->id],
                'qty' => [1, 1],
                'selling_price' => [10, 10],
            ],
            [
                'sale_date' => '2026-07-14',
                'product_id' => [$product->id, $product->id],
                'qty' => [1, 1],
                'selling_price' => [10],
            ],
            [
                'sale_date' => '2026-07-14',
                'sale_item_id' => [$sale->items()->sole()->id],
                'product_unit_id' => [null, null],
                'product_id' => [$product->id, $product->id],
                'qty' => [1, 1],
                'selling_price' => [10, 10],
            ],
            [
                'sale_date' => '2026-07-14',
                'sale_item_id' => [$sale->items()->sole()->id, null],
                'product_unit_id' => [null],
                'product_id' => [$product->id, ''],
                'qty' => [1, ''],
                'selling_price' => [10, ''],
            ],
            [
                'sale_date' => '2026-07-14',
                'product_id' => [''],
                'qty' => [''],
                'selling_price' => [''],
            ],
        ];

        foreach ($payloads as $payload) {
            $payload['revision'] = 1;
            $this->from(route('sales.edit', $sale))
                ->put(route('sales.update', $sale), $payload)
                ->assertRedirect(route('sales.edit', $sale))
                ->assertSessionHasErrors();
            $this->assertSame($before, $this->writeSnapshot());
        }
    }

    public function test_update_discards_fully_blank_trailing_row_and_uses_one_normalized_item_set(): void
    {
        [$sale, $product] = $this->existingSale();
        $item = $sale->items()->sole();

        $this->put(route('sales.update', $sale), [
            'revision' => 1,
            'sale_date' => '2026-07-14',
            'sale_item_id' => [$item->id, null],
            'product_unit_id' => [null, null],
            'product_id' => [$product->id, ''],
            'qty' => ['2.00', ''],
            'selling_price' => ['15.00', ''],
            'delivery_fee' => '0.00',
            'discount' => '0.00',
        ])->assertRedirect(route('sales.show', $sale));

        $sale->refresh();
        $this->assertCount(1, $sale->items);
        $this->assertSame('2.00', $sale->items->sole()->qty);
        $this->assertSame('8.0000', $product->fresh()->stock_qty);
    }

    public function test_duplicate_products_and_units_remain_separate_and_use_continuous_base_stock(): void
    {
        $product = $this->product('Multi unit product', '100.0000');
        $baseUnit = $this->productUnit($product, 'piece', '1.0000', true);
        $packUnit = $this->productUnit($product, 'pack', '12.0000');
        $payload = $this->v2Payload($product);
        $payload['items'] = [
            ['product_id' => $product->id, 'product_unit_id' => $baseUnit->id, 'qty' => '1.00', 'selling_price' => '10.00'],
            ['product_id' => $product->id, 'product_unit_id' => $baseUnit->id, 'qty' => '1.00', 'selling_price' => '10.00'],
            ['product_id' => $product->id, 'product_unit_id' => $packUnit->id, 'qty' => '2.00', 'selling_price' => '100.00'],
        ];
        $payload['promptpay_amount'] = '220.00';

        $this->postJson(route('sales.v2.store'), $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $sale = Sale::query()->sole();
        $this->assertCount(3, $sale->items);
        $this->assertSame('74.0000', $product->fresh()->stock_qty);
        $this->assertEquals(220.00, $sale->total_amount);

        $movements = DB::table('stock_movements')->orderBy('id')->get();
        $this->assertSame(3, $movements->count());
        $this->assertEquals(
            [['100.0000', '99.0000'], ['99.0000', '98.0000'], ['98.0000', '74.0000']],
            $movements->map(fn ($movement) => [
                number_format((float) $movement->stock_before, 4, '.', ''),
                number_format((float) $movement->stock_after, 4, '.', ''),
            ])->all()
        );
    }

    public function test_v2_rejects_invalid_product_unit_configurations_without_writes(): void
    {
        $product = $this->product('Sold product');
        $otherProduct = $this->product('Other product');
        $cases = [
            999999,
            $this->productUnit($otherProduct, 'wrong-owner', '12.0000')->id,
            $this->productUnit($product, 'inactive', '12.0000', false, false)->id,
            $this->productUnit($product, 'not-sale', '12.0000', false, true, false)->id,
            $this->productUnit($product, 'unconfirmed', '12.0000', false, true, true, false)->id,
        ];

        foreach ($cases as $productUnitId) {
            $payload = $this->v2Payload($product);
            $payload['items'][0]['product_unit_id'] = $productUnitId;

            $this->postJson(route('sales.v2.store'), $payload)->assertStatus(422);
            $this->assertNoSaleWrites();
        }
    }

    public function test_quotation_conversion_rejects_missing_and_invalid_stored_items_atomically(): void
    {
        $product = $this->product();
        $missingProduct = $this->quotation('QT-MISSING-PRODUCT');
        $missingProduct->items()->create([
            'product_id' => null,
            'qty' => 1,
            'selling_price' => 10,
            'total' => 10,
        ]);
        $quotations = [
            $this->quotation('QT-EMPTY'),
            $missingProduct,
            $this->quotation('QT-ZERO-QTY', $product, 0, 10),
            $this->quotation('QT-ZERO-PRICE', $product, 1, 0),
            $this->quotation('QT-NEGATIVE-PRICE', $product, 1, -1),
        ];

        foreach ($quotations as $quotation) {
            $this->from(route('quotations.show', $quotation))
                ->post(route('quotations.convert', $quotation))
                ->assertRedirect(route('quotations.show', $quotation))
                ->assertSessionHas('error');

            $this->assertSame('draft', $quotation->fresh()->status);
            $this->assertNoSaleWrites();
        }
    }

    public function test_v2_unexpected_exception_returns_generic_500_without_internal_details(): void
    {
        $product = $this->product();
        $service = Mockery::mock(SaleService::class);
        $service->shouldReceive('createSale')
            ->once()
            ->andThrow(new RuntimeException('secret SQL and stack detail'));
        $this->app->instance(SaleService::class, $service);

        $response = $this->postJson(route('sales.v2.store'), $this->v2Payload($product));

        $response->assertStatus(500)
            ->assertJsonPath('message', 'ไม่สามารถบันทึกการขายได้ กรุณาลองใหม่อีกครั้ง');
        $this->assertStringNotContainsString('secret SQL', $response->getContent());
        $this->assertNoSaleWrites();
    }

    public function test_update_unexpected_exception_redirects_with_generic_message_without_writes(): void
    {
        [$sale, $product] = $this->existingSale();
        $item = $sale->items()->sole();
        $before = $this->writeSnapshot();
        $service = Mockery::mock(SaleService::class);
        $service->shouldReceive('updateSale')
            ->once()
            ->andThrow(new RuntimeException('secret SQL and stack detail'));
        $this->app->instance(SaleService::class, $service);

        $response = $this->from(route('sales.edit', $sale))->put(route('sales.update', $sale), [
            'revision' => 1,
            'sale_date' => '2026-07-14',
            'sale_item_id' => [$item->id],
            'product_unit_id' => [null],
            'product_id' => [$product->id],
            'qty' => ['1.00'],
            'selling_price' => ['10.00'],
            'delivery_fee' => '0.00',
            'discount' => '0.00',
        ]);

        $response->assertRedirect(route('sales.edit', $sale))
            ->assertSessionHas('error', 'ไม่สามารถแก้ไขการขายได้ กรุณาลองใหม่อีกครั้ง');
        $this->assertStringNotContainsString('secret SQL', (string) session('error'));
        $this->assertSame($before, $this->writeSnapshot());
    }

    private function v2Payload(Product $product): array
    {
        return [
            'idempotency_key' => '20000000-0000-4000-8000-'.str_pad((string) $product->id, 12, '0', STR_PAD_LEFT),
            'sale_date' => '2026-07-14',
            'delivery_type' => 'pickup',
            'discount' => '0.00',
            'delivery_fee' => '0.00',
            'payment_method' => 'promptpay',
            'cash_amount' => '0.00',
            'promptpay_amount' => '10.00',
            'received_amount' => '0.00',
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => null,
                'qty' => '1.00',
                'selling_price' => '10.00',
            ]],
        ];
    }

    private function product(string $name = 'Validation product', string $stock = '100.0000'): Product
    {
        $category = Category::firstOrCreate(['name' => 'Validation category']);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'cost_price' => '5.00',
            'selling_price' => '10.00',
            'stock_qty' => $stock,
            'minimum_stock' => 0,
            'active' => true,
        ]);
    }

    private function productUnit(
        Product $product,
        string $code,
        string $rate,
        bool $isBase = false,
        bool $active = true,
        bool $isSale = true,
        bool $confirmed = true
    ): ProductUnit {
        $unit = Unit::create([
            'code' => $code,
            'name' => $code,
            'short_name' => $code,
            'active' => true,
        ]);

        return ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_rate' => $rate,
            'conversion_confirmed_at' => ! $isBase && $confirmed ? now() : null,
            'is_base_unit' => $isBase,
            'is_purchase_unit' => true,
            'is_sale_unit' => $isSale,
            'active' => $active,
        ]);
    }

    private function existingSale(): array
    {
        $product = $this->product('Existing sale product', '9.0000');
        $sale = Sale::create([
            'sale_no' => 'SAL-VALIDATION-1',
            'sale_date' => '2026-07-13',
            'total_amount' => '10.00',
            'delivery_fee' => '0.00',
            'delivery_type' => 'pickup',
            'discount' => '0.00',
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'qty' => '1.00',
            'conversion_rate_used' => '1.0000',
            'base_qty' => '1.0000',
            'selling_price' => '10.00',
            'cost_price' => '5.00',
            'total' => '10.00',
            'profit' => '5.00',
        ]);

        return [$sale, $product];
    }

    private function quotation(
        string $number,
        ?Product $product = null,
        int $qty = 1,
        int $price = 10
    ): Quotation {
        $quotation = Quotation::create([
            'quotation_no' => $number,
            'quotation_date' => '2026-07-14',
            'total_amount' => $qty * $price,
            'status' => 'draft',
        ]);

        if ($product) {
            $quotation->items()->create([
                'product_id' => $product->id,
                'qty' => $qty,
                'selling_price' => $price,
                'total' => $qty * $price,
            ]);
        }

        return $quotation;
    }

    private function assertNoSaleWrites(): void
    {
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('technician_commissions', 0);
    }

    private function writeSnapshot(): array
    {
        return [
            'sales' => DB::table('sales')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'sale_items' => DB::table('sale_items')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'products' => DB::table('products')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'stock_movements' => DB::table('stock_movements')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'commissions' => DB::table('technician_commissions')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }
}
