<?php

namespace Tests\Feature\Backup;

use App\Console\Commands\ResetBusinessDataCommand;
use App\Models\User;
use App\Services\BusinessDataResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class BusinessDataResetWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_the_business_data_reset_form(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('backups.index'))
            ->assertOk()
            ->assertSee('Reset Business Data')
            ->assertSee(ResetBusinessDataCommand::CONFIRMATION)
            ->assertSee('name="confirmation"', false);
    }

    public function test_reset_endpoint_requires_the_exact_confirmation_before_calling_the_command(): void
    {
        Artisan::shouldReceive('call')->never();
        $owner = User::factory()->create(['role' => 'owner']);

        $this->from(route('backups.index'))
            ->actingAs($owner)
            ->post(route('backups.reset-business-data'), ['confirmation' => 'wrong confirmation'])
            ->assertRedirect(route('backups.index'))
            ->assertSessionHasErrors('confirmation');
    }

    public function test_owner_can_run_the_existing_reset_command_from_the_backup_page(): void
    {
        $owner = User::factory()->create([
            'role' => 'owner',
            'password' => Hash::make('owner-password'),
        ]);
        $service = Mockery::mock(BusinessDataResetService::class);
        $service->shouldReceive('run')->once()->andReturn([
            'backup' => [
                'file_name' => 'reset.sql',
                'sha256' => str_repeat('a', 64),
                'bytes' => 10,
            ],
            'reset' => [
                'protected_after' => [
                    'users' => 1,
                    'settings' => 1,
                ],
            ],
        ]);
        $this->app->instance(BusinessDataResetService::class, $service);

        $this->from(route('backups.index'))
            ->actingAs($owner)
            ->post(route('backups.reset-business-data'), [
                'acknowledged' => '1',
                'confirmation' => ResetBusinessDataCommand::CONFIRMATION,
                'password' => 'owner-password',
            ])
            ->assertRedirect(route('backups.index'))
            ->assertSessionHas('success', 'ล้างข้อมูลธุรกิจเรียบร้อยแล้ว');
    }

    public function test_reset_failure_is_reported_without_exposing_command_output(): void
    {
        $owner = User::factory()->create([
            'role' => 'owner',
            'password' => Hash::make('owner-password'),
        ]);
        $service = Mockery::mock(BusinessDataResetService::class);
        $service->shouldReceive('run')
            ->once()
            ->andThrow(new RuntimeException('internal reset failure'));
        $this->app->instance(BusinessDataResetService::class, $service);

        $this->from(route('backups.index'))
            ->actingAs($owner)
            ->post(route('backups.reset-business-data'), [
                'acknowledged' => '1',
                'confirmation' => ResetBusinessDataCommand::CONFIRMATION,
                'password' => 'owner-password',
            ])
            ->assertRedirect(route('backups.index'))
            ->assertSessionHas('error', 'Business data reset failed. Check the application log.');
    }

    public function test_non_owner_cannot_trigger_business_data_reset(): void
    {
        Artisan::shouldReceive('call')->never();
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)
            ->post(route('backups.reset-business-data'), [
                'confirmation' => ResetBusinessDataCommand::CONFIRMATION,
            ])
            ->assertForbidden();
    }

    public function test_owner_sees_the_non_dismissible_reset_modal_contract(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)
            ->get(route('backups.index'))
            ->assertOk()
            ->assertSee('RESET ATRILAK BUSINESS DATA')
            ->assertSee('data-backdrop="static"', false)
            ->assertSee('data-keyboard="false"', false)
            ->assertSee('name="acknowledged"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="confirmation"', false)
            ->assertSee('settings', false)
            ->assertSee('business', false);
    }

    public function test_owner_reset_requires_current_password_before_calling_shared_service(): void
    {
        Artisan::shouldReceive('call')->never();
        $owner = User::factory()->create([
            'role' => 'owner',
            'password' => Hash::make('owner-password'),
        ]);

        $this->from(route('backups.index'))
            ->actingAs($owner)
            ->post(route('backups.reset-business-data'), [
                'acknowledged' => '1',
                'confirmation' => 'RESET ATRILAK BUSINESS DATA',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('backups.index'))
            ->assertSessionHasErrors('password');
    }

    public function test_owner_reset_calls_the_shared_service_after_backend_validation(): void
    {
        $owner = User::factory()->create([
            'role' => 'owner',
            'password' => Hash::make('owner-password'),
        ]);
        $service = Mockery::mock(BusinessDataResetService::class);
        $service->shouldReceive('run')->once()->andReturn([
            'backup' => [
                'file_name' => 'reset.sql',
                'sha256' => str_repeat('a', 64),
                'bytes' => 10,
            ],
            'reset' => [
                'protected_after' => [
                    'users' => 1,
                    'settings' => 1,
                ],
            ],
        ]);
        $this->app->instance(BusinessDataResetService::class, $service);

        $this->from(route('backups.index'))
            ->actingAs($owner)
            ->post(route('backups.reset-business-data'), [
                'acknowledged' => '1',
                'confirmation' => 'RESET ATRILAK BUSINESS DATA',
                'password' => 'owner-password',
            ])
            ->assertRedirect(route('backups.index'))
            ->assertSessionHas('success');
    }

    public function test_reset_route_remains_owner_only_and_is_throttled(): void
    {
        $route = app('router')->getRoutes()->getByName('backups.reset-business-data');

        $this->assertNotNull($route);
        $this->assertContains('role:owner', $route->gatherMiddleware());
        $this->assertContains('throttle:3,1', $route->gatherMiddleware());
    }
}
