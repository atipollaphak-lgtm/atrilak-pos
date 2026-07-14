<?php

namespace Tests\Feature\Sales;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\CreatesSaleTransactionTestSchema;
use Tests\TestCase;

class SaleConcurrencyTest extends TestCase
{
    use CreatesSaleTransactionTestSchema;

    private bool $schemaCreated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required for concurrency tests.');
        }

        if (DB::connection()->getDatabaseName() === 'atrilak_pos') {
            $this->fail('Concurrency tests refused the application database.');
        }

        $this->createSaleTransactionTestSchema();
        $this->schemaCreated = true;
        $this->installStockUpdateDelayTrigger();
    }

    protected function tearDown(): void
    {
        if ($this->schemaCreated) {
            DB::unprepared('DROP FUNCTION IF EXISTS test_delay_product_stock_update() CASCADE');
            $this->dropSaleTransactionTestSchema();
        }

        DB::disconnect('sale_concurrency_blocker');
        parent::tearDown();
    }

    public function test_two_concurrent_creates_when_stock_only_covers_one_sale(): void
    {
        $product = $this->createProduct('Limited product', 5);
        $operation = $this->createOperation([$this->line($product, 4, 10)]);

        $results = $this->runConcurrently($operation, $operation);

        $this->assertSame(1, collect($results)->where('ok', true)->count());
        $this->assertSame(1, collect($results)->where('ok', false)->count());
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertEquals(1.0000, $product->fresh()->stock_qty);
        $this->assertMovementChain($product, 5, 1);
    }

    public function test_two_concurrent_creates_when_stock_covers_both_sales(): void
    {
        $product = $this->createProduct('Sufficient product', 10);
        $operation = $this->createOperation([$this->line($product, 4, 10)]);

        $results = $this->runConcurrently($operation, $operation);

        $this->assertTrue(collect($results)->every(fn (array $result) => $result['ok']));
        $this->assertDatabaseCount('sales', 2);
        $this->assertDatabaseCount('sale_items', 2);
        $this->assertEquals(2.0000, $product->fresh()->stock_qty);
        $this->assertMovementChain($product, 10, 2);
    }

    public function test_concurrent_create_and_update_keep_stock_and_movements_consistent(): void
    {
        $product = $this->createProduct('Create update product', 8);
        $sale = $this->createExistingSale($product, 2, 10);

        $results = $this->runConcurrently(
            $this->createOperation([$this->line($product, 4, 10)]),
            $this->updateOperation($sale, [$this->line($product, 3, 10)])
        );

        $this->assertTrue(collect($results)->every(fn (array $result) => $result['ok']));
        $this->assertEquals(3.0000, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('sales', 2);
        $this->assertMovementChain($product, 10, 3);
    }

    public function test_concurrent_create_and_delete_keep_stock_and_movements_consistent(): void
    {
        $product = $this->createProduct('Create delete product', 8);
        $sale = $this->createExistingSale($product, 2, 10);

        $results = $this->runConcurrently(
            $this->createOperation([$this->line($product, 4, 10)]),
            ['operation' => 'delete', 'sale_id' => $sale->id]
        );

        $this->assertTrue(collect($results)->every(fn (array $result) => $result['ok']));
        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertDatabaseCount('sales', 1);
        $this->assertEquals(6.0000, $product->fresh()->stock_qty);
        $this->assertMovementChain($product, 10, 6);
    }

    public function test_two_concurrent_updates_of_the_same_sale_do_not_restore_stock_twice(): void
    {
        $product = $this->createProduct('Concurrent update product', 8);
        $sale = $this->createExistingSale($product, 2, 10);

        $results = $this->runConcurrently(
            $this->updateOperation($sale, [$this->line($product, 3, 10)]),
            $this->updateOperation($sale, [$this->line($product, 4, 10)])
        );

        $this->assertTrue(collect($results)->every(fn (array $result) => $result['ok']));

        $finalQty = (float) $sale->fresh()->items()->sole()->qty;
        $this->assertContains($finalQty, [3.0, 4.0]);
        $this->assertEquals(10 - $finalQty, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertMovementChain($product, 10, 10 - $finalQty);
    }

    public function test_reversed_multi_product_requests_do_not_deadlock(): void
    {
        $first = $this->createProduct('First ordered product', 20);
        $second = $this->createProduct('Second ordered product', 20);

        $results = $this->runConcurrently(
            $this->createOperation([
                $this->line($first, 2, 10),
                $this->line($second, 3, 10),
            ]),
            $this->createOperation([
                $this->line($second, 3, 10),
                $this->line($first, 2, 10),
            ])
        );

        $this->assertTrue(collect($results)->every(fn (array $result) => $result['ok']));
        $this->assertEquals(16.0000, $first->fresh()->stock_qty);
        $this->assertEquals(14.0000, $second->fresh()->stock_qty);
        $this->assertMovementChain($first, 20, 16);
        $this->assertMovementChain($second, 20, 14);
    }

    public function test_concurrent_sales_in_different_units_share_the_same_base_stock(): void
    {
        $product = $this->createProduct('Concurrent mixed units', 20);
        $piece = $this->createProductUnit($product, 'piece', '1.0000', true);
        $dozen = $this->createProductUnit($product, 'dozen', '12.0000');

        $results = $this->runConcurrently(
            $this->createOperation([[
                'product_id' => $product->id,
                'product_unit_id' => $piece->id,
                'qty' => 2,
                'selling_price' => 10,
            ]]),
            $this->createOperation([[
                'product_id' => $product->id,
                'product_unit_id' => $dozen->id,
                'qty' => 1,
                'selling_price' => 100,
            ]])
        );

        $this->assertTrue(collect($results)->every(fn (array $result) => $result['ok']));
        $this->assertEquals(6.0000, $product->fresh()->stock_qty);
        $this->assertEqualsCanonicalizing(
            ['2.0000', '12.0000'],
            StockMovement::where('product_id', $product->id)->pluck('qty')->all()
        );
        $this->assertMovementChain($product, 20, 6);
    }

    public function test_lock_timeout_rolls_back_without_partial_sale_writes(): void
    {
        $product = $this->createProduct('Timeout product', 10);
        $blocker = $this->blockerConnection();
        $blocker->beginTransaction();

        try {
            $blocker->table('products')->where('id', $product->id)->lockForUpdate()->first();

            $result = $this->runWorker($this->createOperation([
                $this->line($product, 2, 10),
            ]) + [
                'lock_timeout_ms' => 150,
                'statement_timeout_ms' => 1000,
            ]);
        } finally {
            $blocker->rollBack();
        }

        $this->assertFalse($result['ok']);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertEquals(10.0000, $product->fresh()->stock_qty);
    }

    private function installStockUpdateDelayTrigger(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION test_delay_product_stock_update()
RETURNS trigger AS $$
BEGIN
    PERFORM pg_sleep(0.15);
    RETURN NEW;
END;
$$ LANGUAGE plpgsql
SQL);
        DB::unprepared(<<<'SQL'
CREATE TRIGGER test_delay_product_stock_update_trigger
BEFORE UPDATE OF stock_qty ON products
FOR EACH ROW
WHEN (OLD.stock_qty IS DISTINCT FROM NEW.stock_qty)
EXECUTE FUNCTION test_delay_product_stock_update()
SQL);
    }

    private function createProduct(string $name, float $stock): Product
    {
        return Product::create([
            'name' => $name,
            'cost_price' => 5,
            'selling_price' => 10,
            'stock_qty' => $stock,
        ]);
    }

    private function createProductUnit(
        Product $product,
        string $name,
        string $rate,
        bool $base = false
    ): ProductUnit {
        $unitId = DB::table('units')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $unitId,
            'conversion_rate' => $rate,
            'is_base_unit' => $base,
            'is_sale_unit' => true,
            'active' => true,
            'conversion_confirmed_at' => $base ? null : now(),
        ]);
    }

    private function createExistingSale(Product $product, float $qty, float $price): Sale
    {
        $sale = Sale::create([
            'sale_no' => 'SAL-CONCURRENT-'.$product->id,
            'sale_date' => '2026-07-13',
            'total_amount' => $qty * $price,
            'delivery_fee' => 0,
            'delivery_type' => 'pickup',
            'discount' => 0,
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'qty' => $qty,
            'selling_price' => $price,
            'cost_price' => $product->cost_price,
            'total' => $qty * $price,
            'profit' => ($price - $product->cost_price) * $qty,
        ]);
        StockMovement::create([
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

    private function line(Product $product, float $qty, float $price): array
    {
        return [
            'product_id' => $product->id,
            'qty' => $qty,
            'selling_price' => $price,
        ];
    }

    private function createOperation(array $items): array
    {
        return [
            'operation' => 'create',
            'data' => [
                'sale_date' => '2026-07-13',
                'grand_total' => collect($items)->sum(fn (array $item) => $item['qty'] * $item['selling_price']),
                'delivery_type' => 'pickup',
                'discount' => 0,
                'items' => $items,
            ],
        ];
    }

    private function updateOperation(Sale $sale, array $items): array
    {
        return [
            'operation' => 'update',
            'sale_id' => $sale->id,
            'data' => [
                'customer_id' => null,
                'sale_date' => '2026-07-14',
                'items' => $items,
                'delivery_fee' => 0,
                'discount' => 0,
            ],
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
        $encoded = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));

        return new Process(
            [PHP_BINARY, base_path('tests/Support/sale_concurrency_worker.php'), $encoded],
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
        Config::set('database.connections.sale_concurrency_blocker', config('database.connections.'.config('database.default')));
        DB::purge('sale_concurrency_blocker');

        return DB::connection('sale_concurrency_blocker');
    }

    private function assertMovementChain(Product $product, float $initialStock, float $expectedFinalStock): void
    {
        $movements = StockMovement::where('product_id', $product->id)->orderBy('id')->get();
        $expectedBefore = $initialStock;

        foreach ($movements as $movement) {
            $this->assertEquals($expectedBefore, $movement->stock_before);
            $expectedBefore = (float) $movement->stock_after;
        }

        $this->assertEquals($expectedFinalStock, $expectedBefore);
        $this->assertEquals($expectedFinalStock, $product->fresh()->stock_qty);
    }
}
