<?php

namespace Tests\Feature\Quotations;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use App\Services\SaleService;
use DomainException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuotationConversionReliabilityTest extends TestCase
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

    public function test_conversion_and_retry_return_the_same_sale_without_duplicate_writes(): void
    {
        $product = $this->product(stock: 10);
        $quotation = $this->quotation($product, qty: 2, price: 30, headerTotal: 60);
        $service = app(SaleService::class);

        $sale = $service->createSaleFromQuotation($quotation);
        $replayedSale = $service->createSaleFromQuotation($quotation);

        $this->assertSame($sale->id, $replayedSale->id);
        $this->assertSame($quotation->id, $sale->quotation_id);
        $this->assertSame('converted', $quotation->fresh()->status);
        $this->assertEquals(60, $sale->total_amount);
        $this->assertEquals(60, $sale->items()->sole()->total);
        $this->assertNull($sale->items()->sole()->product_unit_id);
        $this->assertSame('1.0000', $sale->items()->sole()->conversion_rate_used);
        $this->assertSame('2.0000', $sale->items()->sole()->base_qty);
        $this->assertNull($sale->payment_method);
        $this->assertNull($sale->cash_amount);
        $this->assertNull($sale->promptpay_amount);
        $this->assertNull($sale->received_amount);
        $this->assertNull($sale->change_amount);
        $this->assertSame($sale->id, $quotation->fresh()->convertedSale->id);
        $this->assertEquals(8, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('technician_commissions', 0);
        $this->assertSame(1, DB::table('sale_number_counters')->value('last_number'));
    }

    public function test_controller_retry_redirects_to_the_existing_sale(): void
    {
        $quotation = $this->quotation($this->product(), qty: 1, price: 30, headerTotal: 30);
        $sale = app(SaleService::class)->createSaleFromQuotation($quotation);

        $this->from(route('quotations.show', $quotation))
            ->post(route('quotations.convert', $quotation))
            ->assertRedirect(route('sales.show', $sale));

        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_converted_legacy_quotation_without_relation_is_not_converted_again(): void
    {
        $quotation = $this->quotation($this->product(), status: 'converted');

        try {
            app(SaleService::class)->createSaleFromQuotation($quotation);
            $this->fail('Legacy converted quotation should not create another sale.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('ระบบรุ่นเก่า', $exception->getMessage());
        }

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame('converted', $quotation->fresh()->status);
    }

    public function test_draft_quotation_with_sale_relation_is_not_auto_repaired_or_converted(): void
    {
        $quotation = $this->quotation($this->product());
        $sale = new Sale([
            'sale_no' => 'SAL-20260714-9001',
            'sale_date' => '2026-07-14',
            'total_amount' => 30,
        ]);
        $sale->quotation_id = $quotation->id;
        $sale->save();

        try {
            app(SaleService::class)->createSaleFromQuotation($quotation);
            $this->fail('Inconsistent quotation should not create another sale.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('สถานะใบเสนอราคาไม่สัมพันธ์', $exception->getMessage());
        }

        $this->assertSame('draft', $quotation->fresh()->status);
        $this->assertSame($sale->id, Sale::query()->sole()->id);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_legacy_and_inconsistent_conversion_errors_are_visible_on_quotation_show(): void
    {
        $legacy = $this->quotation($this->product(), status: 'converted');

        $this->from(route('quotations.show', $legacy))
            ->post(route('quotations.convert', $legacy))
            ->assertRedirect(route('quotations.show', $legacy))
            ->assertSessionHas('error');

        $legacyMessage = session('error');
        $this->get(route('quotations.show', $legacy))
            ->assertOk()
            ->assertSeeText($legacyMessage);

        $inconsistent = $this->quotation($this->product());
        $sale = new Sale([
            'sale_no' => 'SAL-20260714-9003',
            'sale_date' => '2026-07-14',
            'total_amount' => 30,
        ]);
        $sale->quotation_id = $inconsistent->id;
        $sale->save();

        $this->from(route('quotations.show', $inconsistent))
            ->post(route('quotations.convert', $inconsistent))
            ->assertRedirect(route('quotations.show', $inconsistent))
            ->assertSessionHas('error');

        $inconsistentMessage = session('error');
        $this->get(route('quotations.show', $inconsistent))
            ->assertOk()
            ->assertSeeText($inconsistentMessage);

        $this->assertSame('draft', $inconsistent->fresh()->status);
        $this->assertSame($sale->id, $inconsistent->fresh()->convertedSale->id);
    }

    public function test_stock_failure_rolls_back_relation_status_counter_and_all_sale_writes(): void
    {
        $product = $this->product(stock: 1);
        $quotation = $this->quotation($product, qty: 2);

        try {
            app(SaleService::class)->createSaleFromQuotation($quotation);
            $this->fail('Insufficient stock should reject conversion.');
        } catch (DomainException) {
            // Expected domain rejection.
        }

        $this->assertSame('draft', $quotation->fresh()->status);
        $this->assertNull($quotation->fresh()->convertedSale);
        $this->assertEquals(1, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('technician_commissions', 0);
        $this->assertDatabaseCount('sale_number_counters', 0);
    }

    public function test_stock_failure_error_is_visible_without_partial_writes(): void
    {
        $product = $this->product(stock: 1);
        $quotation = $this->quotation($product, qty: 2);

        $this->from(route('quotations.show', $quotation))
            ->post(route('quotations.convert', $quotation))
            ->assertRedirect(route('quotations.show', $quotation))
            ->assertSessionHas('error');

        $message = session('error');
        $this->get(route('quotations.show', $quotation))
            ->assertOk()
            ->assertSeeText($message);

        $this->assertSame('draft', $quotation->fresh()->status);
        $this->assertEquals(1, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_empty_and_missing_product_errors_are_visible_without_partial_writes(): void
    {
        $empty = Quotation::query()->create([
            'quotation_no' => 'QT-EMPTY-FLASH',
            'quotation_date' => '2026-07-14',
            'total_amount' => 0,
            'status' => 'draft',
        ]);
        $missingProduct = $this->quotation($this->product());
        $missingProduct->items()->update(['product_id' => null]);

        foreach ([$empty, $missingProduct] as $quotation) {
            $this->from(route('quotations.show', $quotation))
                ->post(route('quotations.convert', $quotation))
                ->assertRedirect(route('quotations.show', $quotation))
                ->assertSessionHas('error');

            $message = session('error');
            $this->get(route('quotations.show', $quotation))
                ->assertOk()
                ->assertSeeText($message);

            $this->assertSame('draft', $quotation->fresh()->status);
        }

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('technician_commissions', 0);
    }

    public function test_deleted_product_rejects_conversion_without_partial_writes(): void
    {
        $product = $this->product();
        $quotation = $this->quotation($product);
        $product->delete();

        $this->from(route('quotations.show', $quotation))
            ->post(route('quotations.convert', $quotation))
            ->assertRedirect(route('quotations.show', $quotation))
            ->assertSessionHas('error');

        $this->assertSame('draft', $quotation->fresh()->status);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_linked_sale_and_quotation_deletes_are_rejected_but_unlinked_records_still_delete(): void
    {
        $product = $this->product(stock: 10);
        $quotation = $this->quotation($product);
        $linkedSale = app(SaleService::class)->createSaleFromQuotation($quotation);
        $stockAfterConversion = $product->fresh()->stock_qty;

        $this->from(route('sales.show', $linkedSale))
            ->delete(route('sales.destroy', $linkedSale))
            ->assertRedirect(route('sales.show', $linkedSale))
            ->assertSessionHas('error');
        $saleDeleteMessage = session('error');
        $this->get(route('sales.show', $linkedSale))
            ->assertOk()
            ->assertSeeText($saleDeleteMessage);

        $this->from(route('quotations.index'))
            ->delete(route('quotations.destroy', $quotation))
            ->assertRedirect(route('quotations.index'))
            ->assertSessionHas('error');
        $quotationDeleteMessage = session('error');
        $this->get(route('quotations.index'))
            ->assertOk()
            ->assertSeeText($quotationDeleteMessage);

        $this->assertDatabaseHas('sales', ['id' => $linkedSale->id]);
        $this->assertDatabaseHas('quotations', ['id' => $quotation->id]);
        $this->assertEquals($stockAfterConversion, $product->fresh()->stock_qty);

        $normalSale = Sale::query()->create([
            'sale_no' => 'SAL-20260714-9002',
            'sale_date' => '2026-07-14',
            'total_amount' => 0,
        ]);
        app(SaleService::class)->deleteSale($normalSale);
        $this->assertDatabaseMissing('sales', ['id' => $normalSale->id]);

        $draftQuotation = Quotation::query()->create([
            'quotation_no' => 'QT-DELETABLE',
            'quotation_date' => '2026-07-14',
            'total_amount' => 0,
            'status' => 'draft',
        ]);
        app(SaleService::class)->deleteQuotation($draftQuotation);
        $this->assertDatabaseMissing('quotations', ['id' => $draftQuotation->id]);
    }

    public function test_flash_messages_are_escaped_as_plain_text(): void
    {
        $quotation = $this->quotation($this->product());
        $unsafe = '<script>alert("unsafe")</script>';

        $this->withSession(['error' => $unsafe])
            ->get(route('quotations.show', $quotation))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(&quot;unsafe&quot;)&lt;/script&gt;', false)
            ->assertDontSee($unsafe, false);
    }

    private function product(float $stock = 10, bool $active = true): Product
    {
        $category = Category::query()->create([
            'name' => 'Quotation category '.uniqid(),
            'active' => true,
        ]);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Quotation product '.uniqid(),
            'unit' => 'piece',
            'cost_price' => 10,
            'selling_price' => 30,
            'stock_qty' => $stock,
            'minimum_stock' => 0,
            'vat_enabled' => false,
            'active' => $active,
            'auto_price_enabled' => false,
        ]);
    }

    private function quotation(
        Product $product,
        int $qty = 1,
        int $price = 30,
        int $headerTotal = 30,
        string $status = 'draft'
    ): Quotation {
        $quotation = Quotation::query()->create([
            'quotation_no' => 'QT-'.uniqid(),
            'quotation_date' => '2026-07-14',
            'total_amount' => $headerTotal,
            'status' => $status,
        ]);
        $quotation->items()->create([
            'product_id' => $product->id,
            'qty' => $qty,
            'selling_price' => $price,
            'total' => $qty * $price,
        ]);

        return $quotation;
    }
}
