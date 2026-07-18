<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRoleSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_role_update_synchronizes_the_authoritative_and_spatie_role_stores(): void
    {
        Role::findOrCreate('Owner', 'web');
        Role::findOrCreate('Manager', 'web');

        $owner = User::factory()->create(['role' => 'owner']);
        $owner->syncRoles(['Owner']);
        $user = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($owner)
            ->post(route('users.update-role', $user), ['role' => 'manager'])
            ->assertRedirect();

        $user->refresh();

        $this->assertSame('manager', $user->role);
        $this->assertTrue($user->hasRole('Manager'));
        $this->assertFalse($user->hasRole('Owner'));
    }

    public function test_technician_payment_menu_entries_match_manager_route_access(): void
    {
        $entries = collect(config('adminlte.menu'))
            ->filter(fn (array $item): bool => in_array($item['url'] ?? null, [
                'technician-payments',
                'technician-payment-batches',
            ], true));

        $this->assertCount(2, $entries);
        $this->assertTrue($entries->every(fn (array $item): bool => ($item['can'] ?? null) === 'manager'));
    }
}
