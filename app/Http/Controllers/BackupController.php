<?php

namespace App\Http\Controllers;

use App\Console\Commands\ResetBusinessDataCommand;
use App\Services\Backup\DatabaseBackupService;
use App\Services\BusinessDataResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class BackupController extends Controller
{
    public function __construct(
        private DatabaseBackupService $databaseBackupService,
        private BusinessDataResetService $businessDataResetService,
    ) {}

    public function index()
    {
        $backupDir = storage_path('app/backups');

        $files = collect();

        if (File::exists($backupDir)) {
            $files = collect(File::files($backupDir))
                ->sortByDesc(function ($file) {
                    return $file->getMTime();
                });
        }

        return view(
            'backups.index',
            compact('files')
        );
    }

    public function createBackup()
    {
        $result = $this->databaseBackupService->create();
        if ($result->reasonCode() === 'backup_in_progress') {
            return back()->with('error', 'กำลังสำรองข้อมูลอยู่ กรุณาลองใหม่อีกครั้ง');
        }
        if (! $result->successful()) {
            return back()->with('error', 'Backup ไม่สำเร็จ กรุณาตรวจสอบการตั้งค่า PostgreSQL');
        }

        return back()->with('success', 'สร้าง Backup สำเร็จ: '.$result->fileName());
    }

    public function resetBusinessData(Request $request)
    {
        $validated = $request->validate([
            'acknowledged' => ['accepted'],
            'confirmation' => [
                'required',
                'string',
                Rule::in([ResetBusinessDataCommand::CONFIRMATION]),
            ],
            'password' => ['required', 'current_password'],
        ]);

        try {
            $result = $this->businessDataResetService->run(
                $this->databaseBackupService,
                $request->user()->id,
            );
        } catch (Throwable $exception) {
            Log::error('business_data_reset.web_failed', [
                'database' => config('database.connections.pgsql.database'),
                'exception' => $exception::class,
            ]);

            return back()->with('error', 'Business data reset failed. Check the application log.');
        }

        $reset = $result['reset'];

        return back()
            ->with('success', 'ล้างข้อมูลธุรกิจเรียบร้อยแล้ว')
            ->with('reset_summary', [
                'status' => 'success',
                'completed_at' => now()->toIso8601String(),
                'backup_file' => $result['backup']['file_name'],
                'backup_sha256' => substr($result['backup']['sha256'], 0, 12),
                'users' => $reset['protected_after']['users'] ?? null,
                'settings' => $reset['protected_after']['settings'] ?? null,
            ]);
    }

    public function downloadFile($fileName)
    {
        $fileName = basename($fileName);

        $filePath = storage_path(
            'app/backups/'.$fileName
        );

        if (! file_exists($filePath)) {
            return back()->with(
                'error',
                'ไม่พบไฟล์ Backup'
            );
        }

        return response()->download($filePath);
    }
}
