<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 이상 활동 탐지 대시보드 (Phase 2-C Post - Kay #9 완성).
 * 복수 지표 기반 "일반적이지 않은" 패턴 감지.
 */
class AdminAnomalyController extends Controller
{
    public function overview(Request $request)
    {
        $hours = min((int) $request->query('hours', 24), 168);
        $since = now()->subHours($hours);

        return response()->json([
            'success' => true,
            'data' => [
                'hours' => $hours,
                'signals' => [
                    'failed_logins_spike'  => $this->failedLoginsSpike($since),
                    'bulk_signups'         => $this->bulkSignups($since),
                    'mass_reports'         => $this->massReports($since),
                    'payment_failures'     => $this->paymentFailures($since),
                    'new_post_spam'        => $this->newPostSpam($since),
                    'locked_accounts'      => $this->lockedAccounts(),
                    'recent_bans'          => $this->recentBans($since),
                    'suspicious_ips'       => $this->suspiciousIps($since),
                ],
            ],
        ]);
    }

    /** 로그인 실패 급증 */
    protected function failedLoginsSpike($since): array
    {
        if (!\Schema::hasTable('login_histories')) return ['count' => 0, 'level' => 'info'];
        $count = DB::table('login_histories')->where('successful', false)->where('created_at', '>=', $since)->count();
        return [
            'count' => $count,
            'level' => $count > 100 ? 'critical' : ($count > 30 ? 'warning' : 'info'),
            'label' => '실패 로그인',
        ];
    }

    /** 대량 신규 가입 (봇 의심) */
    protected function bulkSignups($since): array
    {
        $count = DB::table('users')->where('created_at', '>=', $since)->count();
        // 동일 도메인에서 가입 (봇 패턴)
        $domains = DB::table('users')
            ->where('created_at', '>=', $since)
            ->select(DB::raw('SUBSTRING_INDEX(email, "@", -1) as domain'), DB::raw('COUNT(*) as c'))
            ->groupBy('domain')
            ->having('c', '>', 5)
            ->orderByDesc('c')
            ->limit(5)
            ->get();

        return [
            'count' => $count,
            'level' => $count > 50 ? 'warning' : ($count > 100 ? 'critical' : 'info'),
            'suspicious_domains' => $domains,
            'label' => '신규 가입',
        ];
    }

    /** 신고 대량 */
    protected function massReports($since): array
    {
        if (!\Schema::hasTable('reports')) return ['count' => 0, 'level' => 'info'];
        $count = DB::table('reports')->where('created_at', '>=', $since)->count();

        // 동일 대상 중복 신고
        $targets = DB::table('reports')
            ->where('created_at', '>=', $since)
            ->select('reportable_type', 'reportable_id', DB::raw('COUNT(*) as c'))
            ->groupBy('reportable_type', 'reportable_id')
            ->having('c', '>=', 3)
            ->orderByDesc('c')
            ->limit(10)
            ->get();

        return [
            'count' => $count,
            'level' => $count > 20 ? 'warning' : 'info',
            'repeated_targets' => $targets,
            'label' => '신고 접수',
        ];
    }

    /** 결제 실패 */
    protected function paymentFailures($since): array
    {
        if (!\Schema::hasTable('payments')) return ['count' => 0, 'level' => 'info'];
        $failed = DB::table('payments')->where('status', 'failed')->where('created_at', '>=', $since)->count();
        $total  = DB::table('payments')->where('created_at', '>=', $since)->count();
        $rate = $total > 0 ? round($failed / $total * 100, 1) : 0;
        return [
            'count' => $failed, 'total' => $total, 'rate' => $rate,
            'level' => $rate > 30 ? 'critical' : ($rate > 10 ? 'warning' : 'info'),
            'label' => '결제 실패율',
        ];
    }

    /** 게시글 대량 작성 (스팸 의심) */
    protected function newPostSpam($since): array
    {
        if (!\Schema::hasTable('posts')) return ['count' => 0, 'level' => 'info'];
        $topSpammers = DB::table('posts')
            ->where('created_at', '>=', $since)
            ->select('user_id', DB::raw('COUNT(*) as c'))
            ->groupBy('user_id')
            ->having('c', '>', 10)
            ->orderByDesc('c')
            ->limit(5)
            ->get();
        return [
            'count' => $topSpammers->count(),
            'level' => $topSpammers->count() > 0 ? 'warning' : 'info',
            'top_posters' => $topSpammers,
            'label' => '과다 게시',
        ];
    }

    /** 차단된 계정 수 */
    protected function lockedAccounts(): array
    {
        $count = DB::table('users')->where('is_banned', true)->count();
        return ['count' => $count, 'level' => 'info', 'label' => '차단 계정'];
    }

    /** 최근 IP 차단 */
    protected function recentBans($since): array
    {
        if (!\Schema::hasTable('ip_bans')) return ['count' => 0, 'level' => 'info'];
        $count = DB::table('ip_bans')->where('created_at', '>=', $since)->count();
        return ['count' => $count, 'level' => $count > 10 ? 'warning' : 'info', 'label' => 'IP 차단'];
    }

    /** 의심 IP — 다수 유저 실패 로그인 */
    protected function suspiciousIps($since): array
    {
        if (!\Schema::hasTable('login_histories')) return ['count' => 0, 'ips' => []];
        $ips = DB::table('login_histories')
            ->where('created_at', '>=', $since)
            ->where('successful', false)
            ->whereNotNull('ip')
            ->select('ip', DB::raw('COUNT(DISTINCT email) as unique_emails'), DB::raw('COUNT(*) as attempts'))
            ->groupBy('ip')
            ->having('unique_emails', '>=', 3)  // 3개 이상 다른 이메일 시도
            ->orderByDesc('attempts')
            ->limit(10)
            ->get();

        return [
            'count' => $ips->count(),
            'level' => $ips->count() > 0 ? 'critical' : 'info',
            'ips'   => $ips,
            'label' => '의심 IP (다중 이메일)',
        ];
    }
}
