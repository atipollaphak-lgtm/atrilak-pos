<?php

namespace Tests\Unit\Products;

use App\Models\Category;
use App\Models\Unit;
use App\Services\Products\ProductImportValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProductImportValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_workbook_is_normalized_without_writing_products(): void
    {
        $category = Category::query()->create([
            'name' => 'Hardware',
            'code_prefix' => 'HAR',
            'barcode_prefix' => '101',
            'active' => true,
        ]);
        $unit = Unit::query()->create([
            'code' => 'KG',
            'name' => 'กิโลกรัม',
            'short_name' => 'กก.',
            'active' => true,
        ]);
        $path = $this->workbook([
            ['เหล็กเส้น', 'Hardware', 'กิโลกรัม', '10.00', '15.00', '5', '', '', 'ใช้งาน', 'ไม่ล็อก', 'รายละเอียด'],
        ]);

        try {
            $result = app(ProductImportValidationService::class)->validate($path);

            $this->assertSame([], $result['file_errors']);
            $this->assertCount(1, $result['rows']);
            $this->assertSame([
                'product_name' => 'เหล็กเส้น',
                'category' => 'Hardware',
                'category_id' => $category->id,
                'base_unit' => 'กิโลกรัม',
                'unit_id' => $unit->id,
                'cost_price' => '10.00',
                'selling_price' => '15.00',
                'opening_stock' => '5.0000',
                'product_code' => null,
                'barcode' => null,
                'status' => true,
                'price_locked' => false,
                'description' => 'รายละเอียด',
            ], $result['rows'][0]['values']);
            $this->assertSame([], $result['rows'][0]['errors']);
            $this->assertDatabaseCount('products', 0);
        } finally {
            @unlink($path);
        }
    }

    public function test_missing_or_unknown_headers_reject_the_file(): void
    {
        $this->category();
        $this->unit();
        $path = $this->workbook([
            ['สินค้าใหม่', 'Hardware', 'กิโลกรัม', '10', 'Header value'],
        ], ['ชื่อสินค้า', 'หมวดหมู่', 'หน่วยหลัก', 'ต้นทุน', 'Header แปลก']);

        try {
            $result = app(ProductImportValidationService::class)->validate($path);

            $this->assertContains('ไม่รู้จัก Header: Header แปลก', $result['file_errors']);
            $this->assertContains('ไม่พบ Header ที่จำเป็น: ราคาขาย', $result['file_errors']);
        } finally {
            @unlink($path);
        }
    }

    public function test_formula_and_invalid_numeric_values_are_row_errors(): void
    {
        $this->category();
        $this->unit();
        $path = $this->workbook([
            ['สินค้าใหม่', 'Hardware', 'กิโลกรัม', '=10+1', '-15', '0', '', '', 'Unknown', 'No', ''],
        ]);

        try {
            $result = app(ProductImportValidationService::class)->validate($path);
            $errors = collect($result['rows'][0]['errors'])->pluck('column')->all();

            $this->assertContains('ข้อมูล', $errors);
            $this->assertContains('cost_price', $errors);
            $this->assertContains('selling_price', $errors);
            $this->assertContains('status', $errors);
        } finally {
            @unlink($path);
        }
    }

    private function category(): Category
    {
        return Category::query()->create([
            'name' => 'Hardware',
            'code_prefix' => 'HAR',
            'barcode_prefix' => '001',
            'active' => true,
        ]);
    }

    private function unit(): Unit
    {
        return Unit::query()->create([
            'code' => 'KG',
            'name' => 'กิโลกรัม',
            'short_name' => 'กก.',
            'active' => true,
        ]);
    }

    private function workbook(array $rows, ?array $headers = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'product-import-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('สินค้า');
        $sheet->fromArray($headers ?? array_values(config('product_import.headers')), null, 'A1');
        $sheet->fromArray($rows, null, 'A2');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
