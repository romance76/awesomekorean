<?php

namespace App\Jobs;

use App\Models\MusicTrack;
use App\Models\Short;
use App\Services\AdminCollectService;
use App\Services\RecipeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * 관리자 "지금 수집" 버튼의 실제 일을 백그라운드에서 한다.
 *
 * - 수집 알고리즘은 건드리지 않는다: 기존 artisan 커맨드(music:fetch / news:fetch / shorts:fetch)와
 *   RecipeService::syncFromApi 를 옛 버튼과 같은 옵션으로 그대로 부른다.
 * - 재시도 없음($tries = 1): 외부 API 쿼터(YouTube 등)를 또 쓰지 않도록.
 * - 끝나면(성공·실패·시간 초과 모두) 종류별 락을 풀고, 마지막 결과를 Cache 에 남긴다.
 * - 큐: 기존 큐 워커가 보는 기본 큐. config('admin_collect.queue') 로 전용 큐로 분리할 수 있다.
 */
class RunAdminCollect implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $maxExceptions = 1;
    public $failOnTimeout = true;
    /** 이 작업의 제한 시간(초) — 워커 기본 60초보다 우선한다. 생성자에서 종류별로 정한다. */
    public $timeout = 900;

    public function __construct(public string $kind, public array $params, public string $runId)
    {
        $this->timeout = (new AdminCollectService())->timeout($kind);
        $queue = config('admin_collect.queue');
        if ($queue) {
            $this->onQueue($queue);
        }
    }

    public function handle(AdminCollectService $svc): void
    {
        // 내 락이 아니면(앞서 락이 만료돼 다른 수집이 시작됨) 중복 실행하지 않는다
        if (!$svc->claim($this->kind, $this->runId)) {
            Log::warning("[admin-collect:{$this->kind}] 다른 수집이 이미 진행 중이라 건너뜀");
            return;
        }

        try {
            @set_time_limit(0);
            $r = $this->execute($svc);
            $svc->finish($this->kind, $this->runId, $r['ok'], $r['summary'], $r['error'] ?? null, $r['data'] ?? []);
        } catch (\Throwable $e) {
            Log::error("[admin-collect:{$this->kind}] " . $svc->oneLine($e->getMessage()));
            $svc->finish($this->kind, $this->runId, false, $svc->label($this->kind) . ' 수집 중 문제가 생겼어요', $svc->oneLine($e->getMessage()));
        } finally {
            $svc->release($this->kind, $this->runId);
        }
    }

    /** 시간 초과·강제 종료처럼 handle() 안에서 끝맺지 못한 경우 */
    public function failed(\Throwable $e): void
    {
        $svc = app(AdminCollectService::class);
        // TimeoutExceededException 은 MaxAttemptsExceededException 의 하위 클래스라 시간 초과를 먼저 가려낸다
        $timedOut = $e instanceof TimeoutExceededException;
        // 같은 작업이 두 번째로 배달된 경우(원래 작업이 아직 도는 중일 수 있음)엔 결과·락을 건드리지 않는다
        if (!$timedOut && $e instanceof MaxAttemptsExceededException) {
            Log::warning("[admin-collect:{$this->kind}] 작업이 두 번 이상 배달돼 건너뜀");
            return;
        }
        $mins = (int) ceil($svc->timeout($this->kind) / 60);
        $error = $timedOut
            ? "제한 시간({$mins}분)을 넘겨 중간에 멈췄어요"
            : $svc->oneLine($e->getMessage());
        Log::error("[admin-collect:{$this->kind}] 실패: {$error}");
        $svc->finish($this->kind, $this->runId, false, $svc->label($this->kind) . ' 수집이 중간에 멈췄어요', $error);
        $svc->release($this->kind, $this->runId);
    }

    /** @return array{ok:bool, summary:string, error?:?string, data?:array} */
    private function execute(AdminCollectService $svc): array
    {
        return match ($this->kind) {
            'music'   => $this->runMusic($svc),
            'news'    => $this->runNews($svc),
            'shorts'  => $this->runShorts($svc),
            'recipes' => $this->runRecipes($svc),
            default   => ['ok' => false, 'summary' => '알 수 없는 수집 종류예요', 'error' => $this->kind],
        };
    }

    private function runMusic(AdminCollectService $svc): array
    {
        $before = MusicTrack::count();
        $code = Artisan::call('music:fetch', ['--daily' => 100]);   // 옛 버튼과 같은 옵션
        $out = Artisan::output();
        $total = MusicTrack::count();
        $added = preg_match('/완료:\s*(\d+)곡 추가/u', $out, $m) ? (int) $m[1] : max(0, $total - $before);
        $data = ['added' => $added, 'total' => $total];

        if ($code !== 0) {
            return ['ok' => false, 'summary' => '음악 수집이 끝까지 되지 않았어요', 'error' => $svc->lastLine($out) ?: '수집 명령이 실패했어요', 'data' => $data];
        }
        // YouTube 하루 사용량(쿼터) 초과 감지 — 옛 버튼과 같은 기준
        if (preg_match('/API 오류: 403|API 한도 도달/u', $out)) {
            return ['ok' => false, 'summary' => "YouTube 하루 사용량을 넘었어요. 이번에 추가된 곡은 {$added}곡이에요. 내일 다시 시도해 주세요.", 'error' => 'YouTube API 사용량 초과(403)', 'data' => $data];
        }
        return ['ok' => true, 'data' => $data, 'summary' => $added > 0
            ? "음악 {$added}곡을 새로 담았어요 (전체 {$total}곡)"
            : "새로 담은 곡이 없어요 (전체 {$total}곡) — 이미 있거나 조건에 맞지 않아 제외됐어요"];
    }

    private function runNews(AdminCollectService $svc): array
    {
        $code = Artisan::call('news:fetch');
        $out = Artisan::output();
        $created = preg_match('/완료:\s*신규=(\d+)\s*중복=(\d+)/u', $out, $m) ? (int) $m[1] : null;
        $skipped = isset($m[2]) ? (int) $m[2] : null;
        $data = ['saved' => $created, 'skipped' => $skipped];

        if ($code !== 0) {
            return ['ok' => false, 'summary' => '뉴스 수집이 끝까지 되지 않았어요', 'error' => $svc->lastLine($out) ?: '수집 명령이 실패했어요', 'data' => $data];
        }
        if ($created === null) {
            return ['ok' => true, 'summary' => '뉴스 수집을 마쳤어요', 'data' => $data];
        }
        return ['ok' => true, 'data' => $data, 'summary' => $created > 0
            ? "뉴스 {$created}건을 새로 담았어요 (이미 있는 {$skipped}건은 건너뜀)"
            : "새로 담은 뉴스가 없어요 (이미 있는 {$skipped}건)"];
    }

    private function runShorts(AdminCollectService $svc): array
    {
        $before = Short::count();
        // 한국 비율은 shorts:fetch 안에서 관리자 설정(board.shorts.korea_ratio)을 직접 읽는다 — 여기서 지정하지 않음
        $code = Artisan::call('shorts:fetch', ['--limit' => 100]);
        $out = Artisan::output();
        $total = Short::count();
        $added = preg_match('/완료:.*?=\s*(\d+)개 추가/u', $out, $m) ? (int) $m[1] : max(0, $total - $before);
        $data = ['added' => $added, 'total' => $total];

        if ($code !== 0) {
            return ['ok' => false, 'summary' => '숏츠 수집이 끝까지 되지 않았어요', 'error' => $svc->lastLine($out) ?: '수집 명령이 실패했어요', 'data' => $data];
        }
        if (str_contains($out, 'API 할당량 초과')) {
            return ['ok' => false, 'summary' => "YouTube 하루 사용량을 넘었어요. 이번에 추가된 숏츠는 {$added}개예요. 내일 다시 시도해 주세요.", 'error' => 'YouTube API 사용량 초과(403)', 'data' => $data];
        }
        return ['ok' => true, 'data' => $data, 'summary' => $added > 0
            ? "숏츠 {$added}개를 새로 담았어요 (전체 {$total}개)"
            : "새로 담은 숏츠가 없어요 (전체 {$total}개)"];
    }

    private function runRecipes(AdminCollectService $svc): array
    {
        $recipes = app(RecipeService::class);
        $all = !empty($this->params['all']);
        $start = $all ? 1 : max(1, (int) ($this->params['start'] ?? 1));
        $end = $all ? 1000 : max($start, (int) ($this->params['end'] ?? 100));

        $saved = 0;
        $skipped = 0;
        $failedChunks = 0;
        $firstError = null;
        // 식품안전나라 API 는 100건씩 나눠 부른다 (옛 "전체 동기화"와 같은 방식)
        for ($i = $start; $i <= $end; $i += 100) {
            $r = $recipes->syncFromApi($i, min($i + 99, $end));
            if (!empty($r['success'])) {
                $saved += (int) ($r['saved'] ?? 0);
                $skipped += (int) ($r['skipped'] ?? 0);
            } else {
                $failedChunks++;
                $firstError ??= $svc->oneLine($r['error'] ?? '알 수 없는 오류');
            }
        }
        $data = ['saved' => $saved, 'skipped' => $skipped, 'start' => $start, 'end' => $end];

        if ($failedChunks > 0) {
            return ['ok' => false, 'data' => $data, 'error' => $firstError,
                'summary' => "레시피 동기화 중 {$failedChunks}구간이 실패했어요 (저장 {$saved}개)"];
        }
        return ['ok' => true, 'data' => $data, 'summary' => $saved > 0
            ? "레시피 {$saved}개를 새로 담았어요 (이미 있는 {$skipped}개는 건너뜀)"
            : "새로 담은 레시피가 없어요 (이미 있는 {$skipped}개)"];
    }
}
