<?php

namespace Tests\Feature\Sales;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerDeliveryAddress;
use App\Models\DeliveryZone;
use App\Models\HoldBillItem;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\CommercialDocumentService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SaleV3PriceOverrideTest extends TestCase
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

    public function test_sale_items_and_hold_items_have_price_snapshot_columns(): void
    {
        foreach (['sale_items', 'hold_bill_items'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'original_price'));
            $this->assertTrue(Schema::hasColumn($table, 'price_override_flag'));
        }
    }

    public function test_price_snapshot_models_cast_override_values(): void
    {
        $saleItem = new SaleItem([
            'original_price' => '100.00',
            'price_override_flag' => true,
        ]);
        $holdItem = new HoldBillItem([
            'original_price' => '99.50',
            'price_override_flag' => false,
        ]);

        $this->assertSame('100.00', $saleItem->original_price);
        $this->assertTrue($saleItem->price_override_flag);
        $this->assertSame('99.50', $holdItem->original_price);
        $this->assertFalse($holdItem->price_override_flag);
    }

    public function test_v3_sale_without_price_edit_keeps_normal_price_metadata(): void
    {
        $product = $this->product('Normal V3 price product');

        $this->postJson(route('sales.v3.store'), $this->payload($product, '100.00', false))
            ->assertOk();

        $item = Sale::query()->sole()->items()->sole();

        $this->assertSame('100.00', $item->selling_price);
        $this->assertNull($item->original_price);
        $this->assertFalse($item->price_override_flag);
    }

    public function test_v3_sale_with_price_edit_keeps_sale_price_and_snapshots_normal_price(): void
    {
        $product = $this->product('Override V3 price product');

        $this->postJson(route('sales.v3.store'), $this->payload($product, '99.50', true))
            ->assertOk();

        $item = Sale::query()->sole()->items()->sole();

        $this->assertSame('99.50', $item->selling_price);
        $this->assertSame('100.00', $item->original_price);
        $this->assertTrue($item->price_override_flag);
    }

    public function test_v3_override_snapshot_uses_the_latest_delivery_zone_context(): void
    {
        $product = $this->product('Delivery context V3 price product');
        $customer = Customer::query()->create(['name' => 'Delivery context customer']);
        $zone = DeliveryZone::query()->create([
            'name' => 'Delivery context zone',
            'price_markup_percent' => '10.00',
            'minimum_profit' => '0.00',
            'rounding_increment' => '0.25',
            'active' => true,
        ]);
        $address = CustomerDeliveryAddress::query()->create([
            'customer_id' => $customer->id,
            'delivery_zone_id' => $zone->id,
            'name' => 'Delivery context address',
        ]);

        $payload = $this->payload($product, '99.50', true);
        $payload['customer_id'] = $customer->id;
        $payload['customer_delivery_address_id'] = $address->id;
        $payload['delivery_type'] = 'delivery';

        $this->postJson(route('sales.v3.store'), $payload)->assertOk();

        $item = Sale::query()->sole()->items()->sole();

        $this->assertSame('99.50', $item->selling_price);
        $this->assertSame('110.00', $item->original_price);
        $this->assertTrue($item->price_override_flag);
    }

    public function test_v3_documents_render_the_override_sale_price(): void
    {
        $product = $this->product('Document V3 price product');

        $this->postJson(route('sales.v3.store'), $this->payload($product, '99.50', true))
            ->assertOk();

        $sale = Sale::query()->sole();
        $service = app(CommercialDocumentService::class);

        foreach (['delivery-note', 'tax-invoice'] as $documentType) {
            $html = view('sales.invoice_v2', [
                'sale' => $sale->fresh(),
                'setting' => null,
                'document' => $service->buildSaleDocument($sale, $documentType),
            ])->render();

            $this->assertStringContainsString('99.50', $html);
        }
    }

    private function payload(Product $product, string $price, bool $priceWasEdited): array
    {
        return [
            'idempotency_key' => str()->uuid()->toString(),
            'sale_date' => '2026-08-03',
            'delivery_type' => 'pickup',
            'payment_method' => 'cash',
            'cash_amount' => $price,
            'promptpay_amount' => '0.00',
            'received_amount' => $price,
            'items' => [[
                'product_id' => $product->id,
                'product_unit_id' => null,
                'qty' => '1.00',
                'selling_price' => $price,
                'price_was_edited' => $priceWasEdited,
            ]],
        ];
    }

    private function product(string $name): Product
    {
        $category = Category::query()->firstOrCreate(['name' => 'V3 price override category']);

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
}
