<?php

namespace Tests\Feature\Backup;

use App\Services\Backup\DatabaseBackupService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutException;
use Symfony\Component\Process\Process as SymfonyProcess;
use Tests\TestCase;

class DatabaseBackupServiceTest extends TestCase
{
    private string $backupDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupDirectory = storage_path('framework/testing/backups/'.uniqid('', true));

        config()->set([
            'backup.pg_dump_path' => __FILE__,
            'backup.directory' => $this->backupDirectory,
            'backup.retention_days' => 30,
            'backup.lock_seconds' => 3600,
            'backup.process_timeout_seconds' => 30,
            'database.default' => 'pgsql',
            'database.connections.pgsql' => [
                'host' => '127.0.0.1',
                'port' => '5432',
                'database' => 'atrilak_pos_test',
                'username' => 'atrilak_test',
                'password' => 'test-password',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupDirectory);

        parent::tearDown();
    }

    public function test_it_finalizes_a_non_empty_database_backup_atomically(): void
    {
        Process::fake(function (PendingProcess $process) {
            File::put($process->command[array_key_last($process->command)], '-- PostgreSQL database dump');

            return Process::result();
        });

        $result = app(DatabaseBackupService::class)->create();

        $this->assertTrue($result->successful());
        $this->assertSame('success', $result->reasonCode());
        $this->assertMatchesRegularExpression(
            '/^atrilak_backup_\d{8}_\d{6}_\d{6}_[a-f0-9]{16}\.sql$/',
            $result->fileName()
        );
        $this->assertFileExists($this->backupDirectory.DIRECTORY_SEPARATOR.$result->fileName());
        $this->assertSame([], File::files($this->backupDirectory.DIRECTORY_SEPARATOR.'.partial'));
    }

    public function test_it_rejects_a_missing_executable_without_running_a_process(): void
    {
        config()->set('backup.pg_dump_path', $this->backupDirectory.DIRECTORY_SEPARATOR.'missing.exe');
        Process::fake();

        $result = app(DatabaseBackupService::class)->create();

        $this->assertFalse($result->successful());
        $this->assertSame('executable_missing', $result->reasonCode());
        Process::assertNothingRan();
    }

    public function test_it_cleans_up_a_partial_file_after_a_non_zero_process_exit(): void
    {
        Process::fake(function (PendingProcess $process) {
            File::put($process->command[array_key_last($process->command)], 'incomplete');

            return Process::result(errorOutput: 'database unavailable', exitCode: 1);
        });

        $result = app(DatabaseBackupService::class)->create();

        $this->assertFalse($result->successful());
        $this->assertSame('process_non_zero', $result->reasonCode());
        $this->assertSame(1, $result->exitCode());
        $this->assertSame([], File::files($this->backupDirectory.DIRECTORY_SEPARATOR.'.partial'));
    }

    public function test_it_cleans_up_a_partial_file_after_a_process_timeout(): void
    {
        Process::fake(function (PendingProcess $process) {
            File::put($process->command[array_key_last($process->command)], 'incomplete');

            throw new SymfonyProcessTimedOutException(
                new SymfonyProcess(['pg_dump'], timeout: 30),
                SymfonyProcessTimedOutException::TYPE_GENERAL,
            );
        });

        $result = app(DatabaseBackupService::class)->create();

        $this->assertFalse($result->successful());
        $this->assertSame('process_timeout', $result->reasonCode());
        $this->assertSame([], File::files($this->backupDirectory.DIRECTORY_SEPARATOR.'.partial'));
    }

    public function test_it_rejects_missing_or_empty_process_output(): void
    {
        Process::fake();

        $missing = app(DatabaseBackupService::class)->create();

        $this->assertSame('output_missing', $missing->reasonCode());

        Process::fake(function (PendingProcess $process) {
            File::put($process->command[array_key_last($process->command)], '');

            return Process::result();
        });

        $empty = app(DatabaseBackupService::class)->create();

        $this->assertSame('output_empty', $empty->reasonCode());
        $this->assertSame([], File::files($this->backupDirectory.DIRECTORY_SEPARATOR.'.partial'));
    }

    public function test_it_uses_unique_filenames_and_keeps_password_out_of_the_command(): void
    {
        Process::fake(function (PendingProcess $process) {
            File::put($process->command[array_key_last($process->command)], 'dump');

            return Process::result();
        });

        $first = app(DatabaseBackupService::class)->create();
        $second = app(DatabaseBackupService::class)->create();

        $this->assertNotSame($first->fileName(), $second->fileName());
        Process::assertRan(function (PendingProcess $process) {
            return ! in_array('test-password', $process->command, true)
                && $process->environment['PGPASSWORD'] === 'test-password';
        });
    }

    public function test_it_skips_when_another_backup_holds_the_shared_lock(): void
    {
        $lock = cache()->lock('atrilak:database-backup', 3600);
        $lock->get();
        Process::fake();

        try {
            $result = app(DatabaseBackupService::class)->create();
        } finally {
            $lock->release();
        }

        $this->assertFalse($result->successful());
        $this->assertSame('backup_in_progress', $result->reasonCode());
        Process::assertNothingRan();
    }

    public function test_it_removes_only_eligible_old_finalized_sql_backups(): void
    {
        File::ensureDirectoryExists($this->backupDirectory.DIRECTORY_SEPARATOR.'.partial');
        $oldSql = $this->backupDirectory.DIRECTORY_SEPARATOR.'old.sql';
        $recentSql = $this->backupDirectory.DIRECTORY_SEPARATOR.'recent.sql';
        $partial = $this->backupDirectory.DIRECTORY_SEPARATOR.'.partial'.DIRECTORY_SEPARATOR.'old.sql.partial';
        $unknown = $this->backupDirectory.DIRECTORY_SEPARATOR.'notes.txt';
        File::put($oldSql, 'old');
        File::put($recentSql, 'recent');
        File::put($partial, 'partial');
        File::put($unknown, 'notes');
        touch($oldSql, now()->subDays(31)->getTimestamp());

        Process::fake(function (PendingProcess $process) {
            File::put($process->command[array_key_last($process->command)], 'new dump');

            return Process::result();
        });

        $result = app(DatabaseBackupService::class)->create();

        $this->assertTrue($result->successful());
        $this->assertFileDoesNotExist($oldSql);
        $this->assertFileExists($recentSql);
        $this->assertFileExists($partial);
        $this->assertFileExists($unknown);
    }
}
