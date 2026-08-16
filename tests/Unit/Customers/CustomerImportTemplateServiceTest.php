<?php

namespace Tests\Unit\Customers;

use App\Services\Customers\CustomerImportTemplateService;
use Tests\TestCase;

class CustomerImportTemplateServiceTest extends TestCase
{
    public function test_template_contains_only_the_supported_customer_headers(): void
    {
        $spreadsheet = app(CustomerImportTemplateService::class)->createTemplate();

        $this->assertSame(
            config('customer_import.template_headers'),
            $spreadsheet->getActiveSheet()->rangeToArray('A1:H1', null, true, false)[0],
        );
    }

    public function test_csv_report_has_utf8_bom_and_neutralizes_formula_like_values(): void
    {
        $csv = app(CustomerImportTemplateService::class)->createCsvReport([
            [
                'external_id' => '=2+2',
                'name' => '+สมาชิก',
                'phone' => '-0800000000',
                'address' => '@address',
                'tax_number' => '0100000000001',
                'status' => 'duplicate',
                'reasons' => ['เหตุผล'],
                'warnings' => ['คำเตือน'],
            ],
        ]);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=2+2", $csv);
        $this->assertStringContainsString("'+สมาชิก", $csv);
        $this->assertStringContainsString("'-0800000000", $csv);
        $this->assertStringContainsString("'@address", $csv);
        $this->assertStringContainsString('เหตุผล', $csv);
        $this->assertStringContainsString('คำเตือน', $csv);
    }
}
