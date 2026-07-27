<?php

namespace Tests\Feature\Stock;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockCountItem;
use App\Models\StockMovement;
use App\Services\StockCountService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class StockCountIntegrityTest extends TestCase
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

    public function test_decimal_stock_count_preserves_four_decimal_places_and_movement_chain(): void
    {
        $product = $this->product('Decimal stock', '10.7500');

        $stockCount = app(StockCountService::class)->create([
            'count_date' => '2026-07-15',
            'items' => [[
                'product_id' => $product->id,
                'actual_qty' => '9.5000',
            ]],
        ]);

        $item = $stockCount->items()->sole();
        $movement = StockMovement::query()->where('product_id', $product->id)->sole();

        $this->assertSame('10.7500', $item->system_qty);
        $this->assertSame('9.5000', $item->actual_qty);
        $this->assertSame('-1.2500', $item->difference);
        $this->assertSame('-1.2500', $movement->qty);
        $this->assertSame('10.7500', $movement->stock_before);
        $this->assertSame('9.5000', $movement->stock_after);
        $this->assertSame('9.5000', $product->fresh()->stock_qty);
    }

    #[DataProvider('validQuantities')]
    public function test_valid_decimal_quantities_are_accepted(string $quantity, string $expected): void
    {
        $product = $this->product('Valid quantity '.$quantity, '2.0000');

        $this->from(route('stock-counts.index'))->post(route('stock-counts.store'), [
            'count_date' => '2026-07-15',
            'product_id' => [$product->id],
            'actual_qty' => [$quantity],
        ])->assertRedirect(route('stock-counts.index'));

        $this->assertSame($expected, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('stock_counts', 1);
    }

    public static function validQuantities(): array
    {
        return [
            'zero' => ['0', '0.0000'],
            'smallest supported fraction' => ['0.0001', '0.0001'],
            'four decimal places' => ['1.2345', '1.2345'],
        ];
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_quantities_are_rejected_without_writes(mixed $quantity): void
    {
        $product = $this->product('Invalid quantity', '10.0000');

        $this->from(route('stock-counts.index'))->post(route('stock-counts.store'), [
            'count_date' => '2026-07-15',
            'product_id' => [$product->id],
            'actual_qty' => [$quantity],
        ])->assertRedirect(route('stock-counts.index'))
            ->assertSessionHasErrors('normalized_items.0.actual_qty');

        $this->assertNoStockCountWrites($product, '10.0000');
    }

    public static function invalidQuantities(): array
    {
        return [
            'negative' => ['-1'],
            'not numeric' => ['not-a-number'],
            'blank' => [''],
            'over precision' => ['1.23456'],
        ];
    }

    public function test_mismatched_parallel_arrays_and_partial_rows_are_rejected(): void
    {
        $product = $this->product('Mismatched arrays', '10.0000');

        foreach ([
            ['product_id' => [$product->id, $product->id], 'actual_qty' => ['8.0000']],
            ['product_id' => [$product->id], 'actual_qty' => ['8.0000', '7.0000']],
            ['product_id' => [$product->id, ''], 'actual_qty' => ['8.0000', '1.0000']],
            ['product_id' => ['', $product->id], 'actual_qty' => ['', '8.0000']],
        ] as $payload) {
            $this->from(route('stock-counts.index'))->post(route('stock-counts.store'), [
                'count_date' => '2026-07-15',
                ...$payload,
            ])->assertRedirect(route('stock-counts.index'))
                ->assertSessionHasErrors();

            $this->assertNoStockCountWrites($product, '10.0000');
        }
    }

    public function test_fully_blank_trailing_row_is_ignored_but_an_empty_document_is_rejected(): void
    {
        $product = $this->product('Trailing blank', '10.0000');

        $this->post(route('stock-counts.store'), [
            'count_date' => '2026-07-15',
            'product_id' => [$product->id, ''],
            'actual_qty' => ['8.2500', ''],
        ])->assertRedirect(route('stock-counts.index'));

        $this->assertDatabaseCount('stock_counts', 1);
        $this->assertDatabaseCount('stock_count_items', 1);

        $this->post(route('stock-counts.store'), [
            'count_date' => '2026-07-15',
            'product_id' => [''],
            'actual_qty' => [''],
        ])->assertSessionHasErrors('normalized_items');

        $this->assertDatabaseCount('stock_counts', 1);
    }

    public function test_duplicate_product_ids_are_rejected_after_integer_normalization(): void
    {
        $product = $this->product('Duplicate product', '10.0000');

        $this->post(route('stock-counts.store'), [
            'count_date' => '2026-07-15',
            'product_id' => [str_pad((string) $product->id, 2, '0', STR_PAD_LEFT), $product->id],
            'actual_qty' => ['8.0000', '7.0000'],
        ])->assertSessionHasErrors('product_id');

        $this->assertNoStockCountWrites($product, '10.0000');
    }

    public function test_inactive_product_can_be_counted_and_browser_system_stock_is_ignored(): void
    {
        $product = $this->product('Inactive product', '10.7500', false);

        $this->post(route('stock-counts.store'), [
            'count_date' => '2026-07-15',
            'product_id' => [$product->id],
            'system_qty' => ['9999.0000'],
            'actual_qty' => ['9.5000'],
        ])->assertRedirect(route('stock-counts.index'));

        $item = StockCountItem::query()->where('product_id', $product->id)->sole();
        $this->assertSame('10.7500', $item->system_qty);
        $this->assertSame('9.5000', $product->fresh()->stock_qty);
    }

    public function test_missing_product_and_invalid_date_do_not_write_any_stock_data(): void
    {
        $product = $this->product('Unchanged product', '10.0000');

        foreach ([
            ['count_date' => '15/07/2026', 'product_id' => [$product->id], 'actual_qty' => ['8.0000']],
            ['count_date' => '2026-07-15', 'product_id' => [999999], 'actual_qty' => ['8.0000']],
        ] as $payload) {
            $this->post(route('stock-counts.store'), $payload)->assertSessionHasErrors();
            $this->assertNoStockCountWrites($product, '10.0000');
        }
    }

    public function test_stock_count_page_displays_escaped_flash_and_validation_messages_with_old_input(): void
    {
        $product = $this->product('Flash product', '10.0000');

        $this->withSession(['error' => '<script>alert("unsafe")</script>'])
            ->get(route('stock-counts.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(&quot;unsafe&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("unsafe")</script>', false);

        $this->from(route('stock-counts.index'))->post(route('stock-counts.store'), [
            'count_date' => '2026-07-15',
            'remark' => 'Keep this remark',
            'product_id' => [$product->id],
            'actual_qty' => ['invalid'],
        ])->assertRedirect(route('stock-counts.index'))
            ->assertSessionHasInput('remark', 'Keep this remark');
    }

    public function test_stock_count_page_lists_inactive_products_for_physical_counting(): void
    {
        $inactive = $this->product('Inactive physical stock', '4.0000', false);

        $this->get(route('stock-counts.index'))
            ->assertOk()
            ->assertSee('value="'.$inactive->id.'"', false)
            ->assertSee('Inactive physical stock (ปิดขาย)');
    }

    public function test_exception_during_item_creation_rolls_back_every_write(): void
    {
        $product = $this->product('Rollback product', '10.0000');
        StockCountItem::creating(function (): never {
            throw new RuntimeException('Synthetic test-only failure');
        });

        try {
            app(StockCountService::class)->create([
                'count_date' => '2026-07-15',
                'items' => [['product_id' => $product->id, 'actual_qty' => '8.0000']],
            ]);
            $this->fail('The synthetic Stock Count failure should be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic test-only failure', $exception->getMessage());
        }

        $this->assertNoStockCountWrites($product, '10.0000');
    }

    public function test_unexpected_error_returns_a_generic_message_without_internal_details(): void
    {
        $product = $this->product('Unexpected error product', '10.0000');
        $service = Mockery::mock(StockCountService::class);
        $service->shouldReceive('create')->once()->andThrow(new RuntimeException('SQL secret details'));
        $this->app->instance(StockCountService::class, $service);

        $this->from(route('stock-counts.index'))->post(route('stock-counts.store'), [
            'count_date' => '2026-07-15',
            'product_id' => [$product->id],
            'actual_qty' => ['8.0000'],
        ])->assertRedirect(route('stock-counts.index'))
            ->assertSessionHas('error', 'ไม่สามารถบันทึกการตรวจนับสต๊อกได้ กรุณาลองใหม่อีกครั้ง')
            ->assertSessionMissing('SQL secret details');

        $this->assertNoStockCountWrites($product, '10.0000');
    }

    private function product(string $name, string $stock, bool $active = true): Product
    {
        $category = Category::query()->firstOrCreate(['name' => 'Stock count category']);

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

    private function assertNoStockCountWrites(Product $product, string $expectedStock): void
    {
        $this->assertDatabaseCount('stock_counts', 0);
        $this->assertDatabaseCount('stock_count_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame($expectedStock, $product->fresh()->stock_qty);
    }
}
