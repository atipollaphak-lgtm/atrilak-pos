<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('atrilak:backup')
    ->dailyAt('20:00');

Schedule::command('pricing:apply-scheduled')
    ->everyMinute();
