<?php

namespace Tests\Feature\Reports;

use App\Http\Controllers\ReportController;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class DailyPaymentSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_payment_summary_uses_stored_payment_allocations_and_excludes_legacy_sales(): void
    {
        $this->sale('2026-07-18', 'cash', '100.25', '0.00', '150.00', '49.75', total: '100.25');
        $this->sale('2026-07-18', 'promptpay', '0.00', '50.50', '0.00', '0.00', total: '50.50');
        $this->sale('2026-07-18', 'mixed', '40.25', '60.50', '50.00', '9.75', total: '100.75');
        $this->sale('2026-07-18', null, null, null, null, null);
        $this->sale('2026-07-17', 'cash', '999.00', '0.00', '999.00', '0.00');

        $view = app(ReportController::class)->dailyProfit(Request::create('/', 'GET', [
            'date' => '2026-07-18',
        ]));
        $summary = $view->getData()['paymentSummary'];

        $this->assertSame('140.50', $summary['cash_total']);
        $this->assertSame('111.00', $summary['promptpay_total']);
        $this->assertSame('251.50', $summary['recorded_total']);
        $this->assertSame('200.00', $summary['received_total']);
        $this->assertSame('59.50', $summary['change_total']);
        $this->assertSame(1, $summary['cash_count']);
        $this->assertSame(1, $summary['promptpay_count']);
        $this->assertSame(1, $summary['mixed_count']);
        $this->assertSame(1, $summary['unrecorded_count']);
        $this->assertStringContainsString('เงินสดสุทธิที่รับจากการขาย', $view->render());
    }

    public function test_daily_payment_summary_excludes_all_voided_payment_states(): void
    {
        $this->sale('2026-07-18', 'cash', '100.25', '0.00', '150.00', '49.75', total: '100.25');
        $this->sale('2026-07-18', 'promptpay', '0.00', '50.50', '0.00', '0.00', total: '50.50');
        $this->sale('2026-07-18', 'mixed', '40.25', '60.50', '50.00', '9.75', total: '100.75');
        $this->sale('2026-07-18', null, null, null, null, null);
        $this->sale('2026-07-18', 'cash', '999.00', '0.00', '999.00', '0.00', Sale::STATUS_VOIDED);
        $this->sale('2026-07-18', 'promptpay', '0.00', '999.00', '0.00', '0.00', Sale::STATUS_VOIDED);
        $this->sale('2026-07-18', 'mixed', '999.00', '999.00', '999.00', '0.00', Sale::STATUS_VOIDED);
        $this->sale('2026-07-18', null, null, null, null, null, Sale::STATUS_VOIDED);

        $summary = app(ReportController::class)->dailyProfit(Request::create('/', 'GET', [
            'date' => '2026-07-18',
        ]))->getData()['paymentSummary'];

        $this->assertSame('140.50', $summary['cash_total']);
        $this->assertSame('111.00', $summary['promptpay_total']);
        $this->assertSame('251.50', $summary['recorded_total']);
        $this->assertSame(1, $summary['cash_count']);
        $this->assertSame(1, $summary['promptpay_count']);
        $this->assertSame(1, $summary['mixed_count']);
        $this->assertSame(1, $summary['unrecorded_count']);
    }

    private function sale(
        string $date,
        ?string $method,
        ?string $cash,
        ?string $promptpay,
        ?string $received,
        ?string $change,
        string $status = Sale::STATUS_ACTIVE,
        string $total = '100.00'
    ): void {
        Sale::query()->create([
            'sale_no' => 'PAY-SUM-'.str()->uuid(),
            'sale_date' => $date,
            'total_amount' => $total,
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'delivery_type' => 'pickup',
            'payment_method' => $method,
            'cash_amount' => $cash,
            'promptpay_amount' => $promptpay,
            'received_amount' => $received,
            'change_amount' => $change,
            'status' => $status,
        ]);
    }
}
