<?php

namespace Tests\Feature\Console;

use App\Console\Commands\ResetBusinessDataCommand;
use App\Services\Backup\DatabaseBackupResult;
use App\Services\Backup\DatabaseBackupService;
use App\Services\BusinessDataResetService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ResetBusinessDataCommandTest extends TestCase
{
    public function test_it_refuses_to_run_when_the_application_is_not_in_production(): void
    {
        $this->artisan('atrilak:reset-business-data', [
            '--confirm' => ResetBusinessDataCommand::CONFIRMATION,
        ])
            ->expectsOutputToContain('APP_ENV must be production')
            ->assertExitCode(1);
    }

    public function test_it_requires_the_exact_confirmation_before_running_preflight_or_backup(): void
    {
        $this->app->instance('env', 'production');

        $connection = Mockery::mock();
        $connection->shouldReceive('getDriverName')->once()->andReturn('pgsql');
        $connection->shouldReceive('selectOne')->once()->andReturn((object) [
            'database_name' => 'atrilak_pos_production',
        ]);

        DB::shouldReceive('connection')->once()->andReturn($connection);

        $this->artisan('atrilak:reset-business-data', [
            '--confirm' => 'wrong confirmation',
        ])
            ->expectsOutputToContain('Type exactly: '.ResetBusinessDataCommand::CONFIRMATION)
            ->assertExitCode(1);
    }

    public function test_it_stops_before_reset_when_backup_fails(): void
    {
        $this->app->instance('env', 'production');

        $connection = Mockery::mock();
        $connection->shouldReceive('getDriverName')->once()->andReturn('pgsql');
        $connection->shouldReceive('selectOne')->once()->andReturn((object) [
            'database_name' => 'atrilak_pos_production',
        ]);
        DB::shouldReceive('connection')->atLeast()->once()->andReturn($connection);

        $resetService = new class extends BusinessDataResetService
        {
            public bool $resetCalled = false;

            public function preflight(): array
            {
                return [
                    'business' => array_fill_keys($this->businessTables(), 0),
                    'protected' => array_fill_keys($this->protectedTables(), 0),
                ];
            }

            public function reset(): array
            {
                $this->resetCalled = true;

                return [];
            }
        };
        $this->app->instance(BusinessDataResetService::class, $resetService);
        $this->app->instance(DatabaseBackupService::class, new class extends DatabaseBackupService
        {
            public function create(): DatabaseBackupResult
            {
                return DatabaseBackupResult::failure('process_non_zero', 1);
            }
        });

        $this->artisan('atrilak:reset-business-data', [
            '--confirm' => ResetBusinessDataCommand::CONFIRMATION,
        ])
            ->expectsOutputToContain('Backup failed before reset: process_non_zero')
            ->assertExitCode(1);

        $this->assertFalse($resetService->resetCalled);
    }

    public function test_it_refuses_a_database_with_the_wrong_identity(): void
    {
        $this->app->instance('env', 'production');

        $connection = Mockery::mock();
        $connection->shouldReceive('getDriverName')->once()->andReturn('pgsql');
        $connection->shouldReceive('selectOne')->once()->andReturn((object) [
            'database_name' => 'atrilak_pos_test',
        ]);
        DB::shouldReceive('connection')->once()->andReturn($connection);

        $this->artisan('atrilak:reset-business-data', [
            '--confirm' => ResetBusinessDataCommand::CONFIRMATION,
        ])
            ->expectsOutputToContain('Database identity mismatch')
            ->assertExitCode(1);
    }

    public function test_it_delegates_the_reset_workflow_to_the_shared_service(): void
    {
        $this->app->instance('env', 'production');

        $resetService = new class extends BusinessDataResetService
        {
            public bool $runCalled = false;

            public function productionIdentity(): array
            {
                return [
                    'app_env' => 'production',
                    'app_url' => 'http://localhost',
                    'database' => 'atrilak_pos_production',
                    'driver' => 'pgsql',
                ];
            }

            public function preflight(): array
            {
                return [
                    'business' => array_fill_keys($this->businessTables(), 0),
                    'protected' => array_fill_keys($this->protectedTables(), 0),
                ];
            }

            public function run(
                DatabaseBackupService $backupService,
                ?int $actorId = null,
                ?array $verifiedIdentity = null,
                ?array $verifiedPreflight = null,
            ): array {
                $this->runCalled = true;

                return [
                    'identity' => [
                        'app_env' => 'production',
                        'app_url' => 'http://localhost',
                        'database' => 'atrilak_pos_production',
                        'driver' => 'pgsql',
                    ],
                    'preflight' => [
                        'business' => array_fill_keys($this->businessTables(), 0),
                        'protected' => array_fill_keys($this->protectedTables(), 0),
                    ],
                    'backup' => [
                        'file_name' => 'reset.sql',
                        'path' => 'reset.sql',
                        'sha256' => str_repeat('a', 64),
                        'bytes' => 10,
                    ],
                    'reset' => [
                        'before' => array_fill_keys($this->businessTables(), 0),
                        'after' => array_fill_keys($this->businessTables(), 0),
                        'protected_before' => array_fill_keys($this->protectedTables(), 0),
                        'protected_after' => array_fill_keys($this->protectedTables(), 0),
                        'sequences' => [],
                        'sequence_states' => [],
                    ],
                    'cache_warnings' => [],
                ];
            }
        };
        $this->app->instance(BusinessDataResetService::class, $resetService);

        $this->artisan('atrilak:reset-business-data', [
            '--confirm' => 'RESET ATRILAK BUSINESS DATA',
        ])
            ->expectsOutputToContain('Reset completed.')
            ->assertExitCode(0);

        $this->assertTrue($resetService->runCalled);
    }
}
