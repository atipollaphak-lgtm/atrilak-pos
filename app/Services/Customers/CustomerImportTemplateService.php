<?php

namespace App\Services\Customers;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class CustomerImportTemplateService
{
    public function createTemplate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Customers');
        $headers = config('customer_import.template_headers');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:H1');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('D:D')->getNumberFormat()->setFormatCode('@');
        $sheet->setCellValueExplicit('D2', '', DataType::TYPE_STRING);

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setWidth(in_array($column, ['B', 'G', 'H'], true) ? 32 : 20);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('คำแนะนำ');
        $instructions->fromArray([
            ['คำแนะนำการนำเข้าสมาชิก'],
            ['external_id ต้องคงเดิมเมื่อเป็นข้อมูลจาก source เดิม เพื่อป้องกันการนำเข้าซ้ำ'],
            ['name เป็นข้อมูลบังคับ ห้ามใส่สูตร และระบบจะไม่แก้ spelling หรือเอาเบอร์ออกจากชื่อ'],
            ['phone และ tax_id จะถูกเก็บเป็นข้อความเพื่อรักษาเลข 0 ด้านหน้า'],
            ['branch_type ใช้ สำนักงานใหญ่ หรือ สาขา และ branch_number ใช้กับสาขา'],
            ['address ว่างได้ และระบบจะไม่กำหนด Delivery Zone อัตโนมัติ'],
        ]);
        $instructions->getColumnDimension('A')->setWidth(110);
        $instructions->getStyle('A1')->getFont()->setBold(true);
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * @param  iterable<array<string, mixed>>  $rows
     */
    public function createCsvReport(iterable $rows): string
    {
        $headers = ['C2M ID', 'ชื่อ', 'เบอร์โทร', 'ที่อยู่', 'Tax ID', 'สถานะ', 'เหตุผล'];
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $headers, ',', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($stream, [
                $this->safeCellValue($row['external_id'] ?? ''),
                $this->safeCellValue($row['name'] ?? ''),
                $this->safeCellValue($row['phone'] ?? ''),
                $this->safeCellValue($row['address'] ?? ''),
                $this->safeCellValue($row['tax_number'] ?? ''),
                $this->safeCellValue($row['status'] ?? ''),
                $this->safeCellValue(implode('; ', [
                    ...($row['reasons'] ?? []),
                    ...($row['warnings'] ?? []),
                ])),
            ], ',', '"', '\\');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? "\xEF\xBB\xBF" : $csv;
    }

    private function safeCellValue(mixed $value): string
    {
        $value = is_scalar($value) || $value === null
            ? (string) $value
            : (json_encode($value, JSON_UNESCAPED_UNICODE) ?: '');

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
