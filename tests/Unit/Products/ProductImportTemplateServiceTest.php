<?php

namespace Tests\Unit\Products;

use App\Services\Products\ProductImportTemplateService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Tests\TestCase;

class ProductImportTemplateServiceTest extends TestCase
{
    public function test_template_contains_required_sheets_and_headers(): void
    {
        $spreadsheet = app(ProductImportTemplateService::class)->createTemplate();

        $this->assertSame(['สินค้า', 'หมวดหมู่', 'หน่วย', 'คำแนะนำ'], $spreadsheet->getSheetNames());
        $this->assertSame([
            'ชื่อสินค้า',
            'หมวดหมู่',
            'หน่วยหลัก',
            'ต้นทุน',
            'ราคาขาย',
            'จำนวนเริ่มต้น',
            'รหัสสินค้า',
            'บาร์โค้ด',
            'สถานะ',
            'ล็อกราคาขาย',
            'รายละเอียด',
        ], $spreadsheet->getSheetByName('สินค้า')->rangeToArray('A1:K1')[0]);
        $this->assertSame(DataType::TYPE_STRING, $spreadsheet->getSheetByName('สินค้า')->getCell('H2')->getDataType());
    }

    public function test_error_report_preserves_rows_and_appends_status_and_error(): void
    {
        $spreadsheet = app(ProductImportTemplateService::class)->createErrorReport([
            [
                'ชื่อสินค้า' => 'สินค้า A',
                'หมวดหมู่' => 'หมวด A',
                'สถานะ' => 'ไม่ผ่าน',
                'ข้อผิดพลาด' => 'ราคาขายไม่ถูกต้อง',
            ],
        ]);

        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame(['ชื่อสินค้า', 'หมวดหมู่', 'สถานะ', 'ข้อผิดพลาด'], $sheet->rangeToArray('A1:D1')[0]);
        $this->assertSame(['สินค้า A', 'หมวด A', 'ไม่ผ่าน', 'ราคาขายไม่ถูกต้อง'], $sheet->rangeToArray('A2:D2')[0]);
    }
}
