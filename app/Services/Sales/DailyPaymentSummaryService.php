<?php

namespace App\Services\Sales;

use App\Models\Sale;
use DomainException;

class DailyPaymentSummaryService
{
    public function __construct(
        private readonly SaleDecimalService $decimalService,
        private readonly SalePaymentResolver $paymentResolver
    ) {}

    public function forBusinessDate(string $date): array
    {
        return $this->forBusinessDates([$date])[$date];
    }

    public function forBusinessDates(array $dates): array
    {
        $dates = array_values(array_unique(array_map('strval', $dates)));
        $summaries = [];

        foreach ($dates as $date) {
            $summaries[$date] = $this->emptySummary();
        }

        if ($dates === []) {
            return $summaries;
        }

        Sale::query()
            ->active()
            ->whereIn('sale_date', $dates)
            ->orderBy('id')
            ->each(function (Sale $sale) use (&$summaries): void {
                $date = (string) $sale->sale_date;
                $summary = &$summaries[$date];

                try {
                    $payment = $this->resolveStoredPayment($sale);
                } catch (DomainException $exception) {
                    $summary['exceptions']->push([
                        'sale' => $sale,
                        'reason' => $exception->getMessage(),
                    ]);

                    return;
                }

                $summary['cash_total'] = $this->decimalService->addMoney($summary['cash_total'], $payment['cash_amount']);
                $summary['promptpay_total'] = $this->decimalService->addMoney($summary['promptpay_total'], $payment['promptpay_amount']);
                $summary['recorded_total'] = $this->decimalService->addMoney(
                    $summary['recorded_total'],
                    $this->decimalService->addMoney($payment['cash_amount'], $payment['promptpay_amount'])
                );
                $summary['received_total'] = $this->decimalService->addMoney($summary['received_total'], $payment['received_amount']);
                $summary['change_total'] = $this->decimalService->addMoney($summary['change_total'], $payment['change_amount']);
                $summary[$payment['payment_method'].'_count']++;
                $summary['valid_sales']->push($sale);
            });

        foreach ($summaries as &$summary) {
            $summary['unrecorded_count'] = $summary['exceptions']->count();
        }
        unset($summary);

        return $summaries;
    }

    private function emptySummary(): array
    {
        return [
            'cash_total' => '0.00',
            'promptpay_total' => '0.00',
            'recorded_total' => '0.00',
            'received_total' => '0.00',
            'change_total' => '0.00',
            'cash_count' => 0,
            'promptpay_count' => 0,
            'mixed_count' => 0,
            'unrecorded_count' => 0,
            'valid_sales' => collect(),
            'exceptions' => collect(),
        ];
    }

    private function resolveStoredPayment(Sale $sale): array
    {
        $payment = $this->paymentResolver->resolve(
            (string) $sale->total_amount,
            $sale->payment_method,
            $sale->cash_amount,
            $sale->promptpay_amount,
            $sale->received_amount
        );

        if ($sale->change_amount !== $payment['change_amount']) {
            throw new DomainException('ข้อมูลเงินทอนไม่สอดคล้องกับวิธีชำระเงิน');
        }

        return $payment;
    }
}
