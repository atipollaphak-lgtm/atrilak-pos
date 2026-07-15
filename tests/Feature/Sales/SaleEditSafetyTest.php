<?php

namespace Tests\Feature\Sales;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class SaleEditSafetyTest extends TestCase
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

    public function test_edit_includes_inactive_historical_product(): void
    {
        [$sale, $product] = $this->existingSale(productActive: false);

        $this->get(route('sales.edit', $sale))
            ->assertOk()
            ->assertSee($product->name.' (ปิดใช้งาน)');
    }

    public function test_edit_keeps_inactive_historical_product_selected(): void
    {
        [$sale, $product] = $this->existingSale(productActive: false);

        $html = $this->get(route('sales.edit', $sale))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<option value="'.preg_quote((string) $product->id, '/').'"[^>]*selected[^>]*>/',
            $html
        );
    }

    public function test_edit_includes_inactive_historical_customer(): void
    {
        [$sale, , $customer] = $this->existingSale(customerActive: false);

        $this->get(route('sales.edit', $sale))
            ->assertOk()
            ->assertSee($customer->name.' (ปิดใช้งาน)');
    }

    public function test_edit_keeps_inactive_historical_customer_selected(): void
    {
        [$sale, , $customer] = $this->existingSale(customerActive: false);

        $html = $this->get(route('sales.edit', $sale))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<option value="'.preg_quote((string) $customer->id, '/').'"[^>]*selected[^>]*>/',
            $html
        );
    }

    public function test_validation_failure_preserves_product_rows(): void
    {
        [$sale, $historicalProduct] = $this->existingSale(productActive: false);
        $secondProduct = $this->product('Second active product');
        $item = $sale->items()->sole();

        $this->from(route('sales.edit', $sale))
            ->put(route('sales.update', $sale), $this->payload(
                saleItemIds: [$item->id, null],
                productIds: [$historicalProduct->id, $secondProduct->id],
                quantities: ['invalid', '2.00'],
                prices: ['11.00', '22.00'],
                customerId: $sale->customer_id
            ))
            ->assertRedirect(route('sales.edit', $sale))
            ->assertSessionHasErrors();

        $html = $this->get(route('sales.edit', $sale))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'class="form-control product-select"'));
        $this->assertStringContainsString('value="invalid"', $html);
        $this->assertStringContainsString('value="22.00"', $html);
    }

    public function test_validation_failure_preserves_customer(): void
    {
        [$sale, $product, $customer] = $this->existingSale(customerActive: false);
        $item = $sale->items()->sole();

        $this->from(route('sales.edit', $sale))
            ->put(route('sales.update', $sale), $this->payload(
                saleItemIds: [$item->id],
                productIds: [$product->id],
                quantities: ['invalid'],
                prices: ['10.00'],
                customerId: $customer->id
            ))
            ->assertSessionHasInput('customer_id', (string) $customer->id);

        $html = $this->get(route('sales.edit', $sale))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<option value="'.preg_quote((string) $customer->id, '/').'"[^>]*selected[^>]*>/',
            $html
        );
    }

    public function test_validation_failure_preserves_qty_price_and_sale_item_id(): void
    {
        [$sale, $product] = $this->existingSale();
        $item = $sale->items()->sole();

        $this->from(route('sales.edit', $sale))
            ->put(route('sales.update', $sale), $this->payload(
                saleItemIds: [$item->id],
                productIds: [$product->id],
                quantities: ['invalid'],
                prices: ['123.45'],
                customerId: $sale->customer_id
            ))
            ->assertSessionHasErrors()
            ->assertSessionHasInput('sale_item_id.0', (string) $item->id)
            ->assertSessionHasInput('qty.0', 'invalid')
            ->assertSessionHasInput('selling_price.0', '123.45');

        $errors = (new ViewErrorBag)->put(
            'default',
            new MessageBag(['qty.0' => ['จำนวนสินค้าไม่ถูกต้อง']])
        );
        $html = view('sales.edit', [
            'sale' => $sale->load('items.product', 'items.productUnit', 'customer'),
            'customers' => Customer::query()->get(),
            'products' => Product::query()->get(),
            'errors' => $errors,
        ])->render();

        $this->assertStringContainsString('value="'.(string) $item->id.'"', $html);
        $this->assertStringContainsString('value="invalid"', $html);
        $this->assertStringContainsString('value="123.45"', $html);
        $this->assertStringContainsString('alert alert-danger', $html);
    }

    public function test_validation_messages_use_readable_item_numbers(): void
    {
        [$sale, $product] = $this->existingSale();
        $secondProduct = $this->product('Second validation product');
        $item = $sale->items()->sole();

        foreach ([
            'invalid' => 'จำนวนสินค้ารายการที่ 2 ไม่ถูกต้อง',
            '1.234' => 'จำนวนสินค้ารายการที่ 2 รับได้สูงสุด 2 ตำแหน่งทศนิยม',
            '0' => 'จำนวนสินค้ารายการที่ 2 ต้องมากกว่า 0',
        ] as $quantity => $expectedMessage) {
            $this->put(route('sales.update', $sale), $this->payload(
                saleItemIds: [$item->id, null],
                productIds: [$product->id, $secondProduct->id],
                quantities: ['1.00', $quantity],
                prices: ['10.00', '10.00'],
                customerId: $sale->customer_id
            ))->assertSessionHasErrors([
                'normalized_items.1.qty' => $expectedMessage,
            ]);
        }
    }

    public function test_edit_does_not_fallback_to_first_product(): void
    {
        $firstActive = $this->product('First active product');
        [$sale, $historicalInactive] = $this->existingSale(productActive: false);

        $html = $this->get(route('sales.edit', $sale))->assertOk()->getContent();

        preg_match(
            '/<select name="product_id\[\]"[^>]*>.*?<option value="(\d+)"[^>]*selected[^>]*>/s',
            $html,
            $selectedProduct
        );

        $this->assertSame((string) $historicalInactive->id, $selectedProduct[1] ?? null);
        $this->assertNotSame((string) $firstActive->id, $selectedProduct[1] ?? null);
    }

    public function test_inactive_unrelated_product_is_not_offered_for_new_selection(): void
    {
        [$sale] = $this->existingSale();
        $inactiveUnrelated = $this->product('Inactive unrelated product', false);

        $this->get(route('sales.edit', $sale))
            ->assertOk()
            ->assertDontSee($inactiveUnrelated->name);
    }

    public function test_update_rejects_new_inactive_product_and_customer(): void
    {
        [$sale, $product] = $this->existingSale();
        $item = $sale->items()->sole();
        $inactiveProduct = $this->product('Inactive replacement', false);
        $inactiveCustomer = $this->customer('Inactive replacement customer', false);

        $this->put(route('sales.update', $sale), $this->payload(
            saleItemIds: [$item->id],
            productIds: [$inactiveProduct->id],
            quantities: ['1.00'],
            prices: ['10.00'],
            customerId: $sale->customer_id
        ))->assertSessionHasErrors('product_id');

        $this->put(route('sales.update', $sale), $this->payload(
            saleItemIds: [$item->id],
            productIds: [$product->id],
            quantities: ['1.00'],
            prices: ['10.00'],
            customerId: $inactiveCustomer->id
        ))->assertSessionHasErrors('customer_id');

        $this->assertSame($product->id, $sale->fresh()->items()->sole()->product_id);
    }

    public function test_update_rejects_reusing_historical_inactive_product_in_new_row(): void
    {
        [$sale, $inactiveProduct] = $this->existingSale(productActive: false);
        $item = $sale->items()->sole();

        $this->put(route('sales.update', $sale), $this->payload(
            saleItemIds: [$item->id, null],
            productIds: [$inactiveProduct->id, $inactiveProduct->id],
            quantities: ['1.00', '1.00'],
            prices: ['10.00', '10.00'],
            customerId: $sale->customer_id
        ))->assertSessionHasErrors('product_id');

        $this->assertCount(1, $sale->fresh()->items);
    }

    public function test_successful_header_only_edit_does_not_change_items_unintentionally(): void
    {
        [$sale, $product, $customer] = $this->existingSale(
            productActive: false,
            customerActive: false
        );
        $item = $sale->items()->sole();
        $beforeMovements = DB::table('stock_movements')->count();

        $this->put(route('sales.update', $sale), $this->payload(
            saleItemIds: [$item->id],
            productIds: [$product->id],
            quantities: [$item->qty],
            prices: [$item->selling_price],
            customerId: $customer->id,
            saleDate: '2026-07-15'
        ))->assertRedirect(route('sales.show', $sale));

        $sale->refresh();
        $updatedItem = $sale->items()->sole();

        $this->assertSame($customer->id, $sale->customer_id);
        $this->assertSame('2026-07-15', $sale->sale_date);
        $this->assertSame($item->id, $updatedItem->id);
        $this->assertSame($product->id, $updatedItem->product_id);
        $this->assertSame('1.00', $updatedItem->qty);
        $this->assertSame('10.00', $updatedItem->selling_price);
        $this->assertSame('9.0000', $product->fresh()->stock_qty);
        $this->assertSame($beforeMovements, DB::table('stock_movements')->count());
    }

    private function existingSale(bool $productActive = true, bool $customerActive = true): array
    {
        $customer = $this->customer('Historical customer', $customerActive);
        $product = $this->product('Historical product', $productActive, '9.0000');
        $sale = Sale::query()->create([
            'sale_no' => 'SAL-EDIT-SAFETY-1',
            'customer_id' => $customer->id,
            'sale_date' => '2026-07-14',
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

        return [$sale, $product, $customer];
    }

    private function payload(
        array $saleItemIds,
        array $productIds,
        array $quantities,
        array $prices,
        ?int $customerId,
        string $saleDate = '2026-07-14'
    ): array {
        return [
            'customer_id' => $customerId,
            'sale_date' => $saleDate,
            'sale_item_id' => $saleItemIds,
            'product_unit_id' => array_fill(0, count($productIds), null),
            'product_id' => $productIds,
            'qty' => $quantities,
            'selling_price' => $prices,
            'delivery_fee' => '0.00',
            'discount' => '0.00',
        ];
    }

    private function product(
        string $name,
        bool $active = true,
        string $stock = '20.0000'
    ): Product {
        $category = Category::query()->firstOrCreate(['name' => 'Sale edit safety category']);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'cost_price' => '5.00',
            'selling_price' => '10.00',
            'stock_qty' => $stock,
            'minimum_stock' => '0.0000',
            'active' => $active,
        ]);
    }

    private function customer(string $name, bool $active = true): Customer
    {
        return Customer::query()->create([
            'name' => $name,
            'active' => $active,
        ]);
    }
}
