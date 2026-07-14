<?php

namespace App\Http\Requests\Sales;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class StoreSaleV1Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'normalized_items' => $this->normalizeParallelItems(),
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_delivery_address_id' => ['nullable', 'integer', 'exists:customer_delivery_addresses,id'],
            'technician_id' => ['nullable', 'integer', 'exists:technicians,id'],
            'sale_date' => ['nullable', 'date_format:Y-m-d'],
            'delivery_type' => ['nullable', 'in:delivery,pickup'],
            'delivery_zone_id' => ['nullable', 'integer'],
            'discount' => ['nullable', $this->decimalRule(2, 10, false)],
            'delivery_fee' => ['nullable', $this->decimalRule(2, 10, false)],
            'base_qty' => ['prohibited'],
            'conversion_rate_used' => ['prohibited'],
            'product_id' => ['required', 'array'],
            'qty' => ['required', 'array'],
            'selling_price' => ['required', 'array'],
            'normalized_items' => ['required', 'array', 'min:1'],
            'normalized_items.*.product_id' => ['bail', 'required', 'integer', 'exists:products,id'],
            'normalized_items.*.qty' => ['bail', 'required', $this->decimalRule(2, 13, true)],
            'normalized_items.*.selling_price' => ['bail', 'required', $this->decimalRule(2, 13, true)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateCoreArrayLengths($validator);
            $this->validateDeliveryAddressOwnership($validator);
        });
    }

    public function normalizedItems(): array
    {
        return $this->input('normalized_items', []);
    }

    private function normalizeParallelItems(): array
    {
        $products = is_array($this->input('product_id')) ? $this->input('product_id') : [];
        $quantities = is_array($this->input('qty')) ? $this->input('qty') : [];
        $prices = is_array($this->input('selling_price')) ? $this->input('selling_price') : [];
        $rowCount = max(count($products), count($quantities), count($prices));
        $items = [];

        for ($index = 0; $index < $rowCount; $index++) {
            $productId = $products[$index] ?? null;
            $qty = $quantities[$index] ?? null;
            $price = $prices[$index] ?? null;

            if ($this->isBlank($productId) && $this->isBlank($qty) && $this->isBlank($price)) {
                continue;
            }

            $items[] = [
                'product_id' => $productId,
                'product_unit_id' => null,
                'qty' => $qty,
                'selling_price' => $price,
            ];
        }

        return $items;
    }

    private function validateCoreArrayLengths(Validator $validator): void
    {
        $arrays = [
            'product_id' => $this->input('product_id'),
            'qty' => $this->input('qty'),
            'selling_price' => $this->input('selling_price'),
        ];

        if (collect($arrays)->contains(fn ($value) => ! is_array($value))) {
            return;
        }

        if (count(array_unique(array_map('count', $arrays))) !== 1) {
            $validator->errors()->add('items', 'จำนวนช่องสินค้า จำนวน และราคาขายต้องตรงกัน');
        }
    }

    private function validateDeliveryAddressOwnership(Validator $validator): void
    {
        $customerId = $this->input('customer_id');
        $addressId = $this->input('customer_delivery_address_id');

        if (! $this->isPositiveInteger($customerId) || ! $this->isPositiveInteger($addressId)) {
            return;
        }

        $belongsToCustomer = DB::table('customer_delivery_addresses')
            ->where('id', $addressId)
            ->where('customer_id', $customerId)
            ->exists();

        if (! $belongsToCustomer) {
            $validator->errors()->add(
                'customer_delivery_address_id',
                'ที่อยู่จัดส่งไม่ตรงกับลูกค้าที่เลือก'
            );
        }
    }

    private function decimalRule(int $scale, int $integerDigits, bool $strictlyPositive): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($scale, $integerDigits, $strictlyPositive): void {
            if ($value === null || $value === '') {
                return;
            }

            $decimal = is_int($value) || is_float($value) || is_string($value)
                ? (string) $value
                : '';

            if (! preg_match('/^\d{1,'.$integerDigits.'}(?:\.\d{1,'.$scale.'})?$/D', $decimal)) {
                $fail('รูปแบบ :attribute ไม่ถูกต้องหรือมีทศนิยมเกิน '.$scale.' ตำแหน่ง');

                return;
            }

            try {
                $number = BigDecimal::of($decimal);
            } catch (MathException) {
                $fail('รูปแบบ :attribute ไม่ถูกต้อง');

                return;
            }

            if ($strictlyPositive ? $number->isLessThanOrEqualTo(0) : $number->isLessThan(0)) {
                $fail($strictlyPositive
                    ? ':attribute ต้องมากกว่า 0'
                    : ':attribute ต้องไม่น้อยกว่า 0');
            }
        };
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function isPositiveInteger(mixed $value): bool
    {
        return (is_int($value) && $value > 0)
            || (is_string($value) && preg_match('/^[1-9]\d*$/D', $value) === 1);
    }
}
