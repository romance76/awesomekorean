<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// 매일 새 YouTube Shorts 수집
Schedule::command('shorts:fetch --limit=500 --korean-ratio=70')->dailyAt('03:00');

// 뉴스 RSS 수집 (하루 3번)
Schedule::command('news:fetch')->dailyAt('06:00');
Schedule::command('news:fetch')->dailyAt('12:00');
Schedule::command('news:fetch')->dailyAt('18:00');

// 음악 트랙 자동 수집 (매일 02:00, 500곡, 한국70%+팝30%, 7일 롤링)
Schedule::command('music:fetch --daily=500 --korean-ratio=70')->dailyAt('02:00');

Schedule::command('elder:check')->everyMinute();
Schedule::command('reservations:expire')->everyMinute();
