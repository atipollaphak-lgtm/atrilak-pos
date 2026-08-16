<?php

namespace Tests\Feature\Customers;

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CustomerImportPreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            Authenticate::class,
            RoleMiddleware::class,
            ValidateCsrfToken::class,
        ]);

        Schema::create('customers', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->text('remark')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('branch_type')->default('สำนักงานใหญ่');
            $table->string('branch_number')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('customer_delivery_addresses', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('name');
            $table->text('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
        Schema::create('customer_external_references', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->string('source_system');
            $table->string('external_id');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('customer_external_references');
        Schema::dropIfExists('customer_delivery_addresses');
        Schema::dropIfExists('customers');

        parent::tearDown();
    }

    public function test_valid_preview_writes_no_customer_address_or_external_reference(): void
    {
        $response = $this->post(route('customers.import.preview'), [
            'file' => $this->xlsx([
                [1, '', 'บริษัท Preview', '', 'ที่อยู่ Preview', 805098556, '', ''],
                ['เลขผู้เสียภาษี 0123456789012'],
            ]),
        ]);

        $response->assertOk()
            ->assertSee('Preview สมาชิกนำเข้า')
            ->assertSee('บริษัท Preview')
            ->assertSee('0123456789012');
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('customer_delivery_addresses', 0);
        $this->assertDatabaseCount('customer_external_references', 0);
    }

    private function xlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'customer-import-upload-').'.xlsx';
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            'ลำดับ', 'การติดต่อ', 'ชื่อ', 'กลุ่มลูกค้า', 'ที่อยู่', 'เบอร์โทร', 'อีเมล์', 'จัดการ',
        ], null, 'A1');
        $sheet->fromArray($rows, null, 'A2');
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            'members.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }
}
