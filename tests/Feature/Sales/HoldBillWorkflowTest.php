<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerDeliveryAddress;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoldBillWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_create_and_list_a_persistent_hold_bill_without_creating_a_sale(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        [$customer, $address] = $this->customerWithAddress();
        [$product, $productUnit] = $this->productWithUnit();

        $response = $this->actingAs($cashier)->postJson('/sales-v3/hold-bills', [
            'customer_id' => $customer->id,
            'customer_delivery_address_id' => $address->id,
            'sale_date' => '2026-07-29',
            'delivery_date' => '2026-08-12',
            'delivery_type' => 'delivery',
            'discount' => '10.00',
            'delivery_fee' => '50.00',
            'total_amount' => '240.00',
            'notes' => 'รอลูกค้ายืนยัน',
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => $productUnit->id,
                'qty' => '2.00',
                'selling_price' => '100.00',
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('hold_bill.hold_no', 'HLD-20260729-0001');

        $this->assertDatabaseHas('hold_bills', [
            'hold_no' => 'HLD-20260729-0001',
            'customer_id' => $customer->id,
            'customer_delivery_address_id' => $address->id,
            'total_amount' => '190.00',
            'delivery_date' => '2026-08-12',
            'delivery_fee_override_flag' => false,
        ]);
        $this->assertDatabaseHas('hold_bill_items', [
            'product_id' => $product->id,
            'product_unit_id' => $productUnit->id,
            'product_name_snapshot' => 'ปูนทดสอบ',
            'unit_name_snapshot' => 'ถุง',
        ]);
        $this->assertDatabaseCount('sales', 0);

        $this->actingAs($cashier)
            ->getJson('/sales-v3/hold-bills')
            ->assertOk()
            ->assertJsonPath('data.0.hold_no', 'HLD-20260729-0001');
    }

    public function test_cashier_can_delete_a_hold_bill_without_touching_sales(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        [$customer, $address] = $this->customerWithAddress();
        [$product, $productUnit] = $this->productWithUnit();

        $created = $this->actingAs($cashier)->postJson('/sales-v3/hold-bills', [
            'customer_id' => $customer->id,
            'customer_delivery_address_id' => $address->id,
            'sale_date' => '2026-07-29',
            'delivery_type' => 'pickup',
            'discount' => '0.00',
            'delivery_fee' => '0.00',
            'total_amount' => '100.00',
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => $productUnit->id,
                'qty' => '1.00',
                'selling_price' => '100.00',
            ]],
        ])->assertCreated();

        $holdId = $created->json('hold_bill.id');

        $this->actingAs($cashier)
            ->deleteJson('/sales-v3/hold-bills/'.$holdId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('hold_bills', ['id' => $holdId]);
        $this->assertDatabaseCount('hold_bill_items', 0);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_hold_bill_rejects_empty_items(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)
            ->postJson('/sales-v3/hold-bills', [
                'sale_date' => '2026-07-29',
                'delivery_type' => 'pickup',
                'items' => [],
            ])
            ->assertUnprocessable();
    }

    public function test_hold_bill_rejects_an_address_in_a_deactivated_delivery_zone(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        [$customer, $address] = $this->customerWithAddress();
        [$product, $productUnit] = $this->productWithUnit();
        $address->deliveryZone->update(['active' => false]);

        $this->actingAs($cashier)
            ->postJson('/sales-v3/hold-bills', [
                'customer_id' => $customer->id,
                'customer_delivery_address_id' => $address->id,
                'sale_date' => '2026-07-29',
                'delivery_type' => 'delivery',
                'delivery_fee' => '0.00',
                'total_amount' => '100.00',
                'items' => [[
                    'product_id' => $product->id,
                    'product_unit_id' => $productUnit->id,
                    'qty' => '1.00',
                    'selling_price' => '100.00',
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'โซนจัดส่งนี้ปิดใช้งานแล้ว กรุณาเลือกที่อยู่ที่ผูกกับโซนที่เปิดใช้งาน');

        $this->assertDatabaseCount('hold_bills', 0);
    }

    public function test_hold_bill_ignores_a_tampered_browser_total_and_persists_the_server_total(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        [$product, $productUnit] = $this->productWithUnit();

        $this->actingAs($cashier)
            ->postJson('/sales-v3/hold-bills', [
                'sale_date' => '2026-07-29',
                'delivery_type' => 'pickup',
                'discount' => '10.00',
                'delivery_fee' => '99.00',
                'total_amount' => '9999.99',
                'items' => [[
                    'product_id' => $product->id,
                    'product_unit_id' => $productUnit->id,
                    'qty' => '2.00',
                    'selling_price' => '100.00',
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('hold_bill.total_amount', '190.00');
    }

    public function test_hold_bill_rejects_item_values_that_exceed_database_scale(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        [$product, $productUnit] = $this->productWithUnit();

        $this->actingAs($cashier)
            ->postJson('/sales-v3/hold-bills', [
                'sale_date' => '2026-07-29',
                'delivery_type' => 'pickup',
                'total_amount' => '100.00',
                'items' => [[
                    'product_id' => $product->id,
                    'product_unit_id' => $productUnit->id,
                    'qty' => '1.001',
                    'selling_price' => '100.001',
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.qty',
                'items.0.selling_price',
            ]);

        $this->assertDatabaseCount('hold_bills', 0);
        $this->assertDatabaseCount('hold_bill_items', 0);
    }

    public function test_successful_sale_consumes_the_resumed_hold_inside_the_sale_transaction(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        [$product, $productUnit] = $this->productWithUnit();
        $holdId = $this->createPickupHold($cashier, $product, $productUnit);

        $this->actingAs($cashier)
            ->postJson('/sales-v3/store', $this->salePayload($product, $productUnit, $holdId))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('hold_bills', ['id' => $holdId]);
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_pickup_hold_persists_explicit_pricing_zone_for_resume_without_delivery_fee(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        [$product, $productUnit] = $this->productWithUnit();
        $pricingZone = DeliveryZone::query()->create([
            'name' => 'โซนเสนอราคา',
            'price_markup_percent' => '10.00',
            'rounding_increment' => '1.00',
            'minimum_profit' => '0.00',
            'active' => true,
        ]);

        $created = $this->actingAs($cashier)->postJson('/sales-v3/hold-bills', [
            'sale_date' => '2026-07-29',
            'delivery_type' => 'pickup',
            'pricing_zone_id' => $pricingZone->id,
            'total_amount' => '110.00',
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => $productUnit->id,
                'qty' => '1.00',
                'selling_price' => '110.00',
            ]],
        ])->assertCreated();

        $holdId = $created->json('hold_bill.id');

        $this->assertDatabaseHas('hold_bills', [
            'id' => $holdId,
            'delivery_type' => 'pickup',
            'pricing_zone_id' => $pricingZone->id,
            'pricing_zone_name_snapshot' => 'โซนเสนอราคา',
            'pricing_zone_markup_percent_snapshot' => '10.00',
            'pricing_zone_rounding_increment_snapshot' => '1.00',
            'delivery_fee' => '0.00',
        ]);
        $this->assertDatabaseHas('hold_bill_items', [
            'hold_bill_id' => $holdId,
            'selling_price' => '110.00',
            'price_override_flag' => false,
        ]);

        $payload = $this->salePayload($product, $productUnit, $holdId);
        $payload['items'][0]['selling_price'] = '110.00';
        $payload['cash_amount'] = '110.00';
        $payload['received_amount'] = '110.00';
        $payload['idempotency_key'] = '90000000-0000-4000-8000-000000000003';

        $this->actingAs($cashier)
            ->postJson('/sales-v3/store', $payload)
            ->assertOk();

        $sale = Sale::query()->sole();
        $this->assertSame('pickup', $sale->delivery_type);
        $this->assertEquals('0.00', $sale->delivery_fee);
        $this->assertSame($pricingZone->id, $sale->pricing_zone_id);
        $this->assertSame('110.00', $sale->items()->sole()->selling_price);
    }

    public function test_failed_sale_keeps_the_resumed_hold_recoverable(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        [$product, $productUnit] = $this->productWithUnit();
        $holdId = $this->createPickupHold($cashier, $product, $productUnit);
        $payload = $this->salePayload($product, $productUnit, $holdId);
        $payload['items'][0]['qty'] = '999.00';

        $this->actingAs($cashier)
            ->postJson('/sales-v3/store', $payload)
            ->assertUnprocessable();

        $this->assertDatabaseHas('hold_bills', ['id' => $holdId]);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_consumed_hold_cannot_create_a_second_sale_with_another_idempotency_key(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        [$product, $productUnit] = $this->productWithUnit();
        $holdId = $this->createPickupHold($cashier, $product, $productUnit);
        $payload = $this->salePayload($product, $productUnit, $holdId);

        $this->actingAs($cashier)
            ->postJson('/sales-v3/store', $payload)
            ->assertOk();

        $payload['idempotency_key'] = '90000000-0000-4000-8000-000000000002';

        $this->actingAs($cashier)
            ->postJson('/sales-v3/store', $payload)
            ->assertStatus(409);

        $this->assertDatabaseCount('sales', 1);
    }

    public function test_resume_response_preserves_evidence_of_a_deleted_product_unit(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        [$product, $productUnit] = $this->productWithUnit();
        $productUnitId = $productUnit->id;
        $holdId = $this->createPickupHold($cashier, $product, $productUnit);

        $productUnit->delete();

        $this->actingAs($cashier)
            ->getJson('/sales-v3/hold-bills/'.$holdId)
            ->assertOk()
            ->assertJsonPath('data.items.0.product_unit_id', null)
            ->assertJsonPath('data.items.0.product_unit_id_snapshot', $productUnitId)
            ->assertJsonPath('data.items.0.product_unit', null);
    }

    public function test_hold_preserves_override_metadata_when_current_price_changes_before_sale(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        [$product, $productUnit] = $this->productWithUnit();

        $created = $this->actingAs($cashier)->postJson('/sales-v3/hold-bills', [
            'sale_date' => '2026-07-29',
            'delivery_type' => 'pickup',
            'total_amount' => '99.50',
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => $productUnit->id,
                'qty' => '1.00',
                'selling_price' => '99.50',
                'price_was_edited' => true,
            ]],
        ])->assertCreated();

        $holdId = $created->json('hold_bill.id');

        $this->assertDatabaseHas('hold_bill_items', [
            'hold_bill_id' => $holdId,
            'selling_price' => '99.50',
            'original_price' => '100.00',
            'price_override_flag' => true,
        ]);

        $product->update(['selling_price' => '110.00']);
        $productUnit->update(['selling_price' => '110.00']);

        $payload = $this->salePayload($product, $productUnit, $holdId);
        $payload['items'][0]['selling_price'] = '99.50';
        $payload['items'][0]['price_was_edited'] = true;
        $payload['cash_amount'] = '99.50';
        $payload['received_amount'] = '99.50';

        $this->actingAs($cashier)
            ->postJson('/sales-v3/store', $payload)
            ->assertOk();

        $item = Sale::query()->sole()->items()->sole();

        $this->assertSame('99.50', $item->selling_price);
        $this->assertSame('100.00', $item->original_price);
        $this->assertTrue($item->price_override_flag);
    }

    public function test_manual_delivery_fee_hold_persists_and_resumes_the_override_flag(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        [$customer, $address] = $this->customerWithAddress('Manual hold customer', '150.00');
        [$product, $productUnit] = $this->productWithUnit();

        $created = $this->actingAs($cashier)->postJson('/sales-v3/hold-bills', [
            'customer_id' => $customer->id,
            'customer_delivery_address_id' => $address->id,
            'sale_date' => '2026-07-29',
            'delivery_type' => 'delivery',
            'discount' => '30.00',
            'delivery_fee' => '25.00',
            'delivery_fee_override_flag' => true,
            'total_amount' => '195.00',
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => $productUnit->id,
                'qty' => '2.00',
                'selling_price' => '100.00',
            ]],
        ])->assertCreated();

        $holdId = $created->json('hold_bill.id');

        $this->assertDatabaseHas('hold_bills', [
            'id' => $holdId,
            'delivery_fee' => '25.00',
            'delivery_fee_override_flag' => true,
        ]);

        $this->actingAs($cashier)
            ->getJson('/sales-v3/hold-bills/'.$holdId)
            ->assertOk()
            ->assertJsonPath('data.delivery_fee', '25.00')
            ->assertJsonPath('data.delivery_fee_override_flag', true);
    }

    public function test_manual_delivery_fee_from_resumed_hold_is_used_for_sale_total(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        [$customer, $address] = $this->customerWithAddress('Manual hold sale customer', '150.00');
        [$product, $productUnit] = $this->productWithUnit();
        $created = $this->actingAs($cashier)->postJson('/sales-v3/hold-bills', [
            'customer_id' => $customer->id,
            'customer_delivery_address_id' => $address->id,
            'sale_date' => '2026-07-29',
            'delivery_type' => 'delivery',
            'discount' => '30.00',
            'delivery_fee' => '25.00',
            'delivery_fee_override_flag' => true,
            'total_amount' => '195.00',
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => $productUnit->id,
                'qty' => '2.00',
                'selling_price' => '100.00',
            ]],
        ])->assertCreated();
        $holdId = $created->json('hold_bill.id');
        $payload = [
            'hold_bill_id' => $holdId,
            'sale_date' => '2026-07-29',
            'delivery_type' => 'delivery',
            'customer_id' => $customer->id,
            'customer_delivery_address_id' => $address->id,
            'delivery_fee' => '25.00',
            'delivery_fee_override_flag' => true,
            'discount' => '30.00',
            'payment_method' => 'cash',
            'cash_amount' => '195.00',
            'promptpay_amount' => '0.00',
            'received_amount' => '195.00',
            'idempotency_key' => '90000000-0000-4000-8000-000000000004',
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => $productUnit->id,
                'qty' => '2.00',
                'selling_price' => '100.00',
            ]],
        ];

        $this->actingAs($cashier)
            ->postJson('/sales-v3/store', $payload)
            ->assertOk();

        $sale = Sale::query()->sole();

        $this->assertEquals('25.00', $sale->delivery_fee);
        $this->assertTrue($sale->delivery_fee_override_flag);
        $this->assertEquals('195.00', $sale->total_amount);
        $this->assertDatabaseMissing('hold_bills', ['id' => $holdId]);
    }

    private function customerWithAddress(
        string $name = 'ลูกค้าพักบิล',
        string $minimumProfit = '0.00'
    ): array {
        $customer = Customer::query()->create([
            'code' => 'CUS-HOLD-1',
            'name' => $name,
            'phone' => '0800000001',
            'active' => true,
        ]);
        $zone = DeliveryZone::query()->create([
            'name' => $name.' zone',
            'price_markup_percent' => '0.00',
            'rounding_increment' => '0.25',
            'base_delivery_fee' => '50.00',
            'minimum_profit' => $minimumProfit,
            'active' => true,
        ]);
        $address = CustomerDeliveryAddress::query()->create([
            'customer_id' => $customer->id,
            'name' => 'บ้าน',
            'address' => '123 ถนนสุขุมวิท',
            'delivery_zone_id' => $zone->id,
            'is_default' => true,
        ]);

        return [$customer, $address];
    }

    private function productWithUnit(): array
    {
        $category = Category::query()->create([
            'name' => 'หมวดทดสอบ',
            'active' => true,
        ]);
        $unit = Unit::query()->create([
            'code' => 'BAG',
            'name' => 'ถุง',
            'short_name' => 'ถุง',
            'active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'ปูนทดสอบ',
            'sku' => 'SKU-HOLD-1',
            'unit_id' => $unit->id,
            'unit' => 'ถุง',
            'cost_price' => '70.00',
            'selling_price' => '100.00',
            'stock_qty' => '20.0000',
            'active' => true,
        ]);
        $productUnit = ProductUnit::query()->create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_rate' => '1.0000',
            'is_base_unit' => true,
            'is_sale_unit' => true,
            'active' => true,
            'selling_price' => '100.00',
        ]);

        return [$product, $productUnit];
    }

    private function createPickupHold(User $cashier, Product $product, ProductUnit $productUnit): int
    {
        return (int) $this->actingAs($cashier)
            ->postJson('/sales-v3/hold-bills', [
                'sale_date' => '2026-07-29',
                'delivery_type' => 'pickup',
                'discount' => '0.00',
                'delivery_fee' => '0.00',
                'total_amount' => '100.00',
                'items' => [[
                    'product_id' => $product->id,
                    'product_unit_id' => $productUnit->id,
                    'qty' => '1.00',
                    'selling_price' => '100.00',
                ]],
            ])
            ->assertCreated()
            ->json('hold_bill.id');
    }

    private function salePayload(Product $product, ProductUnit $productUnit, int $holdId): array
    {
        return [
            'hold_bill_id' => $holdId,
            'sale_date' => '2026-07-29',
            'delivery_type' => 'pickup',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'payment_method' => 'cash',
            'cash_amount' => '100.00',
            'promptpay_amount' => '0.00',
            'received_amount' => '100.00',
            'idempotency_key' => '90000000-0000-4000-8000-000000000001',
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => $productUnit->id,
                'qty' => '1.00',
                'selling_price' => '100.00',
            ]],
        ];
    }
}
