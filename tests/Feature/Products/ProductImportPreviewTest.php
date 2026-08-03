<?php

namespace Tests\Feature\Products;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Category;
use App\Models\Unit;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProductImportPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            Authenticate::class,
            RoleMiddleware::class,
            ValidateCsrfToken::class,
        ]);
    }

    public function test_valid_upload_shows_preview_and_does_not_create_products(): void
    {
        $this->category();
        $this->unit();

        $response = $this->post(route('products.import.preview'), [
            'file' => $this->xlsx([
                ['สินค้าใหม่', 'Hardware', 'กิโลกรัม', '10', '15', '0', '', '', 'Active', 'No', 'รายละเอียด'],
            ]),
        ]);

        $response->assertOk()
            ->assertSee('ตรวจสอบสินค้านำเข้า')
            ->assertSee('สินค้าใหม่')
            ->assertSee('ยืนยันนำเข้าสินค้าทั้งชุด');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_invalid_upload_shows_row_error_and_error_report_link(): void
    {
        $this->category();
        $this->unit();

        $response = $this->post(route('products.import.preview'), [
            'file' => $this->xlsx([
                ['สินค้าผิด', 'Unknown', 'กิโลกรัม', 'cost', '-15', '0', '', '', 'Unknown', 'No', ''],
            ]),
        ]);

        $response->assertOk()
            ->assertSee('ไม่ผ่าน')
            ->assertSee('ดาวน์โหลด Error Report')
            ->assertDontSee('ยืนยันนำเข้าสินค้าทั้งชุด');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_template_download_returns_xlsx(): void
    {
        $response = $this->get(route('products.import.template'));

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    private function xlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'product-import-upload-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('สินค้า');
        $sheet->fromArray(array_values(config('product_import.headers')), null, 'A1');
        $sheet->fromArray($rows, null, 'A2');
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'products.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
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
}
