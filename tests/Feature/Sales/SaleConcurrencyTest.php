<?php

namespace Tests\Feature\Sales;

use App\Exceptions\StaleSaleRevisionException;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\StockMovement;
use Brick\Math\BigDecimal;
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

    public function test_concurrent_sales_for_different_products_receive_unique_daily_numbers(): void
    {
        $first = $this->createProduct('First number product', 10);
        $second = $this->createProduct('Second number product', 10);

        $results = $this->runConcurrently(
            $this->createOperation([$this->line($first, 1, 10)]),
            $this->createOperation([$this->line($second, 1, 10)])
        );

        $this->assertTrue(collect($results)->every(fn (array $result) => $result['ok']));
        $this->assertCount(2, array_unique(array_column($results, 'sale_no')));
        $this->assertEqualsCanonicalizing(
            ['SAL-20260713-0001', 'SAL-20260713-0002'],
            array_column($results, 'sale_no')
        );
    }

    public function test_concurrent_same_idempotency_key_creates_one_sale_and_one_replay(): void
    {
        $product = $this->createProduct('Concurrent replay product', 10);
        $operation = $this->createOperation([$this->line($product, 2, 10)]);
        $operation['data']['idempotency_key'] = '40000000-0000-4000-8000-000000000001';

        $results = $this->runConcurrently($operation, $operation);

        $this->assertTrue(collect($results)->every(fn (array $result) => $result['ok']), json_encode($results));
        $this->assertSame(1, collect($results)->where('idempotent_replay', true)->count());
        $this->assertSame(1, collect($results)->where('idempotent_replay', false)->count());
        $this->assertSame(1, collect($results)->pluck('sale_id')->unique()->count());
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame('8.0000', $product->fresh()->stock_qty);
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

    public function test_two_concurrent_voids_restore_stock_and_append_a_void_movement_once(): void
    {
        $product = $this->createProduct('Concurrent void product', 8);
        $sale = $this->createExistingSale($product, 2, 10);

        $results = $this->runConcurrently(
            $this->voidOperation($sale),
            $this->voidOperation($sale)
        );

        $this->assertSame(1, collect($results)->where('ok', true)->count());
        $this->assertSame(1, collect($results)->where('ok', false)->count());
        $this->assertSame(Sale::STATUS_VOIDED, $sale->fresh()->status);
        $this->assertSame('10.0000', $product->fresh()->stock_qty);
        $this->assertSame(1, StockMovement::query()
            ->where('reference_type', 'sale_void')
            ->where('reference_id', $sale->id)
            ->count());
        $this->assertMovementChain($product, 10, 10);
    }

    public function test_concurrent_update_and_void_leave_the_sale_voided_without_duplicate_stock_effects(): void
    {
        $product = $this->createProduct('Concurrent update void product', 8);
        $sale = $this->createExistingSale($product, 2, 10);

        $results = $this->runConcurrently(
            $this->updateOperation($sale, [$this->line($product, 3, 10)]),
            $this->voidOperation($sale)
        );

        $this->assertTrue($results[1]['ok'], json_encode($results));
        $this->assertSame(Sale::STATUS_VOIDED, $sale->fresh()->status);
        $this->assertSame('10.0000', $product->fresh()->stock_qty);
        $this->assertSame(1, StockMovement::query()
            ->where('reference_type', 'sale_void')
            ->where('reference_id', $sale->id)
            ->count());
        $this->assertMovementChain($product, 10, 10);
    }

    public function test_two_concurrent_updates_of_the_same_sale_do_not_restore_stock_twice(): void
    {
        $product = $this->createProduct('Concurrent update product', 8);
        $sale = $this->createExistingSale($product, 2, 10);
        $originalItemId = $sale->items()->sole()->id;

        $results = $this->runConcurrently(
            $this->updateOperation($sale, [[
                'sale_item_id' => $originalItemId,
                ...$this->line($product, 3, 10),
            ]]),
            $this->updateOperation($sale, [[
                'sale_item_id' => $originalItemId,
                ...$this->line($product, 4, 10),
            ]])
        );

        $this->assertSame(1, collect($results)->where('ok', true)->count(), json_encode($results));
        $this->assertSame(1, collect($results)->where('exception', StaleSaleRevisionException::class)->count());

        $finalQty = (float) $sale->fresh()->items()->sole()->qty;
        $this->assertContains($finalQty, [3.0, 4.0]);
        $this->assertSame($originalItemId, $sale->fresh()->items()->sole()->id);
        $this->assertSame(2, $sale->fresh()->revision);
        $this->assertEquals(10 - $finalQty, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertMovementChain($product, 10, 10 - $finalQty);
    }

    public function test_identical_concurrent_updates_with_the_same_revision_write_stock_once(): void
    {
        $product = $this->createProduct('Identical concurrent update product', 8);
        $sale = $this->createExistingSale($product, 2, 10);
        $itemId = $sale->items()->sole()->id;
        $operation = $this->updateOperation($sale, [[
            'sale_item_id' => $itemId,
            ...$this->line($product, 3, 10),
        ]]);

        $results = $this->runConcurrently($operation, $operation);

        $this->assertSame(1, collect($results)->where('ok', true)->count(), json_encode($results));
        $this->assertSame(1, collect($results)->where('exception', StaleSaleRevisionException::class)->count());
        $this->assertSame(2, $sale->fresh()->revision);
        $this->assertSame($itemId, $sale->fresh()->items()->sole()->id);
        $this->assertSame('3.00', $sale->fresh()->items()->sole()->qty);
        $this->assertSame('7.0000', $product->fresh()->stock_qty);
        $this->assertSame(2, StockMovement::query()->where('reference_type', 'sale_edit')->count());
        $this->assertMovementChain($product, 10, 7);
    }

    public function test_concurrent_create_and_converted_unit_update_use_base_quantity_and_keep_item_identity(): void
    {
        $product = $this->createProduct('Concurrent converted update product', 52);
        $unit = $this->createProductUnit($product, 'box', '24.0000');
        $sale = $this->createExistingConvertedSale($product, $unit, 2, 180, 100);
        $originalItemId = $sale->items()->sole()->id;

        $results = $this->runConcurrently(
            $this->createOperation([$this->line($product, 4, 10)]),
            $this->updateOperation($sale, [[
                'sale_item_id' => $originalItemId,
                'product_id' => $product->id,
                'product_unit_id' => $unit->id,
                'qty' => 1,
                'selling_price' => 180,
            ]])
        );

        $this->assertTrue(collect($results)->every(fn (array $result) => $result['ok']));
        $updatedItem = $sale->fresh()->items()->sole();
        $this->assertSame($originalItemId, $updatedItem->id);
        $this->assertSame('1.00', $updatedItem->qty);
        $this->assertSame('24.0000', $updatedItem->base_qty);
        $this->assertSame('24.0000', $updatedItem->conversion_rate_used);
        $this->assertEquals(72.0000, $product->fresh()->stock_qty);
        $this->assertSame(
            ['48.0000', '24.0000'],
            StockMovement::query()
                ->where('reference_type', 'sale_edit')
                ->orderBy('id')
                ->pluck('qty')
                ->all()
        );
        $this->assertMovementChain($product, 100, 72);
    }

    public function test_concurrent_update_and_delete_of_the_same_sale_leave_no_partial_state(): void
    {
        $product = $this->createProduct('Concurrent update delete product', 8);
        $sale = $this->createExistingSale($product, 2, 10);

        $results = $this->runConcurrently(
            $this->updateOperation($sale, [$this->line($product, 3, 10)]),
            ['operation' => 'delete', 'sale_id' => $sale->id]
        );

        $this->assertTrue($results[1]['ok'], json_encode($results));
        $this->assertContains(collect($results)->where('ok', true)->count(), [1, 2]);
        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertEquals(10.0000, $product->fresh()->stock_qty);
        $this->assertMovementChain($product, 10, 10);
    }

    public function test_two_concurrent_deletes_of_the_same_sale_restore_stock_once(): void
    {
        $product = $this->createProduct('Concurrent double delete product', 8);
        $sale = $this->createExistingSale($product, 2, 10);
        $operation = ['operation' => 'delete', 'sale_id' => $sale->id];

        $results = $this->runConcurrently($operation, $operation);

        $this->assertSame(1, collect($results)->where('ok', true)->count());
        $this->assertSame(1, collect($results)->where('ok', false)->count());
        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertEquals(10.0000, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertMovementChain($product, 10, 10);
    }

    public function test_update_lock_timeout_rolls_back_header_items_stock_and_movements(): void
    {
        $product = $this->createProduct('Update timeout product', 8);
        $sale = $this->createExistingSale($product, 2, 10);
        $blocker = $this->blockerConnection();
        $blocker->beginTransaction();

        try {
            $blocker->table('products')->where('id', $product->id)->lockForUpdate()->first();
            $result = $this->runWorker($this->updateOperation(
                $sale,
                [$this->line($product, 3, 10)]
            ) + [
                'lock_timeout_ms' => 150,
                'statement_timeout_ms' => 1000,
            ]);
        } finally {
            $blocker->rollBack();
        }

        $this->assertFalse($result['ok']);
        $this->assertSame('2026-07-13', $sale->fresh()->sale_date);
        $this->assertSame(1, $sale->fresh()->revision);
        $this->assertEquals(2, $sale->fresh()->items()->sole()->qty);
        $this->assertEquals(8.0000, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertMovementChain($product, 10, 8);
    }

    public function test_delete_lock_timeout_rolls_back_sale_items_stock_and_movements(): void
    {
        $product = $this->createProduct('Delete timeout product', 8);
        $sale = $this->createExistingSale($product, 2, 10);
        $blocker = $this->blockerConnection();
        $blocker->beginTransaction();

        try {
            $blocker->table('products')->where('id', $product->id)->lockForUpdate()->first();
            $result = $this->runWorker([
                'operation' => 'delete',
                'sale_id' => $sale->id,
                'lock_timeout_ms' => 150,
                'statement_timeout_ms' => 1000,
            ]);
        } finally {
            $blocker->rollBack();
        }

        $this->assertFalse($result['ok']);
        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $sale->id]);
        $this->assertEquals(8.0000, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertMovementChain($product, 10, 8);
    }

    public function test_concurrent_commission_payment_blocks_sale_update(): void
    {
        $product = $this->createProduct('Commission payment update product', 8);
        $sale = $this->createExistingSale($product, 2, 10);
        $technicianId = DB::table('technicians')->insertGetId([
            'name' => 'Concurrent paid technician',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $commissionId = DB::table('technician_commissions')->insertGetId([
            'sale_id' => $sale->id,
            'technician_id' => $technicianId,
            'commission_amount' => 5,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $blocker = $this->blockerConnection();
        $blocker->beginTransaction();
        $process = null;

        try {
            $blocker->table('technician_commissions')
                ->where('id', $commissionId)
                ->lockForUpdate()
                ->first();
            $process = $this->workerProcess($this->updateOperation(
                $sale,
                [$this->line($product, 3, 10)]
            ));
            $process->start();
            usleep(250000);
            $blocker->table('technician_commissions')
                ->where('id', $commissionId)
                ->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'updated_at' => now(),
                ]);
            $blocker->commit();
            $process->wait();
        } finally {
            if ($blocker->transactionLevel() > 0) {
                $blocker->rollBack();
            }
            if ($process?->isRunning()) {
                $process->stop();
            }
        }

        $this->assertSame(0, $process?->getExitCode(), $process?->getErrorOutput());
        $result = json_decode($process?->getOutput() ?? '', true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($result['ok']);
        $this->assertDatabaseHas('technician_commissions', [
            'id' => $commissionId,
            'status' => 'paid',
        ]);
        $this->assertSame('2026-07-13', $sale->fresh()->sale_date);
        $this->assertEquals(2, $sale->fresh()->items()->sole()->qty);
        $this->assertEquals(8.0000, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertMovementChain($product, 10, 8);
    }

    public function test_concurrent_commission_batching_blocks_sale_delete(): void
    {
        $product = $this->createProduct('Commission batch delete product', 8);
        $sale = $this->createExistingSale($product, 2, 10);
        $technicianId = DB::table('technicians')->insertGetId([
            'name' => 'Concurrent batched technician',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $commissionId = DB::table('technician_commissions')->insertGetId([
            'sale_id' => $sale->id,
            'technician_id' => $technicianId,
            'commission_amount' => 5,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $blocker = $this->blockerConnection();
        $blocker->beginTransaction();
        $process = null;

        try {
            $blocker->table('technician_commissions')
                ->where('id', $commissionId)
                ->lockForUpdate()
                ->first();
            $process = $this->workerProcess([
                'operation' => 'delete',
                'sale_id' => $sale->id,
            ]);
            $process->start();
            usleep(250000);
            $blocker->table('technician_commissions')
                ->where('id', $commissionId)
                ->update([
                    'payment_batch_id' => 99,
                    'updated_at' => now(),
                ]);
            $blocker->commit();
            $process->wait();
        } finally {
            if ($blocker->transactionLevel() > 0) {
                $blocker->rollBack();
            }
            if ($process?->isRunning()) {
                $process->stop();
            }
        }

        $this->assertSame(0, $process?->getExitCode(), $process?->getErrorOutput());
        $result = json_decode($process?->getOutput() ?? '', true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($result['ok']);
        $this->assertDatabaseHas('technician_commissions', [
            'id' => $commissionId,
            'payment_batch_id' => 99,
        ]);
        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $sale->id]);
        $this->assertEquals(8.0000, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertMovementChain($product, 10, 8);
    }

    public function test_concurrent_commission_batching_blocks_sale_void(): void
    {
        $product = $this->createProduct('Commission batch void product', 8);
        $sale = $this->createExistingSale($product, 2, 10);
        $technicianId = DB::table('technicians')->insertGetId([
            'name' => 'Concurrent void technician',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $commissionId = DB::table('technician_commissions')->insertGetId([
            'sale_id' => $sale->id,
            'technician_id' => $technicianId,
            'commission_amount' => 5,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $blocker = $this->blockerConnection();
        $blocker->beginTransaction();
        $process = null;

        try {
            $blocker->table('technician_commissions')
                ->where('id', $commissionId)
                ->lockForUpdate()
                ->first();
            $process = $this->workerProcess($this->voidOperation($sale));
            $process->start();
            usleep(250000);
            $blocker->table('technician_commissions')
                ->where('id', $commissionId)
                ->update([
                    'payment_batch_id' => 99,
                    'updated_at' => now(),
                ]);
            $blocker->commit();
            $process->wait();
        } finally {
            if ($blocker->transactionLevel() > 0) {
                $blocker->rollBack();
            }
            if ($process?->isRunning()) {
                $process->stop();
            }
        }

        $this->assertSame(0, $process?->getExitCode(), $process?->getErrorOutput());
        $result = json_decode($process?->getOutput() ?? '', true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($result['ok']);
        $this->assertDatabaseHas('technician_commissions', [
            'id' => $commissionId,
            'payment_batch_id' => 99,
        ]);
        $this->assertSame(Sale::STATUS_ACTIVE, $sale->fresh()->status);
        $this->assertEquals(8.0000, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertMovementChain($product, 10, 8);
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

    private function createExistingConvertedSale(
        Product $product,
        ProductUnit $unit,
        float $qty,
        float $price,
        float $stockBefore
    ): Sale {
        $baseQty = $qty * (float) $unit->conversion_rate;
        $sale = Sale::create([
            'sale_no' => 'SAL-CONCURRENT-CONVERTED-'.$product->id,
            'sale_date' => '2026-07-13',
            'total_amount' => $qty * $price,
            'delivery_fee' => 0,
            'delivery_type' => 'pickup',
            'discount' => 0,
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'conversion_rate_used' => $unit->conversion_rate,
            'base_qty' => $baseQty,
            'qty' => $qty,
            'selling_price' => $price,
            'cost_price' => $product->cost_price,
            'total' => $qty * $price,
            'profit' => ($qty * $price) - ($baseQty * $product->cost_price),
        ]);
        StockMovement::create([
            'product_id' => $product->id,
            'type' => 'OUT',
            'qty' => $baseQty,
            'stock_before' => $stockBefore,
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
        $grandTotal = (string) collect($items)
            ->reduce(
                fn (BigDecimal $total, array $item): BigDecimal => $total->plus(
                    BigDecimal::of((string) $item['qty'])
                        ->multipliedBy((string) $item['selling_price'])
                ),
                BigDecimal::zero()
            )
            ->toScale(2);

        return [
            'operation' => 'create',
            'data' => [
                'sale_date' => '2026-07-13',
                'grand_total' => $grandTotal,
                'delivery_type' => 'pickup',
                'discount' => 0,
                'payment_method' => 'cash',
                'cash_amount' => $grandTotal,
                'promptpay_amount' => '0.00',
                'received_amount' => $grandTotal,
                'items' => $items,
            ],
        ];
    }

    private function updateOperation(Sale $sale, array $items): array
    {
        return [
            'operation' => 'update',
            'sale_id' => $sale->id,
            'expected_revision' => (int) $sale->fresh()->revision,
            'data' => [
                'customer_id' => null,
                'sale_date' => '2026-07-14',
                'items' => $items,
                'delivery_fee' => 0,
                'discount' => 0,
            ],
        ];
    }

    private function voidOperation(Sale $sale): array
    {
        return [
            'operation' => 'void',
            'sale_id' => $sale->id,
            'reason' => 'Concurrent void',
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
