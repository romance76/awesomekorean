<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// 매일 새 YouTube Shorts 수집
Schedule::command('shorts:fetch --limit=50 --days=7')->dailyAt('03:00');

// 뉴스 RSS 수집 (하루 3번)
Schedule::command('news:fetch')->dailyAt('06:00');
Schedule::command('news:fetch')->dailyAt('12:00');
Schedule::command('news:fetch')->dailyAt('18:00');

Schedule::command('elder:check')->everyMinute();
Schedule::command('reservations:expire')->everyMinute();
