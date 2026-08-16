<?php

namespace Tests\Unit\Customers;

use App\Services\Customers\CustomerImportValidationService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CustomerImportValidationServiceTest extends TestCase
{
    public function test_c2m_tax_continuation_is_attached_to_the_previous_customer(): void
    {
        $path = $this->workbook([
            [1, 'ลูกค้าทั่วไป', 'บริษัท ABC จำกัด', 'ร้านค้า', '99/1 ถนนสุขุมวิท', 805098556, 'abc@example.test', ''],
            ['เลขผู้เสียภาษี 0123456789012'],
        ]);

        try {
            $result = app(CustomerImportValidationService::class)->validate($path, 'members.xlsx');

            $this->assertSame([], $result['file_errors']);
            $this->assertSame('C2M', $result['source_system']);
            $this->assertCount(1, $result['rows']);
            $this->assertSame('1', $result['rows'][0]['external_id']);
            $this->assertSame('0123456789012', $result['rows'][0]['tax_number']);
            $this->assertSame('บริษัท ABC จำกัด', $result['rows'][0]['name']);
        } finally {
            @unlink($path);
        }
    }

    public function test_atrilak_template_preserves_blank_address_and_unicode_name(): void
    {
        $path = $this->workbook([
            ['A-100', 'ร้านวัสดุไทย', '0800000001', '0100000000001', 'สำนักงานใหญ่', '', '', 'รับของช่วงบ่าย'],
        ], [
            'external_id', 'name', 'phone', 'tax_id', 'branch_type', 'branch_number', 'address', 'remark',
        ]);

        try {
            $result = app(CustomerImportValidationService::class)->validate($path, 'atrilak-members.xlsx');

            $this->assertSame([], $result['file_errors']);
            $this->assertSame('ATRILAK_TEMPLATE', $result['source_system']);
            $this->assertNull($result['rows'][0]['address']);
            $this->assertSame('ร้านวัสดุไทย', $result['rows'][0]['name']);
            $this->assertSame('รับของช่วงบ่าย', $result['rows'][0]['remark']);
        } finally {
            @unlink($path);
        }
    }

    public function test_missing_required_header_is_a_file_error(): void
    {
        $path = $this->workbook([
            [1, 'บริษัทที่ไม่มีชื่อ', '0800000001'],
        ], ['ลำดับ', 'เบอร์โทร', 'ที่อยู่']);

        try {
            $result = app(CustomerImportValidationService::class)->validate($path, 'members.xlsx');

            $this->assertNotEmpty($result['file_errors']);
            $this->assertStringContainsString('Header', implode(' ', $result['file_errors']));
        } finally {
            @unlink($path);
        }
    }

    public function test_malformed_tax_continuation_requires_review_without_creating_a_row(): void
    {
        $path = $this->workbook([
            [1, '', 'ลูกค้าไม่มีเลขภาษีที่ถูกต้อง', '', 'ที่อยู่', '0800000001', '', ''],
            ['เลขผู้เสียภาษี ABC'],
        ]);

        try {
            $result = app(CustomerImportValidationService::class)->validate($path, 'members.xlsx');

            $this->assertCount(1, $result['rows']);
            $this->assertSame('review_required', $result['rows'][0]['status']);
            $this->assertStringContainsString('ภาษี', implode(' ', $result['rows'][0]['reasons']));
        } finally {
            @unlink($path);
        }
    }

    public function test_malformed_tax_continuation_does_not_downgrade_an_invalid_customer_row(): void
    {
        $path = $this->workbook([
            [1, '', '', '', 'ที่อยู่', '0800000001', '', ''],
            ['เลขผู้เสียภาษี ABC'],
        ]);

        try {
            $result = app(CustomerImportValidationService::class)->validate($path, 'members.xlsx');

            $this->assertCount(1, $result['rows']);
            $this->assertSame('invalid', $result['rows'][0]['status']);
            $this->assertStringContainsString('ภาษี', implode(' ', $result['rows'][0]['reasons']));
        } finally {
            @unlink($path);
        }
    }

    public function test_blank_name_is_invalid_and_embedded_phone_requires_review_when_primary_is_blank(): void
    {
        $path = $this->workbook([
            [1, '', '', '', 'ที่อยู่', '', '', ''],
            [2, '', 'เทือง คลองดู่ 0969534388', '', 'ที่อยู่', '', '', ''],
        ]);

        try {
            $result = app(CustomerImportValidationService::class)->validate($path, 'members.xlsx');

            $this->assertSame('invalid', $result['rows'][0]['status']);
            $this->assertSame('review_required', $result['rows'][1]['status']);
            $this->assertStringContainsString('0969534388', implode(' ', $result['rows'][1]['warnings']));
            $this->assertSame('เทือง คลองดู่ 0969534388', $result['rows'][1]['name']);
        } finally {
            @unlink($path);
        }
    }

    public function test_xls_filename_is_rejected_with_save_as_guidance(): void
    {
        $path = $this->workbook([
            [1, '', 'ชื่อ', '', '', '', '', ''],
        ]);

        try {
            $result = app(CustomerImportValidationService::class)->validate($path, 'c2m-members.xls');

            $this->assertStringContainsString('.xls', implode(' ', $result['file_errors']));
            $this->assertStringContainsString('Save As', implode(' ', $result['file_errors']));
            $this->assertSame([], $result['rows']);
        } finally {
            @unlink($path);
        }
    }

    public function test_formula_cell_is_rejected_as_a_row_error(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'customer-import-formula-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['ลำดับ', 'การติดต่อ', 'ชื่อ', 'กลุ่มลูกค้า', 'ที่อยู่', 'เบอร์โทร', 'อีเมล์', 'จัดการ'], null, 'A1');
        $sheet->fromArray([1, '', 'สูตรต้องห้าม', '', '', '', '', ''], null, 'A2');
        $sheet->setCellValue('C2', '=1+1');
        (new Xlsx($spreadsheet))->save($path);

        try {
            $result = app(CustomerImportValidationService::class)->validate($path, 'members.xlsx');

            $this->assertSame('invalid', $result['rows'][0]['status']);
            $this->assertStringContainsString('สูตร', implode(' ', $result['rows'][0]['reasons']));
        } finally {
            @unlink($path);
        }
    }

    private function workbook(array $rows, ?array $headers = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'customer-import-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers ?? [
            'ลำดับ', 'การติดต่อ', 'ชื่อ', 'กลุ่มลูกค้า', 'ที่อยู่', 'เบอร์โทร', 'อีเมล์', 'จัดการ',
        ], null, 'A1');
        $sheet->fromArray($rows, null, 'A2');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
