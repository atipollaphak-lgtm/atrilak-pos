<?php

namespace App\Services;

use App\Services\Backup\DatabaseBackupService;
use App\Support\DatabaseEnvironmentGuard;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class BusinessDataResetService
{
    public const TARGET_DATABASE = 'atrilak_pos_production';

    private const WORKFLOW_LOCK = 'atrilak:business-reset';

    private const BUSINESS_TABLES = [
        'categories',
        'category_pricing_rules',
        'customer_delivery_addresses',
        'customers',
        'daily_payment_closing_sales',
        'daily_payment_closings',
        'hold_bill_items',
        'hold_bills',
        'product_barcodes',
        'product_price_histories',
        'product_price_tiers',
        'product_scheduled_prices',
        'product_unit_promotions',
        'product_units',
        'products',
        'purchase_items',
        'purchases',
        'quotation_items',
        'quotations',
        'sale_items',
        'sale_number_counters',
        'sales',
        'stock_count_items',
        'stock_count_number_counters',
        'stock_counts',
        'stock_movements',
        'suppliers',
        'technician_commission_rules',
        'technician_commissions',
        'technician_payment_batches',
        'technicians',
        'units',
    ];

    private const PROTECTED_TABLES = [
        'users',
        'roles',
        'permissions',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
        'migrations',
        'settings',
        'pricing_settings',
        'delivery_zones',
        'business_reset_audits',
    ];

    private const DELETE_ORDER = [
        'daily_payment_closing_sales',
        'technician_commissions',
        'sale_items',
        'purchase_items',
        'quotation_items',
        'hold_bill_items',
        'stock_count_items',
        'product_barcodes',
        'product_price_histories',
        'product_price_tiers',
        'product_scheduled_prices',
        'product_unit_promotions',
        'technician_commission_rules',
        'stock_movements',
        'sales',
        'daily_payment_closings',
        'purchases',
        'quotations',
        'hold_bills',
        'technician_payment_batches',
        'stock_counts',
        'sale_number_counters',
        'stock_count_number_counters',
        'product_units',
        'products',
        'category_pricing_rules',
        'categories',
        'suppliers',
        'customer_delivery_addresses',
        'customers',
        'technicians',
        'units',
    ];

    /** @var array<string, string> */
    private const SEQUENCE_TABLES = [
        'categories' => 'categories_id_seq',
        'category_pricing_rules' => 'category_pricing_rules_id_seq',
        'customer_delivery_addresses' => 'customer_delivery_addresses_id_seq',
        'customers' => 'customers_id_seq',
        'daily_payment_closing_sales' => 'daily_payment_closing_sales_id_seq',
        'daily_payment_closings' => 'daily_payment_closings_id_seq',
        'hold_bill_items' => 'hold_bill_items_id_seq',
        'hold_bills' => 'hold_bills_id_seq',
        'product_barcodes' => 'product_barcodes_id_seq',
        'product_price_histories' => 'product_price_histories_id_seq',
        'product_price_tiers' => 'product_price_tiers_id_seq',
        'product_scheduled_prices' => 'product_scheduled_prices_id_seq',
        'product_unit_promotions' => 'product_unit_promotions_id_seq',
        'product_units' => 'product_units_id_seq',
        'products' => 'products_id_seq',
        'purchase_items' => 'purchase_items_id_seq',
        'purchases' => 'purchases_id_seq',
        'quotation_items' => 'quotation_items_id_seq',
        'quotations' => 'quotations_id_seq',
        'sale_items' => 'sale_items_id_seq',
        'sales' => 'sales_id_seq',
        'stock_count_items' => 'stock_count_items_id_seq',
        'stock_counts' => 'stock_counts_id_seq',
        'stock_movements' => 'stock_movements_id_seq',
        'suppliers' => 'suppliers_id_seq',
        'technician_commission_rules' => 'technician_commission_rules_id_seq',
        'technician_commissions' => 'technician_commissions_id_seq',
        'technician_payment_batches' => 'technician_payment_batches_id_seq',
        'technicians' => 'technicians_id_seq',
        'units' => 'units_id_seq',
    ];

    /** @return list<string> */
    public function businessTables(): array
    {
        return self::BUSINESS_TABLES;
    }

    /** @return list<string> */
    public function protectedTables(): array
    {
        return self::PROTECTED_TABLES;
    }

    /** @return array<string, string> */
    public function sequenceTables(): array
    {
        return self::SEQUENCE_TABLES;
    }

    /**
     * Run the guarded backup-and-reset workflow used by both web and Artisan.
     *
     * The optional identity and preflight arguments are used by the CLI so it
     * can retain its preflight display without performing either operation a
     * second time. Web callers leave them null and this method performs both
     * checks inside the workflow lock.
     *
     * @return array{
     *     identity: array{app_env: string, app_url: string, database: string, driver: string},
     *     preflight: array{business: array<string, int>, protected: array<string, int>},
     *     backup: array{file_name: string, path: string, manifest: string, sha256: string, bytes: int},
     *     reset: array<string, mixed>,
     *     cache_warnings: list<string>
     * }
     */
    public function run(
        DatabaseBackupService $backupService,
        ?int $actorId = null,
        ?array $verifiedIdentity = null,
        ?array $verifiedPreflight = null,
    ): array {
        $lock = Cache::lock(self::WORKFLOW_LOCK, config('backup.lock_seconds'));

        if (! $lock->get()) {
            Log::warning('business_data_reset.skipped', [
                'reason' => 'reset_in_progress',
            ]);

            throw new RuntimeException('Another business data reset is already in progress.');
        }

        $identity = $verifiedIdentity;
        $preflight = $verifiedPreflight;
        $backup = null;

        try {
            $identity ??= $this->assertAllowedResetIdentity();
            $preflight ??= $this->preflight();
            $backup = $this->createAndVerifyBackup($backupService, $identity['database']);

            Log::info('business_data_reset.started', [
                'database' => $identity['database'],
                'backup_file' => $backup['path'],
                'backup_sha256' => $backup['sha256'],
                'business_counts_before' => $preflight['business'],
            ]);

            $reset = $this->reset();
            $cacheWarnings = $this->clearNecessaryCaches();
            $result = [
                'identity' => $identity,
                'preflight' => $preflight,
                'backup' => $backup,
                'reset' => $reset,
                'cache_warnings' => $cacheWarnings,
            ];

            $this->recordAudit(
                actorId: $actorId,
                identity: $identity,
                preflight: $preflight,
                reset: $reset,
                backup: $backup,
                status: 'success',
                exception: null,
            );

            Log::info('business_data_reset.completed', [
                'database' => $identity['database'],
                'backup_file' => $backup['path'],
                'backup_sha256' => $backup['sha256'],
                'business_counts_before' => $reset['before'],
                'business_counts_after' => $reset['after'],
                'protected_counts_before' => $reset['protected_before'],
                'protected_counts_after' => $reset['protected_after'],
                'cache_warnings' => $cacheWarnings,
            ]);

            return $result;
        } catch (Throwable $exception) {
            $this->recordAudit(
                actorId: $actorId,
                identity: $identity,
                preflight: $preflight,
                reset: null,
                backup: $backup,
                status: 'failed',
                exception: $exception,
            );

            Log::error('business_data_reset.failed', [
                'database' => $identity['database'] ?? null,
                'exception' => $exception::class,
            ]);

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    /** @return array{app_env: string, app_url: string, database: string, driver: string} */
    public function productionIdentity(): array
    {
        return $this->assertProductionIdentity();
    }

    /**
     * @return array{business: array<string, int>, protected: array<string, int>}
     */
    public function preflight(): array
    {
        $this->assertPostgresConnection();
        $this->assertSchema();

        return [
            'business' => $this->counts(self::BUSINESS_TABLES),
            'protected' => $this->counts(self::PROTECTED_TABLES),
        ];
    }

    /** @return array{app_env: string, app_url: string, database: string, driver: string} */
    private function assertProductionIdentity(): array
    {
        if (app()->environment() !== 'production') {
            throw new RuntimeException('APP_ENV must be production. No changes were made.');
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver !== 'pgsql') {
            throw new RuntimeException('Database driver must be PostgreSQL. No changes were made.');
        }

        $row = $connection->selectOne('SELECT current_database() AS database_name');
        $database = (string) $row->database_name;

        if ($database !== self::TARGET_DATABASE) {
            throw new RuntimeException(
                'Database identity mismatch. Expected '.self::TARGET_DATABASE.'. No changes were made.'
            );
        }

        return [
            'app_env' => (string) app()->environment(),
            'app_url' => (string) config('app.url'),
            'database' => $database,
            'driver' => $driver,
        ];
    }

    /** @return array{app_env: string, app_url: string, database: string, driver: string} */
    private function assertAllowedResetIdentity(): array
    {
        $environment = (string) app()->environment();

        if ($environment === 'production') {
            return $this->assertProductionIdentity();
        }

        if (! in_array($environment, ['local', 'testing'], true)) {
            throw new RuntimeException('Reset requires APP_ENV=production or an explicitly named test environment. No changes were made.');
        }

        $this->assertPostgresConnection();
        $database = (string) DB::selectOne('SELECT current_database() AS database_name')->database_name;
        DatabaseEnvironmentGuard::assertTestDatabase($environment, $database);

        return [
            'app_env' => $environment,
            'app_url' => (string) config('app.url'),
            'database' => $database,
            'driver' => (string) DB::connection()->getDriverName(),
        ];
    }

    /** @return array{file_name: string, path: string, manifest: string, sha256: string, bytes: int} */
    private function createAndVerifyBackup(
        DatabaseBackupService $backupService,
        string $database,
    ): array {
        $result = $backupService->create();

        if (! $result->successful() || $result->fileName() === null) {
            throw new RuntimeException(
                'Backup failed before reset: '.($result->reasonCode() ?: 'unknown_error')
            );
        }

        $fileName = $result->fileName();

        if (basename($fileName) !== $fileName || ! preg_match('/^[A-Za-z0-9_.-]+\.sql$/', $fileName)) {
            throw new RuntimeException('Backup returned an invalid file name.');
        }

        $directory = (string) config('backup.directory');
        $path = $directory.DIRECTORY_SEPARATOR.$fileName;
        $manifestPath = $path.'.manifest.json';
        $bytes = is_file($path) ? filesize($path) : false;
        $manifestBytes = is_file($manifestPath) ? filesize($manifestPath) : false;

        if ($bytes === false || $bytes <= 0) {
            throw new RuntimeException('Backup file is missing or empty.');
        }

        if ($manifestBytes === false || $manifestBytes <= 0) {
            throw new RuntimeException('Backup manifest is missing or empty.');
        }

        $sha256 = hash_file('sha256', $path);

        if ($sha256 === false) {
            throw new RuntimeException('Unable to calculate the backup SHA-256.');
        }

        $manifest = json_decode(
            (string) file_get_contents($manifestPath),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (($manifest['database']['database'] ?? null) !== $database
            || ($manifest['database']['sha256'] ?? null) !== $sha256
            || ($manifest['backup_file'] ?? null) !== $fileName) {
            throw new RuntimeException('Backup manifest verification failed.');
        }

        return [
            'file_name' => $fileName,
            'path' => $path,
            'manifest' => $manifestPath,
            'sha256' => $sha256,
            'bytes' => (int) $bytes,
        ];
    }

    /** @return list<string> */
    private function clearNecessaryCaches(): array
    {
        $warnings = [];

        try {
            if (Artisan::call('permission:cache-reset') !== 0) {
                throw new RuntimeException('permission:cache-reset returned a failure status.');
            }
        } catch (Throwable $exception) {
            $warnings[] = 'permission_cache';
            Log::warning('business_data_reset.cache_clear_failed', [
                'cache' => 'permission_cache',
                'exception' => $exception::class,
            ]);
        }

        try {
            if (Cache::flush() !== true) {
                throw new RuntimeException('Cache flush returned a failure status.');
            }
        } catch (Throwable $exception) {
            $warnings[] = 'application_cache';
            Log::warning('business_data_reset.cache_clear_failed', [
                'cache' => 'application_cache',
                'exception' => $exception::class,
            ]);
        }

        return $warnings;
    }

    /**
     * @param  array{app_env: string, app_url: string, database: string, driver: string}|null  $identity
     * @param  array{business: array<string, int>, protected: array<string, int>}|null  $preflight
     * @param  array<string, mixed>|null  $reset
     * @param  array{file_name: string, path: string, manifest: string, sha256: string, bytes: int}|null  $backup
     */
    private function recordAudit(
        ?int $actorId,
        ?array $identity,
        ?array $preflight,
        ?array $reset,
        ?array $backup,
        string $status,
        ?Throwable $exception,
    ): void {
        try {
            if (! Schema::hasTable('business_reset_audits')) {
                return;
            }

            $encode = static fn (?array $value): ?string => $value === null
                ? null
                : json_encode($value, JSON_THROW_ON_ERROR);

            DB::table('business_reset_audits')->insert([
                'user_id' => $actorId,
                'database_name' => $identity['database'] ?? null,
                'business_counts_before' => $encode($preflight['business'] ?? null),
                'business_counts_after' => $encode($reset['after'] ?? null),
                'protected_counts_before' => $encode($preflight['protected'] ?? null),
                'protected_counts_after' => $encode($reset['protected_after'] ?? null),
                'backup_file' => $backup['path'] ?? null,
                'backup_sha256' => $backup['sha256'] ?? null,
                'backup_bytes' => $backup['bytes'] ?? null,
                'status' => $status,
                'error_code' => $exception?->getCode() ? (string) $exception->getCode() : ($exception ? $exception::class : null),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $auditException) {
            Log::error('business_data_reset.audit_failed', [
                'exception' => $auditException::class,
                'status' => $status,
            ]);
        }
    }

    /**
     * @return array{
     *     before: array<string, int>,
     *     after: array<string, int>,
     *     protected_before: array<string, int>,
     *     protected_after: array<string, int>,
     *     sequences: array<string, string>,
     *     sequence_states: array<string, array{sequence: string, last_value: int|null, is_called: bool}>
     * }
     */
    public function reset(): array
    {
        $preflight = $this->preflight();
        $after = [];
        $protectedAfter = [];
        $sequenceStates = [];

        DB::transaction(function () use ($preflight, &$after, &$protectedAfter, &$sequenceStates): void {
            DB::statement("SET LOCAL lock_timeout = '30s'");
            $this->lockBusinessTables();

            foreach (self::DELETE_ORDER as $table) {
                DB::table($table)->delete();
            }

            $this->resetSequences();
            $sequenceStates = $this->sequenceStates();

            foreach ($sequenceStates as $state) {
                if ($state['is_called'] || ($state['last_value'] !== null && $state['last_value'] !== 1)) {
                    throw new RuntimeException('Reset postcondition failed: a business sequence was not restarted at 1.');
                }
            }

            $after = $this->counts(self::BUSINESS_TABLES);
            $protectedAfter = $this->counts(self::PROTECTED_TABLES);

            $remaining = array_filter(
                $after,
                static fn (int $count): bool => $count !== 0
            );

            if ($remaining !== []) {
                throw new RuntimeException('Reset postcondition failed: business tables are not empty.');
            }

            if ($protectedAfter !== $preflight['protected']) {
                throw new RuntimeException('Reset protection check failed: protected table counts changed.');
            }
        });

        return [
            'before' => $preflight['business'],
            'after' => $after,
            'protected_before' => $preflight['protected'],
            'protected_after' => $protectedAfter,
            'sequences' => self::SEQUENCE_TABLES,
            'sequence_states' => $sequenceStates,
        ];
    }

    /**
     * @return array<string, array{sequence: string, last_value: int|null, is_called: bool}>
     */
    public function sequenceStates(): array
    {
        $this->assertPostgresConnection();

        $states = [];

        foreach (self::SEQUENCE_TABLES as $table => $sequence) {
            $row = DB::selectOne(
                'SELECT last_value, is_called FROM "'.$sequence.'"'
            );

            $states[$table] = [
                'sequence' => $sequence,
                'last_value' => $row->last_value === null ? null : (int) $row->last_value,
                'is_called' => (bool) $row->is_called,
            ];
        }

        return $states;
    }

    /** @param list<string> $tables */
    private function counts(array $tables): array
    {
        $counts = [];

        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    private function assertPostgresConnection(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('The reset requires a PostgreSQL connection.');
        }
    }

    private function assertSchema(): void
    {
        foreach (array_merge(self::BUSINESS_TABLES, self::PROTECTED_TABLES) as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Required table is missing: {$table}.");
            }
        }

        foreach (self::SEQUENCE_TABLES as $sequence) {
            $row = DB::selectOne(
                'SELECT to_regclass(?) AS relation_name',
                ['public.'.$sequence]
            );

            if ($row->relation_name === null) {
                throw new RuntimeException("Required sequence is missing: {$sequence}.");
            }
        }
    }

    private function lockBusinessTables(): void
    {
        $tables = self::BUSINESS_TABLES;
        sort($tables);

        $quotedTables = array_map(
            static fn (string $table): string => '"'.str_replace('"', '""', $table).'"',
            $tables
        );

        DB::statement(
            'LOCK TABLE '.implode(', ', $quotedTables).' IN ACCESS EXCLUSIVE MODE'
        );
    }

    private function resetSequences(): void
    {
        foreach (self::SEQUENCE_TABLES as $sequence) {
            DB::statement('ALTER SEQUENCE "'.$sequence.'" RESTART WITH 1');
        }
    }
}
