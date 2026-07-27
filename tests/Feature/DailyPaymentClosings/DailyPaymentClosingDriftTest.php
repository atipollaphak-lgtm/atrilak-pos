<?php

namespace Tests\Feature\DailyPaymentClosings;

use App\Models\DailyPaymentClosing;
use App\Models\Sale;
use App\Models\User;
use App\Services\Sales\DailyPaymentClosingDriftService;
use App\Services\Sales\DailyPaymentClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DailyPaymentClosingDriftTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalized_unchanged_close_has_no_drift(): void
    {
        $closing = $this->finalizeWithSale();

        $comparison = app(DailyPaymentClosingDriftService::class)->compare($closing->fresh());

        $this->assertFalse($comparison['has_drift']);
        $this->assertFalse($comparison['summary_has_drift']);
        $this->assertSame(0, $comparison['added_count']);
        $this->assertSame(0, $comparison['removed_count']);
        $this->assertSame(0, $comparison['changed_count']);
    }

    public function test_finalized_close_reports_late_sale_as_added_without_changing_snapshot(): void
    {
        $closing = $this->finalizeWithSale();
        $snapshot = $closing->sales()->pluck('sale_id')->all();
        $late = $this->sale('LATE-SALE', ['total_amount' => '3.33', 'cash_amount' => '3.33', 'received_amount' => '3.33']);

        $comparison = app(DailyPaymentClosingDriftService::class)->compare($closing->fresh());

        $this->assertTrue($comparison['has_drift']);
        $this->assertSame(1, $comparison['added_count']);
        $this->assertSame($late->id, $comparison['added_sales'][0]['sale_id']);
        $this->assertSame($snapshot, $closing->fresh()->sales()->pluck('sale_id')->all());
    }

    public function test_finalized_close_reports_voided_or_moved_snapshot_sales_as_removed(): void
    {
        $closing = $this->finalizeWithSale();
        $sale = Sale::query()->where('sale_no', 'BASE-SALE')->sole();
        $sale->update(['status' => Sale::STATUS_VOIDED, 'sale_date' => '2026-07-19']);

        $comparison = app(DailyPaymentClosingDriftService::class)->compare($closing->fresh());

        $this->assertSame(1, $comparison['removed_count']);
        $this->assertSame($sale->id, $comparison['removed_sales'][0]['sale_id']);
        $this->assertSame(Sale::STATUS_VOIDED, $comparison['removed_sales'][0]['current_status']);
        $this->assertSame('2026-07-19', $comparison['removed_sales'][0]['current_business_date']);
    }

    public function test_revision_change_reports_financial_field_differences_with_normalized_decimals(): void
    {
        $closing = $this->finalizeWithSale();
        $sale = Sale::query()->where('sale_no', 'BASE-SALE')->sole();
        $sale->forceFill(['revision' => 2, 'total_amount' => '12.00', 'cash_amount' => '12.00', 'received_amount' => '12.00'])->save();

        $comparison = app(DailyPaymentClosingDriftService::class)->compare($closing->fresh());

        $this->assertSame(1, $comparison['changed_count']);
        $changed = $comparison['changed_sales'][0];
        $this->assertContains('revision', $changed['changed_fields']);
        $this->assertContains('sale_total_amount', $changed['changed_fields']);
        $this->assertContains('cash_amount', $changed['changed_fields']);
        $this->assertSame('10.00', $changed['snapshot']['sale_total_amount']);
        $this->assertSame('12.00', $changed['current']['sale_total_amount']);
    }

    public function test_payment_method_and_amount_changes_are_reported_separately_from_revision(): void
    {
        $closing = $this->finalizeWithSale();
        $sale = Sale::query()->where('sale_no', 'BASE-SALE')->sole();
        $sale->forceFill([
            'revision' => 2,
            'payment_method' => 'promptpay',
            'cash_amount' => '0.00',
            'promptpay_amount' => '10.00',
            'received_amount' => '0.00',
        ])->save();

        $comparison = app(DailyPaymentClosingDriftService::class)->compare($closing->fresh());

        $this->assertSame(1, $comparison['changed_count']);
        $this->assertContains('revision', $comparison['changed_sales'][0]['changed_fields']);
        $this->assertContains('payment_method', $comparison['changed_sales'][0]['changed_fields']);
        $this->assertContains('cash_amount', $comparison['changed_sales'][0]['changed_fields']);
        $this->assertContains('promptpay_amount', $comparison['changed_sales'][0]['changed_fields']);
        $this->assertContains('received_amount', $comparison['changed_sales'][0]['changed_fields']);
    }

    public function test_open_close_is_not_classified_as_historical_drift(): void
    {
        $closing = DailyPaymentClosing::query()->create(['business_date' => '2026-07-18']);
        $this->sale('OPEN-SALE');

        $comparison = app(DailyPaymentClosingDriftService::class)->compare($closing);

        $this->assertFalse($comparison['has_drift']);
        $this->assertFalse($comparison['summary_has_drift']);
        $this->assertFalse($comparison['is_finalized']);
    }

    public function test_batch_comparison_uses_a_fixed_live_sales_query_for_multiple_finalized_closings(): void
    {
        $first = $this->finalizeWithSale();
        Sale::query()->where('sale_no', 'BASE-SALE')->update(['sale_date' => '2026-07-19']);
        $second = DailyPaymentClosing::query()->create([
            'business_date' => '2026-07-19',
            'actual_cash_amount' => '10.00',
            'actual_promptpay_amount' => '0.00',
        ]);
        $second = app(DailyPaymentClosingService::class)->finalize($second, 1, User::factory()->create(['role' => 'manager']));
        $closings = DailyPaymentClosing::query()->with('sales')->orderBy('id')->get();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $comparisons = app(DailyPaymentClosingDriftService::class)->compareMany($closings);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertTrue($comparisons[$first->id]['has_drift']);
        $this->assertFalse($comparisons[$second->id]['has_drift']);
        $this->assertLessThanOrEqual(2, count($queries));
    }

    private function finalizeWithSale(): DailyPaymentClosing
    {
        $actor = User::factory()->create(['role' => 'manager']);
        $closing = DailyPaymentClosing::query()->create([
            'business_date' => '2026-07-18',
            'actual_cash_amount' => '10.00',
            'actual_promptpay_amount' => '0.00',
        ]);
        $this->sale('BASE-SALE');

        return app(DailyPaymentClosingService::class)->finalize($closing, 1, $actor);
    }

    private function sale(string $saleNo, array $attributes = []): Sale
    {
        return Sale::query()->create(array_merge([
            'sale_no' => $saleNo,
            'sale_date' => '2026-07-18',
            'total_amount' => '10.00',
            'delivery_fee' => '0.00',
            'discount' => '0.00',
            'delivery_type' => 'pickup',
            'status' => Sale::STATUS_ACTIVE,
            'revision' => 1,
            'payment_method' => 'cash',
            'cash_amount' => '10.00',
            'promptpay_amount' => '0.00',
            'received_amount' => '10.00',
            'change_amount' => '0.00',
        ], $attributes));
    }
}
