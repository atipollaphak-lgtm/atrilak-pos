<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackupService;
use Illuminate\Console\Command;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Backup PostgreSQL database for ATRILAK POS';

    public function handle(DatabaseBackupService $databaseBackupService): int
    {
        $result = $databaseBackupService->create();
        if (! $result->successful()) {
            $this->error('Backup failed.');

            return self::FAILURE;
        }
        $this->info('Backup created: '.$result->fileName());

        return self::SUCCESS;
    }
}
