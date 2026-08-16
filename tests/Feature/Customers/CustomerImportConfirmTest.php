<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\CustomerImportRow;
use App\Services\Customers\CustomerImportService;
use App\Services\Customers\CustomerImportStorageService;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class CustomerImportConfirmTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('customers', function ($table): void {
            $table->id();
            $table->string('code')->nullable();
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
            $table->string('receiver_name')->nullable();
            $table->string('receiver_phone')->nullable();
            $table->text('address')->nullable();
            $table->text('landmark')->nullable();
            $table->unsignedBigInteger('delivery_zone_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_default')->default(false);
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_import_batches', function ($table): void {
            $table->id();
            $table->string('source_system');
            $table->string('original_filename');
            $table->string('file_hash', 64);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('total_parsed')->default(0);
            $table->unsignedInteger('ready_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->unsignedInteger('not_selected_count')->default(0);
            $table->json('counts')->nullable();
            $table->string('status')->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_import_rows', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedInteger('row_number');
            $table->string('external_id')->nullable();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('branch_type')->nullable();
            $table->string('branch_number')->nullable();
            $table->text('address')->nullable();
            $table->text('remark')->nullable();
            $table->string('status');
            $table->json('reasons')->nullable();
            $table->json('warnings')->nullable();
            $table->json('original_values')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_external_references', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->string('source_system');
            $table->string('external_id');
            $table->timestamps();
            $table->unique(['source_system', 'external_id']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('customer_external_references');
        Schema::dropIfExists('customer_import_rows');
        Schema::dropIfExists('customer_import_batches');
        Schema::dropIfExists('customer_delivery_addresses');
        Schema::dropIfExists('customers');

        parent::tearDown();
    }

    public function test_confirm_creates_customer_address_and_external_reference_atomically(): void
    {
        $row = $this->row('1001', 'บริษัททดสอบ', '0805098556', '0123456789012', 'สำนักงานใหญ่', null, 'ที่อยู่ดิบ');
        $row['original_values'] = ['อีเมล์' => 'private@example.test'];
        $token = $this->storePreview([$row]);

        $result = app(CustomerImportService::class)->confirm($token, 7, [2]);

        $this->assertSame(1, $result->importedCount);
        $this->assertSame(1, $result->totalParsed);
        $this->assertDatabaseHas('customers', [
            'name' => 'บริษัททดสอบ',
            'phone' => '0805098556',
            'tax_number' => '0123456789012',
            'branch_type' => 'สำนักงานใหญ่',
            'branch_number' => null,
            'remark' => 'หมายเหตุทดสอบ',
        ]);
        $this->assertDatabaseHas('customer_delivery_addresses', [
            'address' => 'ที่อยู่ดิบ',
            'delivery_zone_id' => null,
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('customer_external_references', [
            'source_system' => 'C2M',
            'external_id' => '1001',
        ]);
        $this->assertDatabaseHas('customer_import_rows', ['row_number' => 2, 'status' => 'imported']);
        $this->assertSame([], CustomerImportRow::query()->sole()->original_values);
        $this->assertDatabaseHas('customer_import_batches', ['status' => 'completed', 'imported_count' => 1]);
        $this->assertSame('used', app(CustomerImportStorageService::class)->get($token, 7)->state);
    }

    public function test_review_duplicate_invalid_and_not_selected_rows_are_not_imported(): void
    {
        $token = $this->storePreview([
            $this->row('2001', 'พร้อมนำเข้า', '0800000001'),
            $this->row('2002', 'ไม่ได้เลือก', '0800000002'),
            $this->row('2003', 'ต้องตรวจ', '0800000003', null, 'สำนักงานใหญ่', null, null, 'review_required'),
            $this->row('2004', 'ซ้ำ', '0800000004', null, 'สำนักงานใหญ่', null, null, 'duplicate'),
            $this->row('2005', '', '0800000005', null, 'สำนักงานใหญ่', null, null, 'invalid'),
        ]);

        $result = app(CustomerImportService::class)->confirm($token, 7, [2]);

        $this->assertSame(1, $result->importedCount);
        $this->assertSame(1, $result->notSelectedCount);
        $this->assertSame(1, $result->reviewCount);
        $this->assertSame(1, $result->duplicateCount);
        $this->assertSame(1, $result->invalidCount);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('customer_import_rows', 5);
        $this->assertDatabaseHas('customer_import_rows', ['row_number' => 3, 'status' => 'not_selected']);
        $this->assertDatabaseHas('customer_import_rows', ['row_number' => 4, 'status' => 'review_required']);
        $this->assertDatabaseHas('customer_import_rows', ['row_number' => 5, 'status' => 'duplicate']);
        $this->assertDatabaseHas('customer_import_rows', ['row_number' => 6, 'status' => 'invalid']);
    }

    public function test_repeating_the_same_workbook_creates_zero_new_customers(): void
    {
        $rows = [$this->row('3001', 'นำเข้าครั้งแรก', '0800000011')];
        $firstToken = $this->storePreview($rows);
        app(CustomerImportService::class)->confirm($firstToken, 7, [2]);

        $secondToken = $this->storePreview($rows);
        $result = app(CustomerImportService::class)->confirm($secondToken, 7, [2]);

        $this->assertSame(0, $result->importedCount);
        $this->assertSame(1, $result->duplicateCount);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('customer_external_references', 1);
    }

    public function test_failure_rolls_back_business_writes_marks_batch_failed_and_keeps_token_pending(): void
    {
        $token = $this->storePreview([
            $this->row('4001', 'คนที่หนึ่ง', '0800000021'),
            $this->row('4002', 'คนที่สอง', '0800000022'),
        ]);
        $created = 0;
        Customer::creating(function () use (&$created): void {
            $created++;
            if ($created === 2) {
                throw new RuntimeException('synthetic import failure');
            }
        });

        try {
            app(CustomerImportService::class)->confirm($token, 7, [2, 3]);
            $this->fail('The synthetic failure should be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('synthetic import failure', $exception->getMessage());
        } finally {
            Customer::flushEventListeners();
        }

        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('customer_delivery_addresses', 0);
        $this->assertDatabaseCount('customer_external_references', 0);
        $this->assertDatabaseCount('customer_import_rows', 0);
        $this->assertDatabaseHas('customer_import_batches', ['status' => 'failed']);
        $this->assertSame('pending', app(CustomerImportStorageService::class)->get($token, 7)->state);
    }

    private function storePreview(array $rows): string
    {
        $rows = array_values($rows);
        foreach ($rows as $index => &$row) {
            $row['row_number'] = $index + 2;
        }
        unset($row);

        return app(CustomerImportStorageService::class)->store(
            7,
            'members.xlsx',
            hash('sha256', json_encode($rows)),
            'C2M',
            $rows,
            [],
        )->token;
    }

    private function row(
        string $externalId,
        string $name,
        string $phone,
        ?string $taxNumber = null,
        string $branchType = 'สำนักงานใหญ่',
        ?string $branchNumber = null,
        ?string $address = null,
        string $status = 'ready',
    ): array {
        return [
            'row_number' => 2,
            'external_id' => $externalId,
            'name' => $name,
            'phone' => $phone,
            'tax_number' => $taxNumber,
            'branch_type' => $branchType,
            'branch_number' => $branchNumber,
            'address' => $address,
            'remark' => 'หมายเหตุทดสอบ',
            'status' => $status,
            'reasons' => $status === 'ready' ? [] : ['สถานะทดสอบ'],
            'warnings' => [],
            'original_values' => [],
        ];
    }
}
