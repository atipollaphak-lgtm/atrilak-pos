<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customers\PreviewCustomerImportRequest;
use App\Services\Customers\CustomerImportDuplicateService;
use App\Services\Customers\CustomerImportStorageService;
use App\Services\Customers\CustomerImportTemplateService;
use App\Services\Customers\CustomerImportValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CustomerImportController extends Controller
{
    public function __construct(
        private CustomerImportValidationService $validationService,
        private CustomerImportDuplicateService $duplicateService,
        private CustomerImportStorageService $storageService,
    ) {}

    public function index()
    {
        return view('customers.import.index');
    }

    public function template(CustomerImportTemplateService $templateService)
    {
        $spreadsheet = $templateService->createTemplate();

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
        }, 'atrilak-customer-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function preview(PreviewCustomerImportRequest $request)
    {
        $startedAt = microtime(true);
        $file = $request->file('file');
        $validation = $this->validationService->validate(
            $file->getRealPath(),
            $file->getClientOriginalName(),
        );

        $sourceSystem = (string) ($validation['source_system'] ?? '');
        $rows = $validation['rows'] ?? [];
        if ($validation['file_errors'] === [] && $sourceSystem !== '') {
            $rows = $this->duplicateService->annotate($sourceSystem, $rows);
        }

        $preview = $this->storageService->store(
            (int) Auth::id(),
            $file->getClientOriginalName(),
            hash_file('sha256', $file->getRealPath()),
            $sourceSystem,
            $rows,
            $validation['file_errors'],
        );

        Log::info('customer_import.preview', [
            'user_id' => Auth::id(),
            'import_token' => $preview->token,
            'filename' => $preview->filename,
            'file_hash' => $preview->fileHash,
            'source_system' => $preview->sourceSystem,
            'row_count' => count($preview->rows),
            'success' => $preview->errors === [],
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'error_summary' => array_slice($preview->errors, 0, 10),
        ]);

        return view('customers.import.preview', compact('preview'));
    }

    public function destroy(string $token): RedirectResponse
    {
        $this->storageService->delete($token, (int) Auth::id());

        return redirect()
            ->route('customers.import.index')
            ->with('success', 'ยกเลิกข้อมูลนำเข้าสำเร็จ');
    }
}
