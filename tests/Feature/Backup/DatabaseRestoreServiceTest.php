<?php

namespace Tests\Feature\Backup;

use App\Services\Backup\DatabaseBackupResult;
use App\Services\Backup\DatabaseBackupService;
use App\Services\Backup\DatabaseRestoreService;
use App\Services\Backup\MaintenanceModeManager;
use App\Services\Backup\RestoreFileLock;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutException;
use Symfony\Component\Process\Process as SymfonyProcess;
use Tests\TestCase;

class DatabaseRestoreServiceTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/restore/'.uniqid('', true));

        config()->set([
            'backup.restore_enabled' => true,
            'backup.psql_path' => __FILE__,
            'backup.restore_max_kb' => 10,
            'backup.restore_timeout_seconds' => 30,
            'backup.restore_staging_directory' => $this->directory.'/staging',
            'backup.restore_lock_path' => $this->directory.'/restore.lock',
            'database.default' => 'pgsql',
            'database.connections.pgsql' => [
                'driver' => 'pgsql',
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
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_it_rejects_disabled_feature_without_staging_or_process(): void
    {
        config()->set('backup.restore_enabled', false);
        Process::fake()->preventStrayProcesses();

        $result = $this->service()->restore($this->sqlFile());

        $this->assertSame('restore_disabled', $result->reasonCode());
        $this->assertFalse($result->successful());
        Process::assertNothingRan();
    }

    public function test_it_rejects_non_pgsql_and_invalid_source_files(): void
    {
        config()->set('database.default', 'sqlite');
        $this->assertSame('connection_not_pgsql', $this->service()->restore($this->sqlFile())->reasonCode());

        config()->set('database.default', 'pgsql');
        $this->assertSame('source_missing', $this->service()->restore($this->directory.'/missing.sql')->reasonCode());

        $wrongExtension = $this->directory.'/restore.txt';
        File::put($wrongExtension, 'select 1;');
        $this->assertSame('source_extension_invalid', $this->service()->restore($wrongExtension)->reasonCode());

        $empty = $this->directory.'/empty.sql';
        File::put($empty, '');
        $this->assertSame('source_empty', $this->service()->restore($empty)->reasonCode());
    }

    public function test_it_rejects_a_symbolic_link_source(): void
    {
        $target = $this->sqlFile('target.sql');
        $link = $this->directory.'/linked.sql';
        if (! @symlink($target, $link)) {
            $this->markTestSkipped('Symbolic links are unavailable in this Windows test environment.');
        }

        $this->assertSame('source_invalid', $this->service()->restore($link)->reasonCode());
    }

    public function test_it_rejects_missing_executable_oversized_and_staging_sources(): void
    {
        config()->set('backup.psql_path', $this->directory.'/missing.exe');
        $this->assertSame('psql_executable_missing', $this->service()->restore($this->sqlFile())->reasonCode());

        config()->set('backup.psql_path', __FILE__);
        config()->set('backup.restore_max_kb', 0);
        $this->assertSame('source_too_large', $this->service()->restore($this->sqlFile())->reasonCode());

        config()->set('backup.restore_max_kb', 10);
        File::ensureDirectoryExists(config('backup.restore_staging_directory'));
        $stagingSource = config('backup.restore_staging_directory').'/existing.sql';
        File::put($stagingSource, 'select 1;');
        $this->assertSame('source_in_staging_directory', $this->service()->restore($stagingSource)->reasonCode());
    }

    public function test_it_rejects_a_staging_path_that_cannot_be_a_directory(): void
    {
        File::ensureDirectoryExists($this->directory);
        $stagingFile = $this->directory.'/staging-file';
        File::put($stagingFile, 'not a directory');
        config()->set('backup.restore_staging_directory', $stagingFile);
        Process::fake()->preventStrayProcesses();

        $result = $this->service()->restore($this->sqlFile());

        $this->assertSame('staging_directory_unavailable', $result->reasonCode());
        Process::assertNothingRan();
    }

    public function test_restore_file_lock_contends_and_stale_metadata_does_not_block(): void
    {
        $first = new RestoreFileLock(config('backup.restore_lock_path'));
        $this->assertTrue($first->acquire(['pid' => 1]));

        $second = new RestoreFileLock(config('backup.restore_lock_path'));
        $this->assertFalse($second->acquire(['pid' => 2]));
        $this->assertSame('lock_held', $second->reasonCode());
        $this->assertTrue($first->release());

        File::put(config('backup.restore_lock_path'), 'stale metadata');
        $third = new RestoreFileLock(config('backup.restore_lock_path'));
        $this->assertTrue($third->acquire(['pid' => 3]));
        $this->assertTrue($third->release());
    }

    public function test_pre_backup_failure_prevents_maintenance_and_process_and_cleans_staging(): void
    {
        Process::fake()->preventStrayProcesses();
        $maintenance = $this->maintenance();
        $result = $this->service(DatabaseBackupResult::skipped('backup_in_progress'), $maintenance)
            ->restore($this->sqlFile());

        $this->assertSame('pre_backup_backup_in_progress', $result->reasonCode());
        $this->assertFalse($maintenance->downCalled);
        $this->assertTrue($result->cleanupSucceeded());
        $this->assertSame([], File::files(config('backup.restore_staging_directory')));
        Process::assertNothingRan();
    }

    public function test_it_stages_purges_and_runs_psql_with_child_only_password_before_remaining_down(): void
    {
        $maintenance = $this->maintenance();
        $events = [];
        DB::shouldReceive('purge')->once()->with('pgsql')->andReturnUsing(function () use (&$events) {
            $events[] = 'purge';
        });
        Process::fake(function (PendingProcess $process) use (&$events) {
            $events[] = 'process';

            $this->assertSame([
                __FILE__, '-X', '-v', 'ON_ERROR_STOP=1', '-h', '127.0.0.1', '-p', '5432',
                '-U', 'atrilak_test', '-d', 'atrilak_pos_test', '-f', $process->command[13],
            ], $process->command);
            $this->assertStringStartsWith(realpath(config('backup.restore_staging_directory')).DIRECTORY_SEPARATOR, $process->command[13]);
            $this->assertSame('test-password', $process->environment['PGPASSWORD']);
            $this->assertNotContains('test-password', $process->command);

            return Process::result(output: 'hidden', errorOutput: 'hidden');
        });

        $result = $this->service(DatabaseBackupResult::success('pre-restore.sql'), $maintenance)
            ->restore($this->sqlFile());

        $this->assertTrue($result->successful());
        $this->assertSame('success', $result->reasonCode());
        $this->assertSame('pre-restore.sql', $result->preBackupFileName());
        $this->assertSame('down', $result->maintenanceState());
        $this->assertFalse($result->partialRestoreRisk());
        $this->assertSame(['purge', 'process'], $events);
        $this->assertTrue($maintenance->downCalled);
        $this->assertFalse($maintenance->upCalled);
        $this->assertSame([], File::files(config('backup.restore_staging_directory')));
    }

    public function test_timeout_and_non_zero_remain_down_with_partial_restore_risk(): void
    {
        foreach (['timeout', 'non_zero'] as $case) {
            $maintenance = $this->maintenance();
            DB::shouldReceive('purge')->once()->with('pgsql');
            Process::fake(function () use ($case) {
                if ($case === 'timeout') {
                    throw new SymfonyProcessTimedOutException(
                        new SymfonyProcess(['psql']),
                        SymfonyProcessTimedOutException::TYPE_GENERAL,
                    );
                }

                return Process::result(errorOutput: 'hidden', exitCode: 1);
            });

            $result = $this->service(DatabaseBackupResult::success('pre.sql'), $maintenance)
                ->restore($this->sqlFile('restore-'.$case.'.sql'));

            $this->assertContains($result->reasonCode(), ['process_timeout', 'process_non_zero']);
            $this->assertTrue($result->partialRestoreRisk());
            $this->assertSame('down', $result->maintenanceState());
            $this->assertFalse($maintenance->upCalled);
        }
    }

    public function test_start_failure_brings_up_only_when_service_brought_application_down(): void
    {
        $maintenance = $this->maintenance();
        DB::shouldReceive('purge')->once()->with('pgsql');
        Process::fake(fn () => throw new ProcessStartFailedException(new SymfonyProcess(['psql']), 'cannot start'));

        $result = $this->service(DatabaseBackupResult::success('pre.sql'), $maintenance)
            ->restore($this->sqlFile());

        $this->assertSame('process_start_failed', $result->reasonCode());
        $this->assertSame('up', $result->maintenanceState());
        $this->assertTrue($maintenance->upCalled);
    }

    public function test_start_failure_never_brings_up_an_application_that_was_already_down(): void
    {
        $maintenance = $this->maintenance(alreadyDown: true);
        DB::shouldReceive('purge')->once()->with('pgsql');
        Process::fake(fn () => throw new ProcessStartFailedException(new SymfonyProcess(['psql']), 'cannot start'));

        $result = $this->service(DatabaseBackupResult::success('pre.sql'), $maintenance)
            ->restore($this->sqlFile());

        $this->assertSame('process_start_failed', $result->reasonCode());
        $this->assertSame('down', $result->maintenanceState());
        $this->assertFalse($maintenance->upCalled);
    }

    public function test_restore_core_does_not_use_legacy_shell_execution(): void
    {
        $source = File::get(app_path('Services/Backup/DatabaseRestoreService.php'));

        $this->assertStringNotContainsString('putenv(', $source);
        $this->assertStringNotContainsString('exec(', $source);
        $this->assertStringNotContainsString('psql.exe', $source);
        $this->assertStringNotContainsString('--single-transaction', $source);
    }

    private function service(?DatabaseBackupResult $backupResult = null, ?FakeMaintenanceModeManager $maintenance = null): DatabaseRestoreService
    {
        $backup = new class($backupResult ?? DatabaseBackupResult::success('pre.sql')) extends DatabaseBackupService
        {
            public function __construct(private DatabaseBackupResult $result) {}

            public function create(): DatabaseBackupResult
            {
                return $this->result;
            }
        };

        return new DatabaseRestoreService($backup, $maintenance ?? $this->maintenance());
    }

    private function maintenance(bool $alreadyDown = false): FakeMaintenanceModeManager
    {
        return new FakeMaintenanceModeManager($alreadyDown);
    }

    private function sqlFile(string $name = 'restore.sql'): string
    {
        File::ensureDirectoryExists($this->directory);
        $path = $this->directory.'/'.$name;
        File::put($path, 'select 1;');

        return $path;
    }
}

class FakeMaintenanceModeManager extends MaintenanceModeManager
{
    public bool $downCalled = false;

    public bool $upCalled = false;

    public function __construct(private bool $active = false) {}

    public function isActive(): bool
    {
        return $this->active;
    }

    public function down(): bool
    {
        $this->downCalled = true;
        $this->active = true;

        return true;
    }

    public function up(): bool
    {
        $this->upCalled = true;
        $this->active = false;

        return true;
    }
}
