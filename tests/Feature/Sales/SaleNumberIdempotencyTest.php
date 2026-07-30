<?php

namespace Tests\Feature\Sales;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Technician;
use App\Models\TechnicianCommissionRule;
use App\Services\Sales\SaleIdempotencyService;
use App\Services\Sales\SaleNumberService;
use App\Services\SaleService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaleNumberIdempotencyTest extends TestCase
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

    public function test_same_key_and_payload_replays_the_original_sale_without_duplicate_writes(): void
    {
        $product = $this->product('Replay product', '10.0000');
        $technician = Technician::create(['name' => 'Replay technician', 'active' => true]);
        TechnicianCommissionRule::create([
            'product_id' => $product->id,
            'name' => 'Replay amount rule',
            'rule_type' => 'amount',
            'rule_value' => 5,
            'active' => true,
        ]);
        $payload = $this->payload($product, '30000000-0000-4000-8000-000000000001');
        $payload['technician_id'] = $technician->id;

        $created = $this->postJson(route('sales.v2.store'), $payload)
            ->assertOk()
            ->assertJsonPath('idempotent_replay', false)
            ->json();
        $replayed = $this->postJson(route('sales.v2.store'), $payload)
            ->assertOk()
            ->assertJsonPath('idempotent_replay', true)
            ->json();

        $this->assertSame($created['sale_id'], $replayed['sale_id']);
        $this->assertSame($created['sale_no'], $replayed['sale_no']);
        $this->assertSame($created['invoice_url'], $replayed['invoice_url']);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('technician_commissions', 1);
        $this->assertSame('8.0000', $product->fresh()->stock_qty);
    }

    public function test_v2_store_returns_the_v2_invoice_url_for_the_created_sale(): void
    {
        $product = $this->product('V2 invoice URL product', '10.0000');

        $response = $this->postJson(
            route('sales.v2.store'),
            $this->payload($product, '30000000-0000-4000-8000-000000000009')
        )->assertOk();

        $saleId = $response->json('sale_id');

        $response->assertJsonPath(
            'invoice_url',
            route('sales.invoice-v2', $saleId)
        );
    }

    public function test_same_key_with_different_payload_returns_conflict_without_writes(): void
    {
        $product = $this->product('Conflict product', '10.0000');
        $payload = $this->payload($product, '30000000-0000-4000-8000-000000000002');

        $this->postJson(route('sales.v2.store'), $payload)->assertOk();
        $payload['items'][0]['qty'] = '3.00';

        $this->postJson(route('sales.v2.store'), $payload)
            ->assertStatus(409)
            ->assertJsonStructure(['message', 'errors']);

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame('8.0000', $product->fresh()->stock_qty);
    }

    public function test_different_keys_with_the_same_payload_create_separate_sales(): void
    {
        $product = $this->product('Separate intent product', '10.0000');

        $this->postJson(
            route('sales.v2.store'),
            $this->payload($product, '30000000-0000-4000-8000-000000000003')
        )->assertOk();
        $this->postJson(
            route('sales.v2.store'),
            $this->payload($product, '30000000-0000-4000-8000-000000000004')
        )->assertOk();

        $this->assertDatabaseCount('sales', 2);
        $this->assertSame(
            ['SAL-20260714-0001', 'SAL-20260714-0002'],
            Sale::query()->orderBy('id')->pluck('sale_no')->all()
        );
        $this->assertSame('6.0000', $product->fresh()->stock_qty);
    }

    public function test_failed_transaction_does_not_reserve_the_idempotency_key(): void
    {
        $product = $this->product('Retry after failure product', '1.0000');
        $payload = $this->payload($product, '30000000-0000-4000-8000-000000000005');

        $this->postJson(route('sales.v2.store'), $payload)->assertStatus(422);
        $this->assertDatabaseCount('sales', 0);

        $product->update(['stock_qty' => '10.0000']);
        $this->postJson(route('sales.v2.store'), $payload)
            ->assertOk()
            ->assertJsonPath('idempotent_replay', false);

        $this->assertDatabaseCount('sales', 1);
    }

    public function test_missing_or_invalid_key_is_rejected_before_sale_writes(): void
    {
        $product = $this->product('Invalid key product', '10.0000');
        $payload = $this->payload($product, 'not-a-uuid');

        $this->postJson(route('sales.v2.store'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('idempotency_key');

        unset($payload['idempotency_key']);
        $this->postJson(route('sales.v2.store'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('idempotency_key');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_payload_hash_normalizes_decimal_representation_but_preserves_item_order(): void
    {
        $product = $this->product('Hash product', '10.0000');
        $first = $this->payload($product, '30000000-0000-4000-8000-000000000006');
        $second = $first;
        $second['discount'] = 0;
        $second['items'][0]['qty'] = 2;
        $second['items'][0]['selling_price'] = '10.0';
        $service = app(SaleIdempotencyService::class);

        $this->assertSame($service->payloadHash($first), $service->payloadHash($second));

        $first['items'][] = $first['items'][0];
        $reversed = $first;
        $reversed['items'][0]['qty'] = '1.00';
        $reversed['items'][1]['qty'] = '2.00';
        $first['items'][0]['qty'] = '2.00';
        $first['items'][1]['qty'] = '1.00';
        $this->assertNotSame($service->payloadHash($first), $service->payloadHash($reversed));

        $withAnotherHold = $second;
        $second['hold_bill_id'] = 10;
        $withAnotherHold['hold_bill_id'] = 11;
        $this->assertNotSame($service->payloadHash($second), $service->payloadHash($withAnotherHold));
    }

    public function test_counter_rolls_back_with_transaction_and_keeps_minimum_width_over_9999(): void
    {
        $service = app(SaleNumberService::class);

        try {
            DB::transaction(function () use ($service): void {
                $this->assertSame('SAL-20260714-0001', $service->generate('2026-07-14'));
                throw new \RuntimeException('rollback counter');
            });
        } catch (\RuntimeException) {
            // Expected test failure after allocating the number.
        }

        $this->assertDatabaseMissing('sale_number_counters', ['sale_date' => '2026-07-14']);
        DB::table('sale_number_counters')->insert([
            'sale_date' => '2026-07-14',
            'last_number' => 9999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertSame('SAL-20260714-10000', $service->generate('2026-07-14'));
        $this->assertSame('SAL-20260715-0001', $service->generate('2026-07-15'));
    }

    public function test_non_idempotency_unique_violation_is_not_treated_as_replay(): void
    {
        $product = $this->product('Unique violation product', '10.0000');
        $service = app(SaleService::class);
        $first = $this->payload($product, '30000000-0000-4000-8000-000000000007');
        $service->createSale($first);
        DB::table('sale_number_counters')->where('sale_date', '2026-07-14')->update(['last_number' => 0]);

        $this->expectException(QueryException::class);
        $service->createSale($this->payload($product, '30000000-0000-4000-8000-000000000008'));
    }

    private function payload(Product $product, string $key): array
    {
        return [
            'idempotency_key' => $key,
            'sale_date' => '2026-07-14',
            'delivery_type' => 'pickup',
            'discount' => '0.00',
            'payment_method' => 'promptpay',
            'cash_amount' => '0.00',
            'promptpay_amount' => '20.00',
            'received_amount' => '0.00',
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => null,
                'qty' => '2.00',
                'selling_price' => '10.00',
            ]],
        ];
    }

    private function product(string $name, string $stock): Product
    {
        $category = Category::firstOrCreate(['name' => 'Idempotency category']);

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
}
