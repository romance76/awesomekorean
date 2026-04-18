<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * /admin/v2/analytics API (Phase 2-C 묶음 9).
 * KPI 카드·차트·피드·감사 로그.
 */
class AdminAnalyticsController extends Controller
{
    public function kpi(Request $request)
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from')) : now()->subDays(29);
        $to   = $request->query('to')   ? Carbon::parse($request->query('to'))   : now();

        $rows = DB::table('kpi_daily')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        $latest = DB::table('kpi_daily')->orderByDesc('date')->first();

        // 전체 합계
        $summary = [
            'total_users'    => $latest?->total_users ?? 0,
            'dau_avg'        => (int) ($rows->avg('dau') ?? 0),
            'mau_latest'     => $latest?->mau ?? 0,
            'new_users_sum'  => (int) $rows->sum('new_users'),
            'posts_sum'      => (int) $rows->sum('posts_count'),
            'comments_sum'   => (int) $rows->sum('comments_count'),
            'market_sum'     => (int) $rows->sum('market_items_count'),
            'realestate_sum' => (int) $rows->sum('real_estate_count'),
            'jobs_sum'       => (int) $rows->sum('jobs_count'),
            'revenue_sum'    => (float) $rows->sum('revenue_usd'),
            'payments_sum'   => (int) $rows->sum('payments_count'),
            'reports_sum'    => (int) $rows->sum('reports_count'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'from'    => $from->toDateString(),
                'to'      => $to->toDateString(),
                'summary' => $summary,
                'series'  => $rows,
            ],
        ]);
    }

    public function funnel(Request $request)
    {
        $days = min((int) $request->query('days', 30), 90);
        $since = now()->subDays($days);

        // 간단한 Funnel: 가입 → 첫 글 → 첫 결제
        $signups = DB::table('users')->where('created_at', '>=', $since)->count();

        $authored = DB::table('users')
            ->where('users.created_at', '>=', $since)
            ->whereExists(function ($q) {
                $q->from('posts')->whereColumn('posts.user_id', 'users.id');
            })
            ->count();

        $paid = DB::table('users')
            ->where('users.created_at', '>=', $since)
            ->whereExists(function ($q) {
                $q->from('payments')->whereColumn('payments.user_id', 'users.id')->where('status', 'completed');
            })
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'days' => $days,
                'stages' => [
                    ['name' => '가입', 'count' => $signups, 'pct' => 100],
                    ['name' => '첫 글',  'count' => $authored, 'pct' => $signups ? round($authored / $signups * 100, 1) : 0],
                    ['name' => '첫 결제','count' => $paid,     'pct' => $signups ? round($paid     / $signups * 100, 1) : 0],
                ],
            ],
        ]);
    }

    public function topUsers(Request $request)
    {
        $metric = $request->query('metric', 'payment_amount');
        $limit  = min((int) $request->query('limit', 10), 50);

        if ($metric === 'payment_amount') {
            $rows = DB::table('payments')
                ->join('users', 'users.id', '=', 'payments.user_id')
                ->where('payments.status', 'completed')
                ->select('users.id', 'users.nickname', 'users.email',
                    DB::raw('COUNT(*) as c'), DB::raw('SUM(payments.amount) as total'))
                ->groupBy('users.id', 'users.nickname', 'users.email')
                ->orderByDesc('total')
                ->limit($limit)
                ->get();
        } elseif ($metric === 'post_count') {
            $rows = DB::table('posts')
                ->join('users', 'users.id', '=', 'posts.user_id')
                ->select('users.id', 'users.nickname', DB::raw('COUNT(*) as c'))
                ->groupBy('users.id', 'users.nickname')
                ->orderByDesc('c')
                ->limit($limit)
                ->get();
        } else {
            $rows = collect();
        }

        return response()->json(['success' => true, 'metric' => $metric, 'data' => $rows]);
    }

    public function feed(Request $request)
    {
        $limit = min((int) $request->query('limit', 20), 100);
        $rows = DB::table('event_log')
            ->leftJoin('users', 'users.id', '=', 'event_log.user_id')
            ->select('event_log.*', 'users.nickname')
            ->orderByDesc('event_log.occurred_at')
            ->limit($limit)
            ->get();
        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function auditLog(Request $request)
    {
        $q = DB::table('admin_audit_log')
            ->leftJoin('users', 'users.id', '=', 'admin_audit_log.admin_id')
            ->select('admin_audit_log.*', 'users.nickname as admin_name');
        if ($request->filled('admin_id')) $q->where('admin_id', $request->query('admin_id'));
        if ($request->filled('action'))   $q->where('action', $request->query('action'));
        return response()->json([
            'success' => true,
            'data' => $q->orderByDesc('admin_audit_log.created_at')->limit(200)->get(),
        ]);
    }

    public function preferences()
    {
        $row = DB::table('dashboard_preferences')->where('user_id', auth()->id())->first();
        return response()->json(['success' => true, 'data' => $row]);
    }

    public function savePreferences(Request $request)
    {
        DB::table('dashboard_preferences')->updateOrInsert(
            ['user_id' => auth()->id()],
            [
                'default_view'  => $request->input('default_view', 'executive'),
                'widget_config' => $request->has('widget_config') ? json_encode($request->input('widget_config')) : null,
                'saved_filters' => $request->has('saved_filters') ? json_encode($request->input('saved_filters')) : null,
                'updated_at'    => now(),
            ]
        );
        return response()->json(['success' => true]);
    }
}
