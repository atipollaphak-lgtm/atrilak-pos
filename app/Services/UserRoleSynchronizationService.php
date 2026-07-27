<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserRoleSynchronizationService
{
    public function synchronize(User $user, string $role): User
    {
        return DB::transaction(function () use ($user, $role): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $spatieRole = Role::findOrCreate(ucfirst($role), 'web');

            $lockedUser->update(['role' => $role]);
            $lockedUser->syncRoles([$spatieRole]);

            return $lockedUser->refresh();
        });
    }
}
