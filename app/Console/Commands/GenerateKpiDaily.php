<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 일별 KPI 집계 (Phase 2-C 묶음 9).
 * Cron: 매일 01:05 (server 부하 낮은 시간).
 */
class GenerateKpiDaily extends Command
{
    protected $signature = 'kpi:daily
        {--date= : YYYY-MM-DD (기본: 어제)}
        {--backfill=0 : N일 과거까지 백필}';

    protected $description = '일별 KPI 지표 집계 → kpi_daily 테이블';

    public function handle(): int
    {
        $endDate   = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::yesterday();
        $backfill  = (int) $this->option('backfill');
        $startDate = $endDate->copy()->subDays($backfill);

        for ($d = $startDate->copy(); $d->lte($endDate); $d->addDay()) {
            $this->computeFor($d->copy());
        }

        return self::SUCCESS;
    }

    protected function computeFor(Carbon $date): void
    {
        $dStr = $date->toDateString();
        $this->info("Computing KPI for {$dStr}...");

        $dayStart = $date->copy()->startOfDay();
        $dayEnd   = $date->copy()->endOfDay();

        $total_users = DB::table('users')->where('created_at', '<=', $dayEnd)->count();
        $new_users   = DB::table('users')->whereBetween('created_at', [$dayStart, $dayEnd])->count();

        // DAU: last_login_at 이 당일
        $dau = DB::table('users')->whereBetween('last_login_at', [$dayStart, $dayEnd])->count();

        // MAU: last_login_at 이 최근 30일 이내 (기준일로부터)
        $mau = DB::table('users')
            ->where('last_login_at', '>=', $dayEnd->copy()->subDays(30))
            ->where('last_login_at', '<=', $dayEnd)
            ->count();

        $tbl = fn($t) => Schema::hasTable($t);

        $posts    = $tbl('posts')    ? DB::table('posts')->whereBetween('created_at', [$dayStart, $dayEnd])->count()    : 0;
        $comments = $tbl('comments') ? DB::table('comments')->whereBetween('created_at', [$dayStart, $dayEnd])->count() : 0;
        $market   = $tbl('market_items')         ? DB::table('market_items')->whereBetween('created_at', [$dayStart, $dayEnd])->count()         : 0;
        $realest  = $tbl('real_estate_listings') ? DB::table('real_estate_listings')->whereBetween('created_at', [$dayStart, $dayEnd])->count() : 0;
        $jobs     = $tbl('jobs')     ? DB::table('jobs')->whereBetween('created_at', [$dayStart, $dayEnd])->count()     : 0;
        $reports  = $tbl('reports')  ? DB::table('reports')->whereBetween('created_at', [$dayStart, $dayEnd])->count()  : 0;
        $sos      = $tbl('elder_sos_logs') ? DB::table('elder_sos_logs')->whereBetween('created_at', [$dayStart, $dayEnd])->count() : 0;
        $ip_bans  = $tbl('ip_bans')  ? DB::table('ip_bans')->whereBetween('created_at', [$dayStart, $dayEnd])->count()  : 0;

        $revenue = 0;
        $payments = 0;
        if ($tbl('payments')) {
            $payments = DB::table('payments')->whereBetween('created_at', [$dayStart, $dayEnd])
                ->where('status', 'completed')->count();
            $revenue = DB::table('payments')->whereBetween('created_at', [$dayStart, $dayEnd])
                ->where('status', 'completed')->sum('amount') ?? 0;
        }

        DB::table('kpi_daily')->updateOrInsert(
            ['date' => $dStr],
            [
                'total_users'        => $total_users,
                'new_users'          => $new_users,
                'dau'                => $dau,
                'mau'                => $mau,
                'posts_count'        => $posts,
                'comments_count'     => $comments,
                'market_items_count' => $market,
                'real_estate_count'  => $realest,
                'jobs_count'         => $jobs,
                'revenue_usd'        => $revenue,
                'payments_count'     => $payments,
                'reports_count'      => $reports,
                'sos_count'          => $sos,
                'ip_bans_count'      => $ip_bans,
                'created_at'         => now(),
            ]
        );

        $this->line("  users={$total_users} new={$new_users} dau={$dau} mau={$mau} posts={$posts} revenue=\${$revenue}");
    }
}
