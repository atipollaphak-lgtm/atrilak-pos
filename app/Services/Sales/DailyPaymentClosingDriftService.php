<?php

namespace App\Services\Sales;

use App\Models\DailyPaymentClosing;
use App\Models\DailyPaymentClosingSale;
use App\Models\Sale;

class DailyPaymentClosingDriftService
{
    private const MONEY_FIELDS = [
        'sale_total_amount' => 'total_amount',
        'cash_amount' => 'cash_amount',
        'promptpay_amount' => 'promptpay_amount',
        'received_amount' => 'received_amount',
        'change_amount' => 'change_amount',
    ];

    public function __construct(
        private readonly DailyPaymentSummaryService $summaryService,
        private readonly SaleDecimalService $decimalService
    ) {}

    /**
     * Compare a finalized closing's immutable snapshot to its current sales.
     * This method is deliberately read-only.
     */
    public function compare(DailyPaymentClosing $closing): array
    {
        if ($closing->status !== DailyPaymentClosing::STATUS_FINALIZED) {
            return $this->openResult();
        }

        $businessDate = (string) $closing->business_date;
        $snapshots = $closing->relationLoaded('sales')
            ? $closing->sales
            : $closing->sales()->get();
        $snapshotBySaleId = $snapshots->keyBy('sale_id');
        $currentSales = Sale::query()
            ->whereIn('id', $snapshotBySaleId->keys())
            ->orWhere(function ($query) use ($businessDate): void {
                $query->active()->whereDate('sale_date', $businessDate);
            })
            ->orderBy('id')
            ->get();
        $currentBySaleId = $currentSales->keyBy('id');

        $added = $currentSales
            ->filter(fn (Sale $sale): bool => $sale->isActive()
                && (string) $sale->sale_date === $businessDate
                && ! $snapshotBySaleId->has($sale->id))
            ->map(fn (Sale $sale): array => $this->currentOnly($sale))
            ->values()
            ->all();
        $removed = $snapshots
            ->filter(function (DailyPaymentClosingSale $snapshot) use ($currentBySaleId, $businessDate): bool {
                $sale = $currentBySaleId->get($snapshot->sale_id);

                return ! $sale instanceof Sale
                    || ! $sale->isActive()
                    || (string) $sale->sale_date !== $businessDate;
            })
            ->map(fn (DailyPaymentClosingSale $snapshot): array => $this->removed(
                $snapshot,
                $currentBySaleId->get($snapshot->sale_id),
                $businessDate
            ))
            ->values()
            ->all();
        $changed = $snapshots
            ->map(function (DailyPaymentClosingSale $snapshot) use ($currentBySaleId, $businessDate): ?array {
                $sale = $currentBySaleId->get($snapshot->sale_id);

                if (! $sale instanceof Sale || ! $sale->isActive() || (string) $sale->sale_date !== $businessDate) {
                    return null;
                }

                $fields = $this->changedFields($snapshot, $sale);

                return $fields === [] ? null : $this->changed($snapshot, $sale, $fields);
            })
            ->filter()
            ->values()
            ->all();
        $currentSummary = $this->summaryService->forBusinessDate($businessDate);
        $snapshotSummary = $this->snapshotSummary($closing);
        $summaryDifferences = $this->summaryDifferences($snapshotSummary, $currentSummary);

        return [
            'is_finalized' => true,
            'has_drift' => $added !== [] || $removed !== [] || $changed !== [] || $summaryDifferences !== [],
            'summary_has_drift' => $summaryDifferences !== [],
            'added_count' => count($added),
            'removed_count' => count($removed),
            'changed_count' => count($changed),
            'current_summary' => $this->compactSummary($currentSummary),
            'snapshot_summary' => $snapshotSummary,
            'summary_differences' => $summaryDifferences,
            'added_sales' => $added,
            'removed_sales' => $removed,
            'changed_sales' => $changed,
        ];
    }

    public function compareMany(iterable $closings): array
    {
        $closings = collect($closings);
        $finalized = $closings->filter(fn (DailyPaymentClosing $closing): bool => $closing->status === DailyPaymentClosing::STATUS_FINALIZED);
        $summaries = $this->summaryService->forBusinessDates($finalized->pluck('business_date')->map(fn ($date): string => (string) $date)->all());
        $snapshots = $finalized->flatMap(fn (DailyPaymentClosing $closing) => $closing->relationLoaded('sales') ? $closing->sales : $closing->sales()->get());
        $snapshotIds = $snapshots->pluck('sale_id')->unique()->values()->all();
        $dates = $finalized->pluck('business_date')->map(fn ($date): string => (string) $date)->unique()->values()->all();
        $currentSales = Sale::query()
            ->where(function ($query) use ($snapshotIds, $dates): void {
                if ($snapshotIds !== []) {
                    $query->whereIn('id', $snapshotIds);
                }
                if ($dates !== []) {
                    $query->{$snapshotIds === [] ? 'where' : 'orWhere'}(function ($query) use ($dates): void {
                        $query->active()->whereIn('sale_date', $dates);
                    });
                }
            })
            ->orderBy('id')
            ->get();

        return $closings->mapWithKeys(function (DailyPaymentClosing $closing) use ($snapshots, $currentSales, $summaries): array {
            if ($closing->status !== DailyPaymentClosing::STATUS_FINALIZED) {
                return [$closing->id => $this->openResult()];
            }

            $businessDate = (string) $closing->business_date;
            $closingSnapshots = $snapshots->where('daily_payment_closing_id', $closing->id)->values();
            $snapshotIds = $closingSnapshots->pluck('sale_id');
            $closingSales = $currentSales
                ->filter(fn (Sale $sale): bool => $snapshotIds->contains($sale->id)
                    || ($sale->isActive() && (string) $sale->sale_date === $businessDate))
                ->values();

            return [$closing->id => $this->comparison(
                $closing,
                $closingSnapshots,
                $closingSales,
                $summaries[$businessDate]
            )];
        })->all();
    }

    private function comparison(DailyPaymentClosing $closing, $snapshots, $currentSales, array $currentSummary): array
    {
        $businessDate = (string) $closing->business_date;
        $snapshotBySaleId = $snapshots->keyBy('sale_id');
        $currentBySaleId = $currentSales->keyBy('id');
        $added = $currentSales->filter(fn (Sale $sale): bool => $sale->isActive() && (string) $sale->sale_date === $businessDate && ! $snapshotBySaleId->has($sale->id))->map(fn (Sale $sale): array => $this->currentOnly($sale))->values()->all();
        $removed = $snapshots->filter(function (DailyPaymentClosingSale $snapshot) use ($currentBySaleId, $businessDate): bool {
            $sale = $currentBySaleId->get($snapshot->sale_id);

            return ! $sale instanceof Sale || ! $sale->isActive() || (string) $sale->sale_date !== $businessDate;
        })->map(fn (DailyPaymentClosingSale $snapshot): array => $this->removed($snapshot, $currentBySaleId->get($snapshot->sale_id), $businessDate))->values()->all();
        $changed = $snapshots->map(function (DailyPaymentClosingSale $snapshot) use ($currentBySaleId, $businessDate): ?array {
            $sale = $currentBySaleId->get($snapshot->sale_id);
            if (! $sale instanceof Sale || ! $sale->isActive() || (string) $sale->sale_date !== $businessDate) {
                return null;
            }
            $fields = $this->changedFields($snapshot, $sale);

            return $fields === [] ? null : $this->changed($snapshot, $sale, $fields);
        })->filter()->values()->all();
        $snapshotSummary = $this->snapshotSummary($closing);
        $summaryDifferences = $this->summaryDifferences($snapshotSummary, $currentSummary);

        return [
            'is_finalized' => true,
            'has_drift' => $added !== [] || $removed !== [] || $changed !== [] || $summaryDifferences !== [],
            'summary_has_drift' => $summaryDifferences !== [],
            'added_count' => count($added), 'removed_count' => count($removed), 'changed_count' => count($changed),
            'current_summary' => $this->compactSummary($currentSummary), 'snapshot_summary' => $snapshotSummary,
            'summary_differences' => $summaryDifferences, 'added_sales' => $added, 'removed_sales' => $removed, 'changed_sales' => $changed,
        ];
    }

    private function changedFields(DailyPaymentClosingSale $snapshot, Sale $sale): array
    {
        $changed = [];

        if ((int) $snapshot->sale_revision !== (int) $sale->revision) {
            $changed[] = 'revision';
        }
        if ((string) $snapshot->sale_status !== (string) $sale->status) {
            $changed[] = 'status';
        }
        if ((string) $snapshot->payment_method !== (string) $sale->payment_method) {
            $changed[] = 'payment_method';
        }
        foreach (self::MONEY_FIELDS as $snapshotField => $saleField) {
            if ($this->money($snapshot->{$snapshotField}) !== $this->money($sale->{$saleField})) {
                $changed[] = $snapshotField;
            }
        }

        return $changed;
    }

    private function snapshotSummary(DailyPaymentClosing $closing): array
    {
        return [
            'cash_total' => $this->money($closing->expected_cash_amount),
            'promptpay_total' => $this->money($closing->expected_promptpay_amount),
            'recorded_total' => $this->money($closing->expected_recorded_sales_amount),
            'received_total' => $this->money($closing->expected_received_cash_amount),
            'change_total' => $this->money($closing->expected_change_amount),
            'cash_count' => (int) $closing->cash_sales_count,
            'promptpay_count' => (int) $closing->promptpay_sales_count,
            'mixed_count' => (int) $closing->mixed_sales_count,
            'unrecorded_count' => (int) $closing->unrecorded_payment_count,
        ];
    }

    private function compactSummary(array $summary): array
    {
        return collect($summary)->only([
            'cash_total', 'promptpay_total', 'recorded_total', 'received_total', 'change_total',
            'cash_count', 'promptpay_count', 'mixed_count', 'unrecorded_count',
        ])->all();
    }

    private function summaryDifferences(array $snapshot, array $current): array
    {
        $differences = [];

        foreach ($snapshot as $field => $snapshotValue) {
            $currentValue = $current[$field];
            $different = str_ends_with($field, '_total')
                ? $this->money($snapshotValue) !== $this->money($currentValue)
                : (int) $snapshotValue !== (int) $currentValue;

            if ($different) {
                $differences[$field] = [
                    'snapshot' => str_ends_with($field, '_total') ? $this->money($snapshotValue) : (int) $snapshotValue,
                    'current' => str_ends_with($field, '_total') ? $this->money($currentValue) : (int) $currentValue,
                ];
            }
        }

        return $differences;
    }

    private function currentOnly(Sale $sale): array
    {
        return [
            'sale_id' => $sale->id,
            'sale_no' => $sale->sale_no,
            'current_status' => $sale->status,
            'current_business_date' => (string) $sale->sale_date,
            'current' => $this->currentValues($sale),
        ];
    }

    private function removed(DailyPaymentClosingSale $snapshot, ?Sale $sale, string $businessDate): array
    {
        return [
            'sale_id' => $snapshot->sale_id,
            'sale_no' => $sale?->sale_no ?? (string) $snapshot->sale_id,
            'snapshot_status' => $snapshot->sale_status,
            'current_status' => $sale?->status,
            'snapshot_business_date' => $businessDate,
            'current_business_date' => $sale ? (string) $sale->sale_date : null,
            'snapshot' => $this->snapshotValues($snapshot),
            'current' => $sale ? $this->currentValues($sale) : null,
        ];
    }

    private function changed(DailyPaymentClosingSale $snapshot, Sale $sale, array $fields): array
    {
        return [
            'sale_id' => $sale->id,
            'sale_no' => $sale->sale_no,
            'snapshot_status' => $snapshot->sale_status,
            'current_status' => $sale->status,
            'business_date' => (string) $sale->sale_date,
            'changed_fields' => $fields,
            'snapshot' => $this->snapshotValues($snapshot),
            'current' => $this->currentValues($sale),
        ];
    }

    private function snapshotValues(DailyPaymentClosingSale $snapshot): array
    {
        return [
            'revision' => (int) $snapshot->sale_revision,
            'status' => $snapshot->sale_status,
            'sale_total_amount' => $this->money($snapshot->sale_total_amount),
            'payment_method' => $snapshot->payment_method,
            'cash_amount' => $this->money($snapshot->cash_amount),
            'promptpay_amount' => $this->money($snapshot->promptpay_amount),
            'received_amount' => $this->money($snapshot->received_amount),
            'change_amount' => $this->money($snapshot->change_amount),
        ];
    }

    private function currentValues(Sale $sale): array
    {
        return [
            'revision' => (int) $sale->revision,
            'status' => $sale->status,
            'sale_total_amount' => $this->money($sale->total_amount),
            'payment_method' => $sale->payment_method,
            'cash_amount' => $this->money($sale->cash_amount),
            'promptpay_amount' => $this->money($sale->promptpay_amount),
            'received_amount' => $this->money($sale->received_amount),
            'change_amount' => $this->money($sale->change_amount),
        ];
    }

    private function money(mixed $value): string
    {
        return $this->decimalService->money($value);
    }

    private function openResult(): array
    {
        return [
            'is_finalized' => false,
            'has_drift' => false,
            'summary_has_drift' => false,
            'added_count' => 0,
            'removed_count' => 0,
            'changed_count' => 0,
            'current_summary' => [],
            'snapshot_summary' => [],
            'summary_differences' => [],
            'added_sales' => [],
            'removed_sales' => [],
            'changed_sales' => [],
        ];
    }
}
