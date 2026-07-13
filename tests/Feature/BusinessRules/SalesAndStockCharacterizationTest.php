<?php

namespace Tests\Feature\BusinessRules;

use App\Models\CustomerDeliveryAddress;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Sales\CommissionService;
use App\Services\Sales\ProfitGuardService;
use App\Services\Sales\SaleItemService;
use App\Services\Sales\SaleNumberService;
use App\Services\Sales\StockService;
use App\Services\SaleService;
use App\Services\StockLockService;
use Mockery;
use Tests\Support\CreatesBusinessRuleTestSchema;
use Tests\TestCase;

class SalesAndStockCharacterizationTest extends TestCase
{
    use CreatesBusinessRuleTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBusinessRuleTestSchema();
    }

    protected function tearDown(): void
    {
        $this->dropBusinessRuleTestSchema();
        parent::tearDown();
    }

    public function test_sale_item_uses_the_current_product_cost_for_total_and_profit(): void
    {
        $product = Product::create([
            'name' => 'Characterization product',
            'cost_price' => 80,
            'selling_price' => 120,
            'stock_qty' => 10,
        ]);
        $sale = Sale::create([
            'sale_no' => 'SAL-TEST-0001',
            'sale_date' => '2026-07-13',
            'total_amount' => 360,
            'delivery_type' => 'pickup',
        ]);

        (new SaleItemService)->createItems($sale, [[
            'product_id' => $product->id,
            'qty' => 3,
            'selling_price' => 120,
        ]]);

        $item = $sale->items()->sole();

        $this->assertEquals(360.00, $item->total);
        $this->assertEquals(80.00, $item->cost_price);
        $this->assertEquals(120.00, $item->profit);
    }

    public function test_stock_deduction_preserves_the_current_movement_values(): void
    {
        $product = Product::create([
            'name' => 'Stock product',
            'cost_price' => 50,
            'selling_price' => 75,
            'stock_qty' => 10,
        ]);
        $sale = Sale::create([
            'sale_no' => 'SAL-TEST-0002',
            'sale_date' => '2026-07-13',
            'total_amount' => 225,
            'delivery_type' => 'pickup',
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'qty' => 3,
            'selling_price' => 75,
            'cost_price' => 50,
            'total' => 225,
            'profit' => 75,
        ]);

        (new StockService)->deductFromSale(
            $sale,
            collect([$product])->keyBy('id')
        );

        $this->assertEquals(7.0, $product->fresh()->stock_qty);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'OUT',
            'qty' => 3,
            'stock_before' => 10,
            'stock_after' => 7,
            'reference_type' => 'sale',
            'reference_id' => $sale->id,
        ]);
    }

    public function test_pickup_sale_total_is_grand_total_less_discount(): void
    {
        $sale = $this->makeSaleService()->createSale([
            'sale_date' => '2026-07-13',
            'grand_total' => 1000,
            'discount' => 75,
            'delivery_type' => 'pickup',
            'items' => [],
        ]);

        $this->assertEquals(925.00, $sale->total_amount);
        $this->assertEquals(0.00, $sale->delivery_fee);
    }

    public function test_delivery_sale_total_adds_the_zone_fee_then_subtracts_discount(): void
    {
        $zone = DeliveryZone::create([
            'name' => 'Test zone',
            'base_delivery_fee' => 80,
            'minimum_profit' => 0,
        ]);
        $address = CustomerDeliveryAddress::create([
            'name' => 'Test address',
            'address' => 'Test only',
            'delivery_zone_id' => $zone->id,
        ]);

        $sale = $this->makeSaleService()->createSale([
            'sale_date' => '2026-07-13',
            'grand_total' => 1000,
            'discount' => 50,
            'delivery_type' => 'delivery',
            'customer_delivery_address_id' => $address->id,
            'items' => [],
        ]);

        $this->assertEquals(1030.00, $sale->total_amount);
        $this->assertEquals(80.00, $sale->delivery_fee);
    }

    private function makeSaleService(): SaleService
    {
        $number = Mockery::mock(SaleNumberService::class);
        $number->shouldReceive('generate')->once()->andReturn('SAL-TEST-9999');

        $stock = Mockery::mock(StockService::class);
        $stock->shouldReceive('deductFromSale')->once();

        $commission = Mockery::mock(CommissionService::class);
        $commission->shouldReceive('createFromSale')->once();

        $profitGuard = Mockery::mock(ProfitGuardService::class);
        $profitGuard->shouldReceive('check')->once()->andReturn([
            'passed' => true,
            'message' => null,
        ]);

        return new SaleService(
            $number,
            new SaleItemService,
            $stock,
            $commission,
            $profitGuard,
            new StockLockService
        );
    }
}
