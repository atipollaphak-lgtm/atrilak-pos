<?php

namespace App\Services\Customers;

use App\Services\Customers\CustomerImportPhoneNormalizer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomerImportValidationService
{
    public function __construct(
        private CustomerImportPhoneNormalizer $phoneNormalizer,
    ) {}

    public function validate(string $path, ?string $originalFilename = null): array
    {
        $fileErrors = $this->validateFile($path, $originalFilename);
        if ($fileErrors !== []) {
            return ['file_errors' => $fileErrors, 'source_system' => null, 'rows' => []];
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            if (! str_ends_with(get_class($reader), '\\Xlsx')) {
                return [
                    'file_errors' => ['ไฟล์นี้ไม่ใช่ Excel Workbook (.xlsx) ที่รองรับ'],
                    'source_system' => null,
                    'rows' => [],
                ];
            }

            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($path);
        } catch (\Throwable) {
            return [
                'file_errors' => ['ไม่สามารถอ่านไฟล์ Excel ได้ กรุณาตรวจสอบว่าไฟล์ไม่เสียหาย'],
                'source_system' => null,
                'rows' => [],
            ];
        }

        [$sheet, $sourceSystem, $headerMap, $headerErrors] = $this->findSheet($spreadsheet->getWorksheetIterator());
        if ($headerErrors !== []) {
            return ['file_errors' => $headerErrors, 'source_system' => null, 'rows' => []];
        }

        $rawRows = $this->rawRows($sheet, $headerMap);
        if ($rawRows === []) {
            return [
                'file_errors' => ['ไฟล์ต้องมีข้อมูลสมาชิกอย่างน้อย 1 รายการ'],
                'source_system' => $sourceSystem,
                'rows' => [],
            ];
        }

        if (count($rawRows) > (int) config('customer_import.max_rows')) {
            return [
                'file_errors' => ['ไฟล์มีสมาชิกเกิน '.config('customer_import.max_rows').' รายการ กรุณาแบ่งไฟล์แล้วนำเข้าใหม่'],
                'source_system' => $sourceSystem,
                'rows' => [],
            ];
        }

        return [
            'file_errors' => [],
            'source_system' => $sourceSystem,
            'rows' => $this->normalizeRows($rawRows, $sourceSystem),
        ];
    }

    private function validateFile(string $path, ?string $originalFilename): array
    {
        if (! is_file($path)) {
            return ['ไม่พบไฟล์ที่อัปโหลด'];
        }

        $extension = strtolower(pathinfo($originalFilename ?: $path, PATHINFO_EXTENSION));
        if ($extension === 'xls') {
            return ['ไฟล์ .xls รูปแบบนี้ไม่สามารถอ่านได้โดยตรง กรุณาเปิดด้วย Microsoft Excel และ Save As เป็น Excel Workbook (.xlsx) ก่อนนำเข้า'];
        }

        if ($extension !== 'xlsx') {
            return ['รองรับเฉพาะไฟล์ .xlsx เท่านั้น'];
        }

        if (filesize($path) > ((int) config('customer_import.max_file_size_kb') * 1024)) {
            return ['ไฟล์มีขนาดใหญ่เกิน '.config('customer_import.max_file_size_kb').' KB'];
        }

        return [];
    }

    private function findSheet(iterable $sheets): array
    {
        $matches = [];

        foreach ($sheets as $sheet) {
            [$sourceSystem, $headerMap, $errors] = $this->headerMap($sheet);
            if ($sourceSystem !== null && $errors === []) {
                $matches[] = [$sheet, $sourceSystem, $headerMap];
            }
        }

        if (count($matches) === 1) {
            return [$matches[0][0], $matches[0][1], $matches[0][2], []];
        }

        if (count($matches) > 1) {
            return [null, null, [], ['พบ Sheet ที่มีรูปแบบสมาชิกมากกว่าหนึ่ง Sheet กรุณาเหลือ Sheet เดียวแล้วลองใหม่']];
        }

        return [null, null, [], ['ไม่พบ Header ที่จำเป็นของ C2M หรือ ATRILAK Template กรุณาตรวจสอบคอลัมน์ external_id และ name']];
    }

    private function headerMap(Worksheet $sheet): array
    {
        $headers = $sheet->rangeToArray(
            'A1:'.$sheet->getHighestColumn().'1',
            null,
            true,
            false,
        )[0] ?? [];

        $normalized = [];
        foreach ($headers as $index => $value) {
            $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($index + 1).'1');
            if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                return [null, [], ['Header ห้ามเป็นสูตร']];
            }

            $normalized[$index + 1] = $this->normalizeHeader($value);
        }

        $candidates = [];
        foreach (config('customer_import.sources') as $source => $definition) {
            $map = [];
            foreach ($normalized as $column => $header) {
                foreach ($definition['headers'] as $key => $aliases) {
                    $aliases = array_map(fn (string $alias): string => $this->normalizeHeader($alias), $aliases);
                    if (in_array($header, $aliases, true) && ! isset($map[$key])) {
                        $map[$key] = $column;
                        break;
                    }
                }
            }

            $missing = array_values(array_filter(
                $definition['required_headers'],
                static fn (string $key): bool => ! isset($map[$key]),
            ));

            if ($missing === []) {
                $candidates[] = [$source, $map];
            }
        }

        if (count($candidates) === 1) {
            return [$candidates[0][0], $candidates[0][1], []];
        }

        if (count($candidates) > 1) {
            return [null, [], ['Header ตรงกับรูปแบบนำเข้ามากกว่าหนึ่งแบบ กรุณาตรวจสอบ Template']];
        }

        return [null, [], ['ไม่พบ Header ที่จำเป็นของ C2M หรือ ATRILAK Template กรุณาตรวจสอบคอลัมน์ external_id และ name']];
    }

    private function rawRows(Worksheet $sheet, array $headerMap): array
    {
        $rows = [];
        for ($rowNumber = 2; $rowNumber <= $sheet->getHighestRow(); $rowNumber++) {
            $values = [];
            $hasValue = false;
            $hasFormula = false;

            foreach ($headerMap as $key => $columnNumber) {
                $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($columnNumber).$rowNumber);
                $value = $cell->getValue();
                $values[$key] = $value;
                $hasValue = $hasValue || ($value !== null && trim((string) $value) !== '');
                $hasFormula = $hasFormula || $cell->getDataType() === DataType::TYPE_FORMULA;
            }

            if (! $hasValue) {
                continue;
            }

            $rows[] = [
                'row_number' => $rowNumber,
                'values' => $values,
                'has_formula' => $hasFormula,
            ];
        }

        return $rows;
    }

    private function normalizeRows(array $rawRows, string $sourceSystem): array
    {
        $rows = [];
        foreach ($rawRows as $rawRow) {
            $line = $this->rowText($rawRow['values']);
            if ($sourceSystem === 'C2M' && preg_match('/^\s*เลขผู้เสียภาษี\s*[:：]?\s*(.*)$/u', $line, $matches) === 1) {
                if ($rows === []) {
                    $rows[] = $this->invalidContinuationRow($rawRow['row_number'], $line);
                    continue;
                }

                $tax = $this->taxNumber($matches[1]);
                if ($tax['valid']) {
                    $rows[array_key_last($rows)]['tax_number'] = $tax['value'];
                } else {
                    $rows[array_key_last($rows)]['status'] = 'review_required';
                    $rows[array_key_last($rows)]['reasons'][] = 'เลขผู้เสียภาษีในแถวต่อเนื่องไม่ครบ 13 หลัก';
                }

                continue;
            }

            $rows[] = $this->normalizeRow($rawRow, $sourceSystem);
        }

        return $rows;
    }

    private function normalizeRow(array $rawRow, string $sourceSystem): array
    {
        $values = $rawRow['values'];
        $name = $this->text($values['name'] ?? null);
        $externalId = $this->text($values['external_id'] ?? null);
        $address = $this->nullableText($values['address'] ?? null);
        $remark = $this->nullableText($values['remark'] ?? null);
        $phoneResult = $this->phoneNormalizer->normalize($values['phone'] ?? null);
        $tax = $this->taxNumber($values['tax_number'] ?? null);
        $warnings = $phoneResult->warnings;
        $reasons = [];

        if ($rawRow['has_formula']) {
            $reasons[] = 'ข้อมูลในแถวห้ามเป็นสูตร';
        }
        if ($name === '') {
            $reasons[] = 'ชื่อลูกค้าต้องไม่ว่าง';
        }
        if ($externalId === '') {
            $reasons[] = 'รหัสอ้างอิงภายนอกต้องไม่ว่าง';
        }
        if (! $tax['valid'] && $tax['value'] !== null) {
            $reasons[] = 'เลขผู้เสียภาษีต้องเป็นตัวเลข 13 หลัก';
        }
        if ($phoneResult->requiresReview()) {
            $reasons[] = 'เบอร์โทรต้องตรวจสอบเพิ่มเติม';
        }

        $embeddedPhones = array_values(array_unique([
            ...$this->phoneNormalizer->findEmbeddedPhones($name),
            ...$this->phoneNormalizer->findEmbeddedPhones((string) ($address ?? '')),
        ]));
        if ($embeddedPhones !== []) {
            foreach ($embeddedPhones as $embeddedPhone) {
                $warnings[] = 'พบเบอร์โทรในข้อความ: '.$embeddedPhone;
            }
            if ($phoneResult->phone === null) {
                $reasons[] = 'พบเบอร์โทรในชื่อหรือที่อยู่แต่ไม่มีเบอร์หลักที่ยืนยันได้';
            }
        }

        $status = $reasons === [] ? 'ready' : 'review_required';
        if ($rawRow['has_formula'] || $name === '' || $externalId === '') {
            $status = 'invalid';
        }

        $branchType = $this->text($values['branch_type'] ?? null) ?: config('customer_import.default_branch_type');
        if (! in_array($branchType, ['สำนักงานใหญ่', 'สาขา'], true)) {
            $branchType = config('customer_import.default_branch_type');
            $warnings[] = 'ประเภทสาขาไม่ตรงรูปแบบ จึงใช้สำนักงานใหญ่เพื่อตรวจสอบ';
        }

        return [
            'row_number' => $rawRow['row_number'],
            'external_id' => $externalId !== '' ? $externalId : null,
            'name' => $name,
            'phone' => $phoneResult->phone,
            'tax_number' => $tax['valid'] ? $tax['value'] : null,
            'branch_type' => $branchType,
            'branch_number' => $this->nullableText($values['branch_number'] ?? null),
            'address' => $address,
            'remark' => $remark,
            'status' => $status,
            'reasons' => array_values(array_unique($reasons)),
            'warnings' => array_values(array_unique($warnings)),
            'original_values' => $values,
            'source_system' => $sourceSystem,
        ];
    }

    private function invalidContinuationRow(int $rowNumber, string $line): array
    {
        return [
            'row_number' => $rowNumber,
            'external_id' => null,
            'name' => '',
            'phone' => null,
            'tax_number' => null,
            'branch_type' => config('customer_import.default_branch_type'),
            'branch_number' => null,
            'address' => null,
            'remark' => null,
            'status' => 'invalid',
            'reasons' => ['พบแถวเลขผู้เสียภาษีโดยไม่มีข้อมูลสมาชิกก่อนหน้า: '.$line],
            'warnings' => [],
            'original_values' => ['continuation' => $line],
        ];
    }

    private function taxNumber(mixed $value): array
    {
        $text = $this->nullableText($value);
        if ($text === null) {
            return ['value' => null, 'valid' => true];
        }

        $digits = preg_replace('/[\s\-]/u', '', $this->toArabicDigits($text)) ?? '';

        return [
            'value' => $digits,
            'valid' => preg_match('/^\d{13}$/D', $digits) === 1,
        ];
    }

    private function rowText(array $values): string
    {
        return trim(implode(' ', array_filter(array_map(
            fn (mixed $value): string => $this->text($value),
            $values,
        ), static fn (string $value): bool => $value !== '')));
    }

    private function normalizeHeader(mixed $value): string
    {
        $value = $this->text($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_strtolower(trim($value));
    }

    private function text(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_float($value) && is_finite($value) && floor($value) === $value) {
            return trim(number_format($value, 0, '.', ''));
        }

        return trim((string) $value);
    }

    private function nullableText(mixed $value): ?string
    {
        $text = $this->text($value);

        return $text === '' ? null : $text;
    }

    private function toArabicDigits(string $value): string
    {
        return strtr($value, [
            '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
            '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
        ]);
    }
}
