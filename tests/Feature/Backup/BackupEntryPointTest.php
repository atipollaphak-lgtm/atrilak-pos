<?php

namespace Tests\Feature\Backup;

use App\Services\Backup\DatabaseBackupResult;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class BackupEntryPointTest extends TestCase
{
    public function test_manual_backup_command_returns_failure_when_the_shared_service_fails(): void
    {
        $this->app->instance(DatabaseBackupService::class, new class extends DatabaseBackupService
        {
            public function create(): DatabaseBackupResult
            {
                return DatabaseBackupResult::failure('process_non_zero', 1);
            }
        });

        $this->artisan('atrilak:backup')
            ->expectsOutput('Backup failed.')
            ->assertExitCode(1);
    }

    public function test_database_backup_command_is_not_scheduled(): void
    {
        $scheduledBackup = collect(app(Schedule::class)->events())
            ->contains(fn ($event): bool => str_contains($event->command, 'atrilak:backup'));

        $this->assertFalse($scheduledBackup);
    }

    public function test_database_backup_command_returns_failure_when_the_shared_service_fails(): void
    {
        $this->app->instance(DatabaseBackupService::class, new class extends DatabaseBackupService
        {
            public function create(): DatabaseBackupResult
            {
                return DatabaseBackupResult::failure('process_non_zero', 1);
            }
        });

        $this->artisan('backup:database')
            ->expectsOutput('Backup failed.')
            ->assertExitCode(1);
    }

    public function test_manual_backup_uses_the_shared_service_and_shows_safe_thai_messages(): void
    {
        $this->app->instance(DatabaseBackupService::class, new class extends DatabaseBackupService
        {
            public function create(): DatabaseBackupResult
            {
                return DatabaseBackupResult::skipped('backup_in_progress');
            }
        });
        Process::fake()->preventStrayProcesses();

        $this->withoutMiddleware()
            ->post(route('backups.create'))
            ->assertRedirect()
            ->assertSessionHas('error', 'กำลังสำรองข้อมูลอยู่ กรุณาลองใหม่อีกครั้ง');
    }
}
