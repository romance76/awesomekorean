<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// 콘텐츠 자동 수집 로그 — 실패해도 아무 흔적이 안 남아 원인 파악이 안 되던
// 문제가 있어, 아래 5개 자동 수집 작업은 전부 이 파일에 출력을 남기도록 함.
$contentLog = storage_path('logs/content-sync.log');

// 매일 새 YouTube Shorts 수집
Schedule::command('shorts:fetch --limit=500 --korean-ratio=70')->dailyAt('03:00')->appendOutputTo($contentLog);

// 뉴스 수집: 오마이뉴스 11개 카테고리별 RSS, 2시간마다 (하루 12번)
Schedule::command('news:fetch')->cron('0 */2 * * *')->withoutOverlapping()->appendOutputTo($contentLog);

// 홈 화면 언론사별 헤드라인 위젯: 여러 언론사 RSS에서 제목+썸네일+링크만 30분마다 수집
Schedule::command('headlines:fetch')->everyThirtyMinutes()->withoutOverlapping()->appendOutputTo($contentLog);

// 홈 화면 인기 주식 위젯 + 증권 페이지 시세: 15분마다 갱신
Schedule::command('market:fetch')->everyFifteenMinutes()->withoutOverlapping()->appendOutputTo($contentLog);

// 음악 트랙 자동 수집 (매일 02:00, 500곡, 한국70%+팝30%, 7일 롤링)
// 주의: shorts:fetch 와 같은 YouTube Data API 키/쿼터를 공유함 — 하루 쿼터를
// 다 쓰면 이후 실행되는 작업(예: 03:00 shorts:fetch)이 조용히 실패할 수 있음.
Schedule::command('music:fetch --daily=500')->dailyAt('02:00')->appendOutputTo($contentLog);

// 레시피 자동 수집 (식품안전나라 API, 매일 04:00) — 기존엔 스케줄에 아예
// 등록돼 있지 않아 관리자가 수동으로 누르지 않는 한 절대 갱신되지 않았음.
Schedule::command('recipes:sync-all')->dailyAt('04:00')->withoutOverlapping()->appendOutputTo($contentLog);

Schedule::command('elder:check')->everyMinute();
Schedule::command('elder:call')->everyMinute()->withoutOverlapping();
Schedule::command('reservations:expire')->everyMinute();
Schedule::command('promotions:expire')->everyMinute();
Schedule::command('events:remind')->everyThirtyMinutes();

// 비활성 개인/그룹 채팅방 자동 잠금 및 삭제 (매시간)
Schedule::command('chat:expire-rooms')->hourly();

// 포커 토너먼트 자동 생성 (매일 00:10 — 내일 스케줄 생성)
Schedule::command('poker:generate-tournaments')->dailyAt('00:10');
// 포커 토너먼트 자동 시작 (매분 — 시간 된 토너먼트 시작)
Schedule::command('poker:start-tournaments')->everyMinute()->withoutOverlapping();

// 매일 새벽 3시 한인 업소 Google Places 업데이트
Schedule::command('places:import')->dailyAt('03:30')->appendOutputTo($contentLog);
