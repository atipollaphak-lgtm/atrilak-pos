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
