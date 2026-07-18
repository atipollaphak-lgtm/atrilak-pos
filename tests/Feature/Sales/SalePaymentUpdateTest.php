<?php

namespace Tests\Feature\Sales;

use App\Http\Controllers\SaleController;
use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Services\SaleService;
use DomainException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalePaymentUpdateTest extends TestCase
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

    public function test_direct_sale_update_persists_canonical_cash_payment(): void
    {
        [$sale] = $this->existingSale();

        app(SaleService::class)->updateSale(
            $sale,
            $this->payload($sale, [
                'payment_method' => 'cash',
                'cash_amount' => '100.00',
                'promptpay_amount' => '0.00',
                'received_amount' => '150.00',
            ]),
            (int) $sale->fresh()->revision
        );

        $sale->refresh();

        $this->assertSame('cash', $sale->payment_method);
        $this->assertSame('100.00', $sale->cash_amount);
        $this->assertSame('0.00', $sale->promptpay_amount);
        $this->assertSame('150.00', $sale->received_amount);
        $this->assertSame('50.00', $sale->change_amount);
    }

    public function test_direct_sale_update_persists_promptpay_payment(): void
    {
        [$sale] = $this->existingSale();

        app(SaleService::class)->updateSale(
            $sale,
            $this->payload($sale, [
                'payment_method' => 'promptpay',
                'cash_amount' => '0.00',
                'promptpay_amount' => '100.00',
                'received_amount' => '0.00',
            ]),
            (int) $sale->fresh()->revision
        );

        $sale->refresh();

        $this->assertSame('promptpay', $sale->payment_method);
        $this->assertSame('0.00', $sale->cash_amount);
        $this->assertSame('100.00', $sale->promptpay_amount);
        $this->assertSame('0.00', $sale->received_amount);
        $this->assertSame('0.00', $sale->change_amount);
    }

    public function test_direct_sale_update_uses_updated_total_and_ignores_browser_change_amount(): void
    {
        [$sale, $product] = $this->existingSale();
        $payload = $this->payload($sale, [
            'payment_method' => 'mixed',
            'cash_amount' => '70.00',
            'promptpay_amount' => '50.00',
            'received_amount' => '100.00',
            'change_amount' => '999.00',
        ]);
        $payload['items'][0]['selling_price'] = '120.00';

        app(SaleService::class)->updateSale($sale, $payload, (int) $sale->fresh()->revision);

        $sale->refresh();

        $this->assertEquals('120.00', $sale->total_amount);
        $this->assertSame('mixed', $sale->payment_method);
        $this->assertSame('70.00', $sale->cash_amount);
        $this->assertSame('50.00', $sale->promptpay_amount);
        $this->assertSame('100.00', $sale->received_amount);
        $this->assertSame('30.00', $sale->change_amount);
        $this->assertSame('9.0000', $product->fresh()->stock_qty);
    }

    public function test_invalid_update_payment_rolls_back_header_items_stock_movements_and_payment(): void
    {
        [$sale] = $this->existingSale();
        $before = $this->snapshot();
        $payload = $this->payload($sale, [
            'payment_method' => 'cash',
            'cash_amount' => '90.00',
            'promptpay_amount' => '10.00',
            'received_amount' => '100.00',
        ]);
        $payload['items'][0]['selling_price'] = '110.00';

        try {
            app(SaleService::class)->updateSale($sale, $payload, (int) $sale->fresh()->revision);
            $this->fail('Expected invalid payment rejection.');
        } catch (DomainException) {
            // Expected.
        }

        $this->assertSame($before, $this->snapshot());
    }

    public function test_legacy_null_payment_sale_edit_loads_and_can_be_saved_with_promptpay(): void
    {
        [$sale] = $this->existingSale();

        $view = app(SaleController::class)->edit($sale);

        $this->assertSame('sales.edit', $view->getName());

        app(SaleService::class)->updateSale(
            $sale,
            $this->payload($sale, [
                'payment_method' => 'promptpay',
                'cash_amount' => '0.00',
                'promptpay_amount' => '100.00',
                'received_amount' => '0.00',
            ]),
            (int) $sale->fresh()->revision
        );

        $sale->refresh();

        $this->assertSame('promptpay', $sale->payment_method);
        $this->assertSame('0.00', $sale->change_amount);
    }

    public function test_direct_sale_update_requires_complete_payment_input(): void
    {
        [$sale] = $this->existingSale();
        $item = $sale->items()->sole();

        $this->from(route('sales.edit', $sale))
            ->put(route('sales.update', $sale), [
                'revision' => $sale->fresh()->revision,
                'customer_id' => null,
                'sale_date' => $sale->sale_date,
                'sale_item_id' => [$item->id],
                'product_unit_id' => [$item->product_unit_id],
                'product_id' => [$item->product_id],
                'qty' => [$item->qty],
                'selling_price' => [$item->selling_price],
                'delivery_fee' => '0.00',
                'discount' => '0.00',
            ])
            ->assertRedirect(route('sales.edit', $sale))
            ->assertSessionHasErrors([
                'payment_method',
                'cash_amount',
                'promptpay_amount',
                'received_amount',
            ]);

        $this->assertNull($sale->fresh()->payment_method);
    }

    public function test_update_request_rejects_browser_change_amount(): void
    {
        [$sale] = $this->existingSale();
        $item = $sale->items()->sole();

        $this->from(route('sales.edit', $sale))
            ->put(route('sales.update', $sale), [
                'revision' => $sale->fresh()->revision,
                'customer_id' => null,
                'sale_date' => $sale->sale_date,
                'sale_item_id' => [$item->id],
                'product_unit_id' => [$item->product_unit_id],
                'product_id' => [$item->product_id],
                'qty' => [$item->qty],
                'selling_price' => [$item->selling_price],
                'delivery_fee' => '0.00',
                'discount' => '0.00',
                'payment_method' => 'cash',
                'cash_amount' => '100.00',
                'promptpay_amount' => '0.00',
                'received_amount' => '150.00',
                'change_amount' => '999.00',
            ])
            ->assertRedirect(route('sales.edit', $sale))
            ->assertSessionHasErrors('change_amount');

        $this->assertNull($sale->fresh()->payment_method);
    }

    private function existingSale(): array
    {
        $category = Category::query()->create(['name' => 'Payment update category']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Payment update product',
            'cost_price' => '40.00',
            'selling_price' => '100.00',
            'stock_qty' => '9.0000',
            'minimum_stock' => '0.0000',
            'active' => true,
        ]);
        $sale = Sale::query()->create([
            'sale_no' => 'SAL-PAYMENT-UPDATE-1',
            'sale_date' => '2026-07-18',
            'total_amount' => '100.00',
            'delivery_type' => 'pickup',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'qty' => '1.00',
            'conversion_rate_used' => '1.0000',
            'base_qty' => '1.0000',
            'selling_price' => '100.00',
            'cost_price' => '40.00',
            'total' => '100.00',
            'profit' => '60.00',
        ]);
        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'OUT',
            'qty' => '1.0000',
            'stock_before' => '10.0000',
            'stock_after' => '9.0000',
            'reference_type' => 'sale',
            'reference_id' => $sale->id,
        ]);

        return [$sale, $product];
    }

    private function payload(Sale $sale, array $payment): array
    {
        $item = $sale->items()->sole();

        return array_merge([
            'customer_id' => null,
            'sale_date' => $sale->sale_date,
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'items' => [[
                'sale_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_unit_id' => $item->product_unit_id,
                'qty' => $item->qty,
                'selling_price' => $item->selling_price,
            ]],
        ], $payment);
    }

    private function snapshot(): array
    {
        return [
            'sales' => DB::table('sales')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'sale_items' => DB::table('sale_items')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'products' => DB::table('products')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'stock_movements' => DB::table('stock_movements')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'technician_commissions' => DB::table('technician_commissions')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }
}
