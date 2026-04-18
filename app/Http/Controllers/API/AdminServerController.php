<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\DigitalOceanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * /admin/v2/server (Phase 2-C 묶음 8).
 * 서버 상태·메트릭·플랜·Snapshot·백업 이력 관리.
 */
class AdminServerController extends Controller
{
    public function overview(DigitalOceanService $do)
    {
        // 서버 로컬 상태 (SSH 없이 PHP 가능한 것만)
        $disk_free = @disk_free_space('/');
        $disk_total = @disk_total_space('/');
        $load = sys_getloadavg();
        $mem = $this->readMeminfo();

        // Redis 연결 여부
        $redisOk = false;
        try { $redisOk = (bool) \Illuminate\Support\Facades\Redis::connection()->ping(); } catch (\Throwable $e) {}

        // DB 연결
        $dbOk = false;
        try { DB::connection()->getPdo(); $dbOk = true; } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'data' => [
                'droplet'  => $do->getDroplet(),
                'mock_mode'=> $do->isMock(),
                'services' => [
                    'database' => $dbOk,
                    'redis'    => $redisOk,
                    'php'      => PHP_VERSION,
                    'laravel'  => app()->version(),
                ],
                'disk'   => [
                    'total_gb'  => $disk_total ? round($disk_total / 1073741824, 1) : null,
                    'free_gb'   => $disk_free  ? round($disk_free  / 1073741824, 1) : null,
                    'used_pct'  => ($disk_total && $disk_free)
                        ? round((1 - $disk_free / $disk_total) * 100, 1)
                        : null,
                ],
                'memory' => $mem,
                'load'   => $load,
                'uptime' => $this->readUptime(),
            ],
        ]);
    }

    public function metrics(Request $request, DigitalOceanService $do)
    {
        $type  = $request->query('type', 'cpu');
        $hours = (int) $request->query('hours', 24);
        return response()->json([
            'success'   => true,
            'mock_mode' => $do->isMock(),
            'type'      => $type,
            'hours'     => $hours,
            'data'      => $do->getMetrics($type, $hours),
        ]);
    }

    public function plans(DigitalOceanService $do)
    {
        return response()->json([
            'success'   => true,
            'mock_mode' => $do->isMock(),
            'data'      => $do->listSizes(),
        ]);
    }

    public function snapshots(DigitalOceanService $do)
    {
        return response()->json([
            'success'   => true,
            'mock_mode' => $do->isMock(),
            'data'      => $do->listSnapshots(),
        ]);
    }

    public function createSnapshot(Request $request, DigitalOceanService $do)
    {
        $name = $request->input('name', 'admin-ui-' . now()->format('Ymd-His'));
        return response()->json($do->createSnapshot($name));
    }

    // ─── 로컬 서버 상세 ───
    protected function readMeminfo(): array
    {
        $info = @file_get_contents('/proc/meminfo');
        if (!$info) return [];
        $out = [];
        foreach (['MemTotal','MemFree','MemAvailable','Buffers','Cached','SwapTotal','SwapFree'] as $k) {
            if (preg_match("/{$k}:\s+(\d+)/", $info, $m)) {
                $out[strtolower($k)] = (int) $m[1]; // KB
            }
        }
        if (isset($out['memtotal'])) {
            $out['total_mb']     = intval($out['memtotal'] / 1024);
            $out['available_mb'] = intval(($out['memavailable'] ?? 0) / 1024);
            $out['used_mb']      = $out['total_mb'] - $out['available_mb'];
            $out['used_pct']     = $out['total_mb'] > 0 ? round($out['used_mb'] / $out['total_mb'] * 100, 1) : 0;
        }
        return $out;
    }

    protected function readUptime(): ?array
    {
        $u = @file_get_contents('/proc/uptime');
        if (!$u) return null;
        $parts = explode(' ', trim($u));
        $secs = (float) ($parts[0] ?? 0);
        $days  = intval($secs / 86400);
        $hours = intval(($secs % 86400) / 3600);
        $mins  = intval(($secs % 3600) / 60);
        return ['seconds' => $secs, 'days' => $days, 'hours' => $hours, 'mins' => $mins, 'pretty' => "{$days}일 {$hours}시간 {$mins}분"];
    }

    // ─── 백업 이력 (로컬) ───
    public function backups()
    {
        if (!\Schema::hasTable('server_backups')) {
            return response()->json(['success' => true, 'data' => [], 'note' => 'server_backups 테이블 미구성 (묶음 8 후속 작업)']);
        }
        $rows = DB::table('server_backups')->orderByDesc('created_at')->limit(50)->get();
        return response()->json(['success' => true, 'data' => $rows]);
    }
}
