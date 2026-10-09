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

// 매일 새 YouTube Shorts 수집 — 한국 비율은 관리자 페이지(숏츠 관리 → 설정)에
// 저장된 board.shorts.korea_ratio를 shorts:fetch가 직접 읽으므로 여기서 지정하지 않음.
// YouTube API 쿼터(일 10,000, 매일 07~08시 UTC 초기화) 안에서 꾸준히 쌓도록 하루 3번 분할 실행.
// search.list 1회=100 → 1회 실행당 12검색(1,200) × 3회 = 3,600 (music 4,800 과 합쳐 약 8,400, 여유 ~1,500)
Schedule::command('shorts:fetch --limit=170 --max-searches=12')->cron('40 9,15,21 * * *')->withoutOverlapping()->appendOutputTo($contentLog);

// 뉴스 수집: 오마이뉴스 11개 카테고리별 RSS, 2시간마다 (하루 12번)
Schedule::command('news:fetch')->cron('0 */2 * * *')->withoutOverlapping()->appendOutputTo($contentLog);

// 홈 화면 언론사별 헤드라인 위젯: 여러 언론사 RSS에서 제목+썸네일+링크만 30분마다 수집
Schedule::command('headlines:fetch')->everyThirtyMinutes()->withoutOverlapping()->appendOutputTo($contentLog);

// 홈 화면 인기 주식 위젯 + 증권 페이지 시세: 15분마다 갱신
Schedule::command('market:fetch')->everyFifteenMinutes()->withoutOverlapping()->appendOutputTo($contentLog);
Schedule::command('earnings:fetch')->everySixHours()->withoutOverlapping()->appendOutputTo($contentLog);
Schedule::call(fn() => \Illuminate\Support\Facades\DB::table('site_visits')->where('visit_date', '<', now('America/New_York')->subDays(45)->toDateString())->delete())->dailyAt('04:10');

// 음악 트랙 자동 수집 (하루 4번 분할, 한국 100%, 30일 보관)
// shorts:fetch 와 같은 YouTube Data API 키/쿼터를 공유 — 쿼터 초기화(07~08시 UTC) 직후인 09시부터
// 실행당 12검색 상한으로 나눠 수집하고, 403/429 가 나면 그 실행만 중단(다음 실행에서 이어서).
Schedule::command('music:fetch --daily=125 --max-searches=12')->cron('10 9,13,17,21 * * *')->withoutOverlapping()->appendOutputTo($contentLog);

// 레시피 자동 수집 (식품안전나라 API, 매일 04:00) — 기존엔 스케줄에 아예
// 등록돼 있지 않아 관리자가 수동으로 누르지 않는 한 절대 갱신되지 않았음.
Schedule::command('recipes:sync-all')->dailyAt('04:00')->withoutOverlapping()->appendOutputTo($contentLog);

Schedule::command('elder:check')->everyMinute();
Schedule::command('elder:call')->everyMinute()->withoutOverlapping();
Schedule::command('calls:cleanup')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('reservations:expire')->everyMinute();
Schedule::command('promotions:expire')->everyMinute();
Schedule::command('events:remind')->everyThirtyMinutes();
// 추첨 시작 5분 전 알림 (응모 후 알림 신청한 회원)
Schedule::command('sweepstakes:send-reminders')->everyMinute()->withoutOverlapping();
// 당첨자에게 "이메일·전화번호·주소 확인" 안내 알림 + 상품 수령 건(claim) 동기화
Schedule::command('sweepstakes:notify-winners')->everyMinute()->withoutOverlapping();

// 비활성 개인/그룹 채팅방 자동 잠금 및 삭제 (매시간)
Schedule::command('chat:expire-rooms')->hourly();

// 포커 토너먼트 자동 생성 (매일 00:10 — 내일 스케줄 생성)
Schedule::command('poker:generate-tournaments')->dailyAt('00:10');
// 포커 토너먼트 자동 시작 (매분 — 시간 된 토너먼트 시작)
Schedule::command('poker:start-tournaments')->everyMinute()->withoutOverlapping();

// 한인 업소 Google Places 업데이트 — 17개 도시 × 46개 검색어(약 800회 이상 유료 요청)라 매일 돌리면
// 비용이 크다. 서버 cron 이 매분 정확히 돌게 된 뒤로는 실제로 매일 실행되므로 주 1회(일요일 새벽)로 낮춤.
Schedule::command('places:import')->weeklyOn(0, '03:30')->withoutOverlapping()->appendOutputTo($contentLog);

// 부동산 더미 데이터를 실제 매물로 대체 (소프트 운영 기간): 전국 한인 밀집 지역
// 매물을 매일 랜덤으로 가져오고(source=scraped, 매매+렌트 둘 다), 30일 지난 건
// 자동 삭제. 회원이 직접 올린 매물(source=user, 룸메이트 포함)은 건드리지 않음 —
// 룸메이트는 MLS 데이터에 없는 카테고리라 회원 직접 등록만 가능. REALTYAPI_KEY
// 없으면 조용히 스킵.
Schedule::command('realestate:scrape --type=sale')->dailyAt('05:00')->appendOutputTo($contentLog);
Schedule::command('realestate:scrape --type=rent')->dailyAt('05:15')->appendOutputTo($contentLog);
Schedule::command('realestate:expire-scraped')->dailyAt('05:30')->appendOutputTo($contentLog);

// 중고장터 더미 데이터를 실제 매물로 대체 (소프트 운영 기간): eBay Browse API(공식,
// 무료, 일 5,000회)에서 카테고리별 중고 매물을 매일 가져오고(source=scraped), 30일
// 지난 건 자동 삭제. 회원이 직접 올린 매물(source=user)은 건드리지 않음.
// EBAY_CLIENT_ID/SECRET 없으면 조용히 스킵.
Schedule::command('market:scrape')->dailyAt('05:45')->appendOutputTo($contentLog);
Schedule::command('market:expire-scraped')->dailyAt('06:00')->appendOutputTo($contentLog);

// '정보' 게시판 자동 생성 요청 — 다른 콘텐츠와 달리 매일 스케줄이 없어서
// 관리자가 "전체 콘텐츠 자동 수집" 버튼을 직접 누르지 않으면 전혀 새 글이
// 안 쌓이던 문제가 있었음. 요청만 등록하고 실제 생성은 시간당 체크인
// 루틴(외부 세션)이 수행.
Schedule::command('info:trigger-generation')->dailyAt('06:15')->appendOutputTo($contentLog);

// NEW 전면광고: 카드 입력만 하고 떠난 신청이 잡은 시간 정리 + 카드 보류(약 7일)가 만료되기 전에 오래 승인 못 한 신청 자동 취소
Artisan::command('flyers:cleanup', function () {
    $stale = \App\Support\FlyerService::purgeStale();
    $held = \App\Support\FlyerService::expireHolds();
    $this->info("결제 미완료 정리 {$stale}건, 보류 만료 취소 {$held}건");
})->purpose('NEW 전면광고 결제 대기/보류 만료 정리');
Schedule::command('flyers:cleanup')->everyThirtyMinutes()->withoutOverlapping();

// 로그인 실패 기록은 30일만 보관
Schedule::call(fn() => \Illuminate\Support\Facades\DB::table('login_failures')->where('created_at', '<', now()->subDays(30))->delete())->dailyAt('04:20');
