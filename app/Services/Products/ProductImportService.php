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
            $productCodes = [];
            $barcodes = [];
            $productNames = [];
            $firstProductCode = null;
            $lastProductCode = null;
            $movementCount = 0;

            foreach ($preview->rows as $row) {
                $values = $row['values'];
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

                $numbers = $this->productNumberService->generateForCategory($category);
                $productCode = $values['product_code'] ?: $numbers['product_code'];
                $barcode = $values['barcode'] ?: $numbers['barcode'];

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
}
