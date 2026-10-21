<?php

namespace App\Support;

use Illuminate\Console\Scheduling\Event;

/**
 * 자동 수집 현황 — 스케줄러가 자동으로 돌리는 수집 작업이 "마지막으로 언제 성공/실패했는지"와
 * "마지막으로 새 콘텐츠가 들어온 때"를 항목별로 보여 주기 위한 기록.
 *
 * 관리자 화면의 "전체 콘텐츠 자동 수집" 체크리스트는 버튼을 눌렀을 때의 기록(manual-sync-all.log)만 보여 준다.
 * 자동으로 도는 수집은 그 기록을 갱신하지 않아서, 자동 수집이 잘 돌아도 화면은 "마지막 수동 실행 날짜"에 멈춰 보였다.
 *
 * routes/console.php 가 각 작업에 watch() 를 붙이면, 작업이 끝날 때 성공/실패 시각이
 * storage/framework/auto-collect-status.json 에 남는다(캐시를 비워도 사라지지 않게 파일).
 * 성공 표시는 "명령이 정상 종료됐다"는 뜻이다 — 새 영상이 하나도 없어도 성공일 수 있어서(예: 유튜브 한도),
 * 항목마다 "새 콘텐츠가 마지막으로 들어온 시각"을 같이 보여 주고 너무 오래되면 경고한다.
 */
class AutoCollectStatus
{
    /**
     * 항목 목록: key => [이름, 실행 주기 설명, 새 콘텐츠 시각을 구하는 함수(없으면 null), 이 시간(시간 단위) 넘게 새 콘텐츠가 없으면 경고(없으면 null)]
     */
    public static function sources(): array
    {
        return [
            'news'        => ['뉴스', '2시간마다', fn () => \App\Models\News::max('created_at'), 6],
            'headlines'   => ['언론사 헤드라인', '30분마다', null, null],
            'market'      => ['주식 시세', '15분마다', fn () => \App\Models\MarketQuote::max('updated_at'), 1],
            'earnings'    => ['실적 발표', '6시간마다', null, null],
            'shorts'      => ['쇼츠', '하루 3번 (09:40·15:40·21:40 UTC)', fn () => \App\Models\Short::max('created_at'), 20],
            'music'       => ['음악', '하루 4번', fn () => \App\Models\MusicTrack::max('created_at'), 14],
            'recipes'     => ['레시피', '매일 04:00', fn () => \App\Models\RecipePost::max('created_at'), null],
            'places'      => ['업소록(구글 장소)', '매주 일요일 03:30', fn () => \App\Models\Business::max('updated_at'), 24 * 10],
            'realestate'  => ['부동산 매물', '매일 05:00·05:15', fn () => \App\Models\RealEstateListing::where('source', 'scraped')->max('created_at'), 36],
            'marketitems' => ['중고장터(eBay)', '매일 05:45', fn () => \App\Models\MarketItem::where('source', 'scraped')->max('created_at'), 36],
            'events'      => ['지역 행사(Ticketmaster)', '매주 일요일 05:30(애틀랜타)', fn () => \App\Models\Event::where('source', 'ticketmaster')->max('updated_at'), 24 * 9],
            'event_feeds' => ['지역 행사(공식 피드)', '매주 일요일 06:30(애틀랜타)', fn () => \App\Models\Event::where('source', 'feed')->max('updated_at'), 24 * 9],
            'info'        => ['정보 글(머니+)', '매일 06:15 · 하루 1편, 11개 분야 순서대로 (다음: ' . self::nextInfoCategory() . ')', fn () => \App\Models\InfoPost::max('created_at'), 36],
        ];
    }

    private static function nextInfoCategory(): string
    {
        try {
            return \App\Console\Commands\TriggerInfoGeneration::nextCategory();
        } catch (\Throwable $e) {
            return '-';
        }
    }

    private static function path(): string
    {
        return storage_path('framework/auto-collect-status.json');
    }

    private static function read(): array
    {
        $raw = @file_get_contents(self::path());
        $j = $raw ? json_decode($raw, true) : null;
        return is_array($j) ? $j : [];
    }

    /** 스케줄 작업에 성공/실패 기록을 붙인다 (routes/console.php 에서 사용) */
    public static function watch(Event $event, string $key): Event
    {
        return $event
            ->onSuccess(fn () => self::record($key, true))
            ->onFailure(fn () => self::record($key, false));
    }

    public static function record(string $key, bool $ok): void
    {
        try {
            $fp = fopen(self::path(), 'c+');
            if (!$fp) return;
            flock($fp, LOCK_EX);
            $raw = stream_get_contents($fp);
            $data = $raw ? (json_decode($raw, true) ?: []) : [];
            $now = now()->toIso8601String();
            $row = $data[$key] ?? [];
            $row['last_run_at'] = $now;
            if ($ok) $row['last_ok_at'] = $now; else $row['last_fail_at'] = $now;
            $data[$key] = $row;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        } catch (\Throwable $e) {
            // 기록 실패가 수집 작업을 깨뜨리면 안 된다
        }
    }

    /**
     * 화면용 목록.
     * state: ok(정상) / late(실행은 됐지만 새 콘텐츠가 오래 없음) / fail(마지막 실행이 실패) / never(아직 기록 없음 — 첫 자동 실행 뒤에 생김)
     */
    public static function snapshot(): array
    {
        $runs = self::read();
        $rows = [];
        foreach (self::sources() as $key => [$label, $schedule, $latestFn, $staleHours]) {
            $r = $runs[$key] ?? [];
            $latest = null;
            if ($latestFn) {
                try {
                    $v = $latestFn();
                    $latest = $v ? \Carbon\Carbon::parse($v)->toIso8601String() : null;
                } catch (\Throwable $e) { /* 칸을 못 읽어도 나머지는 보여 준다 */ }
            }
            $lastOk = $r['last_ok_at'] ?? null;
            $lastFail = $r['last_fail_at'] ?? null;

            $state = 'ok';
            if (!$lastOk && !$lastFail) $state = 'never';
            elseif ($lastFail && (!$lastOk || $lastFail > $lastOk)) $state = 'fail';
            if ($state !== 'fail' && $staleHours && $latest && \Carbon\Carbon::parse($latest)->lt(now()->subHours($staleHours))) $state = 'late';
            // 아직 한 번도 기록이 없어도 새 콘텐츠가 오래 없으면 경고
            if ($state === 'never' && $staleHours && $latest && \Carbon\Carbon::parse($latest)->lt(now()->subHours($staleHours))) $state = 'late';

            $rows[] = [
                'key' => $key, 'label' => $label, 'schedule' => $schedule,
                'last_run_at' => $r['last_run_at'] ?? null, 'last_ok_at' => $lastOk, 'last_fail_at' => $lastFail,
                'latest_content_at' => $latest, 'has_content_metric' => $latestFn !== null, 'stale_hours' => $staleHours,
                'state' => $state,
            ];
        }
        return $rows;
    }

    /** 자동 수집 로그(content-sync.log) 끝부분 — 실패 원인(예: 유튜브 한도 초과)을 볼 수 있게. 너무 크지 않게 뒤에서 $bytes 만큼만. */
    public static function logTail(int $bytes = 12000): string
    {
        $path = storage_path('logs/content-sync.log');
        if (!is_file($path)) return '';
        $size = filesize($path);
        $fp = @fopen($path, 'r');
        if (!$fp) return '';
        if ($size > $bytes) fseek($fp, -$bytes, SEEK_END);
        $txt = (string) stream_get_contents($fp);
        fclose($fp);
        if ($size > $bytes) $txt = preg_replace('/^[^\n]*\n/', '', $txt, 1);   // 잘린 첫 줄 버림
        return $txt;
    }
}
