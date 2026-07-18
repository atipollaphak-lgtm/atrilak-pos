<?php

namespace App\Services\Backup;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;

class MaintenanceModeManager
{
    public function __construct(private readonly Application $app) {}

    public function isActive(): bool
    {
        return $this->app->maintenanceMode()->active();
    }

    public function down(): bool
    {
        return Artisan::call('down', ['--status' => 503]) === 0 && $this->isActive();
    }

    public function up(): bool
    {
        return Artisan::call('up') === 0 && ! $this->isActive();
    }
}
