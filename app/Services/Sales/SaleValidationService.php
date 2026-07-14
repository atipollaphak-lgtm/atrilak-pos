<?php

namespace App\Services\Sales;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use DomainException;
use Illuminate\Support\Facades\DB;

class SaleValidationService
{
    public function assertValidItems(array $items): void
    {
        if ($items === []) {
            throw new DomainException('กรุณาเลือกอย่างน้อยหนึ่งรายการสินค้า');
        }

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;

            if (! $this->isPositiveInteger($productId)) {
                throw new DomainException('ข้อมูลสินค้าไม่ถูกต้อง');
            }

            $this->positiveDecimal(
                $item['qty'] ?? null,
                2,
                13,
                'จำนวนสินค้าไม่ถูกต้อง'
            );
            $this->positiveDecimal(
                $item['selling_price'] ?? null,
                2,
                13,
                'ราคาขายไม่ถูกต้อง'
            );
        }
    }

    public function assertDeliveryAddressBelongsToCustomer(
        mixed $addressId,
        mixed $customerId
    ): void {
        if ($addressId === null || $addressId === '' || $customerId === null || $customerId === '') {
            return;
        }

        $belongsToCustomer = DB::table('customer_delivery_addresses')
            ->where('id', $addressId)
            ->where('customer_id', $customerId)
            ->exists();

        if (! $belongsToCustomer) {
            throw new DomainException('ที่อยู่จัดส่งไม่ตรงกับลูกค้าที่เลือก');
        }
    }

    public function calculateItemsTotal(array $items): string
    {
        $total = BigDecimal::zero();

        foreach ($items as $item) {
            $qty = BigDecimal::of((string) $item['qty']);
            $price = BigDecimal::of((string) $item['selling_price']);
            $total = $total->plus($qty->multipliedBy($price));
        }

        return (string) $total;
    }

    private function positiveDecimal(
        mixed $value,
        int $scale,
        int $integerDigits,
        string $message
    ): BigDecimal {
        $decimal = is_int($value) || is_float($value) || is_string($value)
            ? (string) $value
            : '';

        if (! preg_match('/^\d{1,'.$integerDigits.'}(?:\.\d{1,'.$scale.'})?$/D', $decimal)) {
            throw new DomainException($message);
        }

        try {
            $number = BigDecimal::of($decimal);
        } catch (MathException) {
            throw new DomainException($message);
        }

        if ($number->isLessThanOrEqualTo(0)) {
            throw new DomainException($message);
        }

        return $number;
    }

    private function isPositiveInteger(mixed $value): bool
    {
        if (is_int($value)) {
            return $value > 0;
        }

        return is_string($value)
            && preg_match('/^[1-9]\d*$/D', $value) === 1;
    }
}
