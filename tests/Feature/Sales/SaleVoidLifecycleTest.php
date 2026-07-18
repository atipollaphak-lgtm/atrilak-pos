<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as IlluminateRoute;
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

    public function test_void_route_requires_manager_or_owner_and_valid_reason_but_does_not_mutate_sale_yet(): void
    {
        $sale = $this->sale('SAL-VOID-AUTH');
        $payload = ['void_reason' => 'ลูกค้าขอยกเลิก'];

        $this->post(route('sales.void', $sale), $payload)->assertRedirect(route('login'));
        $this->actingAs($this->user('cashier'))
            ->post(route('sales.void', $sale), $payload)
            ->assertForbidden();

        foreach (['manager', 'owner'] as $role) {
            $this->actingAs($this->user($role))
                ->from(route('sales.show', $sale))
                ->post(route('sales.void', $sale), $payload)
                ->assertRedirect(route('sales.show', $sale))
                ->assertSessionHas('error');
        }

        $this->assertSame(Sale::STATUS_ACTIVE, $sale->fresh()->status);
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

    public function test_quotation_origin_sale_reaches_void_contract_without_changing_the_quotation(): void
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
            ->assertSessionHas('error');

        $this->assertSame($quotation->id, $sale->fresh()->quotation_id);
        $this->assertSame('converted', $quotation->fresh()->status);
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
