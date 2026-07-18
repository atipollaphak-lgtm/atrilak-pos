<?php

namespace App\Http\Controllers;

use App\Services\Backup\DatabaseBackupService;
use Illuminate\Support\Facades\File;

class BackupController extends Controller
{
    public function __construct(private DatabaseBackupService $databaseBackupService) {}

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
