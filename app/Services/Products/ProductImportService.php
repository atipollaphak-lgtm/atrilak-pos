<?php

namespace App\Services\Products;

use App\Data\Products\ProductImportResultData;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\ProductNumberService;
use App\Services\ProductUnitService;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductImportService
{
    public function __construct(
        private ProductImportStorageService $storageService,
        private ProductNumberService $productNumberService,
        private ProductUnitService $productUnitService,
    ) {}

    public function confirm(string $token, int $userId): ProductImportResultData
    {
        $operation = fn (): ProductImportResultData => $this->confirmWithoutLock($token, $userId);
        $store = Cache::getStore();

        if (method_exists($store, 'lock')) {
            return Cache::lock('product_import.confirm.'.$token, 30)->block(10, $operation);
        }

        return $operation();
    }

    private function confirmWithoutLock(string $token, int $userId): ProductImportResultData
    {
        $preview = $this->storageService->get($token, $userId);
        if ($preview === null) {
            throw ValidationException::withMessages([
                'import' => 'ไม่พบข้อมูลนำเข้าหรือ Token ไม่ใช่ของผู้ใช้รายนี้',
            ]);
        }
        if ($preview->state !== 'pending') {
            throw ValidationException::withMessages([
                'import' => 'Token นี้ถูกใช้ไปแล้ว',
            ]);
        }
        if (! $preview->isValid()) {
            throw ValidationException::withMessages([
                'import' => 'ไม่สามารถยืนยันไฟล์ที่มีข้อผิดพลาดได้',
            ]);
        }

        $result = DB::transaction(function () use ($preview): ProductImportResultData {
            $this->lockImportWriters();
            $productCodes = [];
            $barcodes = [];
            $productNames = [];
            $firstProductCode = null;
            $lastProductCode = null;
            $movementCount = 0;

            foreach ($preview->rows as $row) {
                $values = $this->normalizeTransactionValues(
                    $row['values'],
                    (int) ($row['row_number'] ?? 0)
                );
                $category = Category::query()
                    ->whereKey($values['category_id'])
                    ->where('active', true)
                    ->first();
                $baseUnit = Unit::query()
                    ->whereKey($values['unit_id'])
                    ->where('active', true)
                    ->first();

                if (! $category || ! $baseUnit) {
                    throw ValidationException::withMessages([
                        'import' => 'หมวดหมู่หรือหน่วยของแถว '.$row['row_number'].' ไม่พร้อมใช้งานแล้ว',
                    ]);
                }

                $productCode = $values['product_code'];
                $barcode = $values['barcode'];
                $this->assertProvidedIdentifiersMatchCategory(
                    $values,
                    $category,
                    (int) ($row['row_number'] ?? 0)
                );
                if (blank($productCode) || blank($barcode)) {
                    $numbers = $this->productNumberService->generateForCategory($category);
                    $productCode = blank($productCode) ? $numbers['product_code'] : $productCode;
                    $barcode = blank($barcode) ? $numbers['barcode'] : $barcode;
                }

                if (blank($productCode) || blank($barcode)) {
                    throw ValidationException::withMessages([
                        'import' => 'ไม่สามารถสร้างรหัสสินค้าหรือบาร์โค้ดของแถว '.$row['row_number'].' ได้',
                    ]);
                }

                if (isset($productNames[strtolower($values['product_name'])])
                    || isset($productCodes[strtolower($productCode)])
                    || isset($barcodes[strtolower($barcode)])) {
                    throw ValidationException::withMessages([
                        'import' => 'พบรหัสสินค้าหรือบาร์โค้ดซ้ำระหว่างยืนยันข้อมูล',
                    ]);
                }
                $this->assertUniqueAgainstCurrentDatabase($values, $productCode, $barcode);
                $productNames[strtolower($values['product_name'])] = true;
                $productCodes[strtolower($productCode)] = true;
                $barcodes[strtolower($barcode)] = true;

                $product = Product::query()->create([
                    'category_id' => $category->id,
                    'unit_id' => $baseUnit->id,
                    'name' => $values['product_name'],
                    'cost_price' => $values['cost_price'],
                    'selling_price' => $values['selling_price'],
                    'stock_qty' => '0.0000',
                    'minimum_stock' => '0.0000',
                    'vat_enabled' => false,
                    'active' => $values['status'],
                    'remark' => $values['description'],
                    'price_lock' => $values['price_locked'],
                    'pricing_reviewed_cost' => $values['selling_price'] !== null ? $values['cost_price'] : null,
                    'product_code' => $productCode,
                    'barcode' => $barcode,
                ]);

                $createdUnit = $this->productUnitService->createOrUpdateBaseUnit($product, [
                    'unit_id' => $baseUnit->id,
                    'purchase_price' => $values['cost_price'],
                    'selling_price' => $values['selling_price'],
                ]);

                ProductBarcode::query()->create([
                    'product_id' => $product->id,
                    'product_unit_id' => $createdUnit->id,
                    'barcode' => $barcode,
                    'is_default' => true,
                    'active' => true,
                    'sort_order' => 1,
                ]);

                $openingStock = BigDecimal::of((string) $values['opening_stock']);
                if ($openingStock->isGreaterThan(BigDecimal::zero())) {
                    StockMovement::query()->create([
                        'product_id' => $product->id,
                        'type' => 'ADJUST',
                        'qty' => (string) $openingStock,
                        'stock_before' => '0.0000',
                        'stock_after' => (string) $openingStock,
                        'reference_type' => 'product_import',
                        'reference_id' => $product->id,
                        'remark' => 'สต็อกเริ่มต้นจาก Excel Import '.$preview->token,
                    ]);
                    $product->update(['stock_qty' => (string) $openingStock]);
                    $movementCount++;
                }

                $firstProductCode ??= $productCode;
                $lastProductCode = $productCode;
            }

            return new ProductImportResultData(
                productCount: count($preview->rows),
                stockMovementCount: $movementCount,
                firstProductCode: $firstProductCode,
                lastProductCode: $lastProductCode,
                importReference: $preview->token,
            );
        });

        $this->storageService->markUsed($token, $userId);

        return $result;
    }

    private function assertUniqueAgainstCurrentDatabase(array $values, string $productCode, string $barcode): void
    {
        $nameExists = Product::query()
            ->whereRaw('LOWER(name) = LOWER(?)', [$values['product_name']])
            ->exists();
        $codeExists = Product::query()
            ->whereRaw('LOWER(product_code) = LOWER(?)', [$productCode])
            ->exists();
        $barcodeExists = Product::query()
            ->whereRaw('LOWER(barcode) = LOWER(?)', [$barcode])
            ->exists()
            || ProductBarcode::query()
                ->whereRaw('LOWER(barcode) = LOWER(?)', [$barcode])
                ->exists();

        if ($nameExists || $codeExists || $barcodeExists) {
            throw ValidationException::withMessages([
                'import' => 'พบชื่อสินค้า รหัสสินค้า หรือบาร์โค้ดซ้ำกับข้อมูลปัจจุบัน',
            ]);
        }
    }

    /**
     * Preview data is kept in a user-scoped token, but it is still rechecked
     * at the transaction boundary so stale/tampered previews cannot bypass
     * the database scale and import rules.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalizeTransactionValues(array $values, int $rowNumber): array
    {
        $costPrice = $this->transactionDecimal(
            $values['cost_price'] ?? null,
            2,
            true
        );
        $sellingPrice = $this->transactionDecimal(
            $values['selling_price'] ?? null,
            2,
            true
        );
        $openingStock = $this->transactionDecimal(
            blank($values['opening_stock'] ?? null) ? '0' : $values['opening_stock'],
            4,
            false
        );

        if ($costPrice === null || $sellingPrice === null || $openingStock === null) {
            throw ValidationException::withMessages([
                'import' => 'ข้อมูลตัวเลขของแถว '.$rowNumber.' ไม่ถูกต้องหรือเกินขนาดที่ระบบรองรับ',
            ]);
        }

        $values['cost_price'] = $costPrice;
        $values['selling_price'] = $sellingPrice;
        $values['opening_stock'] = $openingStock;
        $values['product_name'] = trim((string) ($values['product_name'] ?? ''));
        $values['product_code'] = blank($values['product_code'] ?? null)
            ? null
            : trim((string) $values['product_code']);
        $values['barcode'] = blank($values['barcode'] ?? null)
            ? null
            : trim((string) $values['barcode']);

        if ($values['product_name'] === '') {
            throw ValidationException::withMessages([
                'import' => 'ชื่อสินค้าของแถว '.$rowNumber.' ต้องไม่ว่าง',
            ]);
        }

        return $values;
    }

    private function transactionDecimal(mixed $value, int $scale, bool $round): ?string
    {
        $decimal = is_int($value) || is_float($value) || is_string($value)
            ? trim((string) $value)
            : '';
        $pattern = '/^\d+(?:\.\d+)?$/D';

        if ($decimal === '' || preg_match($pattern, $decimal) !== 1) {
            return null;
        }

        try {
            $number = BigDecimal::of($decimal)->toScale(
                $scale,
                $round ? RoundingMode::HALF_UP : RoundingMode::UNNECESSARY
            );
        } catch (MathException) {
            return null;
        }

        $maximum = $scale === 2
            ? BigDecimal::of('9999999999.99')
            : BigDecimal::of('999999999999999.9999');

        return $number->isGreaterThan($maximum) ? null : (string) $number;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function assertProvidedIdentifiersMatchCategory(
        array $values,
        Category $category,
        int $rowNumber
    ): void {
        $productCode = $values['product_code'] ?? null;
        if ($productCode !== null && mb_strlen((string) $productCode) > 255) {
            throw ValidationException::withMessages([
                'import' => 'รหัสสินค้าของแถว '.$rowNumber.' ยาวเกิน 255 ตัวอักษร',
            ]);
        }

        if ($productCode !== null && filled($category->code_prefix)
            && preg_match('/^'.preg_quote((string) $category->code_prefix, '/').'-(\d{4})$/', (string) $productCode) !== 1) {
            throw ValidationException::withMessages([
                'import' => 'รหัสสินค้าของแถว '.$rowNumber.' ไม่ตรงกับ Prefix ของหมวดหมู่',
            ]);
        }

        $barcode = $values['barcode'] ?? null;
        if ($barcode !== null && mb_strlen((string) $barcode) > 100) {
            throw ValidationException::withMessages([
                'import' => 'บาร์โค้ดของแถว '.$rowNumber.' ยาวเกิน 100 ตัวอักษร',
            ]);
        }
    }

    private function lockImportWriters(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select("select pg_advisory_xact_lock(hashtext('atrilak:product-import-confirm'))");
        }
    }
}
