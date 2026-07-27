<?php

namespace Tests\Feature\Sales;

use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Services\SaleService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\CreatesSaleTransactionTestSchema;
use Tests\TestCase;

class SaleDeleteTest extends TestCase
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

    public function test_delete_sale_atomically_restores_multiple_products_and_preserves_movements(): void
    {
        [$sale, $firstProduct, $secondProduct] = $this->createExistingSale();

        $this->service()->deleteSale($sale);

        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertDatabaseMissing('sale_items', ['sale_id' => $sale->id]);
        $this->assertDatabaseMissing('technician_commissions', ['sale_id' => $sale->id]);
        $this->assertEquals(10.0000, $firstProduct->fresh()->stock_qty);
        $this->assertEquals(20.0000, $secondProduct->fresh()->stock_qty);

        $deleteMovements = StockMovement::where('reference_type', 'sale_delete')
            ->where('reference_id', $sale->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $deleteMovements);
        $this->assertSame(['IN', 'IN'], $deleteMovements->pluck('type')->all());
        $this->assertEquals(
            [[8, 10], [17, 20]],
            $deleteMovements->map(fn (StockMovement $movement) => [
                (float) $movement->stock_before,
                (float) $movement->stock_after,
            ])->all()
        );
        $this->assertSame(2, StockMovement::where('reference_type', 'sale')->count());
    }

    public function test_exception_after_stock_restore_before_sale_delete_rolls_back_every_write(): void
    {
        [$sale] = $this->createExistingSale();
        $before = $this->snapshot();
        $throw = true;

        Sale::deleting(function () use (&$throw): void {
            if ($throw) {
                $throw = false;
                throw new RuntimeException('Failure before sale delete');
            }
        });

        try {
            $this->service()->deleteSale($sale);
            $this->fail('Expected delete failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Failure before sale delete', $exception->getMessage());
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
        $technicianId = DB::table('technicians')->insertGetId([
            'name' => 'Transaction technician',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sale = Sale::create([
            'sale_no' => 'SAL-TX-DELETE-0001',
            'technician_id' => $technicianId,
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
        DB::table('technician_commissions')->insert([
            'sale_id' => $sale->id,
            'technician_id' => $technicianId,
            'commission_amount' => 25,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$sale, $firstProduct, $secondProduct];
    }

    private function snapshot(): array
    {
        return [
            'sales' => DB::table('sales')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'sale_items' => DB::table('sale_items')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'products' => DB::table('products')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'stock_movements' => DB::table('stock_movements')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'technician_commissions' => DB::table('technician_commissions')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }
}
