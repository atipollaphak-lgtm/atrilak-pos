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

        return $spreadsheet;
    }
}
