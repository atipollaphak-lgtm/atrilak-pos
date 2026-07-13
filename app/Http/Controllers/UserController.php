<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

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
        Request $request,
        User $user
    )
    {
        $request->validate([
            'role' => 'required'
        ]);

        $user->role = $request->role;
        $user->save();

        return back()->with(
            'success',
            'เปลี่ยนสิทธิ์เรียบร้อย'
        );
    }
}
