<?php

namespace App\Services;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class FirstOwnerProvisioningService
{
    public function ownerExists(): bool
    {
        return User::query()
            ->where('role', 'owner')
            ->orWhereHas('roles', fn ($query) => $query->where('name', 'Owner'))
            ->exists();
    }

    public function create(string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $password): User {
            if ($this->ownerExists()) {
                throw new DomainException('An Owner already exists.');
            }

            $role = Role::findOrCreate('Owner', 'web');

            $owner = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'owner',
            ]);

            $owner->syncRoles([$role]);

            return $owner;
        });
    }
}
