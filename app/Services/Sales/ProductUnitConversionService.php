<?php

namespace App\Services\Sales;

use App\Models\ProductUnit;
use App\Models\Unit;
use App\ValueObjects\Sales\ResolvedSaleLine;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use DomainException;
use Illuminate\Support\Collection;

class ProductUnitConversionService
{
    public const BASE_SCALE = 4;

    public function resolveItems(array $items): array
    {
        return collect($this->resolveLines($items, null, false))
            ->map(fn (ResolvedSaleLine $line): array => $line->toArray(false))
            ->all();
    }

    /**
     * @return list<ResolvedSaleLine>
     */
    public function resolveLines(
        array $items,
        ?Collection $products = null,
        bool $includeUnitSnapshots = true
    ): array {
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
                ->when($includeUnitSnapshots, fn ($query) => $query->with('unit'))
                ->whereIn('id', $unitIds->all())
                ->orderBy('id')
                ->get()
                ->keyBy('id');

        $legacyUnits = $includeUnitSnapshots
            ? $this->legacyUnits($items, $products)
            : collect();

        return collect(array_values($items))->map(function (
            array $item,
            int $index
        ) use ($units, $products, $legacyUnits, $includeUnitSnapshots): ResolvedSaleLine {
            $productId = (int) ($item['product_id'] ?? 0);
            $productUnitId = $item['product_unit_id'] ?? null;

            if ($productId <= 0) {
                throw new DomainException('ไม่พบสินค้า');
            }

            $qty = $this->saleQuantity($item['qty'] ?? null);
            $rate = '1.0000';
            $unitName = null;
            $unitCode = null;
            $source = ResolvedSaleLine::SOURCE_LEGACY_FACTOR_ONE;

            if ($productUnitId !== null && $productUnitId !== '') {
                $unit = $units->get((int) $productUnitId);

                if (! $unit) {
                    throw new DomainException('ไม่พบหน่วยขาย');
                }

                $this->assertUnitCanBeSold($unit, $productId);
                $rate = $unit->conversion_rate;
                $unitName = $includeUnitSnapshots ? $unit->unit?->name : null;
                $unitCode = $includeUnitSnapshots ? $unit->unit?->code : null;
                $source = ResolvedSaleLine::SOURCE_CURRENT_PRODUCT_UNIT;
            } elseif ($includeUnitSnapshots && $products !== null) {
                $product = $products->get($productId);
                $legacyUnit = $product?->unit_id === null
                    ? null
                    : $legacyUnits->get((int) $product->unit_id);
                $unitName = $legacyUnit?->name ?? $product?->unit;
                $unitCode = $legacyUnit?->code;
            }

            return new ResolvedSaleLine(
                originalIndex: $index,
                productId: $productId,
                productUnitId: $productUnitId === null || $productUnitId === ''
                    ? null
                    : (int) $productUnitId,
                saleQty: (string) $qty,
                sellingPrice: (string) ($item['selling_price'] ?? ''),
                conversionRateUsed: $this->decimal($rate, 4),
                baseQty: $this->calculateBaseQuantity($qty, $rate),
                unitNameSnapshot: $unitName,
                unitCodeSnapshot: $unitCode,
                resolutionSource: $source,
                sourceLine: $item,
            );
        })->all();
    }

    /**
     * Resolve backend-owned Quotation Item snapshots without consulting the
     * current Product Unit conversion rate.
     *
     * @return list<ResolvedSaleLine>
     */
    public function resolveStoredQuotationLines(
        Collection $quotationItems,
        Collection $products
    ): array {
        $unitIds = $quotationItems
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
        $legacyUnits = $this->legacyUnits(
            $quotationItems->map(fn ($item): array => [
                'product_id' => $item->product_id,
            ])->all(),
            $products
        );

        return $quotationItems->values()->map(function (
            $item,
            int $index
        ) use ($products, $units, $legacyUnits): ResolvedSaleLine {
            $itemNumber = $index + 1;
            $productId = (int) ($item->product_id ?? 0);
            $product = $products->get($productId);

            if ($productId <= 0 || $product === null) {
                throw new DomainException("ไม่พบสินค้าของรายการใบเสนอราคาที่ {$itemNumber}");
            }

            if (! $product->active) {
                throw new DomainException("สินค้าของรายการใบเสนอราคาที่ {$itemNumber} ถูกปิดใช้งาน");
            }

            $qty = $this->saleQuantity($item->qty);
            $productUnitId = $item->product_unit_id === null
                ? null
                : (int) $item->product_unit_id;
            $storedRate = $item->conversion_rate_used;
            $storedBaseQty = $item->base_qty;

            if ($storedBaseQty === null) {
                if ($productUnitId !== null) {
                    throw new DomainException("รายการใบเสนอราคาที่ {$itemNumber} ไม่มีจำนวนหน่วยฐานที่เชื่อถือได้");
                }

                if ($item->unit_name_snapshot !== null || $item->unit_code_snapshot !== null) {
                    throw new DomainException("รายการใบเสนอราคาที่ {$itemNumber} มีข้อมูลหน่วยขายแต่ไม่มีจำนวนหน่วยฐาน");
                }

                $rate = $storedRate === null
                    ? BigDecimal::one()->toScale(4)
                    : $this->storedPositiveDecimal(
                        $storedRate,
                        4,
                        "อัตราแปลงของรายการใบเสนอราคาที่ {$itemNumber} ไม่ถูกต้อง"
                    );

                if (! $rate->isEqualTo(BigDecimal::one())) {
                    throw new DomainException("รายการใบเสนอราคาที่ {$itemNumber} ไม่สามารถใช้ factor 1 ได้");
                }

                $legacyUnit = $product->unit_id === null
                    ? null
                    : $legacyUnits->get((int) $product->unit_id);

                return new ResolvedSaleLine(
                    originalIndex: $index,
                    productId: $productId,
                    productUnitId: null,
                    saleQty: (string) $qty,
                    sellingPrice: (string) $item->selling_price,
                    conversionRateUsed: '1.0000',
                    baseQty: $this->calculateBaseQuantity($qty, $rate),
                    unitNameSnapshot: $legacyUnit?->name ?? $product->unit,
                    unitCodeSnapshot: $legacyUnit?->code,
                    resolutionSource: ResolvedSaleLine::SOURCE_LEGACY_FACTOR_ONE,
                    sourceLine: ['quotation_item_id' => (int) $item->getKey()],
                );
            }

            $baseQty = $this->storedPositiveDecimal(
                $storedBaseQty,
                self::BASE_SCALE,
                "จำนวนหน่วยฐานของรายการใบเสนอราคาที่ {$itemNumber} ไม่ถูกต้อง"
            );

            if ($productUnitId !== null) {
                $unit = $units->get($productUnitId);

                if ($unit === null) {
                    throw new DomainException("ไม่พบหน่วยขายของรายการใบเสนอราคาที่ {$itemNumber}");
                }

                if ((int) $unit->product_id !== $productId) {
                    throw new DomainException("หน่วยขายของรายการใบเสนอราคาที่ {$itemNumber} ไม่ตรงกับสินค้า");
                }
            }

            if ($storedRate === null) {
                $rate = $baseQty->dividedBy(
                    $qty,
                    4,
                    RoundingMode::HALF_UP
                );
            } else {
                $rate = $this->storedPositiveDecimal(
                    $storedRate,
                    4,
                    "อัตราแปลงของรายการใบเสนอราคาที่ {$itemNumber} ไม่ถูกต้อง"
                );
            }

            $reproducedBaseQty = $this->calculateBaseQuantity($qty, $rate);

            if (! BigDecimal::of($reproducedBaseQty)->isEqualTo($baseQty)) {
                throw new DomainException("อัตราแปลงและจำนวนหน่วยฐานของรายการใบเสนอราคาที่ {$itemNumber} ไม่สอดคล้องกัน");
            }

            return new ResolvedSaleLine(
                originalIndex: $index,
                productId: $productId,
                productUnitId: $productUnitId,
                saleQty: (string) $qty,
                sellingPrice: (string) $item->selling_price,
                conversionRateUsed: (string) $rate,
                baseQty: (string) $baseQty,
                unitNameSnapshot: $item->unit_name_snapshot,
                unitCodeSnapshot: $item->unit_code_snapshot,
                resolutionSource: ResolvedSaleLine::SOURCE_STORED_SNAPSHOT,
                sourceLine: ['quotation_item_id' => (int) $item->getKey()],
            );
        })->all();
    }

    /**
     * @param  list<ResolvedSaleLine>  $lines
     * @return array<int, string>
     */
    public function aggregateBaseQuantityByProduct(array $lines): array
    {
        $required = [];

        foreach ($lines as $line) {
            $productId = $line->productId;
            $quantity = BigDecimal::of($line->baseQty);
            $required[$productId] = isset($required[$productId])
                ? $required[$productId]->plus($quantity)
                : $quantity;
        }

        ksort($required, SORT_NUMERIC);

        return collect($required)->map(
            fn (BigDecimal $quantity): string => (string) $quantity
                ->toScale(self::BASE_SCALE, RoundingMode::UNNECESSARY)
        )->all();
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

        if ($baseQty->isGreaterThanOrEqualTo(BigDecimal::of('1000000000000000'))) {
            throw new DomainException('จำนวนหน่วยฐานเกินขอบเขตที่ระบบรองรับ');
        }

        return (string) $baseQty;
    }

    private function saleQuantity(mixed $value): BigDecimal
    {
        try {
            $qty = BigDecimal::of((string) $value)->toScale(2, RoundingMode::UNNECESSARY);
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

    private function storedPositiveDecimal(
        mixed $value,
        int $scale,
        string $message
    ): BigDecimal {
        try {
            $decimal = BigDecimal::of((string) $value)
                ->toScale($scale, RoundingMode::UNNECESSARY);
        } catch (MathException) {
            throw new DomainException($message);
        }

        if ($decimal->isLessThanOrEqualTo(BigDecimal::zero())) {
            throw new DomainException($message);
        }

        return $decimal;
    }

    private function legacyUnits(array $items, ?Collection $products): Collection
    {
        if ($products === null) {
            return collect();
        }

        $unitIds = collect($items)
            ->filter(fn (array $item): bool => ($item['product_unit_id'] ?? null) === null
                || ($item['product_unit_id'] ?? null) === '')
            ->map(fn (array $item) => $products->get(
                (int) ($item['product_id'] ?? 0)
            )?->unit_id)
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();

        return $unitIds->isEmpty()
            ? collect()
            : Unit::query()
                ->whereIn('id', $unitIds->all())
                ->orderBy('id')
                ->get()
                ->keyBy('id');
    }
}
