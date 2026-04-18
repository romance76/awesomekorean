<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2-C Post: 오래된 로그 자동 정리 (retention).
 * - admin_audit_log: 180일 보관
 * - event_log: 60일 보관
 * - api_logs: 30일 보관
 * - login_histories (실패): 90일 / (성공): 365일
 * cron: 0 4 * * * (매일 04:00).
 */
class CleanupOldLogs extends Command
{
    protected $signature = 'logs:cleanup
        {--dry : 삭제 없이 집계만}';
    protected $description = '오래된 로그 자동 정리 (retention 정책)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $report = [];

        $tables = [
            ['admin_audit_log', 'created_at', 180, null],
            ['event_log',       'occurred_at', 60,  null],
            ['api_logs',        'created_at', 30,  null],
            ['api_usage',       'date',        90,  null],
            ['login_histories', 'created_at', 90,  ['successful' => 0]],  // 실패 로그
            ['login_histories', 'created_at', 365, ['successful' => 1]],  // 성공 로그
            ['site_setting_history', 'created_at', 365, null],
            ['static_page_versions', 'created_at', 365, null],
            ['api_key_history',       'created_at', 365, null],
        ];

        foreach ($tables as [$table, $dateCol, $days, $extraWhere]) {
            if (!\Schema::hasTable($table)) continue;

            $q = DB::table($table)->where($dateCol, '<', now()->subDays($days));
            if ($extraWhere) {
                foreach ($extraWhere as $k => $v) $q->where($k, $v);
            }

            $count = $q->count();
            if ($count > 0) {
                if (!$dry) {
                    $deleted = $q->delete();
                    $report[] = "{$table}: deleted {$deleted} rows older than {$days}d";
                } else {
                    $report[] = "{$table}: would delete {$count} rows older than {$days}d (DRY)";
                }
            }
        }

        // 결과 출력
        if (empty($report)) {
            $this->info('정리할 오래된 로그 없음');
        } else {
            foreach ($report as $line) $this->line('  ' . $line);
        }

        return self::SUCCESS;
    }
}
