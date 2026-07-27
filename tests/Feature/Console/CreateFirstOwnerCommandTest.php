<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateFirstOwnerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_first_owner_and_synchronizes_both_role_stores(): void
    {
        $this->artisan('atrilak:owner:create')
            ->expectsQuestion('Name', 'First Owner')
            ->expectsQuestion('Email', 'owner@example.test')
            ->expectsQuestion('Password', 'ValidPassword1!')
            ->expectsQuestion('Confirm password', 'ValidPassword1!')
            ->assertExitCode(0);

        $owner = User::query()->sole();

        $this->assertSame('owner', $owner->role);
        $this->assertTrue($owner->hasRole('Owner'));
        $this->assertDatabaseHas('roles', ['name' => 'Owner', 'guard_name' => 'web']);
    }

    public function test_it_refuses_to_create_another_user_when_an_owner_already_exists(): void
    {
        Role::query()->create(['name' => 'Owner', 'guard_name' => 'web']);
        $owner = User::factory()->create(['role' => 'owner']);
        $owner->assignRole('Owner');

        $this->artisan('atrilak:owner:create')
            ->expectsOutput('An Owner already exists. No changes were made.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'role' => 'owner']);
    }

    public function test_it_refuses_when_a_legacy_user_has_only_the_spatie_owner_role(): void
    {
        Role::query()->create(['name' => 'Owner', 'guard_name' => 'web']);
        $legacyOwner = User::factory()->create(['role' => 'cashier']);
        $legacyOwner->assignRole('Owner');

        $this->artisan('atrilak:owner:create')
            ->expectsOutput('An Owner already exists. No changes were made.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['id' => $legacyOwner->id, 'role' => 'cashier']);
    }

    public function test_it_rejects_invalid_interactive_input_without_creating_a_user(): void
    {
        $this->artisan('atrilak:owner:create')
            ->expectsQuestion('Name', '')
            ->expectsQuestion('Email', 'not-an-email')
            ->expectsQuestion('Password', 'short')
            ->expectsQuestion('Confirm password', 'different')
            ->expectsOutputToContain('The name field is required.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }
}
