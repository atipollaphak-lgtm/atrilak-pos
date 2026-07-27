<?php

namespace App\Services\Purchases;

use App\Models\Purchase;
use App\Models\Supplier;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Collection;

class PurchaseValidationService
{
    public function normalizeItems(array $items): array
    {
        if ($items === []) {
            throw new DomainException('กรุณาเลือกอย่างน้อยหนึ่งรายการสินค้า');
        }

        $normalized = [];
        $productIds = [];

        foreach ($items as $item) {
            $productId = $this->positiveInteger($item['product_id'] ?? null);
            $productIds[] = $productId;
            $normalized[] = [
                'product_id' => $productId,
                'qty' => $this->positiveDecimal($item['qty'] ?? null, 4, 15, 'จำนวนสินค้าไม่ถูกต้อง'),
                'cost_price' => $this->positiveDecimal($item['cost_price'] ?? null, 2, 10, 'ต้นทุนสินค้าไม่ถูกต้อง'),
            ];
        }

        if (count($productIds) !== count(array_unique($productIds))) {
            throw new DomainException('สินค้าในรายการซื้อเข้าต้องไม่ซ้ำกัน');
        }

        return $normalized;
    }

    public function assertCreateReferences(
        mixed $supplierId,
        Collection $lockedProducts
    ): int {
        $supplierId = $this->positiveInteger($supplierId, 'ข้อมูลผู้จำหน่ายไม่ถูกต้อง');
        $supplier = Supplier::query()->find($supplierId);

        if ($supplier === null || ! $supplier->active) {
            throw new DomainException('ไม่พบผู้จำหน่ายที่เปิดใช้งาน');
        }

        if ($lockedProducts->contains(fn ($product): bool => ! $product->active)) {
            throw new DomainException('รายการซื้อเข้ามีสินค้าที่ถูกปิดใช้งาน');
        }

        return $supplierId;
    }

    public function assertUpdateReferences(
        Purchase $purchase,
        mixed $supplierId,
        array $items,
        Collection $lockedProducts
    ): int {
        $supplierId = $this->positiveInteger($supplierId, 'ข้อมูลผู้จำหน่ายไม่ถูกต้อง');
        $supplier = Supplier::query()->find($supplierId);

        if ($supplier === null) {
            throw new DomainException('ไม่พบผู้จำหน่าย');
        }

        if (! $supplier->active && $supplier->id !== (int) $purchase->supplier_id) {
            throw new DomainException('ผู้จำหน่ายนี้ถูกปิดใช้งาน');
        }

        $originalProductIds = $purchase->items->pluck('product_id')
            ->map(fn ($productId): int => (int) $productId);

        foreach ($items as $item) {
            $product = $lockedProducts->get($item['product_id']);

            if ($product === null) {
                throw new DomainException('ไม่พบสินค้า');
            }

            if (! $product->active && ! $originalProductIds->contains($product->id)) {
                throw new DomainException('สินค้าที่ถูกปิดใช้งานสามารถใช้ได้เฉพาะรายการเดิมของเอกสารนี้');
            }
        }

        return $supplierId;
    }

    public function purchaseDate(mixed $value): string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            throw new DomainException('วันที่ซื้อเข้าไม่ถูกต้อง');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new DomainException('วันที่ซื้อเข้าไม่ถูกต้อง');
        }

        return $value;
    }

    private function positiveInteger(mixed $value, string $message = 'ข้อมูลสินค้าไม่ถูกต้อง'): int
    {
        $valid = (is_int($value) && $value > 0)
            || (is_string($value) && preg_match('/^[1-9]\d*$/D', $value) === 1);

        if (! $valid) {
            throw new DomainException($message);
        }

        return (int) $value;
    }

    private function positiveDecimal(
        mixed $value,
        int $scale,
        int $integerDigits,
        string $message
    ): string {
        $decimal = is_int($value) || is_float($value) || is_string($value)
            ? (string) $value
            : '';

        if (! preg_match('/^\d{1,'.$integerDigits.'}(?:\.\d{1,'.$scale.'})?$/D', $decimal)) {
            throw new DomainException($message);
        }

        try {
            $number = BigDecimal::of($decimal)->toScale($scale, RoundingMode::UNNECESSARY);
        } catch (MathException) {
            throw new DomainException($message);
        }

        if ($number->isLessThanOrEqualTo(0)) {
            throw new DomainException($message);
        }

        return (string) $number;
    }
}
