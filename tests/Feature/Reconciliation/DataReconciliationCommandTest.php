<?php

namespace Tests\Feature\Reconciliation;

use App\Services\Reconciliation\DataReconciliationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DataReconciliationCommandTest extends TestCase
{
    use DatabaseTruncation;

    public function test_consistent_data_has_no_confirmed_anomalies(): void
    {
        $productId = $this->createProduct(stock: 8);
        $saleId = $this->createSale(total: 200);
        $this->createSaleItem($saleId, $productId, qty: 2, price: 100, total: 200);
        $this->createMovement($productId, before: 10, after: 8, createdAt: '2026-07-13 10:00:00');

        $report = app(DataReconciliationService::class)->reconcile();

        $this->assertSame(0, $report['summary']['confirmed_anomalies']);
        $this->assertSame(1, $report['summary']['checked']['sales']);
        $this->assertSame(1, $report['summary']['checked']['sale_items']);
        $this->assertSame(1, $report['summary']['checked']['products']);
        $this->assertSame(1, $report['summary']['checked']['stock_movements']);
    }

    public function test_sale_header_and_line_total_mismatches_are_confirmed(): void
    {
        $productId = $this->createProduct();
        $saleId = $this->createSale(total: 250);
        $this->createSaleItem($saleId, $productId, qty: 2, price: 100, total: 190);

        $report = app(DataReconciliationService::class)->reconcile(saleId: $saleId);
        $codes = collect($report['confirmed_anomalies'])->pluck('code');

        $this->assertTrue($codes->contains('SALE_ITEM_TOTAL_MISMATCH'));
        $this->assertTrue($codes->contains('SALE_TOTAL_MISMATCH'));
        $header = collect($report['confirmed_anomalies'])->firstWhere('code', 'SALE_TOTAL_MISMATCH');
        $this->assertSame(1, $header['details']['sale_item_count']);
        $this->assertSame('250.00', $header['actual']);
        $this->assertSame('190.00', $header['expected']);
        $this->assertSame('60.00', $header['difference']);
    }

    public function test_sale_without_items_and_null_financial_values_are_reported_without_crashing(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('delivery_fee', 12, 2)->nullable()->change();
            $table->decimal('discount', 12, 2)->nullable()->change();
        });
        $saleId = null;

        try {
            $saleId = $this->createSale(total: 0, deliveryFee: null, discount: null);

            $report = app(DataReconciliationService::class)->reconcile(saleId: $saleId);

            $this->assertContains(
                'SALE_WITHOUT_ITEMS',
                collect($report['confirmed_anomalies'])->pluck('code')->all()
            );
            $this->assertContains(
                'SALE_NULL_FINANCIAL_COMPONENT',
                collect($report['warnings'])->pluck('code')->all()
            );
        } finally {
            if ($saleId !== null) {
                DB::table('sales')->where('id', $saleId)->update([
                    'delivery_fee' => 0,
                    'discount' => 0,
                ]);
            }

            Schema::table('sales', function (Blueprint $table) {
                $table->decimal('delivery_fee', 12, 2)->default(0)->nullable(false)->change();
                $table->decimal('discount', 12, 2)->default(0)->nullable(false)->change();
            });
        }

        $this->assertRequiredZeroMoneyColumn('delivery_fee');
        $this->assertRequiredZeroMoneyColumn('discount');
    }

    public function test_stock_chain_decimal_mismatch_latest_stock_and_timestamp_tie_are_reported(): void
    {
        $productId = $this->createProduct(stock: 9);
        $this->createMovement($productId, before: 12.75, after: 10.50, createdAt: '2026-07-13 10:00:00');
        $this->createMovement($productId, before: 10.25, after: 9.25, createdAt: '2026-07-13 10:00:00');

        $report = app(DataReconciliationService::class)->reconcile(productId: $productId);
        $confirmed = collect($report['confirmed_anomalies']);

        $this->assertNotNull($confirmed->firstWhere('code', 'STOCK_MOVEMENT_CHAIN_BREAK'));
        $this->assertNotNull($confirmed->firstWhere('code', 'PRODUCT_STOCK_MISMATCH'));
        $this->assertNotNull(
            collect($report['informational_findings'])->firstWhere('code', 'MOVEMENT_TIMESTAMP_TIE')
        );
        $chain = $confirmed->firstWhere('code', 'STOCK_MOVEMENT_CHAIN_BREAK');
        $this->assertSame('10.2500', $chain['actual']);
        $this->assertSame('10.5000', $chain['expected']);
        $this->assertSame('-0.2500', $chain['difference']);
    }

    public function test_no_movement_history_is_informational_and_exits_zero(): void
    {
        $productId = $this->createProduct(stock: 5);

        $exitCode = Artisan::call('atrilak:reconcile-data', [
            '--json' => true,
            '--product-id' => $productId,
        ]);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame(0, $report['summary']['confirmed_anomalies']);
        $this->assertSame('NO_MOVEMENT_HISTORY', $report['informational_findings'][0]['code']);
        $this->assertNull($report['informational_findings'][0]['expected']);
        $this->assertNull($report['informational_findings'][0]['difference']);
    }

    public function test_warning_only_for_commission_expected_from_current_rules_exits_zero(): void
    {
        $technicianId = $this->createTechnician('Current rule technician');
        $productId = $this->createProduct();
        $saleId = $this->createSale(total: 200, technicianId: $technicianId);
        $this->createSaleItem($saleId, $productId, qty: 2, price: 100, total: 200);
        $this->createCommissionRule($productId, ruleType: 'percent', ruleValue: 10);

        $exitCode = Artisan::call('atrilak:reconcile-data', [
            '--json' => true,
            '--sale-id' => $saleId,
        ]);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame(0, $report['summary']['confirmed_anomalies']);
        $this->assertSame('COMMISSION_EXPECTED_FROM_CURRENT_RULES', $report['warnings'][0]['code']);
        $this->assertSame('20.00', $report['warnings'][0]['expected']);
    }

    public function test_duplicate_commission_is_confirmed_and_exits_one(): void
    {
        $technicianId = $this->createTechnician('Duplicate technician');
        $productId = $this->createProduct();
        $saleId = $this->createSale(total: 100, technicianId: $technicianId);
        $this->createSaleItem($saleId, $productId, qty: 1, price: 100, total: 100);
        $detail = $this->supportedCalculationDetail(commission: 10);
        $this->createCommission($saleId, $technicianId, amount: 10, saleTotal: 100, detail: $detail);
        $this->createCommission($saleId, $technicianId, amount: 10, saleTotal: 100, detail: $detail);

        $exitCode = Artisan::call('atrilak:reconcile-data', [
            '--json' => true,
            '--sale-id' => $saleId,
        ]);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $duplicate = collect($report['confirmed_anomalies'])->firstWhere('code', 'COMMISSION_DUPLICATE');
        $this->assertNotNull($duplicate);
        $this->assertSame(2, $duplicate['actual']);
        $this->assertSame(1, $duplicate['difference']);
    }

    public function test_malformed_calculation_detail_is_warning_and_does_not_crash(): void
    {
        $technicianId = $this->createTechnician('Malformed technician');
        $productId = $this->createProduct();
        $saleId = $this->createSale(total: 100, technicianId: $technicianId);
        $this->createSaleItem($saleId, $productId, qty: 1, price: 100, total: 100);

        // PostgreSQL validates JSON before the reconciliation service can inspect
        // legacy malformed payloads. Keep this corruption simulation isolated to
        // the test database and restore the production-equivalent type afterward.
        DB::statement('ALTER TABLE technician_commissions ALTER COLUMN calculation_detail TYPE TEXT USING calculation_detail::text');

        try {
            $this->createCommission($saleId, $technicianId, amount: 10, saleTotal: 100, detail: '{bad json');

            $exitCode = Artisan::call('atrilak:reconcile-data', [
                '--json' => true,
                '--sale-id' => $saleId,
            ]);
            $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame(0, $exitCode);
            $this->assertSame('UNSUPPORTED_CALCULATION_DETAIL_FORMAT', $report['warnings'][0]['code']);
            $this->assertSame('malformed JSON', $report['warnings'][0]['actual']);
        } finally {
            DB::table('technician_commissions')->delete();
            DB::statement('ALTER TABLE technician_commissions ALTER COLUMN calculation_detail TYPE JSON USING calculation_detail::json');
        }
    }

    public function test_commission_relationship_and_calculation_detail_mismatches_are_confirmed(): void
    {
        $saleTechnicianId = $this->createTechnician('Sale technician');
        $otherTechnicianId = $this->createTechnician('Other technician');
        $productId = $this->createProduct();
        $saleId = $this->createSale(total: 100, technicianId: $saleTechnicianId);
        $this->createSaleItem($saleId, $productId, qty: 1, price: 100, total: 100);
        $this->createCommission(
            $saleId,
            $otherTechnicianId,
            amount: 15,
            saleTotal: 90,
            detail: $this->supportedCalculationDetail(commission: 10),
            commissionDate: '2026-07-12'
        );

        $report = app(DataReconciliationService::class)->reconcile(saleId: $saleId);
        $codes = collect($report['confirmed_anomalies'])->pluck('code');

        $this->assertTrue($codes->contains('COMMISSION_TECHNICIAN_MISMATCH'));
        $this->assertTrue($codes->contains('COMMISSION_SALE_TOTAL_MISMATCH'));
        $this->assertTrue($codes->contains('COMMISSION_DATE_MISMATCH'));
        $this->assertTrue($codes->contains('COMMISSION_AMOUNT_DETAIL_MISMATCH'));
    }

    public function test_filters_limit_the_checked_entities(): void
    {
        $firstProduct = $this->createProduct(name: 'First product');
        $secondProduct = $this->createProduct(name: 'Second product');
        $firstSale = $this->createSale(total: 100, saleNo: 'SAL-FILTER-1');
        $secondSale = $this->createSale(total: 200, saleNo: 'SAL-FILTER-2');
        $this->createSaleItem($firstSale, $firstProduct, qty: 1, price: 100, total: 100);
        $this->createSaleItem($secondSale, $secondProduct, qty: 1, price: 200, total: 200);

        $report = app(DataReconciliationService::class)->reconcile(
            saleId: $firstSale,
            productId: $firstProduct
        );

        $this->assertSame($firstSale, $report['filters']['sale_id']);
        $this->assertSame($firstProduct, $report['filters']['product_id']);
        $this->assertSame(1, $report['summary']['checked']['sales']);
        $this->assertSame(1, $report['summary']['checked']['sale_items']);
        $this->assertSame(1, $report['summary']['checked']['products']);
    }

    public function test_running_twice_is_identical_and_does_not_change_rows_or_checksums(): void
    {
        $productId = $this->createProduct(stock: 7);
        $saleId = $this->createSale(total: 95);
        $this->createSaleItem($saleId, $productId, qty: 1, price: 100, total: 95);
        $this->createMovement($productId, before: 8, after: 7, createdAt: '2026-07-13 10:00:00');
        $before = $this->databaseFingerprint();

        $firstExit = Artisan::call('atrilak:reconcile-data', ['--json' => true]);
        $firstOutput = Artisan::output();
        $secondExit = Artisan::call('atrilak:reconcile-data', ['--json' => true]);
        $secondOutput = Artisan::output();
        $after = $this->databaseFingerprint();

        $this->assertSame(1, $firstExit);
        $this->assertSame($firstExit, $secondExit);
        $this->assertSame($firstOutput, $secondOutput);
        $this->assertSame($before, $after);
    }

    public function test_invalid_filter_returns_exit_code_two_without_running_reconciliation(): void
    {
        $exitCode = Artisan::call('atrilak:reconcile-data', [
            '--json' => true,
            '--sale-id' => 'invalid',
        ]);
        $output = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(2, $exitCode);
        $this->assertSame('Data reconciliation failed.', $output['error']);
    }

    private function createProduct(string $name = 'Test product', float $stock = 0): int
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Category '.$name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('products')->insertGetId([
            'category_id' => $categoryId,
            'name' => $name,
            'cost_price' => 50,
            'selling_price' => 100,
            'stock_qty' => $stock,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSale(
        float $total,
        ?int $technicianId = null,
        ?float $deliveryFee = 0,
        ?float $discount = 0,
        ?string $saleNo = null
    ): int {
        return DB::table('sales')->insertGetId([
            'sale_no' => $saleNo ?? 'SAL-TEST-'.uniqid(),
            'technician_id' => $technicianId,
            'sale_date' => '2026-07-13',
            'total_amount' => $total,
            'delivery_fee' => $deliveryFee,
            'discount' => $discount,
            'delivery_type' => 'pickup',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSaleItem(
        int $saleId,
        int $productId,
        float $qty,
        float $price,
        float $total
    ): int {
        return DB::table('sale_items')->insertGetId([
            'sale_id' => $saleId,
            'product_id' => $productId,
            'qty' => $qty,
            'selling_price' => $price,
            'cost_price' => 50,
            'total' => $total,
            'profit' => $total - ($qty * 50),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMovement(
        int $productId,
        float $before,
        float $after,
        string $createdAt
    ): int {
        return DB::table('stock_movements')->insertGetId([
            'product_id' => $productId,
            'type' => 'OUT',
            'qty' => abs($before - $after),
            'stock_before' => $before,
            'stock_after' => $after,
            'reference_type' => 'test',
            'reference_id' => 1,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function createTechnician(string $name): int
    {
        return DB::table('technicians')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createCommissionRule(int $productId, string $ruleType, float $ruleValue): int
    {
        return DB::table('technician_commission_rules')->insertGetId([
            'product_id' => $productId,
            'name' => 'Current test rule',
            'rule_type' => $ruleType,
            'rule_value' => $ruleValue,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createCommission(
        int $saleId,
        int $technicianId,
        float $amount,
        float $saleTotal,
        string $detail,
        string $commissionDate = '2026-07-13'
    ): int {
        return DB::table('technician_commissions')->insertGetId([
            'sale_id' => $saleId,
            'technician_id' => $technicianId,
            'commission_date' => $commissionDate,
            'sale_total' => $saleTotal,
            'commission_amount' => $amount,
            'calculation_detail' => $detail,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function supportedCalculationDetail(float $commission): string
    {
        return json_encode([[
            'product_name' => 'Test product',
            'qty' => '1.00',
            'line_total' => '100.00',
            'rule_name' => 'Test rule',
            'rule_type' => 'percent',
            'rule_value' => '10.00',
            'commission' => $commission,
        ]], JSON_THROW_ON_ERROR);
    }

    private function databaseFingerprint(): array
    {
        $tables = [
            'sales',
            'sale_items',
            'products',
            'stock_movements',
            'technician_commissions',
            'technician_commission_rules',
        ];

        return collect($tables)->mapWithKeys(function (string $table): array {
            $rows = DB::table($table)->orderBy('id')->get();

            return [$table => [
                'count' => $rows->count(),
                'checksum' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)),
            ]];
        })->all();
    }

    private function assertRequiredZeroMoneyColumn(string $column): void
    {
        $metadata = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'sales')
            ->where('column_name', $column)
            ->sole();

        $this->assertSame('numeric', $metadata->data_type);
        $this->assertSame(12, $metadata->numeric_precision);
        $this->assertSame(2, $metadata->numeric_scale);
        $this->assertSame('NO', $metadata->is_nullable);
        $this->assertStringContainsString('0', (string) $metadata->column_default);
    }
}
