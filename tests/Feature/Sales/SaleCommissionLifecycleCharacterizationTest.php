<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\Technician;
use App\Models\TechnicianCommission;
use App\Models\TechnicianCommissionRule;
use App\Services\SaleService;
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

    public function test_header_only_update_keeps_commission_snapshot_unchanged(): void
    {
        [$sale, , $commission] = $this->createCommissionedSale();
        $before = $this->commissionSnapshot($commission);

        app(SaleService::class)->updateSale($sale, array_replace(
            $this->updatePayload($sale),
            ['sale_date' => '2026-07-16']
        ));

        $this->assertSame(
            $before,
            $this->commissionSnapshot($commission->fresh())
        );
        $this->assertSame('2026-07-16', $sale->fresh()->sale_date);
    }

    public function test_item_update_keeps_commission_snapshot_unchanged(): void
    {
        [$sale, , $commission] = $this->createCommissionedSale();
        $before = $this->commissionSnapshot($commission);
        $payload = $this->updatePayload($sale);
        $payload['items'][0]['qty'] = '3.00';

        app(SaleService::class)->updateSale($sale, $payload);

        $this->assertSame(
            $before,
            $this->commissionSnapshot($commission->fresh())
        );
        $this->assertSame('30', (string) $sale->fresh()->total_amount);
        $this->assertSame('3.00', (string) $sale->fresh()->items()->sole()->qty);
    }

    public function test_delete_cascades_pending_commission(): void
    {
        [$sale, , $commission] = $this->createCommissionedSale();

        app(SaleService::class)->deleteSale($sale);

        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertDatabaseMissing('technician_commissions', ['id' => $commission->id]);
    }

    public function test_delete_currently_cascades_paid_batched_commission(): void
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

        app(SaleService::class)->deleteSale($sale);

        $this->assertDatabaseMissing('technician_commissions', ['id' => $commission->id]);
        $this->assertDatabaseHas('technician_payment_batches', ['id' => $batchId]);
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

    private function createCommissionedSale(?string $idempotencyKey = null): array
    {
        $product = $this->product();
        $technician = Technician::query()->create([
            'name' => 'Lifecycle technician',
            'active' => true,
        ]);
        TechnicianCommissionRule::query()->create([
            'product_id' => $product->id,
            'name' => 'Lifecycle amount rule',
            'rule_type' => 'amount',
            'rule_value' => '1.25',
            'active' => true,
        ]);
        $payload = [
            'sale_date' => '2026-07-15',
            'technician_id' => $technician->id,
            'delivery_type' => 'pickup',
            'discount' => '0.00',
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
