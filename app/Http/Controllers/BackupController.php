<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;

class BackupController extends Controller
{
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
        $pgDumpPath = 'C:\\Program Files\\PostgreSQL\\17\\bin\\pg_dump.exe';

        $database = env('DB_DATABASE');
        $username = env('DB_USERNAME');
        $host = env('DB_HOST');
        $port = env('DB_PORT');
        $password = env('DB_PASSWORD');

        $backupDir = storage_path('app/backups');

        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $fileName = 'atrilak_backup_' . date('Ymd_His') . '.sql';

        $filePath = $backupDir . DIRECTORY_SEPARATOR . $fileName;

        $command = '"' . $pgDumpPath . '"'
            . ' -h ' . $host
            . ' -p ' . $port
            . ' -U ' . $username
            . ' -d ' . $database
            . ' -F p'
            . ' --clean'
            . ' --if-exists'
            . ' -f "' . $filePath . '"';

        putenv('PGPASSWORD=' . $password);

        exec($command, $output, $resultCode);

        if ($resultCode !== 0 || !file_exists($filePath)) {
            return back()->with(
                'error',
                'Backup ไม่สำเร็จ กรุณาตรวจสอบ PostgreSQL หรือรหัสผ่านฐานข้อมูล'
            );
        }

        return back()->with(
            'success',
            'สร้าง Backup สำเร็จ: ' . $fileName
        );
    }

    public function downloadFile($fileName)
    {
        $fileName = basename($fileName);

        $filePath = storage_path(
            'app/backups/' . $fileName
        );

        if (!file_exists($filePath)) {
            return back()->with(
                'error',
                'ไม่พบไฟล์ Backup'
            );
        }

        return response()->download($filePath);
    }

    public function restore(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file',
        ]);

        $pgRestorePath = 'C:\\Program Files\\PostgreSQL\\17\\bin\\psql.exe';

        $database = env('DB_DATABASE');
        $username = env('DB_USERNAME');
        $host = env('DB_HOST');
        $port = env('DB_PORT');
        $password = env('DB_PASSWORD');

        $filePath = $request->file('backup_file')->getRealPath();

        putenv('PGPASSWORD=' . $password);

        $command = '"' . $pgRestorePath . '"'
            . ' -h ' . $host
            . ' -p ' . $port
            . ' -U ' . $username
            . ' -d ' . $database
            . ' -v ON_ERROR_STOP=1'
            . ' -f "' . $filePath . '"';

        $output = [];
        $resultCode = null;

        exec($command . ' 2>&1', $output, $resultCode);

        if ($resultCode !== 0) {
            return redirect()
                ->route('backups.index')
                ->with('error', 'Restore ไม่สำเร็จ: ' . implode("\n", $output));
        }

        return redirect()
            ->route('backups.index')
            ->with('success', 'Restore Database สำเร็จ');
    }
}
