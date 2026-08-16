<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customers\ConfirmCustomerImportRequest;
use App\Http\Requests\Customers\PreviewCustomerImportRequest;
use App\Models\CustomerImportBatch;
use App\Services\Customers\CustomerImportDuplicateService;
use App\Services\Customers\CustomerImportService;
use App\Services\Customers\CustomerImportStorageService;
use App\Services\Customers\CustomerImportTemplateService;
use App\Services\Customers\CustomerImportValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class CustomerImportController extends Controller
{
    public function __construct(
        private CustomerImportValidationService $validationService,
        private CustomerImportDuplicateService $duplicateService,
        private CustomerImportStorageService $storageService,
        private CustomerImportService $importService,
        private CustomerImportTemplateService $templateService,
    ) {}

    public function index()
    {
        return view('customers.import.index');
    }

    public function template()
    {
        $spreadsheet = $this->templateService->createTemplate();

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
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

    public function confirm(ConfirmCustomerImportRequest $request)
    {
        $token = $request->string('token')->toString();

        try {
            $result = $this->importService->confirm(
                $token,
                (int) Auth::id(),
                $request->input('selected_rows', []),
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('customers.import.index')
                ->withErrors($exception->errors());
        } catch (Throwable $exception) {
            Log::error('customer_import.confirm_failed', [
                'user_id' => Auth::id(),
                'import_token' => $token,
                'error_class' => get_class($exception),
            ]);

            return redirect()
                ->route('customers.import.index')
                ->withErrors(['import' => 'นำเข้าสมาชิกไม่สำเร็จ ข้อมูลยังไม่ถูกบันทึกบางส่วน']);
        }

        Log::info('customer_import.confirm', [
            'user_id' => Auth::id(),
            'import_token' => $token,
            'batch_id' => $result->batchId,
            'imported_count' => $result->importedCount,
            'success' => true,
        ]);

        return view('customers.import.result', compact('result'));
    }

    public function history()
    {
        $batches = CustomerImportBatch::query()
            ->with('creator')
            ->latest('id')
            ->paginate(20);

        return view('customers.import.history.index', compact('batches'));
    }

    public function show(CustomerImportBatch $batch)
    {
        $batch->load('creator');
        $rows = $batch->rows()->orderBy('row_number')->paginate(50);

        return view('customers.import.history.show', compact('batch', 'rows'));
    }

    public function report(CustomerImportBatch $batch)
    {
        $rows = $batch->rows()
            ->whereIn('status', ['duplicate', 'review_required', 'invalid'])
            ->orderBy('row_number')
            ->get()
            ->map(static fn ($row): array => $row->toArray())
            ->all();
        $csv = $this->templateService->createCsvReport($rows);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="customer-import-report-'.$batch->id.'.csv"',
        ]);
    }

    public function destroy(string $token): RedirectResponse
    {
        $this->storageService->delete($token, (int) Auth::id());

        return redirect()
            ->route('customers.import.index')
            ->with('success', 'ยกเลิกข้อมูลนำเข้าสำเร็จ');
    }
}
