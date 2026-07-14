<?php

namespace App\Services\Sales;

use App\Models\ProductUnit;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use DomainException;

class ProductUnitConversionService
{
    public const BASE_SCALE = 4;

    public function resolveItems(array $items): array
    {
        $unitIds = collect($items)
            ->pluck('product_unit_id')
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();

        $units = $unitIds->isEmpty()
            ? collect()
            : ProductUnit::query()
                ->whereIn('id', $unitIds->all())
                ->orderBy('id')
                ->get()
                ->keyBy('id');

        return collect($items)->map(function (array $item) use ($units): array {
            $productId = (int) ($item['product_id'] ?? 0);
            $productUnitId = $item['product_unit_id'] ?? null;

            if ($productId <= 0) {
                throw new DomainException('ไม่พบสินค้า');
            }

            $qty = $this->saleQuantity($item['qty'] ?? null);
            $rate = '1.0000';

            if ($productUnitId !== null && $productUnitId !== '') {
                $unit = $units->get((int) $productUnitId);

                if (! $unit) {
                    throw new DomainException('ไม่พบหน่วยขาย');
                }

                $this->assertUnitCanBeSold($unit, $productId);
                $rate = $unit->conversion_rate;
            }

            return array_merge($item, [
                'qty' => (string) $qty,
                'product_unit_id' => $productUnitId === null || $productUnitId === ''
                    ? null
                    : (int) $productUnitId,
                'conversion_rate_used' => $this->decimal($rate, 4),
                'base_qty' => $this->calculateBaseQuantity($qty, $rate),
            ]);
        })->all();
    }

    public function calculateBaseQuantity(mixed $saleQty, mixed $conversionRate): string
    {
        try {
            $qty = $this->saleQuantity($saleQty);
            $rate = BigDecimal::of((string) $conversionRate);
        } catch (MathException) {
            throw new DomainException('จำนวนขายหรืออัตราแปลงสต๊อกไม่ถูกต้อง');
        }

        if ($rate->isLessThanOrEqualTo(BigDecimal::zero())) {
            throw new DomainException('อัตราแปลงสต๊อกต้องมากกว่า 0');
        }

        $baseQty = $qty
            ->multipliedBy($rate)
            ->toScale(self::BASE_SCALE, RoundingMode::HALF_UP);

        if ($baseQty->isLessThanOrEqualTo(BigDecimal::zero())) {
            throw new DomainException('จำนวนหน่วยฐานที่คำนวณได้ต้องมากกว่า 0');
        }

        return (string) $baseQty;
    }

    private function saleQuantity(mixed $value): BigDecimal
    {
        try {
            $qty = BigDecimal::of((string) $value)->toScale(2, RoundingMode::HALF_UP);
        } catch (MathException) {
            throw new DomainException('จำนวนขายไม่ถูกต้อง');
        }

        if ($qty->isLessThanOrEqualTo(BigDecimal::zero())) {
            throw new DomainException('จำนวนขายต้องมากกว่า 0');
        }

        return $qty;
    }

    private function assertUnitCanBeSold(ProductUnit $unit, int $productId): void
    {
        if ((int) $unit->product_id !== $productId) {
            throw new DomainException('หน่วยขายไม่ตรงกับสินค้า');
        }

        if (! $unit->active) {
            throw new DomainException('หน่วยขายนี้ไม่ได้เปิดใช้งาน');
        }

        if (! $unit->is_sale_unit) {
            throw new DomainException('หน่วยนี้ไม่ได้กำหนดให้ใช้สำหรับขาย');
        }

        try {
            $rate = BigDecimal::of((string) $unit->conversion_rate);
        } catch (MathException) {
            throw new DomainException('อัตราแปลงสต๊อกไม่ถูกต้อง');
        }

        if ($rate->isLessThanOrEqualTo(BigDecimal::zero())) {
            throw new DomainException('อัตราแปลงสต๊อกต้องมากกว่า 0');
        }

        if ($unit->is_base_unit) {
            if (! $rate->isEqualTo(BigDecimal::one())) {
                throw new DomainException('หน่วยฐานต้องมีอัตราแปลงสต๊อกเท่ากับ 1');
            }

            return;
        }

        if ($unit->conversion_confirmed_at === null) {
            throw new DomainException('หน่วยขายนี้ยังไม่ได้ยืนยันอัตราแปลงสต๊อก');
        }
    }

    private function decimal(mixed $value, int $scale): string
    {
        try {
            return (string) BigDecimal::of((string) $value)
                ->toScale($scale, RoundingMode::UNNECESSARY);
        } catch (MathException) {
            throw new DomainException('อัตราแปลงสต๊อกไม่ถูกต้อง');
        }
    }
}
