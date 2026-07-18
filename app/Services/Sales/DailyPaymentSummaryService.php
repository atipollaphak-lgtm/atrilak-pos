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
        $totals = [
            'cash_total' => '0.00',
            'promptpay_total' => '0.00',
            'recorded_total' => '0.00',
            'received_total' => '0.00',
            'change_total' => '0.00',
            'cash_count' => 0,
            'promptpay_count' => 0,
            'mixed_count' => 0,
        ];
        $validSales = collect();
        $exceptions = collect();

        Sale::query()
            ->active()
            ->whereDate('sale_date', $date)
            ->orderBy('id')
            ->each(function (Sale $sale) use (&$totals, $validSales, $exceptions): void {
                try {
                    $payment = $this->resolveStoredPayment($sale);
                } catch (DomainException $exception) {
                    $exceptions->push([
                        'sale' => $sale,
                        'reason' => $exception->getMessage(),
                    ]);

                    return;
                }

                $totals['cash_total'] = $this->decimalService->addMoney(
                    $totals['cash_total'],
                    $payment['cash_amount']
                );
                $totals['promptpay_total'] = $this->decimalService->addMoney(
                    $totals['promptpay_total'],
                    $payment['promptpay_amount']
                );
                $totals['recorded_total'] = $this->decimalService->addMoney(
                    $totals['recorded_total'],
                    $this->decimalService->addMoney(
                        $payment['cash_amount'],
                        $payment['promptpay_amount']
                    )
                );
                $totals['received_total'] = $this->decimalService->addMoney(
                    $totals['received_total'],
                    $payment['received_amount']
                );
                $totals['change_total'] = $this->decimalService->addMoney(
                    $totals['change_total'],
                    $payment['change_amount']
                );
                $totals[$payment['payment_method'].'_count']++;
                $validSales->push($sale);
            });

        return $totals + [
            'unrecorded_count' => $exceptions->count(),
            'valid_sales' => $validSales,
            'exceptions' => $exceptions,
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
