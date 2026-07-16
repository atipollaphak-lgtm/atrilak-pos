<?php

namespace Tests\Feature\Stock;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\CreatesCompetingStockWriterTestSchema;
use Tests\TestCase;

class CompetingStockWriterConcurrencyTest extends TestCase
{
    use CreatesCompetingStockWriterTestSchema;

    private bool $schemaCreated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required for competing stock writer tests.');
        }

        if (DB::connection()->getDatabaseName() === 'atrilak_pos') {
            $this->fail('Concurrency tests refused the application database.');
        }

        $this->createCompetingStockWriterTestSchema();
        $this->schemaCreated = true;
        $this->installStockUpdateDelayTrigger();
    }

    protected function tearDown(): void
    {
        if ($this->schemaCreated) {
            DB::unprepared('DROP FUNCTION IF EXISTS test_delay_competing_stock_update() CASCADE');
            $this->dropCompetingStockWriterTestSchema();
        }

        DB::disconnect('stock_writer_blocker');
        parent::tearDown();
    }

    public function test_purchase_store_and_sale_create_are_serialized(): void
    {
        $product = $this->product('Purchase store', 10);

        $results = $this->runConcurrently(
            $this->purchaseCreate([[$product->id, 4, 5]]),
            $this->saleCreate([[$product->id, 6, 10]])
        );

        $this->assertAllSucceeded($results);
        $this->assertMovementChain($product, 10, 8);
        $this->assertDatabaseCount('purchases', 1);
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_purchase_update_and_sale_create_are_serialized(): void
    {
        $product = $this->product('Purchase update', 10);
        $purchase = $this->existingPurchase($product, 4);

        $results = $this->runConcurrently(
            $this->purchaseUpdate($purchase, [[$product->id, 2, 7]]),
            $this->saleCreate([[$product->id, 6, 10]])
        );

        $this->assertAllSucceeded($results);
        $this->assertMovementChain($product, 10, 6);
        $this->assertEquals(2, $purchase->fresh()->items()->sole()->qty);
    }

    public function test_purchase_delete_and_sale_create_are_serialized(): void
    {
        $product = $this->product('Purchase delete', 10);
        $purchase = $this->existingPurchase($product, 4);

        $results = $this->runConcurrently(
            ['operation' => 'purchase_delete', 'purchase_id' => $purchase->id],
            $this->saleCreate([[$product->id, 6, 10]])
        );

        $this->assertAllSucceeded($results);
        $this->assertMovementChain($product, 10, 4);
        $this->assertDatabaseMissing('purchases', ['id' => $purchase->id]);
    }

    public function test_stock_count_and_sale_create_form_a_valid_serial_order(): void
    {
        $product = $this->product('Stock count', 10);
        $results = $this->runConcurrently(
            [
                'operation' => 'stock_count',
                'data' => [
                    'count_date' => '2026-07-14',
                    'items' => [['product_id' => $product->id, 'actual_qty' => 8]],
                ],
            ],
            $this->saleCreate([[$product->id, 3, 10]])
        );

        $this->assertAllSucceeded($results);
        $this->assertContains((float) $product->fresh()->stock_qty, [5.0, 8.0]);
        $this->assertMovementChain($product, 10, (float) $product->fresh()->stock_qty);
    }

    public function test_stock_count_and_purchase_create_form_a_valid_serial_order(): void
    {
        $product = $this->product('Stock count purchase', 10);
        $results = $this->runConcurrently(
            $this->stockCount([[$product->id, '8.2500']]),
            $this->purchaseCreate([[$product->id, 3, 5]])
        );

        $this->assertAllSucceeded($results);
        $this->assertContains((float) $product->fresh()->stock_qty, [8.25, 11.25]);
        $this->assertMovementChain($product, 10, (float) $product->fresh()->stock_qty);
    }

    public function test_stock_count_and_manual_adjustment_form_a_valid_serial_order(): void
    {
        $product = $this->product('Stock count adjustment', 10);
        $results = $this->runConcurrently(
            $this->stockCount([[$product->id, '8.2500']]),
            [
                'operation' => 'product_update',
                'product_id' => $product->id,
                'data' => $this->productUpdateData($product, 7),
            ]
        );

        $this->assertAllSucceeded($results);
        $this->assertContains((float) $product->fresh()->stock_qty, [7.0, 8.25]);
        $this->assertMovementChain($product, 10, (float) $product->fresh()->stock_qty);
    }

    public function test_two_stock_counts_are_serialized_with_continuous_movements(): void
    {
        $product = $this->product('Two stock counts', 10);
        $results = $this->runConcurrently(
            $this->stockCount([[$product->id, '8.2500']]),
            $this->stockCount([[$product->id, '7.5000']])
        );

        $this->assertAllSucceeded($results);
        $this->assertContains((float) $product->fresh()->stock_qty, [7.5, 8.25]);
        $this->assertMovementChain($product, 10, (float) $product->fresh()->stock_qty);
        $this->assertDatabaseCount('stock_counts', 2);
        $this->assertDatabaseCount('stock_count_items', 2);
        $this->assertSame(2, DB::table('stock_counts')->pluck('count_no')->unique()->count());
        $this->assertSame(2, DB::table('stock_count_number_counters')->where('count_date', '2026-07-14')->value('last_number'));
    }

    public function test_stock_counts_for_different_products_share_a_safe_daily_counter(): void
    {
        $first = $this->product('Count number first', 10);
        $second = $this->product('Count number second', 10);

        $results = $this->runConcurrently(
            $this->stockCount([[$first->id, 8]]),
            $this->stockCount([[$second->id, 9]])
        );

        $this->assertAllSucceeded($results);
        $this->assertSame(
            ['SC-20260714-0001', 'SC-20260714-0002'],
            DB::table('stock_counts')->orderBy('count_no')->pluck('count_no')->all()
        );
        $this->assertSame(2, DB::table('stock_count_number_counters')->where('count_date', '2026-07-14')->value('last_number'));
    }

    public function test_stock_counts_on_different_dates_use_different_counter_rows(): void
    {
        $first = $this->product('Count date first', 10);
        $second = $this->product('Count date second', 10);

        $results = $this->runConcurrently(
            $this->stockCount([[$first->id, 8]], '2026-07-14'),
            $this->stockCount([[$second->id, 9]], '2026-07-15')
        );

        $this->assertAllSucceeded($results);
        $this->assertSame(
            ['SC-20260714-0001', 'SC-20260715-0001'],
            DB::table('stock_counts')->orderBy('count_no')->pluck('count_no')->all()
        );
        $this->assertDatabaseHas('stock_count_number_counters', ['count_date' => '2026-07-14', 'last_number' => 1]);
        $this->assertDatabaseHas('stock_count_number_counters', ['count_date' => '2026-07-15', 'last_number' => 1]);
    }

    public function test_stock_counts_with_reverse_product_order_do_not_deadlock(): void
    {
        $first = $this->product('Count first', 10);
        $second = $this->product('Count second', 10);
        $results = $this->runConcurrently(
            $this->stockCount([[$first->id, 8], [$second->id, 9]]),
            $this->stockCount([[$second->id, 7], [$first->id, 6]])
        );

        $this->assertAllSucceeded($results);
        $this->assertContains(
            [(float) $first->fresh()->stock_qty, (float) $second->fresh()->stock_qty],
            [[8.0, 9.0], [6.0, 7.0]]
        );
        $this->assertMovementChain($first, 10, (float) $first->fresh()->stock_qty);
        $this->assertMovementChain($second, 10, (float) $second->fresh()->stock_qty);
        $this->assertSame(2, DB::table('stock_counts')->pluck('count_no')->unique()->count());
    }

    public function test_stock_count_locks_products_before_the_daily_counter(): void
    {
        $product = $this->product('Product before counter', 10);
        DB::table('stock_count_number_counters')->insert([
            'count_date' => '2026-07-14',
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $blocker = $this->blockerConnection();
        $blocker->beginTransaction();
        $process = $this->workerProcess($this->stockCount([[$product->id, 8]]) + [
            'lock_timeout_ms' => 2000,
            'statement_timeout_ms' => 5000,
        ]);

        try {
            $blocker->table('products')->where('id', $product->id)->lockForUpdate()->first();
            $process->start();
            usleep(300000);

            DB::transaction(function (): void {
                DB::statement("SET LOCAL lock_timeout = '150ms'");
                DB::table('stock_count_number_counters')
                    ->where('count_date', '2026-07-14')
                    ->lockForUpdate()
                    ->first();
            });
        } finally {
            $blocker->rollBack();
        }

        $process->wait();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame(1, DB::table('stock_count_number_counters')->where('count_date', '2026-07-14')->value('last_number'));
    }

    public function test_manual_adjustment_and_sale_create_form_a_valid_serial_order(): void
    {
        $product = $this->product('Manual adjust', 10);
        $results = $this->runConcurrently(
            [
                'operation' => 'product_update',
                'product_id' => $product->id,
                'data' => $this->productUpdateData($product, 8),
            ],
            $this->saleCreate([[$product->id, 3, 10]])
        );

        $this->assertAllSucceeded($results);
        $this->assertContains((float) $product->fresh()->stock_qty, [5.0, 8.0]);
        $this->assertMovementChain($product, 10, (float) $product->fresh()->stock_qty);
    }

    public function test_quotation_conversion_and_sale_create_are_serialized(): void
    {
        $product = $this->product('Quotation sale', 10);
        $quotation = $this->quotation($product, 2);

        $results = $this->runConcurrently(
            ['operation' => 'quotation_convert', 'quotation_id' => $quotation->id],
            $this->saleCreate([[$product->id, 3, 10]])
        );

        $this->assertAllSucceeded($results);
        $this->assertMovementChain($product, 10, 5);
        $this->assertDatabaseCount('sales', 2);
        $this->assertSame(2, Sale::query()->pluck('sale_no')->unique()->count());
    }

    public function test_sale_update_and_purchase_create_are_serialized(): void
    {
        $product = $this->product('Sale update purchase', 8);
        $sale = $this->existingSale($product, 2);

        $results = $this->runConcurrently(
            $this->saleUpdate($sale, [[$product->id, 3, 10]]),
            $this->purchaseCreate([[$product->id, 4, 5]])
        );

        $this->assertAllSucceeded($results);
        $this->assertEquals(3, $sale->fresh()->items()->sole()->qty);
        $this->assertDatabaseCount('purchases', 1);
        $this->assertMovementChain($product, 10, 11);
    }

    public function test_sale_update_and_stock_count_form_a_valid_serial_order(): void
    {
        $product = $this->product('Sale update stock count', 8);
        $sale = $this->existingSale($product, 2);

        $results = $this->runConcurrently(
            $this->saleUpdate($sale, [[$product->id, 3, 10]]),
            $this->stockCount([[$product->id, 9]])
        );

        $this->assertAllSucceeded($results);
        $this->assertContains((float) $product->fresh()->stock_qty, [8.0, 9.0]);
        $this->assertEquals(3, $sale->fresh()->items()->sole()->qty);
        $this->assertDatabaseCount('stock_counts', 1);
        $this->assertMovementChain($product, 10, (float) $product->fresh()->stock_qty);
    }

    public function test_sale_update_and_manual_adjustment_form_a_valid_serial_order(): void
    {
        $product = $this->product('Sale update adjustment', 8);
        $sale = $this->existingSale($product, 2);

        $results = $this->runConcurrently(
            $this->saleUpdate($sale, [[$product->id, 3, 10]]),
            [
                'operation' => 'product_update',
                'product_id' => $product->id,
                'data' => $this->productUpdateData($product, 9),
            ]
        );

        $this->assertAllSucceeded($results);
        $this->assertContains((float) $product->fresh()->stock_qty, [8.0, 9.0]);
        $this->assertEquals(3, $sale->fresh()->items()->sole()->qty);
        $this->assertMovementChain($product, 10, (float) $product->fresh()->stock_qty);
    }

    public function test_sale_delete_and_purchase_create_are_serialized(): void
    {
        $product = $this->product('Sale delete purchase', 8);
        $sale = $this->existingSale($product, 2);

        $results = $this->runConcurrently(
            $this->saleDelete($sale),
            $this->purchaseCreate([[$product->id, 4, 5]])
        );

        $this->assertAllSucceeded($results);
        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('purchases', 1);
        $this->assertMovementChain($product, 10, 14);
    }

    public function test_sale_delete_and_stock_count_form_a_valid_serial_order(): void
    {
        $product = $this->product('Sale delete stock count', 8);
        $sale = $this->existingSale($product, 2);

        $results = $this->runConcurrently(
            $this->saleDelete($sale),
            $this->stockCount([[$product->id, 9]])
        );

        $this->assertAllSucceeded($results);
        $this->assertContains((float) $product->fresh()->stock_qty, [9.0, 11.0]);
        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('stock_counts', 1);
        $this->assertMovementChain($product, 10, (float) $product->fresh()->stock_qty);
    }

    public function test_sale_delete_and_manual_adjustment_form_a_valid_serial_order(): void
    {
        $product = $this->product('Sale delete adjustment', 8);
        $sale = $this->existingSale($product, 2);

        $results = $this->runConcurrently(
            $this->saleDelete($sale),
            [
                'operation' => 'product_update',
                'product_id' => $product->id,
                'data' => $this->productUpdateData($product, 9),
            ]
        );

        $this->assertAllSucceeded($results);
        $this->assertContains((float) $product->fresh()->stock_qty, [9.0, 11.0]);
        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertMovementChain($product, 10, (float) $product->fresh()->stock_qty);
    }

    public function test_quotation_conversion_and_sale_update_are_serialized(): void
    {
        $product = $this->product('Quotation sale update', 8);
        $sale = $this->existingSale($product, 2);
        $quotation = $this->quotation($product, 2);

        $results = $this->runConcurrently(
            ['operation' => 'quotation_convert', 'quotation_id' => $quotation->id],
            $this->saleUpdate($sale, [[$product->id, 3, 10]])
        );

        $this->assertAllSucceeded($results);
        $this->assertSame('converted', $quotation->fresh()->status);
        $this->assertEquals(3, $sale->fresh()->items()->sole()->qty);
        $this->assertDatabaseCount('sales', 2);
        $this->assertMovementChain($product, 10, 5);
    }

    public function test_same_quotation_converts_to_at_most_one_sale(): void
    {
        $product = $this->product('One quotation', 10);
        $quotation = $this->quotation($product, 2);
        $operation = ['operation' => 'quotation_convert', 'quotation_id' => $quotation->id];

        $results = $this->runConcurrently($operation, $operation);

        $this->assertAllSucceeded($results);
        $this->assertSame(1, collect($results)->pluck('sale_id')->unique()->count());
        $this->assertDatabaseCount('sales', 1);
        $this->assertMovementChain($product, 10, 8);
    }

    public function test_same_converted_unit_quotation_uses_stored_base_quantity_once(): void
    {
        $product = $this->product('One converted quotation', 100);
        $quotation = $this->quotation($product, 2);
        $quotation->update(['total_amount' => '360.00']);
        $quotation->items()->update([
            'conversion_rate_used' => '24.0000',
            'base_qty' => '48.0000',
            'selling_price' => '180.00',
            'total' => '360.00',
            'unit_name_snapshot' => 'Historical Case',
            'unit_code_snapshot' => 'HCASE',
        ]);
        $operation = ['operation' => 'quotation_convert', 'quotation_id' => $quotation->id];

        $results = $this->runConcurrently($operation, $operation);

        $this->assertAllSucceeded($results);
        $this->assertSame(1, collect($results)->pluck('sale_id')->unique()->count());
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('technician_commissions', 0);
        $saleItem = Sale::query()->sole()->items()->sole();
        $this->assertSame('24.0000', $saleItem->conversion_rate_used);
        $this->assertSame('48.0000', $saleItem->base_qty);
        $this->assertSame('Historical Case', $saleItem->unit_name_snapshot);
        $this->assertSame('48.0000', StockMovement::query()->sole()->qty);
        $this->assertSame('52.0000', $product->fresh()->stock_qty);
        $this->assertMovementChain($product, 100, 52);
    }

    public function test_reverse_product_order_does_not_deadlock(): void
    {
        $first = $this->product('First', 10);
        $second = $this->product('Second', 10);

        $results = $this->runConcurrently(
            $this->purchaseCreate([[$first->id, 2, 5], [$second->id, 3, 5]]),
            $this->saleCreate([[$second->id, 4, 10], [$first->id, 1, 10]])
        );

        $this->assertAllSucceeded($results);
        $this->assertMovementChain($first, 10, 11);
        $this->assertMovementChain($second, 10, 9);
    }

    public function test_lock_timeout_has_no_partial_purchase_writes(): void
    {
        $product = $this->product('Timeout', 10);
        $blocker = $this->blockerConnection();
        $blocker->beginTransaction();

        try {
            $blocker->table('products')->where('id', $product->id)->lockForUpdate()->first();
            $result = $this->runWorker($this->purchaseCreate([[$product->id, 2, 5]]) + [
                'lock_timeout_ms' => 150,
                'statement_timeout_ms' => 1000,
            ]);
        } finally {
            $blocker->rollBack();
        }

        $this->assertFalse($result['ok']);
        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('purchase_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertEquals(10, $product->fresh()->stock_qty);
    }

    public function test_stock_count_lock_timeout_has_no_partial_writes(): void
    {
        $product = $this->product('Stock count timeout', 10);
        $blocker = $this->blockerConnection();
        $blocker->beginTransaction();

        try {
            $blocker->table('products')->where('id', $product->id)->lockForUpdate()->first();
            $result = $this->runWorker($this->stockCount([[$product->id, 8]]) + [
                'lock_timeout_ms' => 150,
                'statement_timeout_ms' => 1000,
            ]);
        } finally {
            $blocker->rollBack();
        }

        $this->assertFalse($result['ok']);
        $this->assertDatabaseCount('stock_counts', 0);
        $this->assertDatabaseCount('stock_count_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('stock_count_number_counters', 0);
        $this->assertEquals(10, $product->fresh()->stock_qty);
    }

    private function installStockUpdateDelayTrigger(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION test_delay_competing_stock_update()
RETURNS trigger AS $$
BEGIN
    PERFORM pg_sleep(0.15);
    RETURN NEW;
END;
$$ LANGUAGE plpgsql
SQL);
        DB::unprepared(<<<'SQL'
CREATE TRIGGER test_delay_competing_stock_update_trigger
BEFORE UPDATE OF stock_qty ON products
FOR EACH ROW
WHEN (OLD.stock_qty IS DISTINCT FROM NEW.stock_qty)
EXECUTE FUNCTION test_delay_competing_stock_update()
SQL);
    }

    private function product(string $name, float $stock): Product
    {
        return Product::query()->create([
            'name' => $name,
            'cost_price' => 5,
            'selling_price' => 10,
            'stock_qty' => $stock,
            'auto_price_enabled' => false,
        ]);
    }

    private function existingPurchase(Product $product, float $qty): Purchase
    {
        return app(PurchaseService::class)->create([
            'supplier_id' => Supplier::query()->create(['name' => 'Supplier'])->id,
            'purchase_date' => '2026-07-14',
            'items' => [['product_id' => $product->id, 'qty' => $qty, 'cost_price' => 5]],
        ]);
    }

    private function existingSale(Product $product, float $qty): Sale
    {
        $sale = Sale::query()->create([
            'sale_no' => 'SAL-COMPETING-'.$product->id,
            'sale_date' => '2026-07-14',
            'total_amount' => $qty * 10,
            'delivery_fee' => 0,
            'delivery_type' => 'pickup',
            'discount' => 0,
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'qty' => $qty,
            'selling_price' => 10,
            'cost_price' => $product->cost_price,
            'total' => $qty * 10,
            'profit' => (10 - $product->cost_price) * $qty,
        ]);
        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => 'OUT',
            'qty' => $qty,
            'stock_before' => $product->stock_qty + $qty,
            'stock_after' => $product->stock_qty,
            'reference_type' => 'sale',
            'reference_id' => $sale->id,
        ]);

        return $sale;
    }

    private function quotation(Product $product, float $qty): Quotation
    {
        $quotation = Quotation::query()->create([
            'quotation_no' => 'QT-'.$product->id,
            'quotation_date' => '2026-07-14',
            'total_amount' => $qty * 10,
            'status' => 'draft',
        ]);
        $quotation->items()->create([
            'product_id' => $product->id,
            'qty' => $qty,
            'selling_price' => 10,
            'total' => $qty * 10,
        ]);

        return $quotation;
    }

    private function saleCreate(array $lines): array
    {
        $items = array_map(fn (array $line) => [
            'product_id' => $line[0],
            'qty' => $line[1],
            'selling_price' => $line[2],
        ], $lines);

        return [
            'operation' => 'sale_create',
            'data' => [
                'sale_date' => '2026-07-14',
                'grand_total' => collect($items)->sum(fn (array $item) => $item['qty'] * $item['selling_price']),
                'delivery_type' => 'pickup',
                'discount' => 0,
                'items' => $items,
            ],
        ];
    }

    private function saleUpdate(Sale $sale, array $lines): array
    {
        return [
            'operation' => 'sale_update',
            'sale_id' => $sale->id,
            'expected_revision' => (int) $sale->fresh()->revision,
            'data' => [
                'customer_id' => null,
                'sale_date' => '2026-07-15',
                'delivery_fee' => 0,
                'discount' => 0,
                'items' => array_map(fn (array $line) => [
                    'product_id' => $line[0],
                    'qty' => $line[1],
                    'selling_price' => $line[2],
                ], $lines),
            ],
        ];
    }

    private function saleDelete(Sale $sale): array
    {
        return [
            'operation' => 'sale_delete',
            'sale_id' => $sale->id,
        ];
    }

    private function purchaseCreate(array $lines): array
    {
        return [
            'operation' => 'purchase_create',
            'data' => [
                'supplier_id' => Supplier::query()->firstOrCreate(['name' => 'Supplier'])->id,
                'purchase_date' => '2026-07-14',
                'items' => array_map(fn (array $line) => [
                    'product_id' => $line[0],
                    'qty' => $line[1],
                    'cost_price' => $line[2],
                ], $lines),
            ],
        ];
    }

    private function stockCount(array $lines, string $countDate = '2026-07-14'): array
    {
        return [
            'operation' => 'stock_count',
            'data' => [
                'count_date' => $countDate,
                'items' => array_map(fn (array $line) => [
                    'product_id' => $line[0],
                    'actual_qty' => $line[1],
                ], $lines),
            ],
        ];
    }

    private function purchaseUpdate(Purchase $purchase, array $lines): array
    {
        return [
            'operation' => 'purchase_update',
            'purchase_id' => $purchase->id,
            'data' => [
                'supplier_id' => $purchase->supplier_id,
                'purchase_date' => '2026-07-15',
                'items' => array_map(fn (array $line) => [
                    'product_id' => $line[0],
                    'qty' => $line[1],
                    'cost_price' => $line[2],
                ], $lines),
            ],
        ];
    }

    private function productUpdateData(Product $product, float $stock): array
    {
        return [
            'barcode' => null,
            'name' => $product->name,
            'category_id' => null,
            'unit_id' => null,
            'cost_price' => $product->cost_price,
            'selling_price' => $product->selling_price,
            'stock_qty' => $stock,
            'minimum_stock' => 0,
            'vat_enabled' => 0,
            'active' => 1,
            'remark' => null,
        ];
    }

    private function runConcurrently(array $first, array $second): array
    {
        $startAt = (int) floor(microtime(true) * 1000) + 500;
        $first['start_at_ms'] = $startAt;
        $second['start_at_ms'] = $startAt;
        $processes = [$this->workerProcess($first), $this->workerProcess($second)];

        foreach ($processes as $process) {
            $process->start();
        }

        return array_map(function (Process $process): array {
            $process->wait();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());

            return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }, $processes);
    }

    private function runWorker(array $payload): array
    {
        $process = $this->workerProcess($payload);
        $process->run();
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function workerProcess(array $payload): Process
    {
        return new Process(
            [PHP_BINARY, base_path('tests/Support/competing_stock_writer_worker.php'), base64_encode(json_encode($payload, JSON_THROW_ON_ERROR))],
            base_path(),
            $this->workerEnvironment(),
            null,
            12
        );
    }

    private function workerEnvironment(): array
    {
        $connection = config('database.connections.'.config('database.default'));

        return [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => 'pgsql',
            'DB_URL' => '',
            'DB_HOST' => (string) $connection['host'],
            'DB_PORT' => (string) $connection['port'],
            'DB_DATABASE' => (string) $connection['database'],
            'DB_USERNAME' => (string) $connection['username'],
            'DB_PASSWORD' => (string) $connection['password'],
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ];
    }

    private function blockerConnection()
    {
        Config::set('database.connections.stock_writer_blocker', config('database.connections.'.config('database.default')));
        DB::purge('stock_writer_blocker');

        return DB::connection('stock_writer_blocker');
    }

    private function assertAllSucceeded(array $results): void
    {
        $this->assertTrue(collect($results)->every(fn (array $result) => $result['ok']), json_encode($results));
    }

    private function assertMovementChain(Product $product, float $initialStock, float $expectedFinalStock): void
    {
        $expectedBefore = $initialStock;

        foreach (StockMovement::query()->where('product_id', $product->id)->orderBy('id')->get() as $movement) {
            $this->assertEquals($expectedBefore, $movement->stock_before);
            $expectedBefore = (float) $movement->stock_after;
        }

        $this->assertEquals($expectedFinalStock, $expectedBefore);
        $this->assertEquals($expectedFinalStock, $product->fresh()->stock_qty);
        $this->assertGreaterThanOrEqual(0, (float) $product->fresh()->stock_qty);
    }
}
