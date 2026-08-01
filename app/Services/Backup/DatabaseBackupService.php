<?php

namespace App\Services\Backup;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

class DatabaseBackupService
{
    public function create(): DatabaseBackupResult
    {
        $lock = Cache::lock('atrilak:database-backup', config('backup.lock_seconds'));

        if (! $lock->get()) {
            Log::warning('database_backup.skipped', ['reason' => 'backup_in_progress']);

            return DatabaseBackupResult::skipped('backup_in_progress');
        }

        try {
            return $this->createLocked();
        } finally {
            $lock->release();
        }
    }

    private function createLocked(): DatabaseBackupResult
    {
        $executable = config('backup.pg_dump_path');

        if (! is_string($executable) || ! is_file($executable)) {
            return $this->failure('executable_missing');
        }

        $directory = config('backup.directory');
        $partialDirectory = $directory.DIRECTORY_SEPARATOR.'.partial';

        try {
            File::ensureDirectoryExists($partialDirectory);
        } catch (Throwable $exception) {
            return $this->failure('backup_directory_unavailable', exception: $exception);
        }

        $fileName = $this->fileName();
        $partialPath = $partialDirectory.DIRECTORY_SEPARATOR.$fileName.'.partial';
        $finalPath = $directory.DIRECTORY_SEPARATOR.$fileName;

        try {
            try {
                $result = Process::timeout(config('backup.process_timeout_seconds'))
                    ->env(['PGPASSWORD' => (string) config('database.connections.'.config('database.default').'.password')])
                    ->run($this->command($executable, $partialPath));
            } catch (ProcessTimedOutException $exception) {
                return $this->failure('process_timeout', exception: $exception);
            }

            if ($result->failed()) {
                return $this->failure('process_non_zero', $result->exitCode());
            }

            if (! File::exists($partialPath)) {
                return $this->failure('output_missing');
            }

            if (File::size($partialPath) === 0) {
                return $this->failure('output_empty');
            }

            if (! File::move($partialPath, $finalPath)) {
                return $this->failure('finalization_failed');
            }

            if (! $this->writeManifest($directory, $fileName, $finalPath)) {
                return $this->failure('manifest_write_failed');
            }

            $warningCode = $this->retainBackups($directory, $fileName);

            Log::info('database_backup.completed', [
                'file_name' => $fileName,
                'warning' => $warningCode,
            ]);

            return DatabaseBackupResult::success($fileName, $warningCode);
        } catch (Throwable $exception) {
            return $this->failure('backup_unexpected_error', exception: $exception);
        } finally {
            File::delete($partialPath);
        }
    }

    private function command(string $executable, string $outputPath): array
    {
        $connection = config('database.connections.'.config('database.default'));

        return [
            $executable,
            '-h', (string) $connection['host'],
            '-p', (string) $connection['port'],
            '-U', (string) $connection['username'],
            '-d', (string) $connection['database'],
            '-F', 'p',
            '--clean',
            '--if-exists',
            '-f', $outputPath,
        ];
    }

    private function retainBackups(string $directory, string $currentFileName): ?string
    {
        try {
            $cutoff = now()->subDays(config('backup.retention_days'))->getTimestamp();

            foreach (File::files($directory) as $file) {
                if ($file->getFilename() === $currentFileName
                    || $file->getExtension() !== 'sql'
                    || $file->getMTime() >= $cutoff) {
                    continue;
                }

                File::delete($file->getPathname());
            }
        } catch (Throwable $exception) {
            Log::warning('database_backup.retention_failed', [
                'exception' => $exception::class,
            ]);

            return 'retention_cleanup_failed';
        }

        return null;
    }

    private function writeManifest(string $directory, string $fileName, string $backupPath): bool
    {
        $publicRoot = storage_path('app/public');
        $businessFiles = [];

        if (File::isDirectory($publicRoot)) {
            foreach (File::allFiles($publicRoot) as $file) {
                $relativePath = str_replace('\\', '/', ltrim(str_replace($publicRoot, '', $file->getPathname()), '\\/'));
                $businessFiles[] = [
                    'path' => $relativePath,
                    'size' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getPathname()),
                ];
            }
        }

        usort($businessFiles, fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

        $connection = config('database.connections.'.config('database.default'));
        $manifest = [
            'created_at' => now()->toIso8601String(),
            'database' => [
                'connection' => config('database.default'),
                'host' => (string) ($connection['host'] ?? ''),
                'port' => (string) ($connection['port'] ?? ''),
                'database' => (string) ($connection['database'] ?? ''),
                'sha256' => hash_file('sha256', $backupPath),
            ],
            'backup_file' => $fileName,
            'business_files' => $businessFiles,
        ];

        return File::put(
            $directory.DIRECTORY_SEPARATOR.$fileName.'.manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        ) !== false;
    }

    private function fileName(): string
    {
        return 'atrilak_backup_'.now()->format('Ymd_His_u').'_'.bin2hex(random_bytes(8)).'.sql';
    }

    private function failure(string $reasonCode, ?int $exitCode = null, ?Throwable $exception = null): DatabaseBackupResult
    {
        Log::error('database_backup.failed', array_filter([
            'reason' => $reasonCode,
            'exit_code' => $exitCode,
            'exception' => $exception ? $exception::class : null,
        ], fn ($value) => $value !== null));

        return DatabaseBackupResult::failure($reasonCode, $exitCode);
    }
}
