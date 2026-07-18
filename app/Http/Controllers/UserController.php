<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserRoleRequest;
use App\Models\User;
use App\Services\UserRoleSynchronizationService;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->get();

        return view(
            'users.index',
            compact('users')
        );
    }

    public function updateRole(
        UpdateUserRoleRequest $request,
        User $user,
        UserRoleSynchronizationService $roleSynchronizationService
    )
    {
        $roleSynchronizationService->synchronize(
            $user,
            $request->validated('role')
        );

        return back()->with(
            'success',
            'เปลี่ยนสิทธิ์เรียบร้อย'
        );
    }
}
