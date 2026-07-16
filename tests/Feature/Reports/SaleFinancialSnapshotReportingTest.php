<?php

namespace Tests\Feature\Reports;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\Sales\SaleFinancialSnapshotService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaleFinancialSnapshotReportingTest extends TestCase
{
    use RefreshDatabase;

    private Sale $sale;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-16 10:00:00');

        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Synthetic category',
            'default_profit_percent' => 0,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->product = Product::create([
            'category_id' => $categoryId,
            'name' => 'Snapshot product',
            'cost_price' => '5.00',
            'selling_price' => '180.00',
            'stock_qty' => '100.0000',
            'minimum_stock' => '0.0000',
            'active' => true,
        ]);

        $this->sale = Sale::create([
            'sale_no' => 'SAL-20260716-0001',
            'sale_date' => '2026-07-16',
            'total_amount' => '360.00',
            'delivery_fee' => '0.00',
            'delivery_type' => 'pickup',
            'discount' => '0.00',
        ]);

        SaleItem::create([
            'sale_id' => $this->sale->id,
            'product_id' => $this->product->id,
            'qty' => '2.00',
            'conversion_rate_used' => '24.0000',
            'base_qty' => '48.0000',
            'selling_price' => '180.00',
            'cost_price' => '5.00',
            'total' => '360.00',
            'profit' => '175.00',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_uses_stored_historical_profit(): void
    {
        $data = app(DashboardController::class)
            ->index(app(SaleFinancialSnapshotService::class))
            ->getData();

        $this->assertSame('175.00', $data['todayProfit']);
        $this->assertSame('175.00', $data['monthProfit']);
    }

    public function test_sale_detail_uses_stored_total_profit_and_derived_cost(): void
    {
        $view = app(SaleController::class)->show(
            $this->sale,
            app(SaleFinancialSnapshotService::class)
        );
        $data = $view->getData();

        $this->assertSame('185.00', $data['totalCost']);
        $this->assertSame('175.00', $data['profit']);
        $this->assertStringContainsString('185.00', $view->render());
        $this->assertStringContainsString('175.00', $view->render());
    }

    public function test_daily_monthly_and_yearly_reports_use_stored_financial_snapshots(): void
    {
        $controller = app(ReportController::class);

        $daily = $controller->dailyProfit(Request::create('/', 'GET', [
            'date' => '2026-07-16',
        ]))->getData();
        $monthly = $controller->monthlyProfit(Request::create('/', 'GET', [
            'month' => 7,
            'year' => 2026,
        ]))->getData();
        $yearly = $controller->yearlyProfit(Request::create('/', 'GET', [
            'year' => 2026,
        ]))->getData();

        foreach ([$daily, $monthly, $yearly] as $data) {
            $this->assertSame('360.00', $data['totalSales']);
            $this->assertSame('185.00', $data['totalCost']);
            $this->assertSame('175.00', $data['totalProfit']);
            $this->assertSame('185.00', $data['financialsBySaleId'][$this->sale->id]['cost']);
            $this->assertSame('175.00', $data['financialsBySaleId'][$this->sale->id]['profit']);
        }
    }

    public function test_product_report_uses_stored_total_profit_and_derived_cost(): void
    {
        $this->app->instance('request', Request::create('/', 'GET', [
            'month' => 7,
            'year' => 2026,
        ]));

        $products = app(ReportController::class)->productSales()->getData()['products'];
        $product = $products[$this->product->id];

        $this->assertSame('360.00', $product['sales']);
        $this->assertSame('185.00', $product['cost']);
        $this->assertSame('175.00', $product['profit']);
    }

    public function test_csv_uses_stored_total_and_profit_and_labels_derived_total_cost(): void
    {
        $response = app(ReportController::class)->exportDailyProfit(
            Request::create('/', 'GET', ['date' => '2026-07-16'])
        );

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('ต้นทุนรวม', $csv);
        $this->assertStringContainsString('185.00,360.00,175.00', $csv);
    }

    public function test_product_sales_csv_preserves_original_four_column_contract(): void
    {
        $response = app(ReportController::class)->exportProductSales(
            Request::create('/', 'GET', ['month' => 7, 'year' => 2026])
        );

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();
        $rows = preg_split('/\r\n|\r|\n/', trim($csv));
        $header = str_getcsv($rows[0]);
        $data = str_getcsv($rows[1]);
        $header[0] = ltrim($header[0], "\xEF\xBB\xBF");

        $this->assertSame(['สินค้า', 'หน่วย', 'จำนวนขาย', 'ยอดขายรวม'], $header);
        $this->assertCount(4, $data);
        $this->assertSame('360.00', $data[3]);
    }

    public function test_changing_current_product_cost_does_not_change_historical_results(): void
    {
        $this->product->update(['cost_price' => '999.00']);

        $summary = app(SaleFinancialSnapshotService::class)
            ->saleSummary($this->sale->fresh('items'));

        $this->assertSame('360.00', $summary['revenue']);
        $this->assertSame('185.00', $summary['cost']);
        $this->assertSame('175.00', $summary['profit']);
        $this->assertSame('175.00', $this->sale->items()->sole()->profit);
    }
}
