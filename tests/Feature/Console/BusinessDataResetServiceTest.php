<?php

namespace Tests\Feature\Console;

use App\Models\BusinessResetAudit;
use App\Models\User;
use App\Services\Backup\DatabaseBackupResult;
use App\Services\Backup\DatabaseBackupService;
use App\Services\BusinessDataResetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Throwable;

class BusinessDataResetServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_business_allowlist_and_sequence_allowlist_exclude_protected_data(): void
    {
        $service = app(BusinessDataResetService::class);

        $this->assertSame([], array_intersect(
            $service->businessTables(),
            $service->protectedTables()
        ));
        $this->assertNotContains('users', $service->sequenceTables());
        $this->assertNotContains('settings', $service->sequenceTables());
        $this->assertNotContains('roles', $service->sequenceTables());
        $this->assertNotContains('permissions', $service->sequenceTables());
        $this->assertNotContains('delivery_zones', $service->sequenceTables());
        $this->assertArrayNotHasKey('sale_number_counters', $service->sequenceTables());
        $this->assertArrayNotHasKey('stock_count_number_counters', $service->sequenceTables());
    }

    public function test_shared_workflow_records_safe_success_audit_metadata(): void
    {
        if (! Schema::hasTable('business_reset_audits')) {
            $this->markTestSkipped(
                'The test database has not applied the business reset audit migration.'
            );
        }

        $this->assertTrue(Schema::hasTable('business_reset_audits'));

        $auditColumns = Schema::getColumnListing('business_reset_audits');
        $this->assertNotContains('password', $auditColumns);
        $this->assertNotContains('session', $auditColumns);

        $owner = User::factory()->create(['role' => 'owner']);
        $backupDirectory = storage_path('framework/testing/business-reset-audit');
        File::ensureDirectoryExists($backupDirectory);
        $backupFileName = 'reset-audit-test.sql';
        $backupPath = $backupDirectory.DIRECTORY_SEPARATOR.$backupFileName;
        $manifestPath = $backupPath.'.manifest.json';

        try {
            File::put($backupPath, "-- test backup\n");
            $sha256 = hash_file('sha256', $backupPath);
            File::put($manifestPath, json_encode([
                'database' => [
                    'database' => 'atrilak_pos_production',
                    'sha256' => $sha256,
                ],
                'backup_file' => $backupFileName,
            ], JSON_THROW_ON_ERROR));
            config(['backup.directory' => $backupDirectory]);

            Artisan::shouldReceive('call')
                ->once()
                ->with('permission:cache-reset')
                ->andReturn(0);

            $service = new class extends BusinessDataResetService
            {
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
                        'business' => array_fill_keys($this->businessTables(), 4),
                        'protected' => array_fill_keys($this->protectedTables(), 2),
                    ];
                }

                public function reset(): array
                {
                    return [
                        'before' => array_fill_keys($this->businessTables(), 4),
                        'after' => array_fill_keys($this->businessTables(), 0),
                        'protected_before' => array_fill_keys($this->protectedTables(), 2),
                        'protected_after' => array_fill_keys($this->protectedTables(), 2),
                        'sequences' => $this->sequenceTables(),
                        'sequence_states' => [],
                    ];
                }
            };

            $backupService = new class extends DatabaseBackupService
            {
                public function create(): DatabaseBackupResult
                {
                    return DatabaseBackupResult::success('reset-audit-test.sql');
                }
            };

            $result = $service->run($backupService, $owner->id);

            $this->assertSame(0, $result['reset']['after']['products']);

            $audit = BusinessResetAudit::query()->latest('id')->firstOrFail();
            $this->assertSame($owner->id, $audit->user_id);
            $this->assertSame('atrilak_pos_production', $audit->database_name);
            $this->assertSame('success', $audit->status);
            $this->assertSame($backupFileName, basename((string) $audit->backup_file));
            $this->assertSame($sha256, $audit->backup_sha256);
            $this->assertCount(count($service->businessTables()), $audit->business_counts_before);
            $this->assertCount(count($service->protectedTables()), $audit->protected_counts_before);
        } finally {
            File::deleteDirectory($backupDirectory);
        }
    }

    public function test_reset_deletes_business_rows_preserves_system_rows_and_restarts_sequences(): void
    {
        $this->requireSeparatePostgresTestDatabase();
        $now = now();

        $userId = DB::table('users')->insertGetId([
            'name' => 'Reset test user',
            'email' => 'reset-test-'.uniqid('', true).'@example.test',
            'password' => 'test-password-hash',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Reset test role '.uniqid(),
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $settingId = DB::table('settings')->insertGetId([
            'store_name' => 'Reset test store',
            'logo_image' => 'settings/logo.png',
            'qr_image' => 'settings/qr.png',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $deliveryZoneId = DB::table('delivery_zones')->insertGetId([
            'name' => 'Reset test zone '.uniqid(),
            'base_delivery_fee' => 0,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Reset test category '.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $unitId = DB::table('units')->insertGetId([
            'code' => 'T'.random_int(100000, 999999),
            'name' => 'Reset test unit',
            'short_name' => 'RTU',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $productId = DB::table('products')->insertGetId([
            'category_id' => $categoryId,
            'name' => 'Reset test product',
            'cost_price' => 10,
            'selling_price' => 20,
            'stock_qty' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('product_units')->insert([
            'product_id' => $productId,
            'unit_id' => $unitId,
            'conversion_rate' => 1,
            'is_base_unit' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('stock_movements')->insert([
            'product_id' => $productId,
            'type' => 'IN',
            'qty' => 1,
            'stock_before' => 0,
            'stock_after' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $result = app(BusinessDataResetService::class)->reset();

        $this->assertSame(1, $result['before']['products']);
        $this->assertSame(1, $result['before']['product_units']);
        $this->assertSame(1, $result['before']['stock_movements']);
        $this->assertSame(0, $result['after']['products']);
        $this->assertSame(0, $result['after']['product_units']);
        $this->assertSame(0, $result['after']['stock_movements']);

        $this->assertDatabaseHas('users', ['id' => $userId]);
        $this->assertDatabaseHas('roles', ['id' => $roleId]);
        $this->assertDatabaseHas('settings', ['id' => $settingId]);
        $this->assertDatabaseHas('delivery_zones', ['id' => $deliveryZoneId]);

        $states = app(BusinessDataResetService::class)->sequenceStates();
        $this->assertFalse($states['products']['is_called']);
        $this->assertFalse($states['categories']['is_called']);
    }

    public function test_reset_rolls_back_all_deletes_when_a_postcondition_fails(): void
    {
        $this->requireSeparatePostgresTestDatabase();
        $now = now();

        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Rollback test category '.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $productId = DB::table('products')->insertGetId([
            'category_id' => $categoryId,
            'name' => 'Rollback test product',
            'cost_price' => 10,
            'selling_price' => 20,
            'stock_qty' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::unprepared('DROP TRIGGER IF EXISTS reset_test_reinsert_product ON products');
        DB::unprepared('DROP FUNCTION IF EXISTS reset_test_reinsert_product()');
        DB::unprepared(<<<'SQL'
CREATE FUNCTION reset_test_reinsert_product() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    INSERT INTO products (category_id, name, cost_price, selling_price, stock_qty, created_at, updated_at)
    VALUES (OLD.category_id, 'trigger reinsertion', 0, 0, 0, NOW(), NOW());
    RETURN OLD;
END;
$$;
SQL);
        DB::unprepared(<<<'SQL'
CREATE TRIGGER reset_test_reinsert_product
AFTER DELETE ON products
FOR EACH ROW EXECUTE FUNCTION reset_test_reinsert_product();
SQL);

        $caught = null;

        try {
            app(BusinessDataResetService::class)->reset();
        } catch (Throwable $exception) {
            $caught = $exception;
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS reset_test_reinsert_product ON products');
            DB::unprepared('DROP FUNCTION IF EXISTS reset_test_reinsert_product()');
        }

        $this->assertNotNull($caught);
        $this->assertDatabaseHas('products', ['id' => $productId]);
        $this->assertSame(1, DB::table('products')->where('category_id', $categoryId)->count());
    }

    private function requireSeparatePostgresTestDatabase(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('This integration test requires PostgreSQL.');
        }

        $database = (string) DB::selectOne('SELECT current_database() AS database_name')->database_name;

        $this->assertNotSame(
            'atrilak_pos_production',
            $database,
            'Reset integration tests must never run against Production.'
        );
        $this->assertSame('atrilak_pos_test', $database);
    }
}
