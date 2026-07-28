<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerDeliveryAddress;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Technician;
use App\Models\TechnicianCommission;
use App\Models\TechnicianCommissionRule;
use App\Services\SaleService;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaleCommissionLifecycleCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_creates_commission_once_with_detail_snapshot(): void
    {
        [$sale, , $commission] = $this->createCommissionedSale();

        $this->assertDatabaseCount('technician_commissions', 1);
        $this->assertSame($sale->id, $commission->sale_id);
        $this->assertEquals(2.50, $commission->commission_amount);
        $this->assertEquals(20.00, $commission->sale_total);
        $this->assertSame('pending', $commission->status);

        $detail = json_decode(
            $commission->calculation_detail,
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $this->assertCount(1, $detail);
        $this->assertSame('Lifecycle amount rule', $detail[0]['rule_name']);
        $this->assertEquals(2, $detail[0]['qty']);
        $this->assertEquals(20, $detail[0]['line_total']);
        $this->assertEquals(2.5, $detail[0]['commission']);
    }

    public function test_idempotent_replay_does_not_duplicate_commission(): void
    {
        $key = '12000000-0000-4000-8000-000000000001';
        [$sale, $payload] = $this->createCommissionedSale($key);

        $replayed = app(SaleService::class)->createSale($payload);

        $this->assertSame($sale->id, $replayed->id);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('technician_commissions', 1);
    }

    public function test_customer_only_update_keeps_commission_snapshot_unchanged(): void
    {
        [$sale, , $commission] = $this->createCommissionedSale();
        $before = $this->commissionSnapshot($commission);

        app(SaleService::class)->updateSale($sale, array_replace(
            $this->updatePayload($sale),
            ['customer_id' => null]
        ), (int) $sale->fresh()->revision);

        $this->assertSame(
            $before,
            $this->commissionSnapshot($commission->fresh())
        );
        $this->assertSame('2026-07-15', $sale->fresh()->sale_date);
    }

    public function test_sale_date_update_recreates_pending_commission(): void
    {
        [$sale, , $commission] = $this->createCommissionedSale();

        app(SaleService::class)->updateSale($sale, array_replace(
            $this->updatePayload($sale),
            ['sale_date' => '2026-07-16']
        ), (int) $sale->fresh()->revision);

        $replacement = TechnicianCommission::query()->sole();
        $this->assertSame($commission->id, $replacement->id);
        $this->assertSame('2026-07-16', $replacement->commission_date);
    }

    public function test_item_update_recreates_pending_commission_using_existing_rules(): void
    {
        [$sale, , $commission] = $this->createCommissionedSale();
        $payload = $this->updatePayload($sale);
        $payload['items'][0]['qty'] = '3.00';

        app(SaleService::class)->updateSale($sale, $payload, (int) $sale->fresh()->revision);

        $replacement = TechnicianCommission::query()->sole();
        $this->assertSame($commission->id, $replacement->id);
        $this->assertEquals(3.75, $replacement->commission_amount);
        $this->assertEquals(30.00, $replacement->sale_total);
        $this->assertTrue(BigDecimal::of('30')->isEqualTo((string) $sale->fresh()->total_amount));
        $this->assertSame('3.00', (string) $sale->fresh()->items()->sole()->qty);
    }

    public function test_delete_cascades_pending_commission(): void
    {
        [$sale, , $commission] = $this->createCommissionedSale();

        app(SaleService::class)->deleteSale($sale);

        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertDatabaseMissing('technician_commissions', ['id' => $commission->id]);
    }

    public function test_paid_batched_commission_blocks_delete(): void
    {
        [$sale, , $commission] = $this->createCommissionedSale();
        $batchId = DB::table('technician_payment_batches')->insertGetId([
            'batch_no' => 'PAY-LIFECYCLE-0001',
            'payment_date' => '2026-07-15',
            'total_technicians' => 1,
            'total_items' => 1,
            'total_amount' => '2.50',
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $commission->update([
            'status' => 'paid',
            'payment_batch_id' => $batchId,
            'paid_at' => now(),
        ]);

        $this->expectException(\DomainException::class);

        app(SaleService::class)->deleteSale($sale);
    }

    public function test_paid_commission_blocks_item_update(): void
    {
        [$sale, , $commission] = $this->createCommissionedSale();
        $commission->update(['status' => 'paid', 'paid_at' => now()]);
        $payload = $this->updatePayload($sale);
        $payload['items'][0]['qty'] = '3.00';

        $this->expectException(\DomainException::class);

        app(SaleService::class)->updateSale($sale, $payload, (int) $sale->fresh()->revision);
    }

    public function test_batched_commission_blocks_item_update_even_if_status_is_pending(): void
    {
        [$sale, , $commission] = $this->createCommissionedSale();
        $batchId = DB::table('technician_payment_batches')->insertGetId([
            'batch_no' => 'PAY-LIFECYCLE-PENDING-0001',
            'payment_date' => '2026-07-15',
            'total_technicians' => 1,
            'total_items' => 1,
            'total_amount' => '2.50',
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $commission->update(['payment_batch_id' => $batchId]);
        $payload = $this->updatePayload($sale);
        $payload['items'][0]['qty'] = '3.00';

        $this->expectException(\DomainException::class);

        app(SaleService::class)->updateSale($sale, $payload, (int) $sale->fresh()->revision);
    }

    public function test_paid_commission_allows_customer_only_update(): void
    {
        [$sale, , $commission] = $this->createCommissionedSale();
        $commission->update(['status' => 'paid', 'paid_at' => now()]);

        app(SaleService::class)->updateSale($sale, $this->updatePayload($sale), (int) $sale->fresh()->revision);

        $this->assertSame('paid', $commission->fresh()->status);
        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
    }

    public function test_percentage_commission_is_refreshed_from_updated_stored_total(): void
    {
        [$sale, , $commission] = $this->createCommissionedSale(
            ruleType: 'percent',
            ruleValue: '10.00'
        );
        $payload = $this->updatePayload($sale);
        $payload['items'][0]['selling_price'] = '20.00';

        app(SaleService::class)->updateSale($sale, $payload, (int) $sale->fresh()->revision);

        $this->assertSame($commission->id, TechnicianCommission::query()->sole()->id);
        $this->assertEquals(4.00, $commission->fresh()->commission_amount);
    }

    public function test_dynamic_delivery_fee_closes_profit_shortfall_and_refreshes_commission(): void
    {
        [$sale, , $commission] = $this->createCommissionedSale();
        $customer = Customer::query()->create(['name' => 'Guard customer', 'active' => true]);
        $zone = DeliveryZone::query()->create([
            'name' => 'Guard zone',
            'base_delivery_fee' => '0.00',
            'price_markup_percent' => '3.00',
            'rounding_increment' => '0.50',
            'minimum_profit' => '100.00',
            'active' => true,
        ]);
        $address = CustomerDeliveryAddress::query()->create([
            'customer_id' => $customer->id,
            'delivery_zone_id' => $zone->id,
            'name' => 'Guard address',
        ]);
        $sale->update([
            'customer_id' => $customer->id,
            'customer_delivery_address_id' => $address->id,
            'delivery_type' => 'delivery',
            'delivery_fee' => '0.00',
        ]);
        $item = $sale->items()->sole();
        $product = $item->product;
        $beforeCommission = $this->commissionSnapshot($commission);
        $beforeMovements = StockMovement::query()->count();
        $beforeStock = $product->stock_qty;
        $beforeRevision = $sale->fresh()->revision;
        $payload = $this->updatePayload($sale);
        $payload['items'][0]['selling_price'] = '1.00';

        $updated = app(SaleService::class)->updateSale($sale, $payload, (int) $sale->fresh()->revision)->fresh();

        $this->assertSame($item->id, $updated->items()->sole()->id);
        $this->assertSame('1.00', $item->fresh()->selling_price);
        $this->assertEquals('108.00', $updated->delivery_fee);
        $this->assertEquals('110.00', $updated->total_amount);
        $this->assertSame('3.00', $updated->delivery_zone_markup_percent_snapshot);
        $this->assertSame('0.50', $updated->delivery_zone_rounding_increment_snapshot);
        $this->assertSame('100.00', $updated->delivery_zone_minimum_profit_snapshot);
        $this->assertSame($beforeStock, $product->fresh()->stock_qty);
        $this->assertSame($beforeMovements + 2, StockMovement::query()->count());
        $this->assertNotSame($beforeCommission, $this->commissionSnapshot($commission->fresh()));
        $this->assertGreaterThan($beforeRevision, $updated->revision);
    }

    public function test_category_rounding_snapshot_is_preserved_when_sale_is_edited(): void
    {
        $category = Category::query()->create([
            'name' => 'Snapshot rounding category',
            'rounding_override' => '0.25',
            'active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Snapshot rounding product',
            'unit' => 'piece',
            'cost_price' => '4.00',
            'selling_price' => '6.30',
            'stock_qty' => '100.0000',
            'minimum_stock' => '0.0000',
            'active' => true,
        ]);
        $customer = Customer::query()->create(['name' => 'Snapshot rounding customer', 'active' => true]);
        $zone = DeliveryZone::query()->create([
            'name' => 'Snapshot rounding zone',
            'price_markup_percent' => '3.00',
            'rounding_increment' => '5.00',
            'minimum_profit' => '300.00',
            'active' => true,
        ]);
        $address = CustomerDeliveryAddress::query()->create([
            'customer_id' => $customer->id,
            'delivery_zone_id' => $zone->id,
            'name' => 'Snapshot rounding address',
        ]);
        $this->assertSame('0.25', $product->fresh()->category->rounding_override);
        $payload = [
            'customer_id' => $customer->id,
            'customer_delivery_address_id' => $address->id,
            'sale_date' => '2026-07-15',
            'delivery_type' => 'delivery',
            'discount' => '0.00',
            'payment_method' => 'promptpay',
            'cash_amount' => '0.00',
            'promptpay_amount' => '304.00',
            'received_amount' => '0.00',
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => null,
                'qty' => '1.00',
                'selling_price' => '6.50',
            ]],
        ];
        $sale = app(SaleService::class)->createSale($payload);

        $this->assertSame('0.25', $sale->delivery_zone_rounding_increment_snapshot);
        $category->update(['rounding_override' => '10.00']);
        $zone->update(['rounding_increment' => '10.00', 'price_markup_percent' => '8.00']);

        $item = $sale->items()->sole();
        $updated = app(SaleService::class)->updateSale($sale, [
            'customer_id' => $customer->id,
            'customer_delivery_address_id' => $address->id,
            'sale_date' => '2026-07-15',
            'delivery_fee' => $sale->delivery_fee,
            'discount' => $sale->discount,
            'items' => [[
                'sale_item_id' => $item->id,
                'product_id' => $product->id,
                'product_unit_id' => null,
                'qty' => '1.00',
                'selling_price' => '6.50',
            ]],
        ], (int) $sale->fresh()->revision);

        $this->assertSame('0.25', $updated->fresh()->delivery_zone_rounding_increment_snapshot);
    }

    public function test_failure_during_pending_commission_refresh_rolls_back_sale_stock_and_items(): void
    {
        [$sale, , $commission] = $this->createCommissionedSale();
        $item = $sale->items()->sole();
        $product = $item->product;
        $beforeMovements = StockMovement::query()->count();
        $beforeStock = $product->stock_qty;
        $beforeRevision = $sale->fresh()->revision;
        $throw = true;
        TechnicianCommission::updating(function () use (&$throw): void {
            if ($throw) {
                $throw = false;
                throw new \RuntimeException('Commission refresh failure');
            }
        });
        $payload = $this->updatePayload($sale);
        $payload['items'][0]['qty'] = '3.00';

        try {
            app(SaleService::class)->updateSale($sale, $payload, (int) $sale->fresh()->revision);
            $this->fail('Expected commission refresh failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Commission refresh failure', $exception->getMessage());
        }

        $this->assertSame($item->id, $sale->fresh()->items()->sole()->id);
        $this->assertSame('2.00', $item->fresh()->qty);
        $this->assertSame($beforeStock, $product->fresh()->stock_qty);
        $this->assertSame($beforeMovements, StockMovement::query()->count());
        $this->assertEquals(2.50, $commission->fresh()->commission_amount);
        $this->assertSame($beforeRevision, $sale->fresh()->revision);
    }

    public function test_quotation_conversion_and_replay_keep_commission_empty(): void
    {
        $product = $this->product();
        $quotation = Quotation::query()->create([
            'quotation_no' => 'QT-COMMISSION-LIFECYCLE',
            'quotation_date' => '2026-07-15',
            'total_amount' => '20.00',
            'status' => 'draft',
        ]);
        $quotation->items()->create([
            'product_id' => $product->id,
            'qty' => '2.00',
            'selling_price' => '10.00',
            'total' => '20.00',
        ]);
        $service = app(SaleService::class);

        $sale = $service->createSaleFromQuotation($quotation);
        $replayed = $service->createSaleFromQuotation($quotation);

        $this->assertSame($sale->id, $replayed->id);
        $this->assertNull($sale->technician_id);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('technician_commissions', 0);
    }

    private function createCommissionedSale(
        ?string $idempotencyKey = null,
        string $ruleType = 'amount',
        string $ruleValue = '1.25'
    ): array {
        $product = $this->product();
        $technician = Technician::query()->create([
            'name' => 'Lifecycle technician',
            'active' => true,
        ]);
        TechnicianCommissionRule::query()->create([
            'product_id' => $product->id,
            'name' => 'Lifecycle '.$ruleType.' rule',
            'rule_type' => $ruleType,
            'rule_value' => $ruleValue,
            'active' => true,
        ]);
        $payload = [
            'sale_date' => '2026-07-15',
            'technician_id' => $technician->id,
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

        if ($idempotencyKey !== null) {
            $payload['idempotency_key'] = $idempotencyKey;
        }

        $sale = app(SaleService::class)->createSale($payload);

        return [$sale, $payload, TechnicianCommission::query()->sole()];
    }

    private function product(): Product
    {
        $category = Category::query()->create([
            'name' => 'Lifecycle category '.uniqid(),
            'active' => true,
        ]);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Lifecycle product '.uniqid(),
            'unit' => 'piece',
            'cost_price' => '5.00',
            'selling_price' => '10.00',
            'stock_qty' => '100.0000',
            'minimum_stock' => '0.0000',
            'vat_enabled' => false,
            'active' => true,
            'auto_price_enabled' => false,
        ]);
    }

    private function updatePayload(Sale $sale): array
    {
        return [
            'customer_id' => $sale->customer_id,
            'sale_date' => $sale->sale_date,
            'delivery_fee' => $sale->delivery_fee,
            'discount' => $sale->discount,
            'items' => $sale->items()->orderBy('id')->get()->map(fn ($item): array => [
                'sale_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_unit_id' => $item->product_unit_id,
                'qty' => $item->qty,
                'selling_price' => $item->selling_price,
            ])->all(),
        ];
    }

    private function commissionSnapshot(TechnicianCommission $commission): array
    {
        return array_intersect_key($commission->getRawOriginal(), array_flip([
            'id',
            'sale_id',
            'technician_id',
            'commission_date',
            'sale_total',
            'commission_rate',
            'commission_amount',
            'manual_adjust',
            'payable_amount',
            'adjust_remark',
            'rule_name',
            'calculation_detail',
            'status',
            'paid_at',
            'paid_by',
            'payment_batch_id',
            'remark',
            'created_at',
            'updated_at',
        ]));
    }
}
