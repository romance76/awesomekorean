<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/**
 * 관리자 "시스템 상태" 요약 — 큐(대기·실패), 스케줄러 마지막 실행, 디스크, 메모리, 최근 배포.
 *
 * 값마다 따로 try/catch 해서 일부가 실패해도 나머지는 내려준다(실패한 칸은 null + state 'unknown').
 * 경고 기준(빨강): 실패한 백그라운드 작업 > 0건, 디스크 사용 > 85%, 스케줄러가 3분 넘게 멈춤.
 * 나머지(큐가 많이 쌓임, 메모리 빠듯함, 마지막 배포 실패)는 노랑.
 *
 * 스케줄러 "마지막 실행"은 routes/console.php 가 매분 beat() 로 남기는 작은 파일(storage/framework/scheduler-heartbeat)로 안다.
 * (캐시를 비워도 사라지지 않게 파일로 둠)
 */
class SystemStatus
{
    public const SCHEDULER_STALE_SECONDS = 180;   // 3분
    public const DISK_BAD_PERCENT = 85;
    public const QUEUE_WARN_PENDING = 300;
    public const MEMORY_WARN_PERCENT = 95;
    private const CACHE_KEY = 'admin:system_status:v1';

    private static function heartbeatPath(): string
    {
        return storage_path('framework/scheduler-heartbeat');
    }

    /** 스케줄러가 매분 호출 — 실패해도 스케줄러를 방해하지 않는다 */
    public static function beat(): void
    {
        try {
            @file_put_contents(self::heartbeatPath(), (string) time(), LOCK_EX);
        } catch (\Throwable $e) {
            // 기록 실패는 무시 (상태 화면에 "기록 없음"으로 보일 뿐)
        }
    }

    /** 화면 응답 전체 (무거운 값은 30초 캐시, 스케줄러 시각은 매번 새로) */
    public static function snapshot(): array
    {
        try {
            $base = Cache::remember(self::CACHE_KEY, 30, fn () => self::collectSlow());
        } catch (\Throwable $e) {
            report($e);
            $base = self::collectSlow();
        }

        $deploy = $base['deploy'];
        if (!empty($deploy['at'])) {
            try { $deploy['age_seconds'] = max(0, time() - Carbon::parse($deploy['at'])->timestamp); } catch (\Throwable $e) {}
        }
        $scheduler = self::scheduler($deploy['age_seconds'] ?? null);

        $sections = ['queue' => $base['queue'], 'scheduler' => $scheduler, 'disk' => $base['disk'], 'memory' => $base['memory'], 'deploy' => $deploy];
        $alerts = [];
        foreach ($sections as $key => $s) {
            if (in_array($s['state'] ?? '', ['bad', 'warn'], true)) {
                $alerts[] = ['key' => $key, 'level' => $s['state'], 'message' => $s['message']];
            }
        }
        $level = collect($alerts)->contains('level', 'bad') ? 'bad' : (count($alerts) ? 'warn' : 'ok');

        return ['level' => $level, 'alerts' => $alerts] + $sections + ['generated_at' => now()->toIso8601String()];
    }

    private static function collectSlow(): array
    {
        return ['queue' => self::queue(), 'disk' => self::disk(), 'memory' => self::memory(), 'deploy' => self::deploy()];
    }

    private static function unknown(string $what): array
    {
        return ['state' => 'unknown', 'message' => "{$what} 값을 확인하지 못했어요."];
    }

    // ───────────── 큐 ─────────────
    public static function queue(): array
    {
        $pending = null;
        $failed = null;
        try { $pending = (int) Queue::size(); } catch (\Throwable $e) { report($e); }
        try { $failed = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0; } catch (\Throwable $e) { report($e); }

        if ($pending === null && $failed === null) return ['pending' => null, 'failed' => null] + self::unknown('백그라운드 작업');
        if ($failed !== null && $failed > 0) {
            return ['pending' => $pending, 'failed' => $failed, 'state' => 'bad',
                'message' => "실패한 백그라운드 작업이 {$failed}건 있어요. 알림 보내기·메일 발송 같은 작업이 중간에 멈춘 거예요. 계속 늘어나면 개발자에게 알려 주세요."];
        }
        if ($pending !== null && $pending >= self::QUEUE_WARN_PENDING) {
            return ['pending' => $pending, 'failed' => $failed, 'state' => 'warn',
                'message' => "처리를 기다리는 백그라운드 작업이 {$pending}건 쌓여 있어요. 처리 속도가 느려졌을 수 있어요."];
        }
        return ['pending' => $pending, 'failed' => $failed, 'state' => 'ok',
            'message' => '대기 ' . ($pending ?? '–') . '건, 실패 ' . ($failed ?? '–') . '건 — 정상이에요.'];
    }

    // ───────────── 스케줄러 ─────────────
    public static function scheduler(?int $deployAgeSeconds = null): array
    {
        $ts = null;
        try {
            $raw = @file_get_contents(self::heartbeatPath());
            if ($raw !== false && ctype_digit(trim($raw))) $ts = (int) trim($raw);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($ts === null) {
            // 배포한 지 5분이 안 됐으면 아직 첫 기록 전일 수 있어 "확인 중", 그 뒤에도 기록이 없으면 멈춘 것으로 본다
            if ($deployAgeSeconds === null || $deployAgeSeconds <= 300) {
                return ['last_run_at' => null, 'age_seconds' => null, 'state' => 'unknown',
                    'message' => '자동 작업 실행기의 실행 기록이 아직 없어요. 방금 배포했다면 1~2분 뒤에 다시 확인해 주세요.'];
            }
            return ['last_run_at' => null, 'age_seconds' => null, 'state' => 'bad',
                'message' => '자동 작업 실행기(스케줄러)의 실행 기록이 없어요. 자동 수집·경품 추첨·알림 같은 예약 작업이 돌지 않고 있을 수 있어요. 서버의 매분 실행 설정(cron)을 확인해야 해요.'];
        }

        $age = max(0, time() - $ts);
        $at = Carbon::createFromTimestampUTC($ts)->toIso8601String();
        if ($age > self::SCHEDULER_STALE_SECONDS) {
            $min = intdiv($age, 60);
            return ['last_run_at' => $at, 'age_seconds' => $age, 'state' => 'bad',
                'message' => "자동 작업 실행기(스케줄러)가 {$min}분째 멈춰 있어요. 자동 수집·경품 추첨·알림 같은 예약 작업이 돌지 않고 있어요. 서버의 매분 실행 설정(cron)을 확인해야 해요."];
        }
        return ['last_run_at' => $at, 'age_seconds' => $age, 'state' => 'ok', 'message' => '자동 작업 실행기가 1분마다 잘 돌고 있어요.'];
    }

    // ───────────── 디스크 ─────────────
    public static function disk(): array
    {
        try {
            $path = base_path();
            $total = @disk_total_space($path);
            $free = @disk_free_space($path);
            if (!$total || $free === false) return ['used_percent' => null] + self::unknown('저장공간');
            $pct = round(($total - $free) / $total * 100, 1);
            $freeGb = round($free / 1073741824, 1);
            $totalGb = round($total / 1073741824, 1);
            $base = ['used_percent' => $pct, 'free_gb' => $freeGb, 'total_gb' => $totalGb];
            if ($pct > self::DISK_BAD_PERCENT) {
                return $base + ['state' => 'bad', 'message' => "서버 저장공간이 {$pct}% 찼어요 (남은 공간 {$freeGb}GB). 가득 차면 사진 올리기·로그인 같은 기능이 멈출 수 있어요. 오래된 파일을 정리하거나 용량을 늘려야 해요."];
            }
            return $base + ['state' => 'ok', 'message' => "저장공간 {$pct}% 사용 중 (남은 공간 {$freeGb}GB / 전체 {$totalGb}GB) — 여유 있어요."];
        } catch (\Throwable $e) {
            report($e);
            return ['used_percent' => null] + self::unknown('저장공간');
        }
    }

    // ───────────── 메모리 (/proc/meminfo, 리눅스 서버만) ─────────────
    public static function memory(): array
    {
        try {
            $raw = @file_get_contents('/proc/meminfo');
            if (!$raw) return ['used_percent' => null] + self::unknown('메모리');
            $kb = [];
            foreach (explode("\n", $raw) as $line) {
                if (preg_match('/^(\w+):\s+(\d+)\s*kB/', $line, $m)) $kb[$m[1]] = (int) $m[2];
            }
            $total = $kb['MemTotal'] ?? 0;
            if ($total <= 0) return ['used_percent' => null] + self::unknown('메모리');
            $avail = $kb['MemAvailable'] ?? (($kb['MemFree'] ?? 0) + ($kb['Buffers'] ?? 0) + ($kb['Cached'] ?? 0));
            $pct = round(($total - $avail) / $total * 100, 1);
            $availMb = (int) round($avail / 1024);
            $totalMb = (int) round($total / 1024);
            $swapUsedMb = isset($kb['SwapTotal'], $kb['SwapFree']) ? (int) round(max(0, $kb['SwapTotal'] - $kb['SwapFree']) / 1024) : null;
            $base = ['used_percent' => $pct, 'available_mb' => $availMb, 'total_mb' => $totalMb, 'swap_used_mb' => $swapUsedMb];
            if ($pct >= self::MEMORY_WARN_PERCENT) {
                return $base + ['state' => 'warn', 'message' => "서버 메모리가 {$pct}% 사용 중이라 빠듯해요 (남은 여유 약 {$availMb}MB). 사이트가 느려지거나 일부 작업이 실패할 수 있어요."];
            }
            return $base + ['state' => 'ok', 'message' => "메모리 {$pct}% 사용 중 (남은 여유 약 {$availMb}MB / 전체 {$totalMb}MB)."];
        } catch (\Throwable $e) {
            report($e);
            return ['used_percent' => null] + self::unknown('메모리');
        }
    }

    // ───────────── 최근 배포 ─────────────
    public static function deploy(): array
    {
        try {
            $at = null;
            $commit = null;
            $failedAfter = false;
            $log = storage_path('logs/deploy.log');
            if (is_file($log) && ($fh = @fopen($log, 'rb'))) {
                $size = filesize($log);
                fseek($fh, max(0, $size - 65536));
                $tail = (string) stream_get_contents($fh);
                fclose($fh);
                $lastOk = null;
                $lastFail = null;
                foreach (explode("\n", $tail) as $line) {
                    if (!preg_match('/^\[Deploy (\d{4}-\d{2}-\d{2}_\d{2}:\d{2}:\d{2})\]\s+(.*)$/u', $line, $m)) continue;
                    if (str_contains($m[2], 'DEPLOY SUCCESS')) {
                        $lastOk = $m[1];
                        $commit = preg_match('/→\s*(\w+)/u', $m[2], $c) ? $c[1] : $commit;
                    } elseif (str_contains($m[2], 'FAIL at step')) {
                        $lastFail = $m[1];
                    }
                }
                // 로그 시각은 서버 시계(UTC)로 찍힌다
                if ($lastOk) $at = Carbon::createFromFormat('Y-m-d_H:i:s', $lastOk, 'UTC')->toIso8601String();
                $failedAfter = $lastFail && (!$lastOk || strcmp($lastFail, $lastOk) > 0);
            }
            if (!$at) {
                // 로그가 없으면 빌드 결과물 시각으로 대신 (배포 때마다 새로 만들어짐)
                $manifest = public_path('build/manifest.json');
                if (is_file($manifest)) $at = Carbon::createFromTimestampUTC(filemtime($manifest))->toIso8601String();
            }
            if (!$at) return ['at' => null, 'commit' => null] + self::unknown('최근 배포');
            $age = max(0, time() - Carbon::parse($at)->timestamp);
            if ($failedAfter) {
                return ['at' => $at, 'commit' => $commit, 'age_seconds' => $age, 'state' => 'warn',
                    'message' => '마지막 배포 시도가 중간에 실패한 기록이 있어요. 사이트는 직전에 성공한 버전으로 돌아가고 있을 수 있으니 개발자에게 알려 주세요.'];
            }
            return ['at' => $at, 'commit' => $commit, 'age_seconds' => $age, 'state' => 'ok', 'message' => '마지막 배포가 정상으로 끝났어요.'];
        } catch (\Throwable $e) {
            report($e);
            return ['at' => null, 'commit' => null] + self::unknown('최근 배포');
        }
    }
}
