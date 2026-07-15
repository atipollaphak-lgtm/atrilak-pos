<?php

namespace App\Http\Requests\StockCounts;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreStockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['normalized_items' => $this->normalizeParallelItems()]);
    }

    public function rules(): array
    {
        return [
            'count_date' => ['required', 'date_format:Y-m-d'],
            'remark' => ['nullable', 'string'],
            'product_id' => ['required', 'array'],
            'actual_qty' => ['required', 'array'],
            'system_qty' => ['nullable', 'array'],
            'normalized_items' => ['required', 'array', 'min:1'],
            'normalized_items.*.product_id' => ['bail', 'required', 'integer', 'exists:products,id'],
            'normalized_items.*.actual_qty' => ['bail', 'required', $this->decimalQuantityRule()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateCoreArrayLengths($validator);
            $this->validateDuplicateProducts($validator);
        });
    }

    public function normalizedItems(): array
    {
        return $this->input('normalized_items', []);
    }

    private function normalizeParallelItems(): array
    {
        $products = is_array($this->input('product_id')) ? $this->input('product_id') : [];
        $quantities = is_array($this->input('actual_qty')) ? $this->input('actual_qty') : [];
        $items = [];

        for ($index = 0; $index < max(count($products), count($quantities)); $index++) {
            $items[] = [
                'product_id' => $products[$index] ?? null,
                'actual_qty' => $quantities[$index] ?? null,
            ];
        }

        while ($items !== []) {
            $last = $items[array_key_last($items)];

            if (! $this->isBlank($last['product_id']) || ! $this->isBlank($last['actual_qty'])) {
                break;
            }

            array_pop($items);
        }

        return array_values($items);
    }

    private function validateCoreArrayLengths(Validator $validator): void
    {
        $products = $this->input('product_id');
        $quantities = $this->input('actual_qty');

        if (is_array($products) && is_array($quantities) && count($products) !== count($quantities)) {
            $validator->errors()->add('items', 'จำนวนช่องสินค้าและจำนวนที่ตรวจนับต้องตรงกัน');
        }
    }

    private function validateDuplicateProducts(Validator $validator): void
    {
        $productIds = [];

        foreach ($this->normalizedItems() as $item) {
            $value = $item['product_id'] ?? null;

            if ($this->isPositiveInteger($value)) {
                $productIds[] = (int) $value;
            }
        }

        if (count($productIds) !== count(array_unique($productIds))) {
            $validator->errors()->add('product_id', 'สินค้าในรายการตรวจนับต้องไม่ซ้ำกัน');
        }
    }

    private function decimalQuantityRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            $decimal = is_int($value) || is_float($value) || is_string($value)
                ? (string) $value
                : '';

            if (! preg_match('/^\d{1,15}(?:\.\d{1,4})?$/D', $decimal)) {
                $fail('รูปแบบ :attribute ไม่ถูกต้องหรือมีทศนิยมเกิน 4 ตำแหน่ง');

                return;
            }

            try {
                $number = BigDecimal::of($decimal);
            } catch (MathException) {
                $fail('รูปแบบ :attribute ไม่ถูกต้อง');

                return;
            }

            if ($number->isLessThan(BigDecimal::zero())) {
                $fail(':attribute ต้องไม่น้อยกว่า 0');
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
            || (is_string($value) && preg_match('/^\d+$/D', $value) === 1 && (int) $value > 0);
    }
}
