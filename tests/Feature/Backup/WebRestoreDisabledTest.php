<?php

namespace Tests\Feature\Backup;

use App\Models\User;
use App\Services\Backup\DatabaseBackupResult;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WebRestoreDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_cli_only_restore_guidance_without_a_restore_form(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('backups.index'))
            ->assertOk()
            ->assertSee('Restore ผ่านหน้าเว็บถูกปิดเพื่อความปลอดภัย')
            ->assertSee('การกู้คืนต้องทำผ่านคำสั่งสำหรับ Owner และคู่มือการกู้คืน')
            ->assertDontSee('name="backup_file"', false)
            ->assertDontSee('backups.restore', false)
            ->assertDontSee('action="http://localhost/backups/restore"', false);
    }

    public function test_web_restore_route_and_named_route_are_unavailable(): void
    {
        $this->assertNull(app('router')->getRoutes()->getByName('backups.restore'));

        $this->post('/backups/restore')->assertNotFound();
    }

    public function test_manual_backup_and_download_routes_remain_owner_only(): void
    {
        foreach (['backups.index', 'backups.create', 'backups.download'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertInstanceOf(IlluminateRoute::class, $route, $name);
            $this->assertContains('auth', $route->gatherMiddleware(), $name);
            $this->assertContains('role:owner', $route->gatherMiddleware(), $name);
        }

        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get(route('backups.index'))->assertOk();
    }

    public function test_owner_can_still_create_a_manual_backup(): void
    {
        $this->app->instance(DatabaseBackupService::class, new class extends DatabaseBackupService
        {
            public function create(): DatabaseBackupResult
            {
                return DatabaseBackupResult::success('atrilak_backup_test.sql');
            }
        });
        $owner = User::factory()->create(['role' => 'owner']);

        $this->from(route('backups.index'))
            ->actingAs($owner)
            ->post(route('backups.create'))
            ->assertRedirect(route('backups.index'))
            ->assertSessionHas('success', 'สร้าง Backup สำเร็จ: atrilak_backup_test.sql');
    }

    public function test_cashier_and_manager_cannot_open_backup_page(): void
    {
        foreach (['cashier', 'manager'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('backups.index'))
                ->assertForbidden();
        }
    }

    public function test_backup_controller_contains_no_legacy_restore_execution(): void
    {
        $controller = File::get(app_path('Http/Controllers/BackupController.php'));

        $this->assertStringNotContainsString('putenv(', $controller);
        $this->assertStringNotContainsString('exec(', $controller);
        $this->assertStringNotContainsString('psql.exe', $controller);
        $this->assertDoesNotMatchRegularExpression('/function\s+restore\s*\(/', $controller);
    }

    public function test_restore_configuration_is_disabled_by_default(): void
    {
        $this->assertFalse(config('backup.restore_enabled'));
        $this->assertSame(512000, config('backup.restore_max_kb'));
        $this->assertSame(1800, config('backup.restore_timeout_seconds'));
        $this->assertArrayHasKey('psql_path', config('backup'));
    }
}
