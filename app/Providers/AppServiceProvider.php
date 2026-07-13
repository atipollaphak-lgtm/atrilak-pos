<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('owner', function ($user) {
            return $user->role === 'owner';
        });

        Gate::define('manager', function ($user) {
            return in_array($user->role, ['owner', 'manager']);
        });

        Gate::define('cashier', function ($user) {
            return in_array($user->role, ['owner', 'manager', 'cashier']);
        });
    }
}
