<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Services\SaleService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationOriginSaleConsistencyCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotation_converts_once_and_replay_returns_the_related_sale(): void
    {
        [$quotation, $product] = $this->quotationAndProduct();
        $service = app(SaleService::class);

        $sale = $service->createSaleFromQuotation($quotation);
        $replayed = $service->createSaleFromQuotation($quotation);

        $this->assertSame($sale->id, $replayed->id);
        $this->assertSame($quotation->id, $sale->quotation_id);
        $this->assertSame($sale->id, $quotation->fresh()->convertedSale->id);
        $this->assertSame('converted', $quotation->fresh()->status);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame('18.0000', (string) $product->fresh()->stock_qty);
    }

    public function test_header_only_update_is_currently_allowed_and_keeps_quotation_relation(): void
    {
        [$quotation] = $this->quotationAndProduct();
        $sale = app(SaleService::class)->createSaleFromQuotation($quotation);
        $itemId = $sale->items()->sole()->id;
        $movementCount = StockMovement::query()->count();

        $updated = app(SaleService::class)->updateSale($sale, array_replace(
            $this->updatePayload($sale),
            ['sale_date' => '2026-07-16']
        ));

        $this->assertSame($quotation->id, $updated->fresh()->quotation_id);
        $this->assertSame($itemId, $updated->fresh()->items()->sole()->id);
        $this->assertSame('2026-07-16', $updated->fresh()->sale_date);
        $this->assertSame($movementCount, StockMovement::query()->count());
        $this->assertSame('converted', $quotation->fresh()->status);
    }

    public function test_item_update_is_currently_allowed_and_keeps_quotation_relation(): void
    {
        [$quotation, $product] = $this->quotationAndProduct();
        $sale = app(SaleService::class)->createSaleFromQuotation($quotation);
        $oldItemId = $sale->items()->sole()->id;
        $payload = $this->updatePayload($sale);
        $payload['items'][0]['qty'] = '3.00';
        $payload['items'][0]['selling_price'] = '12.00';

        $updated = app(SaleService::class)->updateSale($sale, $payload)->fresh();
        $updatedItem = $updated->items()->sole();

        $this->assertSame($quotation->id, $updated->quotation_id);
        $this->assertSame($oldItemId, $updatedItem->id);
        $this->assertSame('1.0000', $updatedItem->conversion_rate_used);
        $this->assertSame('3.0000', $updatedItem->base_qty);
        $this->assertSame('36', (string) $updated->total_amount);
        $this->assertSame('17.0000', (string) $product->fresh()->stock_qty);
        $this->assertDatabaseCount('stock_movements', 3);
        $this->assertDatabaseHas('quotation_items', [
            'quotation_id' => $quotation->id,
            'qty' => 2,
            'selling_price' => 10,
            'total' => 20,
        ]);
        $this->assertSame('converted', $quotation->fresh()->status);
    }

    public function test_quotation_origin_sale_delete_remains_blocked(): void
    {
        [$quotation, $product] = $this->quotationAndProduct();
        $sale = app(SaleService::class)->createSaleFromQuotation($quotation);
        $stock = $product->fresh()->stock_qty;
        $movementCount = StockMovement::query()->count();

        try {
            app(SaleService::class)->deleteSale($sale);
            $this->fail('A quotation-origin Sale must retain the established delete guard.');
        } catch (DomainException) {
            // Existing policy rejects deletion of a Sale linked to a Quotation.
        }

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'quotation_id' => $quotation->id,
        ]);
        $this->assertDatabaseHas('quotations', [
            'id' => $quotation->id,
            'status' => 'converted',
        ]);
        $this->assertSame($stock, $product->fresh()->stock_qty);
        $this->assertSame($movementCount, StockMovement::query()->count());
    }

    private function quotationAndProduct(): array
    {
        $category = Category::query()->create([
            'name' => 'Quotation-origin category '.uniqid(),
            'active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Quotation-origin product '.uniqid(),
            'unit' => 'piece',
            'cost_price' => '5.00',
            'selling_price' => '10.00',
            'stock_qty' => '20.0000',
            'minimum_stock' => '0.0000',
            'vat_enabled' => false,
            'active' => true,
            'auto_price_enabled' => false,
        ]);
        $quotation = Quotation::query()->create([
            'quotation_no' => 'QT-ORIGIN-'.uniqid(),
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

        return [$quotation, $product];
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
}
