<?php

namespace Tests\Feature\Customers;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerImportBrowserContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('permissions', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
        });
        Schema::create('roles', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
        });
        Schema::create('role_has_permissions', function ($table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });
        Schema::create('model_has_permissions', function ($table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        Schema::create('model_has_roles', function ($table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');

        parent::tearDown();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('customers.import.index'))
            ->assertRedirect(route('login'));
    }

    public function test_cashier_is_forbidden_from_the_import_page(): void
    {
        $cashier = new User(['name' => 'Cashier', 'email' => 'cashier@example.test', 'role' => 'cashier']);

        $this->actingAs($cashier)
            ->get(route('customers.import.index'))
            ->assertForbidden();
    }

    public function test_manager_and_owner_can_see_the_import_page(): void
    {
        foreach (['manager', 'owner'] as $role) {
            $user = new User(['name' => ucfirst($role), 'email' => $role.'@example.test', 'role' => $role]);

            $this->actingAs($user)
                ->get(route('customers.import.index'))
                ->assertOk()
                ->assertSee('นำเข้าสมาชิกจาก Excel')
                ->assertSee('ประวัติการนำเข้า');
        }
    }
}
