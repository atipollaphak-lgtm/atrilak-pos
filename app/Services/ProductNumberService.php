<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

class ProductNumberService
{
    public function generateForCategory(Category $category): array
    {
        $lockedCategory = Category::query()
            ->lockForUpdate()
            ->findOrFail($category->getKey());

        if (blank($lockedCategory->code_prefix)) {
            throw ValidationException::withMessages([
                'category_id' => 'หมวดหมู่นี้ยังไม่ได้กำหนดตัวย่อสำหรับสร้างรหัสสินค้า',
            ]);
        }

        if (blank($lockedCategory->barcode_prefix)) {
            throw ValidationException::withMessages([
                'category_id' => 'หมวดหมู่นี้ยังไม่ได้กำหนดรหัสบาร์โค้ด 3 หลัก',
            ]);
        }

        $sequence = $this->nextSequence($lockedCategory);
        $productCode = sprintf('%s-%04d', $lockedCategory->code_prefix, $sequence);
        $barcodeBody = sprintf('20%s0%06d', $lockedCategory->barcode_prefix, $sequence);

        return [
            'product_code' => $productCode,
            'barcode' => $barcodeBody.$this->ean13CheckDigit($barcodeBody),
        ];
    }

    private function nextSequence(Category $category): int
    {
        $prefix = preg_quote($category->code_prefix, '/');
        $sequence = Product::query()
            ->where('category_id', $category->getKey())
            ->whereNotNull('product_code')
            ->pluck('product_code')
            ->reduce(function (int $highest, ?string $productCode) use ($prefix): int {
                if (is_string($productCode) && preg_match('/^'.$prefix.'-(\d{4})$/', $productCode, $matches)) {
                    return max($highest, (int) $matches[1]);
                }

                return $highest;
            }, 0) + 1;

        if ($sequence > 9999) {
            throw ValidationException::withMessages([
                'category_id' => 'ไม่สามารถสร้างรหัสสินค้าเพิ่มในหมวดหมู่นี้ได้เนื่องจากเลขรันเต็มแล้ว',
            ]);
        }

        return $sequence;
    }

    private function ean13CheckDigit(string $body): int
    {
        $digits = str_split($body);
        $sum = array_sum(array_map(
            fn (string $digit, int $index): int => (int) $digit * ($index % 2 === 0 ? 1 : 3),
            $digits,
            array_keys($digits)
        ));

        return (10 - ($sum % 10)) % 10;
    }
}
