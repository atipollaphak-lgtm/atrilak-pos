<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AutoBackupCommand extends Command
{
    protected $signature = 'atrilak:backup';

    protected $description =
        'Create automatic database backup';

    public function handle()
    {
        $pgDumpPath =
            'C:\\Program Files\\PostgreSQL\\17\\bin\\pg_dump.exe';

        $database = env('DB_DATABASE');
        $username = env('DB_USERNAME');
        $host = env('DB_HOST');
        $port = env('DB_PORT');
        $password = env('DB_PASSWORD');

        $backupDir =
            storage_path('app/backups');

        if (!File::exists($backupDir)) {
            File::makeDirectory(
                $backupDir,
                0755,
                true
            );
        }

        $fileName =
            'auto_backup_' .
            date('Ymd_His') .
            '.sql';

        $filePath =
            $backupDir .
            DIRECTORY_SEPARATOR .
            $fileName;

        $command =
            '"' . $pgDumpPath . '"'
            . ' -h ' . $host
            . ' -p ' . $port
            . ' -U ' . $username
            . ' -d ' . $database
            . ' -F p'
            . ' --clean'
            . ' --if-exists'
            . ' -f "' . $filePath . '"';

        putenv(
            'PGPASSWORD=' . $password
        );

        exec(
            $command,
            $output,
            $resultCode
        );

        if ($resultCode === 0) {

            $this->info(
                'Backup Success : '
                . $fileName
            );

        } else {

            $this->error(
                'Backup Failed'
            );

        }

        return 0;
    }
}
