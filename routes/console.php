<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// 매일 새 YouTube Shorts 수집
Schedule::command('shorts:fetch --limit=500 --korean-ratio=70')->dailyAt('03:00');

// 뉴스 수집: 오마이뉴스 11개 카테고리별 RSS, 2시간마다 (하루 12번)
Schedule::command('news:fetch')->cron('0 */2 * * *')->withoutOverlapping();

// 음악 트랙 자동 수집 (매일 02:00, 500곡, 한국70%+팝30%, 7일 롤링)
Schedule::command('music:fetch --daily=500 --korean-ratio=70')->dailyAt('02:00');

Schedule::command('elder:check')->everyMinute();
Schedule::command('elder:call')->everyMinute()->withoutOverlapping();
Schedule::command('reservations:expire')->everyMinute();

// 포커 토너먼트 자동 생성 (매일 00:10 — 내일 스케줄 생성)
Schedule::command('poker:generate-tournaments')->dailyAt('00:10');
// 포커 토너먼트 자동 시작 (매분 — 시간 된 토너먼트 시작)
Schedule::command('poker:start-tournaments')->everyMinute()->withoutOverlapping();

// 매일 새벽 3시 한인 업소 Google Places 업데이트
Schedule::command('places:import')->dailyAt('03:30');
