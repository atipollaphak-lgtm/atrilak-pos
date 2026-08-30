<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProductNumberService
{
    public function __construct(
        private CategoryPrefixAllocator $prefixAllocator,
    ) {}

    public function generateForCategory(Category $category): array
    {
        return DB::transaction(function () use ($category): array {
            // Keep the advisory lock alive through the sequence read. Callers
            // normally already have an outer transaction, but this also makes
            // direct service calls safe against concurrent allocations.
            $lockedCategory = $this->prefixAllocator->ensure($category);
            $this->lockNumberAllocation($lockedCategory);

            $sequence = $this->nextSequence($lockedCategory);
            $productCode = sprintf('%s-%04d', $lockedCategory->code_prefix, $sequence);
            $barcodeBody = sprintf('20%s0%06d', $lockedCategory->barcode_prefix, $sequence);

            return [
                'product_code' => $productCode,
                'barcode' => $barcodeBody.$this->ean13CheckDigit($barcodeBody),
            ];
        });
    }

    private function nextSequence(Category $category): int
    {
        $codePrefix = preg_quote((string) $category->code_prefix, '/');
        $highestCode = Product::query()
            ->where('category_id', $category->getKey())
            ->whereNotNull('product_code')
            ->pluck('product_code')
            ->reduce(function (int $highest, ?string $productCode) use ($codePrefix): int {
                if (is_string($productCode) && preg_match('/^'.$codePrefix.'-(\d{4})$/', $productCode, $matches)) {
                    return max($highest, (int) $matches[1]);
                }

                return $highest;
            }, 0);

        $barcodePrefix = preg_quote((string) $category->barcode_prefix, '/');
        $highestBarcode = Product::query()
            ->whereNotNull('barcode')
            ->pluck('barcode')
            ->reduce(function (int $highest, ?string $barcode) use ($barcodePrefix): int {
                if (is_string($barcode)
                    && preg_match('/^20'.$barcodePrefix.'0(\d{6})\d$/', $barcode, $matches)) {
                    return max($highest, (int) $matches[1]);
                }

                return $highest;
            }, 0);

        if (Schema::hasTable('product_barcodes')) {
            $highestBarcode = ProductBarcode::query()
                ->whereNotNull('barcode')
                ->pluck('barcode')
                ->reduce(function (int $highest, ?string $barcode) use ($barcodePrefix): int {
                    if (is_string($barcode)
                        && preg_match('/^20'.$barcodePrefix.'0(\d{6})\d$/', $barcode, $matches)) {
                        return max($highest, (int) $matches[1]);
                    }

                    return $highest;
                }, $highestBarcode);
        }

        $sequence = max($highestCode, $highestBarcode) + 1;

        if ($sequence > 9999) {
            throw ValidationException::withMessages([
                'category_id' => 'ไม่สามารถสร้างรหัสสินค้าเพิ่มในหมวดหมู่นี้ได้เนื่องจากเลขรันเต็มแล้ว',
            ]);
        }

        return $sequence;
    }

    private function lockNumberAllocation(Category $category): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select(
                'select pg_advisory_xact_lock(hashtext(?))',
                ['atrilak:product-number:'.$category->getKey()]
            );
        }
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
