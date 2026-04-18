<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * 시스템 헬스 체크 (Phase 2-C Post).
 * DB / Redis / Queue / Storage / 외부 의존성 종합 상태.
 */
class AdminSystemHealthController extends Controller
{
    public function check()
    {
        $checks = [];

        // DB
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $checks['database'] = [
                'status' => 'healthy',
                'response_time_ms' => (int) ((microtime(true) - $start) * 1000),
                'name' => config('database.default'),
            ];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'down', 'error' => $e->getMessage()];
        }

        // Redis
        try {
            $start = microtime(true);
            $ping = Redis::connection()->ping();
            $info = Redis::connection()->info('memory');
            $checks['redis'] = [
                'status' => 'healthy',
                'response_time_ms' => (int) ((microtime(true) - $start) * 1000),
                'used_memory_human' => $info['used_memory_human'] ?? null,
                'ping' => $ping ? 'PONG' : 'no response',
            ];
        } catch (\Throwable $e) {
            $checks['redis'] = ['status' => 'down', 'error' => $e->getMessage()];
        }

        // Queue (Redis 기반)
        try {
            $pending = 0;
            try { $pending = Redis::connection()->llen('queues:default'); } catch (\Throwable $e) {}
            $checks['queue'] = [
                'status'  => 'healthy',
                'driver'  => config('queue.default'),
                'pending' => $pending,
            ];
        } catch (\Throwable $e) {
            $checks['queue'] = ['status' => 'down', 'error' => $e->getMessage()];
        }

        // Storage
        try {
            $disk = storage_path('app');
            $free = disk_free_space($disk) ?: 0;
            $total = disk_total_space($disk) ?: 0;
            $checks['storage'] = [
                'status' => $total > 0 && ($free / $total) > 0.1 ? 'healthy' : 'warning',
                'disk_free_gb' => round($free / 1073741824, 1),
                'disk_total_gb' => round($total / 1073741824, 1),
                'used_pct' => $total > 0 ? round((1 - $free / $total) * 100, 1) : 0,
            ];
        } catch (\Throwable $e) {
            $checks['storage'] = ['status' => 'unknown', 'error' => $e->getMessage()];
        }

        // Mail (설정만 확인 — 실 발송 테스트 별도)
        $checks['mail'] = [
            'status' => config('mail.default') ? 'configured' : 'not_configured',
            'driver' => config('mail.default'),
            'host'   => config('mail.mailers.smtp.host'),
        ];

        // 외부 서비스 (ApiKeyManager 활성 키 요약)
        if (\Schema::hasTable('api_keys')) {
            $apis = DB::table('api_keys')
                ->select('service', 'is_active', 'last_verified_at', 'last_error_at')
                ->get();
            $checks['apis'] = [
                'total' => $apis->count(),
                'active' => $apis->where('is_active', 1)->count(),
                'recently_failed' => $apis->filter(fn($k) => $k->last_error_at && strtotime($k->last_error_at) > time() - 3600)->count(),
            ];
        }

        // Reverb 포트 (localhost 8080)
        $reverbOk = false;
        try {
            $fp = @fsockopen('127.0.0.1', 8080, $errno, $errstr, 2);
            if ($fp) { fclose($fp); $reverbOk = true; }
        } catch (\Throwable $e) {}
        $checks['reverb'] = ['status' => $reverbOk ? 'healthy' : 'down', 'port' => 8080];

        // DB 최근 24h growth (핵심 테이블)
        $growth = [];
        foreach (['users' => 'created_at', 'posts' => 'created_at', 'payments' => 'created_at', 'login_histories' => 'created_at'] as $t => $col) {
            if (\Schema::hasTable($t)) {
                $growth[$t] = DB::table($t)->where($col, '>=', now()->subDay())->count();
            }
        }
        $checks['activity_24h'] = $growth;

        // 종합 상태
        $statuses = collect($checks)->pluck('status')->filter();
        $overallStatus = 'healthy';
        if ($statuses->contains('down')) $overallStatus = 'critical';
        elseif ($statuses->contains('warning')) $overallStatus = 'degraded';

        return response()->json([
            'success' => true,
            'data' => [
                'overall' => $overallStatus,
                'checked_at' => now()->toIso8601String(),
                'checks' => $checks,
                'versions' => [
                    'php' => PHP_VERSION,
                    'laravel' => app()->version(),
                    'timezone' => config('app.timezone'),
                ],
            ],
        ]);
    }
}
