<?php

namespace Tests\Feature\Units;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as IlluminateRoute;
use Tests\TestCase;

class UnitRouteAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_unit_management_routes(): void
    {
        $unit = Unit::query()->create($this->unitData('GST'));

        $requests = [
            fn () => $this->get(route('units.index')),
            fn () => $this->post(route('units.store'), $this->unitData('NEW')),
            fn () => $this->put(route('units.update', $unit), $this->unitData('UPD')),
            fn () => $this->delete(route('units.destroy', $unit)),
            fn () => $this->post(route('units.seed')),
            fn () => $this->post(route('units.merge'), [
                'from_unit_id' => $unit->id,
                'to_unit_id' => $unit->id + 1,
            ]),
        ];

        foreach ($requests as $request) {
            $request()->assertRedirect(route('login'));
        }
    }

    public function test_cashiers_cannot_access_unit_management_routes(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $unit = Unit::query()->create($this->unitData('CSH'));

        $requests = [
            fn () => $this->actingAs($cashier)->get(route('units.index')),
            fn () => $this->actingAs($cashier)->post(route('units.store'), $this->unitData('NEW')),
            fn () => $this->actingAs($cashier)->put(route('units.update', $unit), $this->unitData('UPD')),
            fn () => $this->actingAs($cashier)->delete(route('units.destroy', $unit)),
            fn () => $this->actingAs($cashier)->post(route('units.seed')),
            fn () => $this->actingAs($cashier)->post(route('units.merge'), [
                'from_unit_id' => $unit->id,
                'to_unit_id' => $unit->id + 1,
            ]),
        ];

        foreach ($requests as $request) {
            $request()->assertForbidden();
        }
    }

    public function test_managers_can_access_all_unit_management_routes(): void
    {
        $this->assertRoleCanManageUnits('manager');
    }

    public function test_owners_can_access_all_unit_management_routes(): void
    {
        $this->assertRoleCanManageUnits('owner');
    }

    public function test_unit_route_names_and_urls_remain_compatible(): void
    {
        $unit = new Unit(['id' => 42]);
        $unit->setAttribute('id', 42);
        $unit->exists = true;

        $this->assertSame(url('/units'), route('units.index'));
        $this->assertSame(url('/units'), route('units.store'));
        $this->assertSame(url('/units/42'), route('units.update', $unit));
        $this->assertSame(url('/units/42'), route('units.destroy', $unit));
        $this->assertSame(url('/units/seed'), route('units.seed'));
        $this->assertSame(url('/units/merge'), route('units.merge'));
    }

    public function test_active_unit_routes_are_all_protected_by_manager_middleware(): void
    {
        foreach ([
            'units.index',
            'units.store',
            'units.update',
            'units.destroy',
            'units.seed',
            'units.merge',
        ] as $name) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertInstanceOf(IlluminateRoute::class, $route, $name);
            $this->assertContains('auth', $route->gatherMiddleware(), $name);
            $this->assertContains('role:manager', $route->gatherMiddleware(), $name);
        }
    }

    private function assertRoleCanManageUnits(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get(route('units.index'))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('units.store'), $this->unitData(strtoupper(substr($role, 0, 3))))
            ->assertRedirect(route('units.index'));

        $unitToUpdate = Unit::query()->create($this->unitData('UP1'));
        $this->actingAs($user)
            ->put(route('units.update', $unitToUpdate), $this->unitData('UP2'))
            ->assertRedirect(route('units.index'));
        $this->assertDatabaseHas('units', ['id' => $unitToUpdate->id, 'code' => 'UP2']);

        $unitToDelete = Unit::query()->create($this->unitData('DEL'));
        $this->actingAs($user)
            ->delete(route('units.destroy', $unitToDelete))
            ->assertRedirect(route('units.index'));
        $this->assertDatabaseMissing('units', ['id' => $unitToDelete->id]);

        $this->actingAs($user)
            ->post(route('units.seed'))
            ->assertRedirect(route('units.index'));

        $from = Unit::query()->create($this->unitData('FRM'));
        $to = Unit::query()->create($this->unitData('TGT'));
        $this->actingAs($user)
            ->post(route('units.merge'), [
                'from_unit_id' => $from->id,
                'to_unit_id' => $to->id,
            ])
            ->assertRedirect(route('units.index'));
        $this->assertDatabaseMissing('units', ['id' => $from->id]);
    }

    private function unitData(string $code): array
    {
        return [
            'code' => $code,
            'name' => 'Unit '.$code,
            'short_name' => $code,
            'active' => true,
            'sort_order' => 10,
        ];
    }
}
