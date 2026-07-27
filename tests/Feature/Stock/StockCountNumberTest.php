<?php

namespace Tests\Feature\Stock;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockCountItem;
use App\Services\StockCountNumberService;
use App\Services\StockCountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class StockCountNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbers_keep_the_existing_format_and_increment_per_day(): void
    {
        $service = app(StockCountNumberService::class);

        $this->assertSame('SC-20260715-0001', $service->generate('2026-07-15'));
        $this->assertSame('SC-20260715-0002', $service->generate('2026-07-15'));
        $this->assertSame('SC-20260716-0001', $service->generate('2026-07-16'));
    }

    public function test_number_width_is_a_minimum_when_the_suffix_exceeds_9999(): void
    {
        DB::table('stock_count_number_counters')->insert([
            'count_date' => '2026-07-15',
            'last_number' => 9999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            'SC-20260715-10000',
            app(StockCountNumberService::class)->generate('2026-07-15')
        );
    }

    public function test_failed_transaction_rolls_back_the_counter_and_reuses_the_number(): void
    {
        try {
            DB::transaction(function (): never {
                $this->assertSame(
                    'SC-20260715-0001',
                    app(StockCountNumberService::class)->generate('2026-07-15')
                );

                throw new RuntimeException('Synthetic rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic rollback', $exception->getMessage());
        }

        $this->assertDatabaseMissing('stock_count_number_counters', [
            'count_date' => '2026-07-15',
        ]);
        $this->assertSame(
            'SC-20260715-0001',
            app(StockCountNumberService::class)->generate('2026-07-15')
        );
    }

    public function test_stock_count_failure_rolls_back_header_stock_and_counter(): void
    {
        $product = $this->product('Counter rollback', '10.0000');
        StockCountItem::creating(function (): never {
            throw new RuntimeException('Synthetic failure after number allocation');
        });

        try {
            app(StockCountService::class)->create([
                'count_date' => '2026-07-15',
                'items' => [['product_id' => $product->id, 'actual_qty' => '8.0000']],
            ]);
            $this->fail('The synthetic Stock Count failure should be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic failure after number allocation', $exception->getMessage());
            // The assertions below prove every write, including the counter, rolled back.
        }

        $this->assertDatabaseCount('stock_counts', 0);
        $this->assertDatabaseCount('stock_count_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('stock_count_number_counters', 0);
        $this->assertSame('10.0000', $product->fresh()->stock_qty);
    }

    public function test_stock_count_page_has_a_single_submit_guard_without_browser_idempotency(): void
    {
        $script = file_get_contents(public_path('js/modules/stock-count.js'));
        $view = file_get_contents(resource_path('views/stock-counts/index.blade.php'));

        $this->assertStringContainsString("$('#stock-count-form').on('submit'", $script);
        $this->assertStringContainsString("form.data('submitting')", $script);
        $this->assertStringContainsString("form.data('submitting', true)", $script);
        $this->assertStringContainsString("form.find('[type=\"submit\"]').prop('disabled', true)", $script);
        $this->assertStringContainsString('id="stock-count-form"', $view);
        $this->assertStringNotContainsString('idempotency', strtolower($script.$view));
    }

    private function product(string $name, string $stock): Product
    {
        $category = Category::query()->firstOrCreate(['name' => 'Stock count number category']);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'cost_price' => '5.00',
            'selling_price' => '10.00',
            'stock_qty' => $stock,
            'minimum_stock' => '0.0000',
            'active' => true,
        ]);
    }
}
