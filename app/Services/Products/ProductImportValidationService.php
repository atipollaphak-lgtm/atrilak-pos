<?php

namespace App\Services\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\Unit;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductImportValidationService
{
    public function validate(string $path, ?string $originalFilename = null): array
    {
        $fileErrors = $this->validateFile($path, $originalFilename);
        if ($fileErrors !== []) {
            return ['file_errors' => $fileErrors, 'rows' => []];
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($path);
        } catch (\Throwable $exception) {
            return [
                'file_errors' => ['ไม่สามารถอ่านไฟล์ Excel ได้'],
                'rows' => [],
            ];
        }

        $sheet = $spreadsheet->getSheetByName(config('product_import.sheet_name'));
        if (! $sheet instanceof Worksheet) {
            return [
                'file_errors' => ['ไม่พบ Sheet สินค้า'],
                'rows' => [],
            ];
        }

        [$headerMap, $headerErrors] = $this->headerMap($sheet);
        if ($headerErrors !== []) {
            return ['file_errors' => $headerErrors, 'rows' => []];
        }

        $rawRows = $this->rawRows($sheet, $headerMap);
        if ($rawRows === []) {
            return [
                'file_errors' => ['ไฟล์ต้องมีข้อมูลสินค้าอย่างน้อย 1 รายการ'],
                'rows' => [],
            ];
        }
        if (count($rawRows) > (int) config('product_import.max_rows')) {
            return [
                'file_errors' => ['ไฟล์มีสินค้าเกิน '.config('product_import.max_rows').' รายการ กรุณาแบ่งไฟล์แล้วนำเข้าใหม่'],
                'rows' => [],
            ];
        }

        return [
            'file_errors' => [],
            'rows' => $this->validateRows($rawRows),
        ];
    }

    private function validateFile(string $path, ?string $originalFilename = null): array
    {
        if (! is_file($path)) {
            return ['ไม่พบไฟล์ที่อัปโหลด'];
        }

        $extension = strtolower(pathinfo($originalFilename ?: $path, PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            return ['รองรับเฉพาะไฟล์ .xlsx เท่านั้น'];
        }

        if (filesize($path) > ((int) config('product_import.max_file_size_kb') * 1024)) {
            return ['ไฟล์มีขนาดใหญ่เกิน '.config('product_import.max_file_size_kb').' KB'];
        }

        return [];
    }

    private function headerMap(Worksheet $sheet): array
    {
        $expected = config('product_import.headers');
        $headerNames = array_map(
            fn (mixed $value): string => $this->normalizeText($value),
            $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1', null, true, false)[0]
        );
        $knownByName = collect($expected)
            ->mapWithKeys(fn (string $label, string $key): array => [$this->normalizeText($label) => $key])
            ->all();
        $headerMap = [];
        $errors = [];

        foreach ($headerNames as $index => $headerName) {
            if ($headerName === '') {
                continue;
            }

            $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($index + 1).'1');
            if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                $errors[] = 'Header ห้ามเป็นสูตร';

                continue;
            }

            if (! array_key_exists($headerName, $knownByName)) {
                $errors[] = 'ไม่รู้จัก Header: '.$headerName;

                continue;
            }

            $key = $knownByName[$headerName];
            if (isset($headerMap[$key])) {
                $errors[] = 'Header ซ้ำ: '.$expected[$key];

                continue;
            }

            $headerMap[$key] = $index + 1;
        }

        foreach (config('product_import.required_headers') as $requiredKey) {
            if (! isset($headerMap[$requiredKey])) {
                $errors[] = 'ไม่พบ Header ที่จำเป็น: '.$expected[$requiredKey];
            }
        }

        return [$headerMap, array_values(array_unique($errors))];
    }

    private function rawRows(Worksheet $sheet, array $headerMap): array
    {
        $rows = [];
        $headers = config('product_import.headers');

        for ($rowNumber = 2; $rowNumber <= $sheet->getHighestRow(); $rowNumber++) {
            $original = [];
            $hasValue = false;
            $hasFormula = false;

            foreach ($headerMap as $key => $columnNumber) {
                $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($columnNumber).$rowNumber);
                $value = $cell->getValue();
                $original[$headers[$key]] = $value;
                $hasValue = $hasValue || $value !== null && trim((string) $value) !== '';
                $hasFormula = $hasFormula || $cell->getDataType() === DataType::TYPE_FORMULA;
            }

            if (! $hasValue) {
                continue;
            }

            $rows[] = [
                'row_number' => $rowNumber,
                'original_values' => $original,
                'has_formula' => $hasFormula,
            ];
        }

        return $rows;
    }

    private function validateRows(array $rawRows): array
    {
        $categories = $this->referenceMap(Category::query()->where('active', true)->get(), 'name');
        $units = $this->referenceMap(Unit::query()->where('active', true)->get(), 'name');
        $dbCodes = $this->existingValues(Product::query(), 'product_code', $rawRows, 'product_code');
        $dbBarcodes = $this->existingValues(Product::query(), 'barcode', $rawRows, 'barcode');
        $dbBarcodeTable = $this->existingValues(ProductBarcode::query(), 'barcode', $rawRows, 'barcode');
        $dbNames = $this->existingValues(Product::query(), 'name', $rawRows, 'product_name', true);
        $seen = ['product_name' => [], 'product_code' => [], 'barcode' => []];
        $result = [];

        foreach ($rawRows as $rawRow) {
            $source = $rawRow['original_values'];
            $values = $this->normalizeRow($source, $categories, $units);
            $errors = [];

            if ($rawRow['has_formula']) {
                $errors[] = $this->error('ข้อมูล', 'ห้ามใช้สูตรในช่องข้อมูล');
            }

            $errors = [...$errors, ...$this->validateRowValues($values, $categories, $units)];
            $errors = [...$errors, ...$this->duplicateErrors($values, $seen)];
            $errors = [...$errors, ...$this->databaseDuplicateErrors($values, $dbCodes, $dbBarcodes, $dbBarcodeTable, $dbNames)];

            foreach (['product_name', 'product_code', 'barcode'] as $key) {
                if (($values[$key] ?? null) !== null && $values[$key] !== '') {
                    $seen[$key][strtolower((string) $values[$key])] = true;
                }
            }

            $result[] = [
                'row_number' => $rawRow['row_number'],
                'values' => $values,
                'original_values' => $source,
                'errors' => $errors,
            ];
        }

        return $result;
    }

    private function normalizeRow(array $source, Collection $categories, Collection $units): array
    {
        $categoryName = $this->normalizeText($source[config('product_import.headers.category')] ?? null);
        $unitName = $this->normalizeText($source[config('product_import.headers.base_unit')] ?? null);
        $openingStockSource = $source[config('product_import.headers.opening_stock')] ?? null;
        $openingStock = $openingStockSource === null || trim((string) $openingStockSource) === ''
            ? '0.0000'
            : $this->decimal($openingStockSource, 4);

        return [
            'product_name' => $this->normalizeText($source[config('product_import.headers.product_name')] ?? null),
            'category' => $categoryName,
            'category_id' => $categories->get(strtolower($categoryName))?->getKey(),
            'base_unit' => $unitName,
            'unit_id' => $units->get(strtolower($unitName))?->getKey(),
            'cost_price' => $this->decimal($source[config('product_import.headers.cost_price')] ?? null, 2),
            'selling_price' => $this->decimal($source[config('product_import.headers.selling_price')] ?? null, 2),
            'opening_stock' => $openingStock,
            'product_code' => $this->optionalText($source[config('product_import.headers.product_code')] ?? null, 255),
            'barcode' => $this->optionalBarcode($source[config('product_import.headers.barcode')] ?? null),
            'status' => $this->boolean($source[config('product_import.headers.status')] ?? null, true),
            'price_locked' => $this->boolean($source[config('product_import.headers.price_locked')] ?? null, false),
            'description' => $this->optionalText($source[config('product_import.headers.description')] ?? null, null),
        ];
    }

    private function validateRowValues(array $values, Collection $categories, Collection $units): array
    {
        $errors = [];
        if ($values['product_name'] === '') {
            $errors[] = $this->error('product_name', 'ชื่อสินค้าต้องไม่ว่าง');
        } elseif (mb_strlen($values['product_name']) > 255 || $this->hasControlCharacter($values['product_name'])) {
            $errors[] = $this->error('product_name', 'ชื่อสินค้ายาวเกินกำหนดหรือมีอักขระควบคุม');
        }
        if ($values['category_id'] === null) {
            $errors[] = $this->error('category', $values['category'] === '' ? 'หมวดหมู่ต้องไม่ว่าง' : 'ไม่พบหมวดหมู่ที่เปิดใช้งาน');
        }
        if ($values['unit_id'] === null) {
            $errors[] = $this->error('base_unit', $values['base_unit'] === '' ? 'หน่วยหลักต้องไม่ว่าง' : 'ไม่พบหน่วยที่เปิดใช้งาน');
        }
        if ($values['cost_price'] === null) {
            $errors[] = $this->error('cost_price', 'ต้นทุนต้องเป็นตัวเลขไม่ติดลบและมีทศนิยมไม่เกิน 2 ตำแหน่ง');
        }
        if ($values['selling_price'] === null) {
            $errors[] = $this->error('selling_price', 'ราคาขายต้องเป็นตัวเลขไม่ติดลบและมีทศนิยมไม่เกิน 2 ตำแหน่ง');
        }
        if ($values['opening_stock'] === null) {
            $errors[] = $this->error('opening_stock', 'จำนวนเริ่มต้นต้องเป็นตัวเลขไม่ติดลบและมีทศนิยมไม่เกิน 4 ตำแหน่ง');
        }
        if ($values['status'] === null) {
            $errors[] = $this->error('status', 'สถานะต้องเป็น Active, Inactive หรือค่าที่ Template รองรับ');
        }
        if ($values['price_locked'] === null) {
            $errors[] = $this->error('price_locked', 'ค่าล็อกราคาขายไม่ถูกต้อง');
        }
        if ($values['product_code'] !== null && $this->hasControlCharacter($values['product_code'])) {
            $errors[] = $this->error('product_code', 'รหัสสินค้ามีอักขระไม่ถูกต้อง');
        }
        if ($values['product_code'] !== null && mb_strlen($values['product_code']) > 255) {
            $errors[] = $this->error('product_code', 'รหัสสินค้ายาวเกิน 255 ตัวอักษร');
        }
        $category = $categories->get(strtolower((string) $values['category']));
        if ($values['product_code'] !== null && $category && blank($category->code_prefix)) {
            $errors[] = $this->error('product_code', 'หมวดหมู่ยังไม่กำหนด Prefix สำหรับรหัสสินค้า');
        } elseif ($values['product_code'] !== null && $category
            && preg_match('/^'.preg_quote((string) $category->code_prefix, '/').'-(\d{4})$/', $values['product_code']) !== 1) {
            $errors[] = $this->error('product_code', 'รหัสสินค้าต้องตรงกับ Prefix และรูปแบบเดิมของหมวดหมู่');
        }
        if ($values['barcode'] !== null && $this->hasControlCharacter($values['barcode'])) {
            $errors[] = $this->error('barcode', 'บาร์โค้ดมีอักขระไม่ถูกต้อง');
        }
        if ($values['barcode'] !== null && mb_strlen($values['barcode']) > 100) {
            $errors[] = $this->error('barcode', 'บาร์โค้ดยาวเกิน 100 ตัวอักษร');
        }

        return $errors;
    }

    private function duplicateErrors(array $values, array $seen): array
    {
        $errors = [];
        foreach (['product_name', 'product_code', 'barcode'] as $key) {
            $value = $values[$key] ?? null;
            if ($value !== null && $value !== '' && isset($seen[$key][strtolower((string) $value)])) {
                $errors[] = $this->error($key, 'ข้อมูลซ้ำภายในไฟล์');
            }
        }

        return $errors;
    }

    private function databaseDuplicateErrors(
        array $values,
        array $dbCodes,
        array $dbBarcodes,
        array $dbBarcodeTable,
        array $dbNames
    ): array {
        $errors = [];
        if ($values['product_code'] !== null && isset($dbCodes[strtolower($values['product_code'])])) {
            $errors[] = $this->error('product_code', 'รหัสสินค้ามีอยู่ในระบบแล้ว');
        }
        if ($values['barcode'] !== null && (isset($dbBarcodes[strtolower($values['barcode'])]) || isset($dbBarcodeTable[strtolower($values['barcode'])]))) {
            $errors[] = $this->error('barcode', 'บาร์โค้ดมีอยู่ในระบบแล้ว');
        }
        if ($values['product_name'] !== '' && isset($dbNames[strtolower($values['product_name'])])) {
            $errors[] = $this->error('product_name', 'ชื่อสินค้ามีอยู่ในระบบแล้ว');
        }

        return $errors;
    }

    private function referenceMap(Collection $models, string $field): Collection
    {
        return $models->keyBy(fn ($model): string => strtolower($this->normalizeText($model->{$field})));
    }

    private function existingValues($query, string $column, array $rawRows, string $key, bool $caseInsensitive = false): array
    {
        $values = collect($rawRows)
            ->map(fn (array $row): string => $this->normalizeText($row['original_values'][config('product_import.headers.'.$key)] ?? null))
            ->filter()
            ->unique()
            ->values();

        if ($values->isEmpty()) {
            return [];
        }

        if (! $caseInsensitive) {
            return $query->whereIn($column, $values->all())
                ->pluck($column)
                ->mapWithKeys(fn ($value): array => [strtolower((string) $value) => true])
                ->all();
        }

        return $query->where(function ($nested) use ($column, $values): void {
            foreach ($values as $value) {
                $nested->orWhereRaw('LOWER('.$column.') = LOWER(?)', [$value]);
            }
        })->pluck($column)
            ->mapWithKeys(fn ($value): array => [strtolower((string) $value) => true])
            ->all();
    }

    private function decimal(mixed $value, int $scale): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);
        $pattern = '/^\d+(?:\.\d{1,'.$scale.'})?$/D';
        if (preg_match($pattern, $value) !== 1) {
            return null;
        }

        try {
            return (string) BigDecimal::of($value)->toScale($scale, RoundingMode::UNNECESSARY);
        } catch (MathException) {
            return null;
        }
    }

    private function optionalText(mixed $value, ?int $maxLength): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = $this->normalizeText($value);

        return $value;
    }

    private function optionalBarcode(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);
        if (is_numeric($value) && str_contains(strtolower($value), 'e')) {
            $value = number_format((float) $value, 0, '.', '');
        }

        return $value;
    }

    private function boolean(mixed $value, bool $default): ?bool
    {
        if ($value === null || trim((string) $value) === '') {
            return $default;
        }

        return match (strtolower(trim((string) $value))) {
            'ใช้งาน', 'active', '1', 'true', 'yes', 'ล็อก' => true,
            'ไม่ใช้งาน', 'inactive', '0', 'false', 'no', 'ไม่ล็อก' => false,
            default => null,
        };
    }

    private function normalizeText(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function hasControlCharacter(string $value): bool
    {
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1;
    }

    private function error(string $column, string $message): array
    {
        return ['column' => $column, 'message' => $message];
    }
}
