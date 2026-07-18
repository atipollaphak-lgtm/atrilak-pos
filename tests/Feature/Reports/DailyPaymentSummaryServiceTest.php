<?php

namespace Tests\Feature\Reports;

use App\Models\Quotation;
use App\Models\Sale;
use App\Services\Sales\DailyPaymentSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyPaymentSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_exposes_the_business_date_summary_contract(): void
    {
        $this->assertTrue(method_exists(
            DailyPaymentSummaryService::class,
            'forBusinessDate'
        ));
    }

    public function test_service_returns_decimal_payment_totals_for_valid_active_sales(): void
    {
        $cash = $this->sale('cash', '100.25', '0.00', '150.00', '49.75', total: '100.25');
        $promptpay = $this->sale('promptpay', '0.00', '50.50', '0.00', '0.00', total: '50.50');
        $mixed = $this->sale('mixed', '40.25', '60.50', '50.00', '9.75', total: '100.75');
        $legacy = $this->sale(null, null, null, null, null, total: '75.00');
        $incomplete = $this->sale('cash', null, '0.00', '10.00', null, total: '10.00');
        $invalid = $this->sale('cash', '90.00', '10.00', '100.00', '10.00');
        $quotation = Quotation::query()->create([
            'quotation_no' => 'QT-PAYMENT-NULL',
            'quotation_date' => '2026-07-18',
            'total_amount' => '25.00',
        ]);
        $quotationSale = $this->sale(null, null, null, null, null, total: '25.00');
        $quotationSale->update(['quotation_id' => $quotation->id]);
        $this->sale('cash', '999.00', '0.00', '999.00', '0.00', status: Sale::STATUS_VOIDED, total: '999.00');
        $this->sale('cash', '888.00', '0.00', '888.00', '0.00', date: '2026-07-17', total: '888.00');

        $summary = app(DailyPaymentSummaryService::class)
            ->forBusinessDate('2026-07-18');

        $this->assertSame('140.50', $summary['cash_total']);
        $this->assertSame('111.00', $summary['promptpay_total']);
        $this->assertSame('251.50', $summary['recorded_total']);
        $this->assertSame('200.00', $summary['received_total']);
        $this->assertSame('59.50', $summary['change_total']);
        $this->assertSame(1, $summary['cash_count']);
        $this->assertSame(1, $summary['promptpay_count']);
        $this->assertSame(1, $summary['mixed_count']);
        $this->assertSame(4, $summary['unrecorded_count']);
        $this->assertSame([$cash->id, $promptpay->id, $mixed->id], $summary['valid_sales']->pluck('id')->all());
        $this->assertSame(
            [$legacy->id, $incomplete->id, $invalid->id, $quotationSale->id],
            $summary['exceptions']->pluck('sale.id')->all()
        );
    }

    private function sale(
        ?string $method,
        ?string $cash,
        ?string $promptpay,
        ?string $received,
        ?string $change,
        string $date = '2026-07-18',
        string $status = Sale::STATUS_ACTIVE,
        string $total = '100.00'
    ): Sale {
        return Sale::query()->create([
            'sale_no' => 'PAY-SERVICE-'.str()->uuid(),
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
