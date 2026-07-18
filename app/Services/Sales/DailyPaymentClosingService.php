<?php

namespace App\Services\Sales;

use App\Models\DailyPaymentClosing;
use App\Models\DailyPaymentClosingSale;
use App\Models\User;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class DailyPaymentClosingService
{
    public function __construct(
        private readonly DailyPaymentSummaryService $summaryService,
        private readonly SaleDecimalService $decimalService
    ) {}

    public function open(string $businessDate, User $actor): array
    {
        $existing = DailyPaymentClosing::query()
            ->where('business_date', $businessDate)
            ->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        try {
            $closing = DailyPaymentClosing::query()->create([
                'business_date' => $businessDate,
                'status' => DailyPaymentClosing::STATUS_OPEN,
                'opened_by' => $actor->id,
            ]);

            return [$closing, true];
        } catch (QueryException $exception) {
            $closing = DailyPaymentClosing::query()
                ->where('business_date', $businessDate)
                ->first();

            if ($closing !== null) {
                return [$closing, false];
            }

            throw $exception;
        }
    }

    public function update(
        DailyPaymentClosing $closing,
        string $actualCashAmount,
        string $actualPromptpayAmount,
        ?string $notes,
        int $expectedRevision
    ): DailyPaymentClosing {
        return DB::transaction(function () use (
            $closing,
            $actualCashAmount,
            $actualPromptpayAmount,
            $notes,
            $expectedRevision
        ): DailyPaymentClosing {
            $locked = $this->lock($closing);
            $this->assertOpenRevision($locked, $expectedRevision);

            $locked->fill([
                'actual_cash_amount' => $this->moneyInput($actualCashAmount),
                'actual_promptpay_amount' => $this->moneyInput($actualPromptpayAmount),
                'notes' => $notes,
                'revision' => $locked->revision + 1,
            ])->save();

            return $locked->refresh();
        });
    }

    public function finalize(
        DailyPaymentClosing $closing,
        int $expectedRevision,
        User $actor
    ): DailyPaymentClosing {
        return DB::transaction(function () use ($closing, $expectedRevision, $actor): DailyPaymentClosing {
            $locked = $this->lock($closing);
            $this->assertOpenRevision($locked, $expectedRevision);
            $actualCash = $this->moneyInput($locked->actual_cash_amount);
            $actualPromptpay = $this->moneyInput($locked->actual_promptpay_amount);
            $summary = $this->summaryService->forBusinessDate((string) $locked->business_date);

            if ($summary['unrecorded_count'] > 0) {
                throw new DomainException('Cannot finalize while active sales have invalid payment data.', 422);
            }

            $locked->sales()->delete();

            foreach ($summary['valid_sales'] as $sale) {
                DailyPaymentClosingSale::query()->create([
                    'daily_payment_closing_id' => $locked->id,
                    'sale_id' => $sale->id,
                    'sale_revision' => $sale->revision,
                    'sale_status' => $sale->status,
                    'sale_total_amount' => $this->storedMoney($sale->total_amount),
                    'payment_method' => $sale->payment_method,
                    'cash_amount' => $this->storedMoney($sale->cash_amount),
                    'promptpay_amount' => $this->storedMoney($sale->promptpay_amount),
                    'received_amount' => $this->storedMoney($sale->received_amount),
                    'change_amount' => $this->storedMoney($sale->change_amount),
                ]);
            }

            $locked->fill([
                'expected_cash_amount' => $summary['cash_total'],
                'expected_promptpay_amount' => $summary['promptpay_total'],
                'expected_recorded_sales_amount' => $summary['recorded_total'],
                'expected_received_cash_amount' => $summary['received_total'],
                'expected_change_amount' => $summary['change_total'],
                'cash_sales_count' => $summary['cash_count'],
                'promptpay_sales_count' => $summary['promptpay_count'],
                'mixed_sales_count' => $summary['mixed_count'],
                'unrecorded_payment_count' => $summary['unrecorded_count'],
                'actual_cash_amount' => $actualCash,
                'actual_promptpay_amount' => $actualPromptpay,
                'cash_variance' => $this->decimalService->subtractMoney($actualCash, $summary['cash_total']),
                'promptpay_variance' => $this->decimalService->subtractMoney($actualPromptpay, $summary['promptpay_total']),
                'status' => DailyPaymentClosing::STATUS_FINALIZED,
                'finalized_by' => $actor->id,
                'finalized_at' => now(),
                'revision' => $locked->revision + 1,
            ])->save();

            return $locked->refresh();
        });
    }

    public function reopen(
        DailyPaymentClosing $closing,
        string $reason,
        int $expectedRevision,
        User $actor
    ): DailyPaymentClosing {
        return DB::transaction(function () use ($closing, $reason, $expectedRevision, $actor): DailyPaymentClosing {
            $locked = $this->lock($closing);

            if ($locked->status !== DailyPaymentClosing::STATUS_FINALIZED) {
                throw new DomainException('Only finalized daily payment closings can be reopened.', 409);
            }

            $this->assertRevision($locked, $expectedRevision);
            $reason = trim($reason);

            if ($reason === '') {
                throw new DomainException('A reopen reason is required.', 422);
            }

            $locked->fill([
                'status' => DailyPaymentClosing::STATUS_OPEN,
                'reopened_by' => $actor->id,
                'reopened_at' => now(),
                'reopen_reason' => $reason,
                'revision' => $locked->revision + 1,
            ])->save();

            return $locked->refresh();
        });
    }

    private function lock(DailyPaymentClosing $closing): DailyPaymentClosing
    {
        return DailyPaymentClosing::query()->lockForUpdate()->findOrFail($closing->id);
    }

    private function assertOpenRevision(DailyPaymentClosing $closing, int $expectedRevision): void
    {
        if ($closing->status !== DailyPaymentClosing::STATUS_OPEN) {
            throw new DomainException('This daily payment closing is finalized and immutable.', 409);
        }

        $this->assertRevision($closing, $expectedRevision);
    }

    private function assertRevision(DailyPaymentClosing $closing, int $expectedRevision): void
    {
        if ($closing->revision !== $expectedRevision) {
            throw new DomainException('The daily payment closing has changed. Refresh and try again.', 409);
        }
    }

    private function moneyInput(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^\d+\.\d{2}$/', $value)) {
            throw new DomainException('Amounts must be non-negative decimal strings with exactly two decimal places.', 422);
        }

        return $this->decimalService->money($value);
    }

    private function storedMoney(mixed $value): string
    {
        if (is_int($value)) {
            return $this->decimalService->money((string) $value);
        }

        if (! is_string($value)) {
            throw new DomainException('Stored sale payment data is invalid.', 422);
        }

        return $this->decimalService->money($value);
    }
}
