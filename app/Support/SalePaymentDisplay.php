<?php

namespace App\Support;

use App\Models\Sale;

final class SalePaymentDisplay
{
    public static function label(?string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'cash' => 'เงินสด',
            'promptpay' => 'พร้อมเพย์',
            'mixed' => 'เงินสด + พร้อมเพย์',
            default => 'ไม่ระบุ',
        };
    }

    /**
     * @return array<int, array{label: string, value: string}>|null
     */
    public static function screenRows(Sale $sale): ?array
    {
        return match ($sale->payment_method) {
            'cash' => self::rows($sale, true, false, true, true),
            'promptpay' => self::rows($sale, false, true, false, false),
            'mixed' => self::rows($sale, true, true, true, true),
            default => null,
        };
    }

    /**
     * @return array<int, array{label: string, value: string}>|null
     */
    public static function documentRows(Sale $sale): ?array
    {
        return match ($sale->payment_method) {
            'cash' => self::rows($sale, false, false, true, true),
            'promptpay' => self::rows($sale, false, true, false, false),
            'mixed' => self::rows($sale, true, true, true, true),
            default => null,
        };
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private static function rows(
        Sale $sale,
        bool $includeCash,
        bool $includePromptPay,
        bool $includeReceived,
        bool $includeChange
    ): array {
        return array_values(array_filter([
            $includeCash ? ['label' => 'ยอดชำระเงินสด', 'value' => $sale->cash_amount] : null,
            $includePromptPay ? ['label' => 'ยอดชำระพร้อมเพย์', 'value' => $sale->promptpay_amount] : null,
            $includeReceived ? ['label' => 'รับเงินสด', 'value' => $sale->received_amount] : null,
            $includeChange ? ['label' => 'เงินทอน', 'value' => $sale->change_amount] : null,
        ]));
    }
}
