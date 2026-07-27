<?php

namespace App\Http\Requests\Sales\Concerns;

use Illuminate\Validation\Validator;

trait HandlesSaleRequestShape
{
    protected function normalizeParallelSaleItems(
        array $optionalFields = [],
        array $fixedValues = []
    ): array {
        $coreFields = ['product_id', 'qty', 'selling_price'];
        $fields = array_values(array_unique(array_merge($coreFields, $optionalFields)));
        $arrays = collect($fields)->mapWithKeys(fn (string $field) => [
            $field => is_array($this->input($field)) ? $this->input($field) : [],
        ])->all();
        $rowCount = max(array_map('count', $arrays));
        $lastPopulatedIndex = null;

        for ($index = $rowCount - 1; $index >= 0; $index--) {
            $hasValue = collect($fields)->contains(
                fn (string $field) => ! $this->isBlank($arrays[$field][$index] ?? null)
            );

            if ($hasValue) {
                $lastPopulatedIndex = $index;

                break;
            }
        }

        if ($lastPopulatedIndex === null) {
            return [];
        }

        $items = [];

        for ($index = 0; $index <= $lastPopulatedIndex; $index++) {
            $item = [];

            foreach ($fields as $field) {
                $item[$field] = array_key_exists($field, $fixedValues)
                    ? $fixedValues[$field]
                    : ($arrays[$field][$index] ?? null);
            }

            $items[$index] = $item;
        }

        return $items;
    }

    protected function validateParallelArrayAlignment(
        Validator $validator,
        array $optionalFields = []
    ): void {
        $coreFields = ['product_id', 'qty', 'selling_price'];
        $coreArrays = collect($coreFields)->mapWithKeys(fn (string $field) => [
            $field => $this->input($field),
        ])->all();

        if (collect($coreArrays)->contains(fn ($value) => ! is_array($value))) {
            return;
        }

        $expectedKeys = array_keys($coreArrays['product_id']);
        $sequentialKeys = $expectedKeys === []
            || $expectedKeys === range(0, count($expectedKeys) - 1);
        $coreAligned = collect($coreArrays)->every(
            fn (array $value) => array_keys($value) === $expectedKeys
        );

        if (! $sequentialKeys || ! $coreAligned) {
            $validator->errors()->add(
                'items',
                'จำนวนช่องสินค้า จำนวน และราคาขายต้องตรงกันตามลำดับรายการ'
            );
        }

        foreach ($optionalFields as $field) {
            $value = $this->input($field);

            if ($value !== null && is_array($value) && array_keys($value) !== $expectedKeys) {
                $validator->errors()->add(
                    $field,
                    'จำนวนช่อง '.$this->parallelFieldLabel($field).' ต้องตรงกับจำนวนสินค้าและลำดับรายการ'
                );
            }
        }
    }

    protected function validateNestedItemList(Validator $validator): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        if (! array_is_list($items)) {
            $validator->errors()->add('items', 'รายการสินค้าต้องเรียงตามลำดับต่อเนื่อง');
        }
    }

    protected function saleItemAttributeLabel(string $attribute): string
    {
        if (preg_match('/^(?:normalized_items|items)\.(\d+)\.(qty|selling_price)$/D', $attribute, $matches) === 1) {
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

    protected function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function parallelFieldLabel(string $field): string
    {
        return match ($field) {
            'sale_item_id' => 'รหัสรายการขาย',
            'product_unit_id' => 'หน่วยขาย',
            default => $field,
        };
    }
}
