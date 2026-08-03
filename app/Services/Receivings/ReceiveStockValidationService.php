<?php

namespace App\Services\Receivings;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Supplier;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Collection;

class ReceiveStockValidationService
{
    public const SOURCE_SUPPLIER = 'supplier';

    public const SOURCE_PRODUCTION = 'production';

    /**
     * @return array{source:string,supplier_id:?int,purchase_date:string,supplier_document_number:?string,remark:?string,items:array<int,array<string,mixed>>}
     */
    public function normalize(array $data): array
    {
        $source = $data['source'] ?? null;

        if (! in_array($source, [self::SOURCE_SUPPLIER, self::SOURCE_PRODUCTION], true)) {
            throw new DomainException('แหล่งรับสินค้าไม่ถูกต้อง');
        }

        $supplierId = $this->positiveInteger($data['supplier_id'] ?? null, allowNull: true);

        if ($source === self::SOURCE_SUPPLIER && $supplierId === null) {
            throw new DomainException('กรุณาเลือกผู้จำหน่าย');
        }

        if ($source === self::SOURCE_PRODUCTION && $supplierId !== null) {
            throw new DomainException('การผลิตเองไม่ต้องระบุผู้จำหน่าย');
        }

        $purchaseDate = $data['purchase_date'] ?? null;
        if (! is_string($purchaseDate) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $purchaseDate) !== 1) {
            throw new DomainException('วันที่รับสินค้าไม่ถูกต้อง');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $purchaseDate);
        if ($date === false || $date->format('Y-m-d') !== $purchaseDate) {
            throw new DomainException('วันที่รับสินค้าไม่ถูกต้อง');
        }

        $rawItems = $data['items'] ?? [];
        if (! is_array($rawItems) || $rawItems === []) {
            throw new DomainException('กรุณาเพิ่มรายการสินค้าอย่างน้อยหนึ่งรายการ');
        }

        $items = [];
        $productIds = [];

        foreach ($rawItems as $item) {
            if (! is_array($item)) {
                throw new DomainException('รูปแบบรายการสินค้าไม่ถูกต้อง');
            }

            $productId = $this->positiveInteger($item['product_id'] ?? null);
            if (in_array($productId, $productIds, true)) {
                throw new DomainException('รายการสินค้าซ้ำกันไม่ได้');
            }

            $productIds[] = $productId;
            $items[] = [
                'product_id' => $productId,
                'product_unit_id' => $this->positiveInteger($item['product_unit_id'] ?? null, allowNull: true),
                'qty' => $this->positiveDecimal($item['qty'] ?? null, 4, 'จำนวนสินค้าไม่ถูกต้อง'),
                'cost_price' => $this->positiveDecimal($item['cost_price'] ?? null, 2, 'ต้นทุนสินค้าไม่ถูกต้อง'),
            ];
        }

        return [
            'source' => $source,
            'supplier_id' => $supplierId,
            'purchase_date' => $purchaseDate,
            'supplier_document_number' => $this->nullableText($data['supplier_document_number'] ?? null, 100),
            'remark' => $this->nullableText($data['remark'] ?? null, 5000),
            'items' => $items,
        ];
    }

    public function assertSourceReferences(array $data): void
    {
        if ($data['source'] === self::SOURCE_SUPPLIER) {
            $supplier = Supplier::query()->find($data['supplier_id']);
            if (! $supplier || ! $supplier->active) {
                throw new DomainException('ไม่พบผู้จำหน่ายที่เปิดใช้งาน');
            }
        }
    }

    /**
     * @param  Collection<int, Product>  $lockedProducts
     * @return array<int, array<string, mixed>>
     */
    public function resolveItems(array $items, Collection $lockedProducts): array
    {
        $resolved = [];

        foreach ($items as $item) {
            $product = $lockedProducts->get((int) $item['product_id']);
            if (! $product || ! $product->active) {
                throw new DomainException('ไม่พบสินค้าที่เปิดใช้งาน');
            }

            $unit = $this->resolveUnit($product, $item['product_unit_id']);
            $conversionRate = BigDecimal::of($unit?->conversion_rate ?: '1')
                ->toScale(4, RoundingMode::UNNECESSARY);
            if ($conversionRate->isLessThanOrEqualTo(0)) {
                throw new DomainException('อัตราแปลงหน่วยไม่ถูกต้อง');
            }

            $qty = BigDecimal::of($item['qty'])->toScale(4, RoundingMode::UNNECESSARY);
            $costPrice = BigDecimal::of($item['cost_price'])->toScale(2, RoundingMode::UNNECESSARY);
            $baseQty = $qty->multipliedBy($conversionRate)->toScale(4, RoundingMode::UNNECESSARY);
            $baseCostPrice = $costPrice->dividedBy($conversionRate, 8, RoundingMode::HALF_UP)
                ->toScale(2, RoundingMode::HALF_UP);

            $resolved[] = $item + [
                'unit' => $unit,
                'conversion_rate' => (string) $conversionRate,
                'base_qty' => (string) $baseQty,
                'base_cost_price' => (string) $baseCostPrice,
            ];
        }

        return $resolved;
    }

    private function resolveUnit(Product $product, ?int $unitId): ?ProductUnit
    {
        $units = $product->productUnits()
            ->with('unit')
            ->where('active', true)
            ->where('is_purchase_unit', true)
            ->orderByDesc('is_base_unit')
            ->orderBy('id')
            ->get();

        if ($units->isEmpty()) {
            if ($unitId !== null) {
                throw new DomainException('ไม่พบหน่วยรับสินค้าของสินค้า');
            }

            return null;
        }

        $unit = $unitId === null
            ? $units->first()
            : $units->firstWhere('id', $unitId);

        if (! $unit) {
            throw new DomainException('หน่วยรับสินค้าไม่ถูกต้อง');
        }

        if (! $unit->is_base_unit && $unit->conversion_confirmed_at === null) {
            throw new DomainException('หน่วยสินค้ายังไม่ได้ยืนยันอัตราแปลงหน่วย');
        }

        return $unit;
    }

    private function positiveInteger(mixed $value, bool $allowNull = false): ?int
    {
        if ($allowNull && ($value === null || $value === '')) {
            return null;
        }

        $valid = (is_int($value) && $value > 0)
            || (is_string($value) && preg_match('/^[1-9]\d*$/D', $value) === 1);

        if (! $valid) {
            throw new DomainException('รหัสข้อมูลไม่ถูกต้อง');
        }

        return (int) $value;
    }

    private function positiveDecimal(mixed $value, int $scale, string $message): string
    {
        $value = is_int($value) || is_float($value) || is_string($value) ? (string) $value : '';
        if (! preg_match('/^\d{1,15}(?:\.\d{1,'.$scale.'})?$/D', $value)) {
            throw new DomainException($message);
        }

        try {
            $number = BigDecimal::of($value)->toScale($scale, RoundingMode::UNNECESSARY);
        } catch (MathException) {
            throw new DomainException($message);
        }

        if ($number->isLessThanOrEqualTo(0)) {
            throw new DomainException($message);
        }

        return (string) $number;
    }

    private function nullableText(mixed $value, int $maxLength): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || mb_strlen($value) > $maxLength) {
            throw new DomainException('ข้อความยาวเกินกำหนด');
        }

        return trim($value) ?: null;
    }
}
