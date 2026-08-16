<?php

namespace App\Services\Customers;

use App\Data\Customers\CustomerImportPreviewData;
use App\Data\Customers\CustomerImportResultData;
use App\Models\Customer;
use App\Models\CustomerDeliveryAddress;
use App\Models\CustomerExternalReference;
use App\Models\CustomerImportBatch;
use App\Models\CustomerImportRow;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CustomerImportService
{
    public function __construct(
        private CustomerImportStorageService $storageService,
        private CustomerImportDuplicateService $duplicateService,
        private CustomerCodeAllocator $codeAllocator,
    ) {}

    /**
     * @param  array<int, int|string>  $selectedRows
     */
    public function confirm(string $token, int $userId, array $selectedRows): CustomerImportResultData
    {
        $operation = fn (): CustomerImportResultData => $this->confirmWithoutLock($token, $userId, $selectedRows);
        $store = Cache::getStore();

        if (method_exists($store, 'lock')) {
            return Cache::lock('customer_import.confirm.'.$token, 30)->block(10, $operation);
        }

        return $operation();
    }

    /**
     * @param  array<int, int|string>  $selectedRows
     */
    private function confirmWithoutLock(string $token, int $userId, array $selectedRows): CustomerImportResultData
    {
        $preview = $this->storageService->get($token, $userId);
        if ($preview === null) {
            throw ValidationException::withMessages([
                'import' => 'ไม่พบข้อมูลนำเข้าหรือ Token ไม่ใช่ของผู้ใช้รายนี้',
            ]);
        }
        if (! $preview->isPending()) {
            throw ValidationException::withMessages([
                'import' => 'Token นี้ถูกใช้ไปแล้ว',
            ]);
        }
        if ($preview->errors !== []) {
            throw ValidationException::withMessages([
                'import' => 'ไม่สามารถยืนยันไฟล์ที่มีข้อผิดพลาดได้',
            ]);
        }

        $selectedRowNumbers = $this->normalizeSelectedRows($selectedRows);
        $previewRows = $this->rowsByNumber($preview->rows);
        foreach ($selectedRowNumbers as $rowNumber) {
            if (! isset($previewRows[$rowNumber])) {
                throw ValidationException::withMessages([
                    'selected_rows' => 'พบรายการที่ไม่ได้อยู่ใน Preview นี้',
                ]);
            }
            if (($previewRows[$rowNumber]['status'] ?? null) !== 'ready') {
                throw ValidationException::withMessages([
                    'selected_rows' => 'เลือกได้เฉพาะรายการที่พร้อมนำเข้าเท่านั้น',
                ]);
            }
        }

        $batch = CustomerImportBatch::create([
            'source_system' => $preview->sourceSystem,
            'original_filename' => $preview->filename,
            'file_hash' => $preview->fileHash,
            'created_by' => $userId,
            'total_parsed' => count($preview->rows),
            'ready_count' => $preview->counts()['ready'],
            'counts' => $preview->counts(),
            'status' => 'processing',
        ]);

        try {
            $result = DB::transaction(function () use ($batch, $preview, $previewRows, $selectedRowNumbers): CustomerImportResultData {
                $this->lockImportWriters();

                return $this->importWithinTransaction($batch, $preview, $previewRows, $selectedRowNumbers);
            });

            $this->storageService->markUsed($preview->token, $preview->userId);

            return $result;
        } catch (Throwable $exception) {
            $batch->forceFill([
                'status' => 'failed',
                'failure_reason' => 'เกิดข้อผิดพลาดระหว่างบันทึกข้อมูลนำเข้า',
            ])->save();

            throw $exception;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $previewRows
     * @param  list<int>  $selectedRowNumbers
     */
    private function importWithinTransaction(
        CustomerImportBatch $batch,
        CustomerImportPreviewData $preview,
        array $previewRows,
        array $selectedRowNumbers,
    ): CustomerImportResultData {
        $selected = array_fill_keys($selectedRowNumbers, true);
        $recheckedRows = $this->duplicateService->annotate($preview->sourceSystem, $preview->rows);
        $rowsToImport = [];
        $finalRows = [];

        foreach ($recheckedRows as $row) {
            $rowNumber = (int) ($row['row_number'] ?? 0);
            $wasSelected = isset($selected[$rowNumber]);
            $wasReady = ($previewRows[$rowNumber]['status'] ?? null) === 'ready';

            if ($wasSelected && ($row['status'] ?? null) === 'ready') {
                $rowsToImport[] = $row;

                continue;
            }

            if (! $wasSelected && $wasReady && ($row['status'] ?? null) === 'ready') {
                $row['status'] = 'not_selected';
                $row['reasons'] = array_values(array_unique([
                    ...($row['reasons'] ?? []),
                    'ผู้ใช้ไม่ได้เลือกรายการนี้เพื่อยืนยันนำเข้า',
                ]));
            }

            $finalRows[] = $row;
        }

        $codes = $this->codeAllocator->reserveCodes(count($rowsToImport));
        $importedByRow = [];

        foreach ($rowsToImport as $index => $row) {
            $customer = Customer::query()->create([
                'code' => $codes[$index],
                'name' => $row['name'],
                'phone' => $row['phone'] ?? null,
                'tax_number' => $row['tax_number'] ?? null,
                'branch_type' => $row['branch_type'] ?? config('customer_import.default_branch_type'),
                'branch_number' => $row['branch_number'] ?? null,
                'remark' => $row['remark'] ?? null,
                'active' => true,
            ]);

            $address = trim((string) ($row['address'] ?? ''));
            if ($address !== '') {
                CustomerDeliveryAddress::query()->create([
                    'customer_id' => $customer->id,
                    'name' => 'หลัก',
                    'receiver_name' => null,
                    'receiver_phone' => $row['phone'] ?? null,
                    'address' => $address,
                    'delivery_zone_id' => null,
                    'is_default' => true,
                ]);

                $customer->update(['address' => $address]);
            }

            CustomerExternalReference::query()->create([
                'customer_id' => $customer->id,
                'batch_id' => $batch->id,
                'source_system' => $preview->sourceSystem,
                'external_id' => $row['external_id'],
            ]);

            $row['status'] = 'imported';
            $row['customer_id'] = $customer->id;
            $importedByRow[(int) $row['row_number']] = $row;
        }

        foreach ($recheckedRows as $row) {
            $rowNumber = (int) ($row['row_number'] ?? 0);
            if (isset($importedByRow[$rowNumber])) {
                $finalRows[] = $importedByRow[$rowNumber];

                continue;
            }

            if (! in_array($rowNumber, array_column($finalRows, 'row_number'), true)) {
                $finalRows[] = $row;
            }
        }

        $statusCounts = array_fill_keys(['imported', 'duplicate', 'review_required', 'invalid', 'not_selected'], 0);
        foreach ($finalRows as $row) {
            $status = array_key_exists($row['status'] ?? '', $statusCounts) ? $row['status'] : 'invalid';
            $statusCounts[$status]++;

            CustomerImportRow::query()->create([
                'batch_id' => $batch->id,
                'customer_id' => $row['customer_id'] ?? null,
                'row_number' => (int) ($row['row_number'] ?? 0),
                'external_id' => $row['external_id'] ?? null,
                'name' => $row['name'] ?? null,
                'phone' => $row['phone'] ?? null,
                'tax_number' => $row['tax_number'] ?? null,
                'branch_type' => $row['branch_type'] ?? null,
                'branch_number' => $row['branch_number'] ?? null,
                'address' => $row['address'] ?? null,
                'remark' => $row['remark'] ?? null,
                'status' => $status,
                'reasons' => $row['reasons'] ?? [],
                'warnings' => $row['warnings'] ?? [],
                'original_values' => [],
            ]);
        }

        $batch->update([
            'imported_count' => $statusCounts['imported'],
            'duplicate_count' => $statusCounts['duplicate'],
            'review_count' => $statusCounts['review_required'],
            'invalid_count' => $statusCounts['invalid'],
            'not_selected_count' => $statusCounts['not_selected'],
            'counts' => [
                'ready' => $preview->counts()['ready'],
                ...$statusCounts,
            ],
            'status' => 'completed',
            'confirmed_at' => now(),
        ]);

        return new CustomerImportResultData(
            batchId: $batch->id,
            status: 'completed',
            totalParsed: count($recheckedRows),
            importedCount: $statusCounts['imported'],
            duplicateCount: $statusCounts['duplicate'],
            reviewCount: $statusCounts['review_required'],
            invalidCount: $statusCounts['invalid'],
            notSelectedCount: $statusCounts['not_selected'],
        );
    }

    /**
     * @param  array<int, int|string>  $selectedRows
     * @return list<int>
     */
    private function normalizeSelectedRows(array $selectedRows): array
    {
        $normalized = [];
        foreach ($selectedRows as $rowNumber) {
            if (filter_var($rowNumber, FILTER_VALIDATE_INT) === false || (int) $rowNumber < 1) {
                throw ValidationException::withMessages([
                    'selected_rows' => 'รายการที่เลือกไม่ถูกต้อง',
                ]);
            }
            $normalized[] = (int) $rowNumber;
        }

        $normalized = array_values(array_unique($normalized));
        if ($normalized === []) {
            throw ValidationException::withMessages([
                'selected_rows' => 'กรุณาเลือกรายการพร้อมนำเข้าอย่างน้อย 1 รายการ',
            ]);
        }

        sort($normalized);

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function rowsByNumber(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) ($row['row_number'] ?? 0)] = $row;
        }

        return $indexed;
    }

    private function lockImportWriters(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select("select pg_advisory_xact_lock(hashtext('atrilak:customer-import-confirm'))");
        }
    }
}
