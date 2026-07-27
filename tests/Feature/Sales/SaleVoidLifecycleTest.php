<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Technician;
use App\Models\TechnicianCommission;
use App\Models\Unit;
use App\Models\User;
use App\Services\SaleService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class SaleVoidLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_lifecycle_helpers_and_scopes_distinguish_active_and_voided_sales(): void
    {
        $active = $this->sale('SAL-VOID-ACTIVE');
        $voided = $this->sale('SAL-VOID-VOIDED', ['status' => Sale::STATUS_VOIDED]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($active->isVoided());
        $this->assertTrue($voided->isVoided());
        $this->assertFalse($voided->isActive());
        $this->assertSame([$active->id], Sale::query()->active()->pluck('id')->all());
        $this->assertSame([$voided->id], Sale::query()->voided()->pluck('id')->all());
    }

    public function test_destroy_route_is_unavailable_and_void_route_is_manager_protected(): void
    {
        $sale = $this->sale('SAL-VOID-ROUTE');

        $this->assertNull(app('router')->getRoutes()->getByName('sales.destroy'));
        $this->delete('/sales/'.$sale->id)->assertStatus(405);

        $route = app('router')->getRoutes()->getByName('sales.void');
        $this->assertInstanceOf(IlluminateRoute::class, $route);
        $this->assertSame('sales/'.$sale->id.'/void', trim(parse_url(route('sales.void', $sale), PHP_URL_PATH), '/'));
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('role:manager', $route->gatherMiddleware());
    }

    public function test_void_route_requires_manager_or_owner_and_voids_an_active_sale(): void
    {
        $sale = $this->saleWithItem('SAL-VOID-AUTH');
        $payload = ['void_reason' => 'ลูกค้าขอยกเลิก'];

        $this->post(route('sales.void', $sale), $payload)->assertRedirect(route('login'));
        $this->actingAs($this->user('cashier'))
            ->post(route('sales.void', $sale), $payload)
            ->assertForbidden();

        foreach (['manager'] as $role) {
            $this->actingAs($this->user($role))
                ->from(route('sales.show', $sale))
                ->post(route('sales.void', $sale), $payload)
                ->assertRedirect(route('sales.show', $sale))
                ->assertSessionHas('success');
        }

        $this->assertSame(Sale::STATUS_VOIDED, $sale->fresh()->status);
    }

    public function test_void_reason_is_required_and_cannot_be_whitespace_only(): void
    {
        $sale = $this->sale('SAL-VOID-VALIDATE');
        $manager = $this->user('manager');

        $this->actingAs($manager)
            ->post(route('sales.void', $sale), [])
            ->assertSessionHasErrors('void_reason');

        $this->actingAs($manager)
            ->post(route('sales.void', $sale), ['void_reason' => " \t "])
            ->assertSessionHasErrors('void_reason');
    }

    public function test_voided_sales_cannot_be_edited_or_updated_while_active_sales_retain_update_behavior(): void
    {
        $voided = $this->sale('SAL-VOID-GUARD', ['status' => Sale::STATUS_VOIDED]);
        $active = $this->saleWithItem('SAL-VOID-ACTIVE-UPDATE');
        $voidedWithItem = $this->saleWithItem('SAL-VOID-UPDATE', ['status' => Sale::STATUS_VOIDED]);
        $cashier = $this->user('cashier');

        $this->actingAs($cashier)
            ->get(route('sales.edit', $voided))
            ->assertStatus(409);

        $this->actingAs($cashier)
            ->get(route('sales.edit', $active))
            ->assertOk();

        try {
            app(SaleService::class)->updateSale(
                $voidedWithItem,
                $this->updateData($voidedWithItem),
                $voidedWithItem->revision
            );
            $this->fail('Expected voided Sale update to be rejected.');
        } catch (DomainException) {
            // Expected authoritative service guard.
        }

        $this->actingAs($cashier)
            ->put(route('sales.update', $active), $this->updatePayload($active))
            ->assertRedirect(route('sales.show', $active));

        $this->assertSame(2, $active->fresh()->revision);
    }

    public function test_quotation_origin_sale_can_be_voided_without_changing_the_quotation(): void
    {
        $quotation = Quotation::query()->create([
            'quotation_no' => 'QT-VOID-ORIGIN',
            'quotation_date' => '2026-07-18',
            'total_amount' => '10.00',
            'status' => 'converted',
        ]);
        $sale = $this->sale('SAL-VOID-ORIGIN');
        Sale::query()->whereKey($sale->id)->update(['quotation_id' => $quotation->id]);
        $sale->refresh();

        $this->actingAs($this->user('manager'))
            ->from(route('sales.show', $sale))
            ->post(route('sales.void', $sale), ['void_reason' => 'ทดสอบสัญญาใบเสนอราคา'])
            ->assertRedirect(route('sales.show', $sale))
            ->assertSessionHas('success');

        $this->assertSame($quotation->id, $sale->fresh()->quotation_id);
        $this->assertSame('converted', $quotation->fresh()->status);
        $this->assertSame(Sale::STATUS_VOIDED, $sale->fresh()->status);
    }

    public function test_owner_can_void_an_active_sale(): void
    {
        $sale = $this->saleWithItem('SAL-VOID-OWNER');

        $this->actingAs($this->user('owner'))
            ->post(route('sales.void', $sale), ['void_reason' => 'owner approved void'])
            ->assertRedirect(route('sales.show', $sale))
            ->assertSessionHas('success');

        $this->assertSame(Sale::STATUS_VOIDED, $sale->fresh()->status);
    }

    public function test_void_preserves_sale_history_restores_authoritative_stock_and_voids_pending_commission(): void
    {
        $sale = $this->saleWithItem('SAL-VOID-ATOMIC');
        $item = $sale->items->sole();
        $item->update(['base_qty' => '24.0000', 'qty' => '1.00']);
        $product = $item->product;
        $product->update(['stock_qty' => '76.0000']);
        $originalMovement = StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'OUT',
            'qty' => '24.0000',
            'stock_before' => '100.0000',
            'stock_after' => '76.0000',
            'reference_type' => 'sale',
            'reference_id' => $sale->id,
        ]);
        $commission = $this->pendingCommission($sale);
        $actor = $this->user('manager');

        $this->actingAs($actor)
            ->post(route('sales.void', $sale), ['void_reason' => '  customer requested cancellation  '])
            ->assertRedirect(route('sales.show', $sale))
            ->assertSessionHas('success');

        $voided = $sale->fresh();
        $this->assertSame(Sale::STATUS_VOIDED, $voided->status);
        $this->assertSame($actor->id, $voided->voided_by);
        $this->assertNotNull($voided->voided_at);
        $this->assertSame('customer requested cancellation', $voided->void_reason);
        $this->assertDatabaseHas('sale_items', ['id' => $item->id]);
        $this->assertSame('100.0000', $product->fresh()->stock_qty);
        $this->assertSame('voided', $commission->fresh()->status);
        $this->assertEquals(25, $commission->fresh()->commission_amount);
        $this->assertDatabaseHas('stock_movements', ['id' => $originalMovement->id]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'IN',
            'qty' => '24.0000',
            'reference_type' => 'sale_void',
            'reference_id' => $sale->id,
        ]);
    }

    public function test_second_void_is_rejected_without_another_stock_or_movement_write(): void
    {
        $sale = $this->saleWithItem('SAL-VOID-ONCE');
        $product = $sale->items->sole()->product;
        $actor = $this->user('manager');

        app(SaleService::class)->voidSale($sale, $actor, 'first void');
        $stockAfterFirstVoid = $product->fresh()->stock_qty;
        $movementCount = StockMovement::query()->count();

        try {
            app(SaleService::class)->voidSale($sale, $actor, 'second void');
            $this->fail('Expected the second void to be rejected.');
        } catch (DomainException) {
            $this->assertSame($stockAfterFirstVoid, $product->fresh()->stock_qty);
            $this->assertSame($movementCount, StockMovement::query()->count());
        }
    }

    public function test_legacy_factor_one_sale_voids_but_ambiguous_converted_sale_blocks_without_writes(): void
    {
        $legacy = $this->saleWithItem('SAL-VOID-LEGACY');
        $legacyItem = $legacy->items->sole();
        $legacyItem->update(['base_qty' => null, 'product_unit_id' => null, 'qty' => '2.00']);
        $legacyItem->product->update(['stock_qty' => '8.0000']);
        $actor = $this->user('manager');

        app(SaleService::class)->voidSale($legacy, $actor, 'legacy factor one');

        $this->assertSame('10.0000', $legacyItem->product->fresh()->stock_qty);

        $ambiguous = $this->saleWithItem('SAL-VOID-AMBIGUOUS');
        $ambiguousItem = $ambiguous->items->sole();
        $unit = Unit::query()->create([
            'code' => 'AMB-VOID',
            'name' => 'Ambiguous void unit',
            'short_name' => 'AMB',
        ]);
        $productUnitId = DB::table('product_units')->insertGetId([
            'product_id' => $ambiguousItem->product_id,
            'unit_id' => $unit->id,
            'conversion_rate' => '24.0000',
            'is_base_unit' => false,
            'is_purchase_unit' => false,
            'is_sale_unit' => true,
            'active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $ambiguousItem->update(['base_qty' => null, 'product_unit_id' => $productUnitId]);
        $beforeStock = $ambiguousItem->product->stock_qty;
        $beforeMovements = StockMovement::query()->count();

        try {
            app(SaleService::class)->voidSale($ambiguous, $actor, 'ambiguous contract');
            $this->fail('Expected an ambiguous converted historical item to block void.');
        } catch (DomainException) {
            $this->assertSame(Sale::STATUS_ACTIVE, $ambiguous->fresh()->status);
            $this->assertSame($beforeStock, $ambiguousItem->product->fresh()->stock_qty);
            $this->assertSame($beforeMovements, StockMovement::query()->count());
        }
    }

    public function test_paid_or_batched_commission_blocks_void_before_any_mutation(): void
    {
        foreach ([
            ['status' => 'paid', 'paid_at' => now()],
            ['status' => 'pending', 'payment_batch_id' => $this->paymentBatchId()],
        ] as $index => $state) {
            $sale = $this->saleWithItem('SAL-VOID-COMMISSION-'.$index);
            $commission = $this->pendingCommission($sale);
            $commission->update($state);
            $product = $sale->items->sole()->product;
            $beforeStock = $product->stock_qty;
            $beforeMovements = StockMovement::query()->count();

            try {
                app(SaleService::class)->voidSale($sale, $this->user('manager'), 'commission blocked');
                $this->fail('Expected paid or batched commission to block void.');
            } catch (DomainException) {
                $this->assertSame(Sale::STATUS_ACTIVE, $sale->fresh()->status);
                $this->assertSame($beforeStock, $product->fresh()->stock_qty);
                $this->assertSame($beforeMovements, StockMovement::query()->count());
                $this->assertSame($state['status'], $commission->fresh()->status);
            }
        }
    }

    public function test_exception_after_stock_restore_rolls_back_stock_movements_commission_and_sale_status(): void
    {
        $sale = $this->saleWithItem('SAL-VOID-ROLLBACK');
        $commission = $this->pendingCommission($sale);
        $product = $sale->items->sole()->product;
        $beforeStock = $product->stock_qty;
        $beforeMovements = StockMovement::query()->count();
        $throw = true;

        TechnicianCommission::updating(function () use (&$throw): void {
            if ($throw) {
                $throw = false;
                throw new RuntimeException('forced commission failure');
            }
        });

        try {
            app(SaleService::class)->voidSale($sale, $this->user('manager'), 'rollback');
            $this->fail('Expected the forced commission failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced commission failure', $exception->getMessage());
        }

        $this->assertSame(Sale::STATUS_ACTIVE, $sale->fresh()->status);
        $this->assertSame($beforeStock, $product->fresh()->stock_qty);
        $this->assertSame($beforeMovements, StockMovement::query()->count());
        $this->assertSame('pending', $commission->fresh()->status);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function sale(string $saleNo, array $attributes = []): Sale
    {
        return Sale::query()->create(array_merge([
            'sale_no' => $saleNo,
            'sale_date' => '2026-07-18',
            'total_amount' => '10.00',
            'delivery_fee' => '0.00',
            'delivery_type' => 'pickup',
            'discount' => '0.00',
        ], $attributes))->fresh();
    }

    private function saleWithItem(string $saleNo, array $attributes = []): Sale
    {
        $category = Category::query()->create(['name' => 'Void lifecycle category '.$saleNo]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Void lifecycle product '.$saleNo,
            'cost_price' => '5.00',
            'selling_price' => '10.00',
            'stock_qty' => '9.0000',
            'minimum_stock' => '0.0000',
            'active' => true,
        ]);
        $sale = $this->sale($saleNo, $attributes);
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

        return $sale->fresh('items');
    }

    private function pendingCommission(Sale $sale): TechnicianCommission
    {
        $technician = Technician::query()->create([
            'name' => 'Void lifecycle technician '.$sale->sale_no,
            'active' => true,
        ]);

        return TechnicianCommission::query()->create([
            'sale_id' => $sale->id,
            'technician_id' => $technician->id,
            'commission_amount' => '25.00',
            'status' => 'pending',
        ]);
    }

    private function paymentBatchId(): int
    {
        return DB::table('technician_payment_batches')->insertGetId([
            'batch_no' => 'PAY-VOID-BATCH',
            'payment_date' => '2026-07-18',
            'total_technicians' => 1,
            'total_items' => 1,
            'total_amount' => '25.00',
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function updateData(Sale $sale): array
    {
        $item = $sale->items()->sole();

        return [
            'customer_id' => $sale->customer_id,
            'sale_date' => $sale->sale_date,
            'items' => [[
                'sale_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_unit_id' => $item->product_unit_id,
                'qty' => $item->qty,
                'selling_price' => $item->selling_price,
            ]],
            'delivery_fee' => $sale->delivery_fee,
            'discount' => $sale->discount,
        ];
    }

    private function updatePayload(Sale $sale): array
    {
        $item = $sale->items()->sole();

        return [
            'revision' => $sale->revision,
            'sale_date' => $sale->sale_date,
            'sale_item_id' => [$item->id],
            'product_unit_id' => [$item->product_unit_id],
            'product_id' => [$item->product_id],
            'qty' => [$item->qty],
            'selling_price' => [$item->selling_price],
            'delivery_fee' => $sale->delivery_fee,
            'discount' => $sale->discount,
            'payment_method' => 'promptpay',
            'cash_amount' => '0.00',
            'promptpay_amount' => '10.00',
            'received_amount' => '0.00',
        ];
    }
}
