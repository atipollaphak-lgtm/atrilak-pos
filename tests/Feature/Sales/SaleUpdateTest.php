<?php

namespace Tests\Feature\Sales;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Services\SaleService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\CreatesSaleTransactionTestSchema;
use Tests\TestCase;

class SaleUpdateTest extends TestCase
{
    use CreatesSaleTransactionTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSaleTransactionTestSchema();
    }

    protected function tearDown(): void
    {
        $this->dropSaleTransactionTestSchema();
        parent::tearDown();
    }

    public function test_update_sale_atomically_replaces_multiple_items_and_preserves_totals(): void
    {
        [$sale, $firstProduct, $secondProduct] = $this->createExistingSale();
        $this->service()->updateSale($sale, $this->updateData(
            [$firstProduct->id, $secondProduct->id],
            [1, 4],
            [120, 70]
        ), (int) $sale->fresh()->revision);

        $sale->refresh();

        $this->assertSame('2026-07-14', $sale->sale_date);
        $this->assertEquals(415.00, $sale->total_amount);
        $this->assertEquals(25.00, $sale->delivery_fee);
        $this->assertEquals(10.00, $sale->discount);
        $this->assertEquals(9.0000, $firstProduct->fresh()->stock_qty);
        $this->assertEquals(16.0000, $secondProduct->fresh()->stock_qty);

        $items = $sale->items()->orderBy('product_id')->get();
        $this->assertCount(2, $items);
        $this->assertEquals([120.00, 280.00], $items->pluck('total')->map(fn ($value) => (float) $value)->all());
        $this->assertEquals([40.00, 80.00], $items->pluck('profit')->map(fn ($value) => (float) $value)->all());

        $editMovements = StockMovement::where('reference_type', 'sale_edit')
            ->where('reference_id', $sale->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(4, $editMovements);
        $this->assertSame(['IN', 'IN', 'OUT', 'OUT'], $editMovements->pluck('type')->all());
        $this->assertEquals(
            [[8, 10], [17, 20], [10, 9], [20, 16]],
            $editMovements->map(fn (StockMovement $movement) => [
                (float) $movement->stock_before,
                (float) $movement->stock_after,
            ])->all()
        );
        $this->assertSame(2, StockMovement::where('reference_type', 'sale')->count());
    }

    public function test_exception_after_old_stock_restore_rolls_back_every_write(): void
    {
        [$sale, $firstProduct, $secondProduct] = $this->createExistingSale();
        $before = $this->snapshot();
        $throw = true;

        StockMovement::created(function (StockMovement $movement) use (&$throw): void {
            if ($throw && $movement->reference_type === 'sale_edit' && $movement->type === 'IN') {
                $throw = false;
                throw new RuntimeException('Failure after old stock restore');
            }
        });

        try {
            $this->service()->updateSale($sale, $this->updateData(
                [$firstProduct->id, $secondProduct->id],
                [1, 4],
                [120, 70]
            ), (int) $sale->fresh()->revision);
            $this->fail('Expected update failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Failure after old stock restore', $exception->getMessage());
        }

        $this->assertSame($before, $this->snapshot());
    }

    public function test_exception_after_old_items_are_deleted_rolls_back_every_write(): void
    {
        [$sale, $firstProduct, $secondProduct] = $this->createExistingSale();
        $before = $this->snapshot();
        $throw = true;

        DB::listen(function (QueryExecuted $query) use (&$throw): void {
            if ($throw && str_starts_with(strtolower($query->sql), 'delete from "sale_items"')) {
                $throw = false;
                throw new RuntimeException('Failure after old items delete');
            }
        });

        try {
            $this->service()->updateSale($sale, $this->updateData(
                [$firstProduct->id, $secondProduct->id],
                [1, 4],
                [120, 70]
            ), (int) $sale->fresh()->revision);
            $this->fail('Expected update failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Failure after old items delete', $exception->getMessage());
        }

        $this->assertSame($before, $this->snapshot());
    }

    public function test_exception_during_new_item_creation_rolls_back_every_write(): void
    {
        [$sale, $firstProduct, $secondProduct] = $this->createExistingSale();
        $before = $this->snapshot();
        $newItemCount = 0;

        SaleItem::creating(function () use (&$newItemCount): void {
            $newItemCount++;

            if ($newItemCount === 2) {
                throw new RuntimeException('Failure during new item creation');
            }
        });

        try {
            $this->service()->updateSale($sale, $this->updateData(
                [$firstProduct->id, $secondProduct->id],
                [1, 4],
                [120, 70]
            ), (int) $sale->fresh()->revision);
            $this->fail('Expected update failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Failure during new item creation', $exception->getMessage());
        }

        $this->assertSame($before, $this->snapshot());
    }

    public function test_exception_while_updating_sale_header_rolls_back_new_stock_and_items(): void
    {
        [$sale, $firstProduct, $secondProduct] = $this->createExistingSale();
        $before = $this->snapshot();
        $throw = true;

        Sale::updating(function () use (&$throw): void {
            if ($throw) {
                $throw = false;
                throw new RuntimeException('Failure while updating sale header');
            }
        });

        try {
            $this->service()->updateSale($sale, $this->updateData(
                [$firstProduct->id, $secondProduct->id],
                [1, 4],
                [120, 70]
            ), (int) $sale->fresh()->revision);
            $this->fail('Expected update failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Failure while updating sale header', $exception->getMessage());
        }

        $this->assertSame($before, $this->snapshot());
    }

    private function service(): SaleService
    {
        return app(SaleService::class);
    }

    private function createExistingSale(): array
    {
        $firstProduct = Product::create([
            'name' => 'First product',
            'cost_price' => 80,
            'selling_price' => 100,
            'stock_qty' => 8,
        ]);
        $secondProduct = Product::create([
            'name' => 'Second product',
            'cost_price' => 50,
            'selling_price' => 60,
            'stock_qty' => 17,
        ]);
        $sale = Sale::create([
            'sale_no' => 'SAL-TX-0001',
            'sale_date' => '2026-07-13',
            'total_amount' => 390,
            'delivery_fee' => 20,
            'delivery_type' => 'delivery',
            'discount' => 10,
        ]);

        $sale->items()->createMany([
            [
                'product_id' => $firstProduct->id,
                'qty' => 2,
                'selling_price' => 100,
                'cost_price' => 80,
                'total' => 200,
                'profit' => 40,
            ],
            [
                'product_id' => $secondProduct->id,
                'qty' => 3,
                'selling_price' => 60,
                'cost_price' => 50,
                'total' => 180,
                'profit' => 30,
            ],
        ]);

        StockMovement::create([
            'product_id' => $firstProduct->id,
            'type' => 'OUT',
            'qty' => 2,
            'stock_before' => 10,
            'stock_after' => 8,
            'reference_type' => 'sale',
            'reference_id' => $sale->id,
        ]);
        StockMovement::create([
            'product_id' => $secondProduct->id,
            'type' => 'OUT',
            'qty' => 3,
            'stock_before' => 20,
            'stock_after' => 17,
            'reference_type' => 'sale',
            'reference_id' => $sale->id,
        ]);

        return [$sale, $firstProduct, $secondProduct];
    }

    private function updateData(array $productIds, array $quantities, array $prices): array
    {
        return [
            'customer_id' => null,
            'sale_date' => '2026-07-14',
            'items' => collect($productIds)->map(fn (int $productId, int $index) => [
                'product_id' => $productId,
                'product_unit_id' => null,
                'qty' => $quantities[$index],
                'selling_price' => $prices[$index],
            ])->all(),
            'delivery_fee' => 25,
            'discount' => 10,
        ];
    }

    private function snapshot(): array
    {
        return [
            'sales' => DB::table('sales')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'sale_items' => DB::table('sale_items')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'products' => DB::table('products')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'stock_movements' => DB::table('stock_movements')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }
}
