<?php

namespace Tests\Feature\Sales;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Technician;
use App\Models\TechnicianCommissionRule;
use App\Services\SaleService;
use DomainException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SalePaymentPersistenceTest extends TestCase
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

    #[DataProvider('validPayments')]
    public function test_pos_v1_persists_canonical_payment(array $payment, array $expected): void
    {
        $product = $this->product('V1 payment product');

        $this->postJson(route('sales.store'), $this->v1Payload($product, $payment))
            ->assertOk();

        $this->assertStoredPayment($expected);
        $this->assertSame('9.0000', $product->fresh()->stock_qty);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    #[DataProvider('validPayments')]
    public function test_pos_v2_persists_canonical_payment(array $payment, array $expected): void
    {
        $product = $this->product('V2 payment product');

        $this->postJson(route('sales.v2.store'), $this->v2Payload($product, $payment))
            ->assertOk();

        $this->assertStoredPayment($expected);
        $this->assertSame('9.0000', $product->fresh()->stock_qty);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public static function validPayments(): array
    {
        return [
            'cash exact' => [
                self::payment('cash', '100.00', '0.00', '100.00'),
                self::storedPayment('cash', '100.00', '0.00', '100.00', '0.00'),
            ],
            'cash with change' => [
                self::payment('cash', '100.00', '0.00', '150.00'),
                self::storedPayment('cash', '100.00', '0.00', '150.00', '50.00'),
            ],
            'promptpay' => [
                self::payment('promptpay', '0.00', '100.00', '0.00'),
                self::storedPayment('promptpay', '0.00', '100.00', '0.00', '0.00'),
            ],
            'mixed' => [
                self::payment('mixed', '40.00', '60.00', '50.00'),
                self::storedPayment('mixed', '40.00', '60.00', '50.00', '10.00'),
            ],
        ];
    }

    #[DataProvider('requestVersions')]
    public function test_direct_pos_rejects_missing_payment_payload(string $routeName, string $version): void
    {
        $product = $this->product('Missing payment product');
        $payload = $version === 'v1'
            ? $this->v1Payload($product)
            : $this->v2Payload($product);

        $errorFields = [
            'payment_method',
            'cash_amount',
            'promptpay_amount',
            'received_amount',
        ];

        if ($version === 'v1') {
            $this->from(route('sales.index'))
                ->post(route($routeName), $payload)
                ->assertRedirect(route('sales.index'))
                ->assertSessionHasErrors($errorFields);
        } else {
            $this->postJson(route($routeName), $payload)
                ->assertStatus(422)
                ->assertJsonValidationErrors($errorFields);
        }

        $this->assertNoSaleSideEffects($product);
    }

    public static function requestVersions(): array
    {
        return [
            'POS V1' => ['sales.store', 'v1'],
            'POS V2' => ['sales.v2.store', 'v2'],
        ];
    }

    #[DataProvider('invalidRequestPayments')]
    public function test_pos_v2_rejects_invalid_payment_input_shape(
        array $payment,
        string $errorField
    ): void {
        $product = $this->product('Invalid payment shape product');

        $this->postJson(route('sales.v2.store'), $this->v2Payload($product, $payment))
            ->assertStatus(422)
            ->assertJsonValidationErrors($errorField);

        $this->assertNoSaleSideEffects($product);
    }

    public static function invalidRequestPayments(): array
    {
        return [
            'unknown method' => [self::payment('card', '100.00', '0.00', '100.00'), 'payment_method'],
            'scale over two' => [self::payment('cash', '100.001', '0.00', '100.00'), 'cash_amount'],
            'array amount' => [self::payment('cash', ['100.00'], '0.00', '100.00'), 'cash_amount'],
            'browser change amount' => [
                self::payment('cash', '100.00', '0.00', '150.00') + ['change_amount' => '999.00'],
                'change_amount',
            ],
        ];
    }

    #[DataProvider('invalidCanonicalPayments')]
    public function test_payment_contract_failure_rolls_back_every_sale_side_effect(array $payment): void
    {
        $product = $this->product('Payment rollback product');
        $technician = Technician::query()->create(['name' => 'Payment technician', 'active' => true]);
        TechnicianCommissionRule::query()->create([
            'product_id' => $product->id,
            'name' => 'Payment rollback rule',
            'rule_type' => 'amount',
            'rule_value' => '5.00',
            'active' => true,
        ]);
        $payload = $this->v2Payload($product, $payment);
        $payload['technician_id'] = $technician->id;

        $this->postJson(route('sales.v2.store'), $payload)->assertStatus(422);

        $this->assertNoSaleSideEffects($product);
    }

    public static function invalidCanonicalPayments(): array
    {
        return [
            'allocation mismatch' => [self::payment('cash', '90.00', '10.00', '100.00')],
            'received below cash' => [self::payment('cash', '100.00', '0.00', '99.99')],
            'mixed zero cash' => [self::payment('mixed', '0.00', '100.00', '0.00')],
            'mixed zero promptpay' => [self::payment('mixed', '100.00', '0.00', '100.00')],
        ];
    }

    public function test_service_rejects_missing_payment_contract(): void
    {
        $product = $this->product('Service payment product');

        try {
            app(SaleService::class)->createSale($this->servicePayload($product));
            $this->fail('Direct Sale service must require a payment contract.');
        } catch (DomainException) {
            $this->assertNoSaleSideEffects($product);
        }
    }

    public function test_invalid_payment_does_not_reserve_key_and_corrected_retry_succeeds(): void
    {
        $product = $this->product('Corrected retry product');
        $key = $this->key(80);
        $invalid = $this->v2Payload(
            $product,
            self::payment('cash', '90.00', '10.00', '100.00'),
            $key
        );

        $this->postJson(route('sales.v2.store'), $invalid)->assertStatus(422);
        $this->assertDatabaseCount('sales', 0);

        $corrected = $this->v2Payload(
            $product,
            self::payment('cash', '100.00', '0.00', '100.00'),
            $key
        );
        $this->postJson(route('sales.v2.store'), $corrected)
            ->assertOk()
            ->assertJsonPath('idempotent_replay', false);

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame(1, DB::table('sale_number_counters')->value('last_number'));
    }

    public function test_same_key_and_payment_payload_replays_without_duplicate_effects(): void
    {
        $product = $this->product('Payment replay product');
        $payload = $this->v2Payload(
            $product,
            self::payment('mixed', '40.00', '60.00', '50.00'),
            $this->key(81)
        );

        $first = $this->postJson(route('sales.v2.store'), $payload)
            ->assertOk()
            ->assertJsonPath('idempotent_replay', false)
            ->json('sale_id');
        $second = $this->postJson(route('sales.v2.store'), $payload)
            ->assertOk()
            ->assertJsonPath('idempotent_replay', true)
            ->json('sale_id');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame('9.0000', $product->fresh()->stock_qty);
    }

    #[DataProvider('changedPaymentFields')]
    public function test_same_key_with_different_payment_intent_conflicts(array $replacement): void
    {
        $product = $this->product('Payment conflict product');
        $key = $this->key(82);
        $payload = $this->v2Payload(
            $product,
            self::payment('cash', '100.00', '0.00', '100.00'),
            $key
        );

        $this->postJson(route('sales.v2.store'), $payload)->assertOk();
        $payload = array_replace($payload, $replacement);

        $this->postJson(route('sales.v2.store'), $payload)->assertStatus(409);

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame('9.0000', $product->fresh()->stock_qty);
    }

    public static function changedPaymentFields(): array
    {
        return [
            'method' => [[
                'payment_method' => 'promptpay',
                'cash_amount' => '0.00',
                'promptpay_amount' => '100.00',
                'received_amount' => '0.00',
            ]],
            'cash amount' => [['cash_amount' => '99.00']],
            'promptpay amount' => [['promptpay_amount' => '1.00']],
            'received amount' => [['received_amount' => '150.00']],
        ];
    }

    private function v1Payload(
        Product $product,
        array $payment = [],
        ?string $key = null
    ): array {
        return array_merge([
            'idempotency_key' => $key ?? $this->key($product->id),
            'sale_date' => '2026-07-16',
            'delivery_type' => 'pickup',
            'discount' => '0.00',
            'delivery_fee' => '0.00',
            'product_id' => [$product->id],
            'product_unit_id' => [null],
            'qty' => ['1.00'],
            'selling_price' => ['100.00'],
        ], $payment);
    }

    private function v2Payload(
        Product $product,
        array $payment = [],
        ?string $key = null
    ): array {
        return array_merge($this->servicePayload($product), [
            'idempotency_key' => $key ?? $this->key($product->id),
            'delivery_fee' => '0.00',
        ], $payment);
    }

    private function servicePayload(Product $product): array
    {
        return [
            'sale_date' => '2026-07-16',
            'delivery_type' => 'pickup',
            'discount' => '0.00',
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => null,
                'qty' => '1.00',
                'selling_price' => '100.00',
            ]],
        ];
    }

    private function product(string $name): Product
    {
        $category = Category::query()->firstOrCreate(['name' => 'Payment persistence category']);

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

    private function assertStoredPayment(array $expected): void
    {
        $sale = Sale::query()->sole();

        foreach ($expected as $field => $value) {
            $this->assertSame($value, $sale->{$field});
        }
    }

    private function assertNoSaleSideEffects(Product $product): void
    {
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('technician_commissions', 0);
        $this->assertDatabaseCount('sale_number_counters', 0);
        $this->assertSame('10.0000', $product->fresh()->stock_qty);
    }

    private static function payment(
        string $method,
        mixed $cash,
        mixed $promptpay,
        mixed $received
    ): array {
        return [
            'payment_method' => $method,
            'cash_amount' => $cash,
            'promptpay_amount' => $promptpay,
            'received_amount' => $received,
        ];
    }

    private static function storedPayment(
        string $method,
        string $cash,
        string $promptpay,
        string $received,
        string $change
    ): array {
        return self::payment($method, $cash, $promptpay, $received) + [
            'change_amount' => $change,
        ];
    }

    private function key(int $suffix): string
    {
        return '16000000-0000-4000-8000-'.str_pad((string) $suffix, 12, '0', STR_PAD_LEFT);
    }
}
