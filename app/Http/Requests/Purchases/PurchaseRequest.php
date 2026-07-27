<?php

namespace App\Http\Requests\Purchases;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class PurchaseRequest extends FormRequest
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
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'purchase_date' => ['required', 'date_format:Y-m-d'],
            'product_id' => ['required', 'array'],
            'qty' => ['required', 'array'],
            'cost_price' => ['required', 'array'],
            'normalized_items' => ['required', 'array', 'min:1'],
            'normalized_items.*.product_id' => ['bail', 'required', 'integer', 'exists:products,id'],
            'normalized_items.*.qty' => ['bail', 'required', $this->positiveDecimalRule(4, 15)],
            'normalized_items.*.cost_price' => ['bail', 'required', $this->positiveDecimalRule(2, 10)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateArrayLengths($validator);
            $this->validateDuplicateProducts($validator);
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
        $costPrices = is_array($this->input('cost_price')) ? $this->input('cost_price') : [];
        $rowCount = max(count($products), count($quantities), count($costPrices));
        $items = [];

        for ($index = 0; $index < $rowCount; $index++) {
            $items[] = [
                'product_id' => $products[$index] ?? null,
                'qty' => $quantities[$index] ?? null,
                'cost_price' => $costPrices[$index] ?? null,
            ];
        }

        while ($items !== []) {
            $last = $items[array_key_last($items)];

            if (! $this->isBlank($last['product_id'])
                || ! $this->isBlank($last['qty'])
                || ! $this->isBlank($last['cost_price'])) {
                break;
            }

            array_pop($items);
        }

        return array_values($items);
    }

    private function validateArrayLengths(Validator $validator): void
    {
        $arrays = [
            $this->input('product_id'),
            $this->input('qty'),
            $this->input('cost_price'),
        ];

        if (collect($arrays)->contains(fn (mixed $value): bool => ! is_array($value))) {
            return;
        }

        if (count(array_unique(array_map('count', $arrays))) !== 1) {
            $validator->errors()->add(
                'items',
                'จำนวนช่องสินค้า จำนวน และต้นทุนต้องตรงกัน'
            );
        }
    }

    private function validateDuplicateProducts(Validator $validator): void
    {
        $productIds = [];

        foreach ($this->normalizedItems() as $item) {
            $productId = $item['product_id'] ?? null;

            if ($this->isPositiveInteger($productId)) {
                $productIds[] = (int) $productId;
            }
        }

        if (count($productIds) !== count(array_unique($productIds))) {
            $validator->errors()->add(
                'product_id',
                'สินค้าในรายการซื้อเข้าต้องไม่ซ้ำกัน'
            );
        }
    }

    private function validateActiveReferences(Validator $validator): void
    {
        $purchase = $this->route('purchase');
        $purchase = $purchase instanceof Purchase ? $purchase : null;
        $supplierId = $this->input('supplier_id');

        if ($this->isPositiveInteger($supplierId)) {
            $supplier = Supplier::query()->find((int) $supplierId);

            if ($supplier !== null
                && ! $supplier->active
                && ($purchase === null || (int) $purchase->supplier_id !== $supplier->id)) {
                $validator->errors()->add('supplier_id', 'ผู้จำหน่ายนี้ถูกปิดใช้งาน');
            }
        }

        $submittedIds = collect($this->normalizedItems())
            ->pluck('product_id')
            ->filter(fn (mixed $value): bool => $this->isPositiveInteger($value))
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values();
        $originalIds = $purchase?->items()->pluck('product_id')->map(fn ($id): int => (int) $id)
            ?? collect();

        Product::query()
            ->whereIn('id', $submittedIds->all())
            ->where('active', false)
            ->pluck('id')
            ->each(function (int $productId) use ($validator, $purchase, $originalIds): void {
                if ($purchase === null || ! $originalIds->contains($productId)) {
                    $validator->errors()->add(
                        'product_id',
                        'สินค้าที่ถูกปิดใช้งานสามารถใช้ได้เฉพาะรายการเดิมของเอกสารนี้'
                    );
                }
            });
    }

    private function positiveDecimalRule(int $scale, int $integerDigits): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($scale, $integerDigits): void {
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

            if ($number->isLessThanOrEqualTo(0)) {
                $fail(':attribute ต้องมากกว่า 0');
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
