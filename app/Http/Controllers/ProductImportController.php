<?php

namespace App\Http\Controllers;

use App\Http\Requests\Products\ConfirmProductImportRequest;
use App\Http\Requests\Products\PreviewProductImportRequest;
use App\Services\Products\ProductImportService;
use App\Services\Products\ProductImportStorageService;
use App\Services\Products\ProductImportTemplateService;
use App\Services\Products\ProductImportValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProductImportController extends Controller
{
    public function __construct(
        private ProductImportService $importService,
        private ProductImportStorageService $storageService,
        private ProductImportTemplateService $templateService,
        private ProductImportValidationService $validationService,
    ) {}

    public function index()
    {
        return view('products.import.index');
    }

    public function template()
    {
        $spreadsheet = $this->templateService->createTemplate();

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'atrilak-product-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function preview(PreviewProductImportRequest $request)
    {
        $startedAt = microtime(true);
        $file = $request->file('file');
        $validation = $this->validationService->validate($file->getRealPath(), $file->getClientOriginalName());
        $preview = $this->storageService->store(
            (int) Auth::id(),
            $file->getClientOriginalName(),
            hash_file('sha256', $file->getRealPath()),
            $validation['rows'],
            $validation['file_errors']
        );
        Log::info('product_import.preview', [
            'user_id' => Auth::id(),
            'import_token' => $preview->token,
            'filename' => $preview->filename,
            'file_hash' => $preview->fileHash,
            'row_count' => count($preview->rows),
            'success' => $preview->isValid(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'error_summary' => array_slice($preview->errors, 0, 10),
        ]);

        return view('products.import.preview', compact('preview'));
    }

    public function confirm(ConfirmProductImportRequest $request)
    {
        $startedAt = microtime(true);
        $token = $request->string('token')->toString();
        try {
            $result = $this->importService->confirm($token, (int) Auth::id());
        } catch (ValidationException $exception) {
            Log::warning('product_import.confirm_failed', [
                'user_id' => Auth::id(),
                'import_token' => $token,
                'success' => false,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error_summary' => array_slice($exception->errors(), 0, 10),
            ]);

            return redirect()
                ->route('products.import.index')
                ->withErrors($exception->errors());
        }
        Log::info('product_import.confirm', [
            'user_id' => Auth::id(),
            'import_token' => $token,
            'row_count' => $result->productCount,
            'success' => true,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return view('products.import.result', compact('result'));
    }

    public function errors(string $token)
    {
        $preview = $this->storageService->get($token, (int) Auth::id());
        abort_unless($preview, 404);

        $rows = collect($preview->rows)
            ->map(function (array $row): array {
                $values = $row['original_values'] ?? [];
                $values['สถานะ'] = ($row['errors'] ?? []) === [] ? 'ผ่าน' : 'ไม่ผ่าน';
                $values['ข้อผิดพลาด'] = collect($row['errors'] ?? [])
                    ->map(fn (array $error): string => ($error['column'] ?? 'ข้อมูล').': '.($error['message'] ?? 'ข้อมูลไม่ถูกต้อง'))
                    ->implode('; ');

                return $values;
            })
            ->all();

        if ($preview->errors !== []) {
            $rows[] = ['สถานะ' => 'ไม่ผ่าน', 'ข้อผิดพลาด' => implode('; ', $preview->errors)];
        }

        $spreadsheet = $this->templateService->createErrorReport($rows);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'atrilak-product-import-errors-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function destroy(string $token): RedirectResponse
    {
        $this->storageService->delete($token, (int) Auth::id());

        return redirect()
            ->route('products.import.index')
            ->with('success', 'ยกเลิกข้อมูลนำเข้าสำเร็จ');
    }
}
