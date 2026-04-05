<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('shorts:fetch --limit=1000 --days=30')->dailyAt('03:00');
Schedule::command('maangchi:fetch')->weeklyOn(1, '04:00');

Schedule::command('elder:check')->everyMinute();
