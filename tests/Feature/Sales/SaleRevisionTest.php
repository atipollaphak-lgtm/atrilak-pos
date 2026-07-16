<?php

namespace Tests\Feature\Sales;

use App\Exceptions\StaleSaleRevisionException;
use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Services\SaleService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaleRevisionTest extends TestCase
{
    use RefreshDatabase;

    private const CONFLICT_MESSAGE = 'ใบขายนี้ถูกแก้ไขจากหน้าจออื่นแล้ว กรุณาตรวจสอบข้อมูลล่าสุดและแก้ไขใหม่อีกครั้ง';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            Authenticate::class,
            RoleMiddleware::class,
            ValidateCsrfToken::class,
        ]);
    }

    public function test_edit_page_contains_current_hidden_revision(): void
    {
        [$sale] = $this->existingSale();

        $this->get(route('sales.edit', $sale))
            ->assertOk()
            ->assertSee('name="revision"', false)
            ->assertSee('value="1"', false);
    }

    public function test_missing_and_invalid_revision_are_rejected(): void
    {
        [$sale] = $this->existingSale();
        $payload = $this->payload($sale);

        unset($payload['revision']);
        $this->put(route('sales.update', $sale), $payload)
            ->assertSessionHasErrors('revision');

        $payload['revision'] = 0;
        $this->put(route('sales.update', $sale), $payload)
            ->assertSessionHasErrors('revision');

        $this->assertSame(1, $sale->fresh()->revision);
    }

    public function test_normal_validation_failure_preserves_submitted_revision(): void
    {
        [$sale] = $this->existingSale();
        $payload = $this->payload($sale);
        $payload['qty'] = ['invalid'];

        $this->from(route('sales.edit', $sale))
            ->put(route('sales.update', $sale), $payload)
            ->assertSessionHasErrors()
            ->assertSessionHasInput('revision', 1);
    }

    public function test_header_only_item_and_no_op_updates_each_increment_revision_once(): void
    {
        [$sale] = $this->existingSale();
        $service = app(SaleService::class);

        $service->updateSale($sale, $this->serviceData($sale, saleDate: '2026-07-17'), 1);
        $this->assertSame(2, $sale->fresh()->revision);

        $itemData = $this->serviceData($sale->fresh());
        $itemData['items'][0]['qty'] = '2.00';
        $service->updateSale($sale->fresh(), $itemData, 2);
        $this->assertSame(3, $sale->fresh()->revision);

        $service->updateSale($sale->fresh(), $this->serviceData($sale->fresh()), 3);
        $this->assertSame(4, $sale->fresh()->revision);
    }

    public function test_stale_service_update_changes_nothing(): void
    {
        [$sale] = $this->existingSale();
        $before = $this->writeSnapshot();

        try {
            app(SaleService::class)->updateSale(
                $sale,
                $this->serviceData($sale, saleDate: '2026-07-20'),
                99
            );
            $this->fail('Expected a stale Sale revision conflict.');
        } catch (StaleSaleRevisionException $exception) {
            $this->assertSame(self::CONFLICT_MESSAGE, $exception->getMessage());
        }

        $this->assertSame($before, $this->writeSnapshot());
    }

    public function test_stale_http_conflict_discards_old_input_and_reloads_latest_sale(): void
    {
        [$sale] = $this->existingSale();
        app(SaleService::class)->updateSale(
            $sale,
            $this->serviceData($sale, saleDate: '2026-07-17'),
            1
        );
        $stalePayload = $this->payload($sale->fresh(), revision: 1, saleDate: '2026-07-20');
        $stalePayload['qty'] = ['3.00'];

        $this->from(route('sales.edit', $sale))
            ->put(route('sales.update', $sale), $stalePayload)
            ->assertRedirect(route('sales.edit', $sale))
            ->assertSessionHas('error', self::CONFLICT_MESSAGE)
            ->assertSessionMissing('_old_input');

        $sale->refresh();
        $this->assertSame(2, $sale->revision);
        $this->assertSame('2026-07-17', $sale->sale_date);
        $this->assertSame('1.00', $sale->items()->sole()->qty);

        $this->get(route('sales.edit', $sale))
            ->assertOk()
            ->assertSee('value="2"', false)
            ->assertSee('value="2026-07-17"', false)
            ->assertDontSee('value="2026-07-20"', false);
    }

    public function test_quotation_origin_sale_uses_the_same_revision_contract(): void
    {
        [$sale] = $this->existingSale();
        $quotation = Quotation::query()->create([
            'quotation_no' => 'QT-REVISION-1',
            'quotation_date' => '2026-07-16',
            'total_amount' => '10.00',
            'status' => 'converted',
        ]);
        DB::table('sales')->where('id', $sale->id)->update([
            'quotation_id' => $quotation->id,
        ]);

        app(SaleService::class)->updateSale(
            $sale->fresh(),
            $this->serviceData($sale->fresh(), saleDate: '2026-07-17'),
            1
        );

        $this->assertSame(2, $sale->fresh()->revision);
        $this->assertSame($quotation->id, $sale->fresh()->quotation_id);
    }

    public function test_stale_tab_cannot_remove_a_line_added_by_a_newer_update(): void
    {
        [$sale, $originalProduct] = $this->existingSale();
        $newProduct = Product::query()->create([
            'category_id' => $originalProduct->category_id,
            'name' => 'Newer revision product',
            'cost_price' => '4.00',
            'selling_price' => '8.00',
            'stock_qty' => '10.0000',
            'minimum_stock' => '0.0000',
            'active' => true,
        ]);
        $staleData = $this->serviceData($sale);
        $newerData = $staleData;
        $newerData['items'][] = [
            'sale_item_id' => null,
            'product_id' => $newProduct->id,
            'product_unit_id' => null,
            'qty' => '1.00',
            'selling_price' => '8.00',
        ];

        app(SaleService::class)->updateSale($sale, $newerData, 1);
        $afterNewerUpdate = $this->writeSnapshot();

        try {
            app(SaleService::class)->updateSale($sale, $staleData, 1);
            $this->fail('Expected a stale Sale revision conflict.');
        } catch (StaleSaleRevisionException) {
            // Expected.
        }

        $this->assertSame($afterNewerUpdate, $this->writeSnapshot());
        $this->assertSame(2, $sale->fresh()->revision);
        $this->assertCount(2, $sale->fresh()->items);
    }

    public function test_stock_failure_rolls_back_revision_with_every_sale_write(): void
    {
        [$sale] = $this->existingSale();
        $data = $this->serviceData($sale);
        $data['items'][0]['qty'] = '20.00';
        $before = $this->writeSnapshot();

        try {
            app(SaleService::class)->updateSale($sale, $data, 1);
            $this->fail('Expected insufficient stock rejection.');
        } catch (\DomainException) {
            // Expected.
        }

        $this->assertSame($before, $this->writeSnapshot());
        $this->assertSame(1, $sale->fresh()->revision);
    }

    private function existingSale(): array
    {
        $category = Category::query()->create(['name' => 'Sale revision category']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Sale revision product',
            'cost_price' => '5.00',
            'selling_price' => '10.00',
            'stock_qty' => '9.0000',
            'minimum_stock' => '0.0000',
            'active' => true,
        ]);
        $sale = Sale::query()->create([
            'sale_no' => 'SAL-REVISION-1',
            'sale_date' => '2026-07-16',
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

    private function payload(
        Sale $sale,
        int $revision = 1,
        string $saleDate = '2026-07-16'
    ): array {
        $item = $sale->items()->sole();

        return [
            'revision' => $revision,
            'customer_id' => $sale->customer_id,
            'sale_date' => $saleDate,
            'sale_item_id' => [$item->id],
            'product_unit_id' => [$item->product_unit_id],
            'product_id' => [$item->product_id],
            'qty' => [$item->qty],
            'selling_price' => [$item->selling_price],
            'delivery_fee' => $sale->delivery_fee,
            'discount' => $sale->discount,
        ];
    }

    private function serviceData(Sale $sale, string $saleDate = '2026-07-16'): array
    {
        $item = $sale->items()->sole();

        return [
            'customer_id' => $sale->customer_id,
            'sale_date' => $saleDate,
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

    private function writeSnapshot(): array
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
