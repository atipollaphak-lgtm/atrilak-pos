<?php

namespace App\Services\Backup;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Throwable;

class DatabaseRestoreService
{
    public function __construct(
        private readonly DatabaseBackupService $databaseBackupService,
        private readonly MaintenanceModeManager $maintenanceMode,
    ) {}

    public function restore(string $sourcePath): DatabaseRestoreResult
    {
        if (! config('backup.restore_enabled')) {
            return DatabaseRestoreResult::failure('restore_disabled');
        }

        $connectionName = config('database.default');
        $connection = config('database.connections.'.$connectionName);
        if (! is_array($connection) || ($connection['driver'] ?? null) !== 'pgsql') {
            return DatabaseRestoreResult::failure('connection_not_pgsql');
        }

        $executable = config('backup.psql_path');
        if (! is_string($executable) || ! is_file($executable)) {
            return DatabaseRestoreResult::failure('psql_executable_missing');
        }

        $source = $this->validatedSource($sourcePath);
        if (is_string($source)) {
            return DatabaseRestoreResult::failure($source);
        }
        $source = $source['path'];

        try {
            File::ensureDirectoryExists(config('backup.restore_staging_directory'));
        } catch (Throwable $exception) {
            return $this->failure('staging_directory_unavailable', exception: $exception);
        }

        $stagedPath = config('backup.restore_staging_directory').DIRECTORY_SEPARATOR.bin2hex(random_bytes(16)).'.sql';
        $stagedFileName = basename($stagedPath);

        try {
            if (! File::copy($source, $stagedPath) || File::size($source) !== File::size($stagedPath)) {
                return $this->finalize(
                    DatabaseRestoreResult::failure('staging_copy_mismatch', $stagedFileName),
                    $stagedPath,
                    null,
                );
            }
        } catch (Throwable $exception) {
            return $this->finalize(
                $this->failure('staging_copy_failed', $stagedFileName, exception: $exception),
                $stagedPath,
                null,
            );
        }

        $lock = new RestoreFileLock(config('backup.restore_lock_path'));
        if (! $lock->acquire([
            'pid' => getmypid(),
            'started_at' => now()->toIso8601String(),
            'staged_file' => $stagedFileName,
        ])) {
            Log::warning('database_restore.locked', ['reason' => $lock->reasonCode()]);

            return $this->finalize(
                DatabaseRestoreResult::failure($lock->reasonCode() ?? 'lock_unavailable', $stagedFileName),
                $stagedPath,
                null,
            );
        }

        Log::info('database_restore.requested', $this->logContext($connection, $source, $stagedPath));

        try {
            $preBackup = $this->databaseBackupService->create();
            if (! $preBackup->successful()) {
                return $this->finalize(
                    DatabaseRestoreResult::failure('pre_backup_'.$preBackup->reasonCode(), $stagedFileName),
                    $stagedPath,
                    $lock,
                );
            }

            Log::info('database_restore.pre_backup_completed', $this->logContext($connection, $source, $stagedPath, $preBackup->fileName()));

            $wasAlreadyDown = $this->maintenanceMode->isActive();
            if (! $wasAlreadyDown && ! $this->maintenanceMode->down()) {
                return $this->finalize(
                    $this->failure('maintenance_down_failed', $stagedFileName, $preBackup->fileName(), maintenanceState: $this->maintenanceState(), exception: null),
                    $stagedPath,
                    $lock,
                );
            }

            if (! $this->maintenanceMode->isActive()) {
                return $this->finalize(
                    DatabaseRestoreResult::failure('maintenance_not_active', $stagedFileName, $preBackup->fileName(), maintenanceState: 'up'),
                    $stagedPath,
                    $lock,
                );
            }

            try {
                DB::purge($connectionName);
            } catch (Throwable $exception) {
                return $this->finalize(
                    $this->preProcessFailure('database_purge_failed', $stagedFileName, $preBackup->fileName(), $wasAlreadyDown, $exception),
                    $stagedPath,
                    $lock,
                );
            }

            Log::info('database_restore.started', $this->logContext($connection, $source, $stagedPath, $preBackup->fileName()));

            try {
                $process = Process::timeout(config('backup.restore_timeout_seconds'))
                    ->env(['PGPASSWORD' => (string) ($connection['password'] ?? '')])
                    ->run($this->command($executable, $connection, realpath($stagedPath)));
            } catch (ProcessTimedOutException $exception) {
                return $this->finalize(
                    $this->failure('process_timeout', $stagedFileName, $preBackup->fileName(), maintenanceState: 'down', partialRestoreRisk: true, exception: $exception),
                    $stagedPath,
                    $lock,
                );
            } catch (ProcessStartFailedException $exception) {
                return $this->finalize(
                    $this->preProcessFailure('process_start_failed', $stagedFileName, $preBackup->fileName(), $wasAlreadyDown, $exception),
                    $stagedPath,
                    $lock,
                );
            } catch (Throwable $exception) {
                return $this->finalize(
                    $this->failure('process_unknown_failure', $stagedFileName, $preBackup->fileName(), maintenanceState: 'down', partialRestoreRisk: true, exception: $exception),
                    $stagedPath,
                    $lock,
                );
            }

            if ($process->failed()) {
                return $this->finalize(
                    $this->failure('process_non_zero', $stagedFileName, $preBackup->fileName(), $process->exitCode(), 'down', true),
                    $stagedPath,
                    $lock,
                );
            }

            return $this->finalize(DatabaseRestoreResult::success($preBackup->fileName(), $stagedFileName), $stagedPath, $lock, $connection, $source);
        } catch (Throwable $exception) {
            return $this->finalize(
                $this->failure('restore_unexpected_error', $stagedFileName, maintenanceState: $this->maintenanceState(), partialRestoreRisk: $this->maintenanceMode->isActive(), exception: $exception),
                $stagedPath,
                $lock,
            );
        }
    }

    private function validatedSource(string $sourcePath): array|string
    {
        $resolved = realpath($sourcePath);
        if ($resolved === false) {
            return 'source_missing';
        }

        if (is_link($sourcePath) || ! is_file($resolved) || ! is_readable($resolved)) {
            return 'source_invalid';
        }

        if (strtolower(pathinfo($resolved, PATHINFO_EXTENSION)) !== 'sql') {
            return 'source_extension_invalid';
        }

        $stagingRoot = realpath(config('backup.restore_staging_directory'));
        if ($stagingRoot !== false && str_starts_with($resolved, $stagingRoot.DIRECTORY_SEPARATOR)) {
            return 'source_in_staging_directory';
        }

        $size = filesize($resolved);
        if ($size === false || $size === 0) {
            return 'source_empty';
        }

        if ($size > config('backup.restore_max_kb') * 1024) {
            return 'source_too_large';
        }

        return ['path' => $resolved];
    }

    private function command(string $executable, array $connection, string|false $stagedPath): array
    {
        return [
            $executable,
            '-X',
            '-v', 'ON_ERROR_STOP=1',
            '-h', (string) $connection['host'],
            '-p', (string) $connection['port'],
            '-U', (string) $connection['username'],
            '-d', (string) $connection['database'],
            '-f', (string) $stagedPath,
        ];
    }

    private function preProcessFailure(string $reasonCode, string $stagedFileName, ?string $preBackupFileName, bool $wasAlreadyDown, Throwable $exception): DatabaseRestoreResult
    {
        $state = 'down';
        if (! $wasAlreadyDown && $this->maintenanceMode->up()) {
            $state = 'up';
        }

        return $this->failure($reasonCode, $stagedFileName, $preBackupFileName, maintenanceState: $state, exception: $exception);
    }

    private function finalize(DatabaseRestoreResult $result, string $stagedPath, ?RestoreFileLock $lock, ?array $connection = null, ?string $source = null): DatabaseRestoreResult
    {
        $cleanupSucceeded = true;
        try {
            $cleanupSucceeded = ! File::exists($stagedPath) || File::delete($stagedPath);
        } catch (Throwable) {
            $cleanupSucceeded = false;
        }

        if (! $cleanupSucceeded) {
            Log::warning('database_restore.cleanup_failed', ['staged_file' => basename($stagedPath)]);
        }

        if ($lock && ! $lock->release()) {
            $cleanupSucceeded = false;
            Log::error('database_restore.failed', ['reason' => $lock->reasonCode()]);
        }

        $result = $result->withCleanupSucceeded($cleanupSucceeded);
        $context = $connection && $source ? $this->logContext($connection, $source, $stagedPath, $result->preBackupFileName()) : [];
        $context += [
            'reason' => $result->reasonCode(),
            'exit_code' => $result->exitCode(),
            'maintenance_state' => $result->maintenanceState(),
            'partial_restore_risk' => $result->partialRestoreRisk(),
            'cleanup_succeeded' => $result->cleanupSucceeded(),
        ];

        Log::{$result->successful() ? 'info' : 'error'}(
            $result->successful() ? 'database_restore.completed' : 'database_restore.failed',
            $context,
        );

        return $result;
    }

    private function failure(string $reasonCode, ?string $stagedFileName = null, ?string $preBackupFileName = null, ?int $exitCode = null, string $maintenanceState = 'up', bool $partialRestoreRisk = false, ?Throwable $exception = null): DatabaseRestoreResult
    {
        Log::error('database_restore.failed', array_filter([
            'reason' => $reasonCode,
            'exit_code' => $exitCode,
            'maintenance_state' => $maintenanceState,
            'partial_restore_risk' => $partialRestoreRisk,
            'exception' => $exception ? $exception::class : null,
        ], fn ($value) => $value !== null));

        return DatabaseRestoreResult::failure($reasonCode, $stagedFileName, $preBackupFileName, $exitCode, $maintenanceState, $partialRestoreRisk);
    }

    private function maintenanceState(): string
    {
        return $this->maintenanceMode->isActive() ? 'down' : 'up';
    }

    private function logContext(array $connection, string $source, string $stagedPath, ?string $preBackupFileName = null): array
    {
        return array_filter([
            'actor' => 'local-cli',
            'database' => $connection['database'],
            'source_file' => basename($source),
            'source_size' => filesize($source) ?: null,
            'staged_file' => basename($stagedPath),
            'staged_size' => File::exists($stagedPath) ? File::size($stagedPath) : null,
            'staged_sha256' => File::exists($stagedPath) ? hash_file('sha256', $stagedPath) : null,
            'pre_backup_file' => $preBackupFileName,
        ], fn ($value) => $value !== null);
    }
}
