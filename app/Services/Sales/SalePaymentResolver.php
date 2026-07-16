<?php

namespace App\Services\Sales;

use Brick\Math\BigDecimal;
use DomainException;

class SalePaymentResolver
{
    private const METHODS = ['cash', 'promptpay', 'mixed'];

    public function __construct(
        private readonly SaleDecimalService $decimalService
    ) {}

    public function resolve(
        mixed $netTotal,
        mixed $paymentMethod,
        mixed $cashAmount,
        mixed $promptpayAmount,
        mixed $receivedAmount
    ): array {
        if (! is_string($paymentMethod) || ! in_array($paymentMethod, self::METHODS, true)) {
            throw new DomainException('วิธีชำระเงินไม่ถูกต้อง');
        }

        $total = $this->normalizeMoney($netTotal, 'ยอดสุทธิ');
        $cash = $this->normalizeMoney($cashAmount, 'ยอดชำระเงินสด');
        $promptpay = $this->normalizeMoney($promptpayAmount, 'ยอดชำระพร้อมเพย์');
        $received = $this->normalizeMoney($receivedAmount, 'จำนวนเงินสดที่รับ');

        if (! $this->equals(
            $this->decimalService->addMoney($cash, $promptpay),
            $total
        )) {
            throw new DomainException('ยอดชำระเงินสดและพร้อมเพย์รวมกันต้องเท่ากับยอดสุทธิ');
        }

        if (BigDecimal::of($received)->isLessThan($cash)) {
            throw new DomainException('จำนวนเงินสดที่รับต้องไม่น้อยกว่ายอดชำระเงินสด');
        }

        match ($paymentMethod) {
            'cash' => $this->assertCash($total, $cash, $promptpay),
            'promptpay' => $this->assertPromptpay($total, $cash, $promptpay, $received),
            'mixed' => $this->assertMixed($cash, $promptpay),
        };

        return [
            'payment_method' => $paymentMethod,
            'cash_amount' => $cash,
            'promptpay_amount' => $promptpay,
            'received_amount' => $received,
            'change_amount' => $this->decimalService->subtractMoney($received, $cash),
        ];
    }

    private function assertCash(string $total, string $cash, string $promptpay): void
    {
        if (! $this->equals($cash, $total) || ! $this->isZero($promptpay)) {
            throw new DomainException('การชำระเงินสดต้องชำระยอดสุทธิทั้งหมดด้วยเงินสด');
        }
    }

    private function assertPromptpay(
        string $total,
        string $cash,
        string $promptpay,
        string $received
    ): void {
        if (! $this->isZero($cash)
            || ! $this->equals($promptpay, $total)
            || ! $this->isZero($received)) {
            throw new DomainException('การชำระพร้อมเพย์ต้องชำระยอดสุทธิทั้งหมดโดยไม่มีเงินสดหรือเงินทอน');
        }
    }

    private function assertMixed(string $cash, string $promptpay): void
    {
        if (! BigDecimal::of($cash)->isGreaterThan(0)
            || ! BigDecimal::of($promptpay)->isGreaterThan(0)) {
            throw new DomainException('การชำระแบบผสมต้องมียอดเงินสดและยอดพร้อมเพย์มากกว่า 0');
        }
    }

    private function normalizeMoney(mixed $value, string $label): string
    {
        if ($value === null || $value === '') {
            throw new DomainException($label.'ไม่ครบถ้วน');
        }

        if (is_int($value)) {
            $value = (string) $value;
        }

        if (! is_string($value) || preg_match('/^\d{1,13}(?:\.\d{1,2})?$/D', $value) !== 1) {
            throw new DomainException($label.'ไม่ถูกต้องหรือต้องมีทศนิยมไม่เกิน 2 ตำแหน่ง');
        }

        return $this->decimalService->money($value);
    }

    private function equals(string $left, string $right): bool
    {
        return BigDecimal::of($left)->isEqualTo($right);
    }

    private function isZero(string $value): bool
    {
        return BigDecimal::of($value)->isZero();
    }
}
