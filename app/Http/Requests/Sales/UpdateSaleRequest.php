<?php

namespace App\Http\Requests\Sales;

use App\Models\Sale;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class UpdateSaleRequest extends FormRequest
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
            'sale_date' => ['required', 'date_format:Y-m-d'],
            'delivery_type' => ['nullable', 'in:delivery,pickup'],
            'delivery_zone_id' => ['nullable', 'integer'],
            'discount' => ['nullable', $this->decimalRule(2, 10, false)],
            'delivery_fee' => ['nullable', $this->decimalRule(2, 10, false)],
            'base_qty' => ['prohibited'],
            'conversion_rate_used' => ['prohibited'],
            'product_id' => ['required', 'array'],
            'qty' => ['required', 'array'],
            'selling_price' => ['required', 'array'],
            'sale_item_id' => ['nullable', 'array'],
            'product_unit_id' => ['nullable', 'array'],
            'normalized_items' => ['required', 'array', 'min:1'],
            'normalized_items.*.product_id' => ['bail', 'required', 'integer', 'exists:products,id'],
            'normalized_items.*.sale_item_id' => ['nullable', 'integer'],
            'normalized_items.*.product_unit_id' => ['nullable', 'integer', 'exists:product_units,id'],
            'normalized_items.*.qty' => ['bail', 'required', $this->decimalRule(2, 13, true)],
            'normalized_items.*.selling_price' => ['bail', 'required', $this->decimalRule(2, 13, true)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateArrayLengths($validator);
            $this->validateDeliveryAddressOwnership($validator);
            $this->validateActiveReferences($validator);
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
        $saleItemIds = is_array($this->input('sale_item_id')) ? $this->input('sale_item_id') : [];
        $productUnitIds = is_array($this->input('product_unit_id')) ? $this->input('product_unit_id') : [];
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
                'sale_item_id' => $saleItemIds[$index] ?? null,
                'product_id' => $productId,
                'product_unit_id' => $productUnitIds[$index] ?? null,
                'qty' => $qty,
                'selling_price' => $price,
            ];
        }

        return $items;
    }

    private function validateArrayLengths(Validator $validator): void
    {
        $coreArrays = [
            'product_id' => $this->input('product_id'),
            'qty' => $this->input('qty'),
            'selling_price' => $this->input('selling_price'),
        ];

        if (! collect($coreArrays)->contains(fn ($value) => ! is_array($value))) {
            $coreCounts = array_map('count', $coreArrays);

            if (count(array_unique($coreCounts)) !== 1) {
                $validator->errors()->add('items', 'จำนวนช่องสินค้า จำนวน และราคาขายต้องตรงกัน');
            }

            $expectedCount = count($coreArrays['product_id']);

            foreach (['sale_item_id', 'product_unit_id'] as $field) {
                $value = $this->input($field);

                if ($value !== null && is_array($value) && count($value) !== $expectedCount) {
                    $validator->errors()->add($field, 'จำนวนช่อง '.$field.' ต้องตรงกับจำนวนสินค้า');
                }
            }
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

    private function validateActiveReferences(Validator $validator): void
    {
        $sale = $this->route('sale');

        if (! $sale instanceof Sale) {
            return;
        }

        $customerId = $this->input('customer_id');

        if ($this->isPositiveInteger($customerId)
            && (int) $customerId !== (int) $sale->customer_id
            && DB::table('customers')->where('id', $customerId)->where('active', false)->exists()) {
            $validator->errors()->add('customer_id', 'ลูกค้าที่ปิดใช้งานเลือกใช้กับใบขายนี้ไม่ได้');
        }

        $submittedProductIds = collect($this->normalizedItems())
            ->pluck('product_id')
            ->filter(fn ($productId) => $this->isPositiveInteger($productId))
            ->map(fn ($productId) => (int) $productId)
            ->unique();

        if ($submittedProductIds->isEmpty()) {
            return;
        }

        $inactiveProductIds = DB::table('products')
            ->whereIn('id', $submittedProductIds)
            ->where('active', false)
            ->pluck('id')
            ->map(fn ($productId) => (int) $productId);

        if ($inactiveProductIds->isEmpty()) {
            return;
        }

        $historicalItems = $sale->items()
            ->pluck('product_id', 'id')
            ->mapWithKeys(fn ($productId, $saleItemId) => [(int) $saleItemId => (int) $productId]);

        foreach ($this->normalizedItems() as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $saleItemId = (int) ($item['sale_item_id'] ?? 0);
            $isOriginalHistoricalReference = $saleItemId > 0
                && $historicalItems->get($saleItemId) === $productId;

            if ($inactiveProductIds->contains($productId) && ! $isOriginalHistoricalReference) {
                $validator->errors()->add('product_id', 'สินค้าที่ปิดใช้งานเลือกเพิ่มในใบขายไม่ได้');

                return;
            }
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
            $attributeLabel = $this->attributeLabel($attribute);

            if (! preg_match('/^\d{1,'.$integerDigits.'}(?:\.\d{1,'.$scale.'})?$/D', $decimal)) {
                $hasTooManyDecimalPlaces = preg_match('/^\d+(?:\.(\d+))?$/D', $decimal, $matches) === 1
                    && isset($matches[1])
                    && strlen($matches[1]) > $scale;

                $fail($hasTooManyDecimalPlaces
                    ? $attributeLabel.'รับได้สูงสุด '.$scale.' ตำแหน่งทศนิยม'
                    : $attributeLabel.'ไม่ถูกต้อง');

                return;
            }

            try {
                $number = BigDecimal::of($decimal);
            } catch (MathException) {
                $fail($attributeLabel.'ไม่ถูกต้อง');

                return;
            }

            if ($strictlyPositive ? $number->isLessThanOrEqualTo(0) : $number->isLessThan(0)) {
                $fail($strictlyPositive
                    ? $attributeLabel.'ต้องมากกว่า 0'
                    : $attributeLabel.'ต้องไม่น้อยกว่า 0');
            }
        };
    }

    private function attributeLabel(string $attribute): string
    {
        if (preg_match('/^normalized_items\.(\d+)\.(qty|selling_price)$/D', $attribute, $matches) === 1) {
            $rowNumber = (int) $matches[1] + 1;
            $fieldName = $matches[2] === 'qty' ? 'จำนวนสินค้า' : 'ราคาขาย';

            return $fieldName.'รายการที่ '.$rowNumber.' ';
        }

        return match ($attribute) {
            'discount' => 'ส่วนลด ',
            'delivery_fee' => 'ค่าขนส่ง ',
            default => $attribute.' ',
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
