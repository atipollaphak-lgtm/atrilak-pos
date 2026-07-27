<?php

namespace Tests\Feature\Purchases;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PurchaseValidationTest extends TestCase
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

    public function test_purchase_resource_routes_are_registered_once(): void
    {
        foreach ([
            'purchases.edit' => ['GET', 'HEAD'],
            'purchases.update' => ['PUT', 'PATCH'],
            'purchases.show' => ['GET', 'HEAD'],
        ] as $name => $methods) {
            $routes = collect(Route::getRoutes()->getRoutes())
                ->filter(fn ($route) => $route->getName() === $name);

            $this->assertCount(1, $routes, "Route [{$name}] must be registered once.");
            $this->assertSame($methods, $routes->sole()->methods());
        }
    }

    public function test_create_accepts_four_decimal_quantity_and_ignores_a_fully_blank_trailing_row(): void
    {
        $supplier = $this->supplier('Create supplier');
        $product = $this->product('Create product');

        $this->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-07-15',
            'product_id' => [$product->id, ''],
            'qty' => ['0.1234', ''],
            'cost_price' => ['25.50', ''],
        ])->assertRedirect();

        $purchase = Purchase::query()->sole();
        $this->assertDatabaseCount('purchase_items', 1);
        $this->assertSame('0.1234', $purchase->items()->sole()->qty);
        $this->assertSame('0.1234', $product->fresh()->stock_qty);
    }

    public function test_create_rejects_inactive_or_missing_supplier_and_product_without_writes(): void
    {
        $activeSupplier = $this->supplier('Active supplier');
        $inactiveSupplier = $this->supplier('Inactive supplier', false);
        $activeProduct = $this->product('Active product');
        $inactiveProduct = $this->product('Inactive product', false);

        foreach ([
            [$inactiveSupplier->id, $activeProduct->id],
            [999999, $activeProduct->id],
            [$activeSupplier->id, $inactiveProduct->id],
            [$activeSupplier->id, 999999],
        ] as [$supplierId, $productId]) {
            $this->post(route('purchases.store'), $this->payload($supplierId, [$productId]))
                ->assertSessionHasErrors();

            $this->assertDatabaseCount('purchases', 0);
            $this->assertDatabaseCount('purchase_items', 0);
        }
    }

    public function test_update_keeps_original_inactive_references_visible_and_usable(): void
    {
        $supplier = $this->supplier('Legacy inactive supplier', false);
        $product = $this->product('Legacy inactive product', false);
        $purchase = $this->legacyPurchase($supplier, $product);

        $response = $this->get(route('purchases.edit', $purchase))
            ->assertOk()
            ->assertSee('Legacy inactive supplier (ปิดใช้งาน)')
            ->assertSee('Legacy inactive product (ปิดใช้งาน)');

        $this->assertMatchesRegularExpression(
            '/<option value="'.preg_quote((string) $supplier->id, '/').'"\s+selected>/',
            $response->getContent()
        );
        $this->assertMatchesRegularExpression(
            '/<option value="'.preg_quote((string) $product->id, '/').'"\s+selected>/',
            $response->getContent()
        );

        $this->put(route('purchases.update', $purchase), $this->payload($supplier->id, [$product->id]))
            ->assertRedirect(route('purchases.show', $purchase));

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'supplier_id' => $supplier->id,
        ]);
        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_update_rejects_a_new_inactive_reference(): void
    {
        $supplier = $this->supplier('Original active supplier');
        $product = $this->product('Original active product');
        $inactiveSupplier = $this->supplier('New inactive supplier', false);
        $inactiveProduct = $this->product('New inactive product', false);
        $purchase = $this->legacyPurchase($supplier, $product);

        $this->put(route('purchases.update', $purchase), $this->payload($inactiveSupplier->id, [$product->id]))
            ->assertSessionHasErrors('supplier_id');
        $this->put(route('purchases.update', $purchase), $this->payload($supplier->id, [$inactiveProduct->id]))
            ->assertSessionHasErrors();

        $this->assertSame($supplier->id, $purchase->fresh()->supplier_id);
        $this->assertSame($product->id, $purchase->fresh()->items()->sole()->product_id);
    }

    public function test_mismatched_arrays_partial_rows_duplicates_and_empty_documents_are_rejected(): void
    {
        $supplier = $this->supplier('Invalid payload supplier');
        $first = $this->product('First product');
        $second = $this->product('Second product');
        $payloads = [
            ['product_id' => [$first->id, $second->id], 'qty' => ['1.0000'], 'cost_price' => ['10.00', '20.00']],
            ['product_id' => [$first->id, ''], 'qty' => ['1.0000', '2.0000'], 'cost_price' => ['10.00', '20.00']],
            ['product_id' => [$first->id, $first->id], 'qty' => ['1.0000', '2.0000'], 'cost_price' => ['10.00', '20.00']],
            ['product_id' => [''], 'qty' => [''], 'cost_price' => ['']],
        ];

        foreach ($payloads as $items) {
            $this->post(route('purchases.store'), [
                'supplier_id' => $supplier->id,
                'purchase_date' => '2026-07-15',
                ...$items,
            ])->assertSessionHasErrors();
        }

        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('purchase_items', 0);
    }

    #[DataProvider('invalidDecimals')]
    public function test_invalid_quantity_and_cost_are_rejected(string $field, mixed $value): void
    {
        $supplier = $this->supplier('Decimal supplier');
        $product = $this->product('Decimal product');
        $payload = $this->payload($supplier->id, [$product->id]);
        $payload[$field] = [$value];

        $this->post(route('purchases.store'), $payload)->assertSessionHasErrors();

        $this->assertDatabaseCount('purchases', 0);
        $this->assertSame('0.0000', $product->fresh()->stock_qty);
    }

    public static function invalidDecimals(): array
    {
        return [
            'zero quantity' => ['qty', '0'],
            'negative quantity' => ['qty', '-1'],
            'quantity over precision' => ['qty', '1.23456'],
            'non numeric quantity' => ['qty', 'not-a-number'],
            'zero cost' => ['cost_price', '0'],
            'negative cost' => ['cost_price', '-1'],
            'cost over precision' => ['cost_price', '10.999'],
            'non numeric cost' => ['cost_price', 'not-a-number'],
        ];
    }

    public function test_purchase_pages_render_errors_old_input_valid_forms_and_submit_guard(): void
    {
        $supplier = $this->supplier('UI supplier');
        $product = $this->product('UI product');

        $this->from(route('purchases.index'))->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-07-15',
            'product_id' => [$product->id],
            'qty' => ['invalid'],
            'cost_price' => ['10.00'],
        ])->assertRedirect(route('purchases.index'))
            ->assertSessionHasInput('qty.0', 'invalid');

        $response = $this->get(route('purchases.index'))
            ->assertOk()
            ->assertSee('invalid')
            ->assertSee('id="purchase-create-form"', false)
            ->assertSee('js/modules/purchase.js', false);

        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'id="purchase-create-form"'));
        $script = file_get_contents(public_path('js/modules/purchase.js'));
        $this->assertStringContainsString("form.data('submitting')", $script);
        $this->assertStringContainsString("prop('disabled', true)", $script);
        $this->assertStringNotContainsString('idempotency', strtolower($script));
    }

    public function test_edit_uses_separate_update_and_delete_forms(): void
    {
        $supplier = $this->supplier('Form supplier');
        $product = $this->product('Form product');
        $purchase = $this->legacyPurchase($supplier, $product);

        $html = $this->get(route('purchases.edit', $purchase))->assertOk()->getContent();
        $updateForm = strpos($html, 'id="purchase-update-form"');
        $updateFormEnd = strpos($html, '</form>', $updateForm);
        $deleteAction = strpos($html, route('purchases.destroy', $purchase), $updateFormEnd);

        $this->assertNotFalse($updateForm);
        $this->assertNotFalse($updateFormEnd);
        $this->assertNotFalse($deleteAction);
        $this->assertLessThan($deleteAction, $updateFormEnd);
        $this->assertStringNotContainsString('onsubmit=', $html);
    }

    private function payload(int $supplierId, array $productIds): array
    {
        return [
            'supplier_id' => $supplierId,
            'purchase_date' => '2026-07-15',
            'product_id' => $productIds,
            'qty' => array_fill(0, count($productIds), '1.0000'),
            'cost_price' => array_fill(0, count($productIds), '10.00'),
        ];
    }

    private function legacyPurchase(Supplier $supplier, Product $product): Purchase
    {
        $purchase = Purchase::query()->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-07-15',
            'total_amount' => '10.00',
        ]);
        $purchase->items()->create([
            'product_id' => $product->id,
            'qty' => '1.00',
            'cost_price' => '10.00',
            'total' => '10.00',
        ]);
        $product->update(['stock_qty' => '1.0000']);

        return $purchase;
    }

    private function product(string $name, bool $active = true): Product
    {
        $category = Category::query()->firstOrCreate(['name' => 'Purchase validation category']);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'cost_price' => '5.00',
            'selling_price' => '10.00',
            'stock_qty' => '0.0000',
            'minimum_stock' => '0.0000',
            'active' => $active,
            'auto_price_enabled' => false,
        ]);
    }

    private function supplier(string $name, bool $active = true): Supplier
    {
        return Supplier::query()->create([
            'name' => $name,
            'active' => $active,
        ]);
    }
}
