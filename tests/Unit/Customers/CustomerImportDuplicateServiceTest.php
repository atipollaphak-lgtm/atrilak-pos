<?php

namespace Tests\Unit\Customers;

use App\Models\Customer;
use App\Models\CustomerExternalReference;
use App\Services\Customers\CustomerImportDuplicateService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerImportDuplicateServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('customers', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('branch_type')->default('สำนักงานใหญ่');
            $table->string('branch_number')->nullable();
            $table->text('remark')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('customer_external_references', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->string('source_system');
            $table->string('external_id');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('customer_external_references');
        Schema::dropIfExists('customers');

        parent::tearDown();
    }

    public function test_duplicate_hierarchy_uses_reference_phone_then_tax_name_branch(): void
    {
        $now = now();
        $referenceCustomer = Customer::query()->create([
            'name' => 'อ้างอิงเดิม',
            'phone' => '0811111111',
        ]);
        CustomerExternalReference::query()->create([
            'customer_id' => $referenceCustomer->id,
            'source_system' => 'C2M',
            'external_id' => 'C2M-001',
        ]);

        Customer::query()->insert([
            [
                'name' => 'ลูกค้าโทรซ้ำ',
                'phone' => '0805098556',
                'tax_number' => null,
                'branch_type' => 'สำนักงานใหญ่',
                'branch_number' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'บริษัทภาษีซ้ำ',
                'phone' => '0822222222',
                'tax_number' => '0100000000001',
                'branch_type' => 'สำนักงานใหญ่',
                'branch_number' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'ชื่อเตือนอย่างเดียว',
                'phone' => '0833333333',
                'tax_number' => null,
                'branch_type' => 'สำนักงานใหญ่',
                'branch_number' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $rows = [
            $this->row('C2M-001', 'ชื่อใหม่ 1', '0891111111'),
            $this->row('C2M-002', 'ชื่อใหม่ 2', '0805098556'),
            $this->row('C2M-003', 'บริษัทภาษีซ้ำ', '0893333333', '0100000000001'),
            $this->row('C2M-004', 'บริษัทภาษีซ้ำ', '0894444444', '0100000000001', 'สาขา', '00001'),
            $this->row('C2M-005', 'ชื่อเตือนอย่างเดียว', '0895555555'),
        ];

        $annotated = app(CustomerImportDuplicateService::class)->annotate('C2M', $rows);

        $this->assertSame('duplicate', $annotated[0]['status']);
        $this->assertStringContainsString('external', implode(' ', $annotated[0]['reasons']));
        $this->assertSame('duplicate', $annotated[1]['status']);
        $this->assertStringContainsString('โทร', implode(' ', $annotated[1]['reasons']));
        $this->assertSame('duplicate', $annotated[2]['status']);
        $this->assertStringContainsString('ภาษี', implode(' ', $annotated[2]['reasons']));
        $this->assertSame('review_required', $annotated[3]['status']);
        $this->assertStringContainsString('สาขา', implode(' ', $annotated[3]['reasons']));
        $this->assertSame('ready', $annotated[4]['status']);
        $this->assertStringContainsString('ชื่อ', implode(' ', $annotated[4]['warnings']));
    }

    public function test_workbook_external_id_is_invalid_and_duplicate_phone_skips_from_second_row(): void
    {
        $rows = [
            $this->row('DUP-001', 'คนที่หนึ่ง', '0800000001'),
            $this->row('DUP-001', 'คนที่สอง', '0800000002'),
            $this->row('DUP-002', 'โทรคนแรก', '0800000003'),
            $this->row('DUP-003', 'โทรคนที่สอง', '0800000003'),
        ];

        $annotated = app(CustomerImportDuplicateService::class)->annotate('C2M', $rows);

        $this->assertSame('invalid', $annotated[0]['status']);
        $this->assertSame('invalid', $annotated[1]['status']);
        $this->assertStringContainsString('ซ้ำ', implode(' ', $annotated[0]['reasons']));
        $this->assertSame('ready', $annotated[2]['status']);
        $this->assertSame('duplicate', $annotated[3]['status']);
        $this->assertStringContainsString('เบอร์โทร', implode(' ', $annotated[3]['reasons']));
    }

    private function row(
        string $externalId,
        string $name,
        string $phone,
        ?string $taxNumber = null,
        string $branchType = 'สำนักงานใหญ่',
        ?string $branchNumber = null,
    ): array {
        return [
            'row_number' => 2,
            'external_id' => $externalId,
            'name' => $name,
            'phone' => $phone,
            'tax_number' => $taxNumber,
            'branch_type' => $branchType,
            'branch_number' => $branchNumber,
            'address' => null,
            'remark' => null,
            'status' => 'ready',
            'reasons' => [],
            'warnings' => [],
            'original_values' => [],
        ];
    }
}
