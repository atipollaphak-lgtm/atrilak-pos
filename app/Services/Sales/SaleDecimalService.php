<?php

namespace App\Services\Sales;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class SaleDecimalService
{
    private const MONEY_SCALE = 2;

    public function money(mixed $value): string
    {
        return (string) $this->decimal($value)
            ->toScale(self::MONEY_SCALE, RoundingMode::HALF_UP);
    }

    public function lineTotal(mixed $qty, mixed $sellingPrice): string
    {
        return $this->money(
            $this->decimal($qty)->multipliedBy($this->decimal($sellingPrice))
        );
    }

    public function lineProfit(mixed $qty, mixed $sellingPrice, mixed $costPrice): string
    {
        return $this->money(
            $this->decimal($sellingPrice)
                ->minus($this->decimal($costPrice))
                ->multipliedBy($this->decimal($qty))
        );
    }

    public function itemsTotal(array $items): string
    {
        $total = BigDecimal::zero()->toScale(self::MONEY_SCALE);

        foreach ($items as $item) {
            $total = $total->plus($this->lineTotal(
                $item['qty'],
                $item['selling_price']
            ));
        }

        return (string) $total;
    }

    public function netTotal(mixed $subtotal, mixed $deliveryFee, mixed $discount): string
    {
        return $this->money(
            $this->decimal($this->money($subtotal))
                ->plus($this->money($deliveryFee))
                ->minus($this->money($discount))
        );
    }

    public function addMoney(mixed $left, mixed $right): string
    {
        return $this->money(
            $this->decimal($this->money($left))->plus($this->money($right))
        );
    }

    public function nonNegativeDifference(mixed $expected, mixed $actual): string
    {
        $difference = $this->decimal($this->money($expected))
            ->minus($this->money($actual));

        if ($difference->isLessThan(BigDecimal::zero())) {
            return '0.00';
        }

        return $this->money($difference);
    }

    public function percentCommission(mixed $lineTotal, mixed $ruleValue): string
    {
        return $this->money(
            $this->decimal($lineTotal)
                ->multipliedBy($this->decimal($ruleValue))
                ->dividedBy(100, 6, RoundingMode::HALF_UP)
        );
    }

    public function amountCommission(mixed $saleQty, mixed $ruleValue): string
    {
        return $this->money(
            $this->decimal($saleQty)->multipliedBy($this->decimal($ruleValue))
        );
    }

    public function isPositive(mixed $value): bool
    {
        return $this->decimal($value)->isGreaterThan(BigDecimal::zero());
    }

    private function decimal(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) ($value ?? 0));
    }
}
