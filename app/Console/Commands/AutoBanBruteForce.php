<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2-C Post: Brute-force 자동 IP 차단.
 * login_histories 에서 최근 1시간 실패 ≥10회 IP 자동으로 ip_bans 에 추가.
 * cron: */15 * * * * (15분 간격).
 */
class AutoBanBruteForce extends Command
{
    protected $signature = 'security:auto-ban-brute-force
        {--threshold=10 : 차단 트리거 실패 횟수}
        {--window=60 : 관측 시간(분)}
        {--ban-hours=24 : 차단 유지 시간(시간)}';

    protected $description = '로그인 실패 임계치 초과 IP 자동 차단';

    public function handle(): int
    {
        if (!\Schema::hasTable('login_histories') || !\Schema::hasTable('ip_bans')) {
            $this->warn('필수 테이블 없음 — 스킵');
            return self::SUCCESS;
        }

        $threshold = (int) $this->option('threshold');
        $window    = (int) $this->option('window');
        $banHours  = (int) $this->option('ban-hours');
        $since     = now()->subMinutes($window);

        // 최근 window 분 실패 그룹핑
        $badIps = DB::table('login_histories')
            ->where('successful', false)
            ->where('created_at', '>=', $since)
            ->select('ip', DB::raw('COUNT(*) as attempts'))
            ->whereNotNull('ip')
            ->groupBy('ip')
            ->havingRaw('COUNT(*) >= ?', [$threshold])
            ->get();

        $bannedNew = 0;
        foreach ($badIps as $row) {
            $ip = $row->ip;
            // 이미 활성 ban 존재 시 스킵
            $existing = DB::table('ip_bans')
                ->where('ip_address', $ip)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->exists();
            if ($existing) continue;

            DB::table('ip_bans')->insert([
                'ip_address' => $ip,
                'reason'     => "자동 차단: {$row->attempts}회 로그인 실패 (최근 {$window}분)",
                'banned_by'  => null,
                'expires_at' => now()->addHours($banHours),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $bannedNew++;
            $this->info("Banned {$ip} ({$row->attempts} attempts)");

            // admin_audit_log 기록
            if (\Schema::hasTable('admin_audit_log')) {
                DB::table('admin_audit_log')->insert([
                    'admin_id'    => 1,  // system admin
                    'action'      => 'auto_ban_bruteforce',
                    'target_type' => 'ip_ban',
                    'after_value' => json_encode(['ip' => $ip, 'attempts' => $row->attempts, 'window_min' => $window, 'ban_hours' => $banHours], JSON_UNESCAPED_UNICODE),
                    'created_at'  => now(),
                ]);
            }
        }

        // 만료된 ban 자동 해제
        $expired = DB::table('ip_bans')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        $this->line("Summary: banned {$bannedNew} new IPs, expired {$expired} old bans");
        return self::SUCCESS;
    }
}
