<?php

namespace App\Services\Products;

use App\Models\Category;
use App\Models\Unit;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ProductImportTemplateService
{
    public function createTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $main = $spreadsheet->getActiveSheet();
        $main->setTitle(config('product_import.sheet_name'));

        $headers = array_values(config('product_import.headers'));
        $main->fromArray($headers, null, 'A1');
        $main->freezePane('A2');
        $main->setAutoFilter('A1:K1');
        $main->getStyle('A1:K1')->getFont()->setBold(true);
        $main->getStyle('H:H')->getNumberFormat()->setFormatCode('@');
        $main->setCellValueExplicit('H2', '', DataType::TYPE_STRING);

        foreach (range('A', 'K') as $column) {
            $main->getColumnDimension($column)->setWidth($column === 'K' ? 36 : 20);
        }

        $this->addReferenceSheet(
            $spreadsheet,
            config('product_import.reference_sheets.categories'),
            ['ชื่อหมวดหมู่', 'Code Prefix', 'Barcode Prefix', 'สถานะ'],
            $this->activeCategories()
        );
        $this->addReferenceSheet(
            $spreadsheet,
            config('product_import.reference_sheets.units'),
            ['ชื่อหน่วย', 'สัญลักษณ์', 'สถานะ'],
            $this->activeUnits()
        );

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle(config('product_import.reference_sheets.instructions'));
        $instructions->fromArray([
            ['คำแนะนำการนำเข้าสินค้า'],
            ['ห้ามเปลี่ยนชื่อ Header หรือเพิ่มคอลัมน์ที่ระบบไม่รู้จัก'],
            ['หมวดหมู่และหน่วยต้องเลือกจากรายการที่มีอยู่และเปิดใช้งาน'],
            ['ต้นทุนและราคาขายต้องเป็นตัวเลขที่ไม่ติดลบ'],
            ['จำนวนเริ่มต้นว่างได้และมีค่าเริ่มต้นเป็น 0'],
            ['รหัสสินค้าและบาร์โค้ดว่างได้ ระบบจะสร้างให้อัตโนมัติ'],
            ['ห้ามใส่สูตรในช่องข้อมูล'],
        ]);
        $instructions->getColumnDimension('A')->setWidth(100);
        $instructions->getStyle('A1')->getFont()->setBold(true);

        return $spreadsheet;
    }

    public function createErrorReport(array $rows): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(config('product_import.sheet_name'));

        $headers = $this->errorHeaders($rows);
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:'.$this->columnLetter(count($headers)).'1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$this->columnLetter(count($headers)).'1');

        foreach ($rows as $rowIndex => $row) {
            $values = array_map(
                fn (string $header): string => $this->safeCellValue($row[$header] ?? ''),
                $headers
            );
            $sheet->fromArray($values, null, 'A'.($rowIndex + 2));
        }

        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimension($this->columnLetter($columnIndex))->setWidth(24);
        }

        return $spreadsheet;
    }

    private function addReferenceSheet(
        Spreadsheet $spreadsheet,
        string $title,
        array $headers,
        array $rows
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:'.$this->columnLetter(count($headers)).'1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->fromArray($rows, null, 'A2');

        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimension($this->columnLetter($columnIndex))->setWidth(24);
        }
    }

    private function activeCategories(): array
    {
        if (! Schema::hasTable('categories')) {
            return [];
        }

        return Category::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['name', 'code_prefix', 'barcode_prefix'])
            ->map(fn (Category $category): array => [$category->name, $category->code_prefix, $category->barcode_prefix, 'ใช้งาน'])
            ->all();
    }

    private function activeUnits(): array
    {
        if (! Schema::hasTable('units')) {
            return [];
        }

        return Unit::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['name', 'short_name'])
            ->map(fn (Unit $unit): array => [$unit->name, $unit->short_name, 'ใช้งาน'])
            ->all();
    }

    private function errorHeaders(array $rows): array
    {
        $configuredHeaders = array_values(config('product_import.headers'));
        $presentHeaders = collect($rows)
            ->flatMap(fn (array $row): array => array_keys($row))
            ->unique()
            ->values()
            ->all();
        $headers = collect($configuredHeaders)
            ->filter(fn (string $header): bool => in_array($header, $presentHeaders, true))
            ->values()
            ->all();
        $extraHeaders = collect($presentHeaders)
            ->filter(fn (string $header): bool => ! in_array($header, $headers, true))
            ->values()
            ->all();

        return [...$headers, ...$extraHeaders];
    }

    private function safeCellValue(mixed $value): string
    {
        $value = is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }

    private function columnLetter(int $column): string
    {
        $letter = '';

        while ($column > 0) {
            $remainder = ($column - 1) % 26;
            $letter = chr(65 + $remainder).$letter;
            $column = intdiv($column - 1, 26);
        }

        return $letter;
    }
}
