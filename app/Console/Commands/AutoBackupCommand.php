<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackupService;
use Illuminate\Console\Command;

class AutoBackupCommand extends Command
{
    protected $signature = 'atrilak:backup';

    protected $description =
        'Create automatic database backup';

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
