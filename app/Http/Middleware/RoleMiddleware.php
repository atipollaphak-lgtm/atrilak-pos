<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $userRole = $request->user()->role;

        $levels = [
            'cashier' => 1,
            'manager' => 2,
            'owner' => 3,
        ];

        if (! isset($levels[$userRole]) || ! isset($levels[$role])) {
            abort(403);
        }

        if ($levels[$userRole] < $levels[$role]) {
            abort(403);
        }

        return $next($request);
    }
}
