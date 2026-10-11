<?php

namespace App\Services;

use App\Jobs\RunAdminCollect;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 관리자 "지금 수집" 버튼(음악·뉴스·숏츠·레시피)을 큐 작업으로 돌리기 위한 공용 로직.
 *
 * - 시작: 종류별 락(Cache::add)을 잡고 RunAdminCollect 작업을 큐에 넣은 뒤 바로 응답한다.
 *   이미 락이 있으면(수집 중) 409 로 "이미 수집 중이에요 (○분 전 시작)" 안내.
 * - 상태: 락(진행 중) + 마지막 결과(last) 를 Cache 에서만 읽는다 — DB 를 쓰지 않는다.
 * - 락은 작업이 끝나면(성공/실패 모두) 풀리고, 작업이 강제로 죽어도 TTL(제한 시간 + 여유)이 지나면 저절로 풀린다.
 */
class AdminCollectService
{
    /** 종류별 이름표와 작업 제한 시간(초). 재시도는 하지 않는다(외부 쿼터 보호). */
    public const KINDS = [
        'music'   => ['label' => '음악',   'timeout' => 1200],
        'news'    => ['label' => '뉴스',   'timeout' => 1200],
        'shorts'  => ['label' => '숏츠',   'timeout' => 1200],
        'recipes' => ['label' => '레시피', 'timeout' => 900],
    ];

    /** 락 유지 시간 = 작업 제한 시간 + 이 여유(대기열에서 기다리는 시간 포함) */
    private const LOCK_MARGIN_SEC = 300;
    private const LAST_KEEP_DAYS = 30;

    public function label(string $kind): string
    {
        return self::KINDS[$kind]['label'] ?? $kind;
    }

    public function timeout(string $kind): int
    {
        return (int) (self::KINDS[$kind]['timeout'] ?? 900);
    }

    private function lockKey(string $kind): string
    {
        return 'admin_collect:' . $kind;
    }

    private function lastKey(string $kind): string
    {
        return 'admin_collect:last:' . $kind;
    }

    private function lockTtl(string $kind): int
    {
        return $this->timeout($kind) + self::LOCK_MARGIN_SEC;
    }

    /**
     * 수집을 큐에 넣고 바로 JSON 응답을 만든다.
     * 응답: {success, queued, message, ...} — 이미 돌고 있으면 409, 큐에 못 넣으면 503.
     */
    public function begin(string $kind, array $params = [], ?int $userId = null): JsonResponse
    {
        if (!isset(self::KINDS[$kind])) {
            return response()->json(['success' => false, 'queued' => false, 'message' => '알 수 없는 수집 종류예요.'], 422);
        }
        $label = $this->label($kind);
        $key = $this->lockKey($kind);
        $state = [
            'run_id'     => (string) Str::uuid(),
            'status'     => 'queued',
            'started_at' => now()->toIso8601String(),
            'user_id'    => $userId,
        ];

        // 이미 수집 중이면 새로 넣지 않는다 (연타·여러 관리자 동시 클릭 방지)
        if (!Cache::add($key, $state, $this->lockTtl($kind))) {
            $cur = Cache::get($key);
            $startedAt = is_array($cur) ? ($cur['started_at'] ?? null) : null;
            $ago = $this->agoText($startedAt);
            return response()->json([
                'success'    => false,
                'queued'     => false,
                'code'       => 'already_running',
                'running'    => true,
                'started_at' => $startedAt,
                'message'    => "이미 {$label} 수집 중이에요" . ($ago ? " ({$ago} 시작)" : ''),
            ], 409);
        }

        try {
            Bus::dispatch(new RunAdminCollect($kind, $params, $state['run_id']));   // 이 줄에서 바로 큐에 들어감
        } catch (\Throwable $e) {
            // 큐(Redis)에 못 넣었으면 락을 되돌려 다음 시도가 막히지 않게
            Cache::forget($key);
            Log::error("[admin-collect:{$kind}] 큐 등록 실패: " . $this->oneLine($e->getMessage()));
            return response()->json([
                'success' => false,
                'queued'  => false,
                'code'    => 'queue_unavailable',
                'message' => '수집 작업을 접수하지 못했어요. 잠시 뒤에 다시 시도해 주세요.',
            ], 503);
        }

        return response()->json([
            'success'    => true,
            'queued'     => true,
            'kind'       => $kind,
            'started_at' => $state['started_at'],
            'message'    => "{$label} 수집을 시작했어요. 끝나면 알려 드릴게요.",
        ]);
    }

    /** 작업이 실제로 돌기 직전에 호출 — 내 락이 맞을 때만 true (중복 실행 방지) */
    public function claim(string $kind, string $runId): bool
    {
        $key = $this->lockKey($kind);
        $cur = Cache::get($key);
        if (is_array($cur)) {
            if (($cur['run_id'] ?? null) !== $runId) {
                return false;   // 다른 실행이 락을 잡고 있음 → 이 작업은 건너뜀
            }
            $cur['status'] = 'running';
            $cur['run_started_at'] = now()->toIso8601String();
            Cache::put($key, $cur, $this->lockTtl($kind));   // 실행 시작 시점부터 TTL 다시 계산
            return true;
        }
        // 락이 이미 만료됨(대기가 너무 길었음) — 아무도 안 잡고 있으면 다시 잡고 실행
        return Cache::add($key, [
            'run_id'         => $runId,
            'status'         => 'running',
            'started_at'     => now()->toIso8601String(),
            'run_started_at' => now()->toIso8601String(),
            'user_id'        => null,
        ], $this->lockTtl($kind));
    }

    /** 마지막 결과 저장 (락 해제는 release 에서) */
    public function finish(string $kind, string $runId, bool $ok, string $summary, ?string $error = null, array $data = []): void
    {
        $lock = Cache::get($this->lockKey($kind));
        $startedAt = is_array($lock) ? ($lock['started_at'] ?? null) : null;
        $duration = null;
        if ($startedAt) {
            try { $duration = max(0, now()->getTimestamp() - Carbon::parse($startedAt)->getTimestamp()); } catch (\Throwable $e) {}
        }
        Cache::put($this->lastKey($kind), [
            'run_id'       => $runId,
            'ok'           => $ok,
            'summary'      => $summary,
            'error'        => $error,
            'data'         => $data,
            'started_at'   => $startedAt,
            'finished_at'  => now()->toIso8601String(),
            'duration_sec' => $duration,
        ], now()->addDays(self::LAST_KEEP_DAYS));
    }

    /** 내 락이면 풀어 준다 (다른 실행의 락은 건드리지 않음) */
    public function release(string $kind, string $runId): void
    {
        $key = $this->lockKey($kind);
        $cur = Cache::get($key);
        if (!is_array($cur) || ($cur['run_id'] ?? null) === $runId) {
            Cache::forget($key);
        }
    }

    /** 종류별 상태 — GET /api/admin/collect-status 가 그대로 돌려준다 */
    public function status(): array
    {
        $out = [];
        foreach (self::KINDS as $kind => $meta) {
            $lock = Cache::get($this->lockKey($kind));
            $last = Cache::get($this->lastKey($kind));
            $running = is_array($lock);
            $startedAt = $running ? ($lock['started_at'] ?? null) : null;
            $elapsed = null;
            if ($startedAt) {
                try { $elapsed = max(0, intdiv(now()->getTimestamp() - Carbon::parse($startedAt)->getTimestamp(), 60)); } catch (\Throwable $e) {}
            }
            $out[$kind] = [
                'label'           => $meta['label'],
                'running'         => $running,
                'state'           => $running ? (($lock['status'] ?? 'queued') === 'running' ? 'running' : 'queued') : 'idle',
                'started_at'      => $startedAt,
                'elapsed_minutes' => $elapsed,
                'started_ago'     => $this->agoText($startedAt),
                'last'            => is_array($last) ? [
                    'ok'           => (bool) ($last['ok'] ?? false),
                    'summary'      => $last['summary'] ?? null,
                    'error'        => $last['error'] ?? null,
                    'data'         => $last['data'] ?? [],
                    'started_at'   => $last['started_at'] ?? null,
                    'finished_at'  => $last['finished_at'] ?? null,
                    'duration_sec' => $last['duration_sec'] ?? null,
                ] : null,
            ];
        }
        return $out;
    }

    /** "방금" / "3분 전" / "2시간 전" */
    public function agoText(?string $iso): ?string
    {
        if (!$iso) return null;
        try {
            $sec = max(0, now()->getTimestamp() - Carbon::parse($iso)->getTimestamp());
        } catch (\Throwable $e) {
            return null;
        }
        $min = intdiv($sec, 60);
        if ($min < 1) return '방금';
        if ($min < 120) return "{$min}분 전";
        return intdiv($min, 60) . '시간 전';
    }

    /**
     * 화면·캐시에 남길 오류는 한 줄로 줄이고, 주소(키가 들어 있을 수 있음)와 키처럼 보이는 값은 가린다.
     */
    public function oneLine(?string $text, int $max = 200): string
    {
        $t = (string) $text;
        $t = preg_replace('/\e\[[0-9;]*m/', '', $t) ?? $t;                                 // 터미널 색상 코드
        $t = preg_replace('#https?://\S+#i', '(주소 생략)', $t) ?? $t;                      // 주소 속 키 노출 방지
        $t = preg_replace('/\b(api[_-]?key|service[_-]?key|key|token|secret|password|authorization)\s*[=:]\s*\S+/i', '$1=***', $t) ?? $t;
        $t = preg_replace('/\b[A-Za-z0-9_\-]{32,}\b/', '***', $t) ?? $t;                   // 긴 토큰 모양
        $t = trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
        return mb_substr($t, 0, $max);
    }

    /** 커맨드 출력에서 마지막으로 의미 있는 줄 (오류 한 줄 용) */
    public function lastLine(string $output): string
    {
        $lines = preg_split('/\R/u', trim($output)) ?: [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = $this->oneLine($lines[$i]);
            if ($line !== '') return $line;
        }
        return '';
    }
}
