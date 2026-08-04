<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackupService;
use App\Services\BusinessDataResetService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResetBusinessDataCommand extends Command
{
    public const CONFIRMATION = 'RESET ATRILAK BUSINESS DATA';

    protected $signature = 'atrilak:reset-business-data
        {--confirm= : Must exactly match the production reset confirmation phrase}';

    protected $description = 'Reset ATRILAK POS business data while preserving system access and settings';

    public function handle(
        DatabaseBackupService $backupService,
        BusinessDataResetService $resetService,
    ): int {
        $identity = null;

        try {
            $identity = $resetService->productionIdentity();

            if ((string) ($this->option('confirm') ?? '') !== self::CONFIRMATION) {
                Log::warning('business_data_reset.rejected', [
                    'database' => $identity['database'],
                    'reason' => 'confirmation_mismatch',
                ]);
                $this->error('No changes made. Type exactly: '.self::CONFIRMATION);

                return self::FAILURE;
            }

            $preflight = $resetService->preflight();
            $this->renderPreflight($identity, $preflight, $resetService);

            $result = $resetService->run($backupService, null, $identity, $preflight);
            $backup = $result['backup'];
            $this->info('Backup verified.');
            $this->line('Backup path: '.$backup['path']);
            $this->line('Backup SHA-256: '.$backup['sha256']);
            $this->line('Backup size: '.$backup['bytes'].' bytes');

            $this->renderSummary($result['reset'], $result['cache_warnings']);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('business_data_reset.failed', [
                'database' => $identity['database'] ?? null,
                'exception' => $exception::class,
            ]);

            $this->error('Reset failed and database changes were rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array{app_env: string, app_url: string, database: string, driver: string}  $identity
     * @param  array{business: array<string, int>, protected: array<string, int>}  $preflight
     */
    private function renderPreflight(
        array $identity,
        array $preflight,
        BusinessDataResetService $resetService,
    ): void {
        $this->info('Preflight');
        $this->line('APP_ENV: '.$identity['app_env']);
        $this->line('APP_URL: '.$identity['app_url']);
        $this->line('Database: '.$identity['database']);
        $this->line('Driver: '.$identity['driver']);

        $this->table(
            ['Business table', 'Rows before'],
            array_map(
                static fn (string $table): array => [$table, $preflight['business'][$table]],
                $resetService->businessTables()
            )
        );

        $this->line('Users: '.$preflight['protected']['users']);
        $this->line('Roles: '.$preflight['protected']['roles']);
        $this->line('Permissions: '.$preflight['protected']['permissions']);
        $this->line('Settings: '.$preflight['protected']['settings']);
        $this->line('Delivery Zones: '.$preflight['protected']['delivery_zones']);
        $this->line('Tables to clear: '.implode(', ', $resetService->businessTables()));
        $this->line('Tables to keep: '.implode(', ', $resetService->protectedTables()));
    }

    /**
     * @param array{
     *     before: array<string, int>,
     *     after: array<string, int>,
     *     protected_before: array<string, int>,
     *     protected_after: array<string, int>,
     *     sequences: array<string, string>,
     *     sequence_states: array<string, array{sequence: string, last_value: int|null, is_called: bool}>
     * } $result
     * @param  list<string>  $cacheWarnings
     */
    private function renderSummary(array $result, array $cacheWarnings): void
    {
        $this->info('Reset completed.');
        $this->table(
            ['Business table', 'Before', 'After'],
            array_map(
                static fn (string $table): array => [
                    $table,
                    $result['before'][$table],
                    $result['after'][$table],
                ],
                array_keys($result['before'])
            )
        );

        $this->line('Users after: '.$result['protected_after']['users']);
        $this->line('Roles after: '.$result['protected_after']['roles']);
        $this->line('Permissions after: '.$result['protected_after']['permissions']);
        $this->line('Settings after: '.$result['protected_after']['settings']);
        $this->line('Delivery Zones after: '.$result['protected_after']['delivery_zones']);
        $this->line('Sequences reset: '.count($result['sequence_states']));

        foreach ($result['sequence_states'] as $table => $state) {
            $this->line(sprintf(
                'Sequence %s (%s): last_value=%s, is_called=%s',
                $table,
                $state['sequence'],
                $state['last_value'] === null ? 'NULL' : (string) $state['last_value'],
                $state['is_called'] ? 'true' : 'false'
            ));
        }

        if ($cacheWarnings === []) {
            $this->line('Necessary caches cleared.');
        } else {
            $this->warn('Cache warnings: '.implode(', ', $cacheWarnings));
        }

        $this->line('Users, roles, permissions, settings, delivery zones, migrations, and schema were preserved.');
    }
}
