<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 관리자 "오늘 할 일" 건수 한 번에 — 휴대폰 홈이 여러 API 를 따로 부르지 않고(합계 2.5초 이상) 이 주소 하나만 부르게 한다.
 * 등급: 운영자 이상은 신고만, 관리자 이상은 광고·전단·소유권·보상 대기, 최고관리자는 안 보낸 경품·실패한 큐 작업까지.
 * 값이 null 이면 "이 등급에는 보이지 않는 항목"이다. 30초 캐시(등급별).
 */
class AdminTodoCountsController extends Controller
{
    private function count(string $table, array $where = []): ?int
    {
        try {
            if (!Schema::hasTable($table)) return null;
            $q = DB::table($table);
            foreach ($where as $col => $val) { $q->where($col, $val); }
            return (int) $q->count();
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    public function index()
    {
        $role = auth()->user()->role;
        $rank = ['moderator' => 1, 'admin' => 2, 'super_admin' => 3][$role] ?? 0;

        $data = Cache::remember('admin_todo_counts:' . $rank, 30, function () use ($rank) {
            $out = [
                'reports_pending' => $this->count('reports', ['status' => 'pending']),
                'banners_pending' => null, 'flyers_pending' => null, 'ownership_pending' => null, 'event_proofs_pending' => null,
                'prizes_unsent' => null, 'queue_failed' => null,
            ];
            if ($rank >= 2) {
                $out['banners_pending'] = $this->count('banner_ads', ['status' => 'pending']);
                $out['flyers_pending'] = $this->count('flyer_ads', ['status' => 'pending']);
                $out['ownership_pending'] = $this->count('business_claims', ['status' => 'pending']);
                $out['event_proofs_pending'] = $this->count('event_attendees', ['proof_status' => 'pending']);
            }
            if ($rank >= 3) {
                // 안 보낸 경품: 지급 상태가 대기이거나 비어 있는 당첨 건
                try {
                    $out['prizes_unsent'] = Schema::hasTable('sweepstakes_prize_claims')
                        ? (int) DB::table('sweepstakes_prize_claims')->where(function ($q) { $q->whereNull('delivery_status')->orWhere('delivery_status', 'pending'); })->count()
                        : null;
                } catch (\Throwable $e) { report($e); }
                $out['queue_failed'] = $this->count('failed_jobs');
            }
            $out['total'] = array_sum(array_filter($out, fn ($v) => is_int($v)));
            return $out;
        });

        return response()->json(['success' => true, 'data' => $data, 'generated_at' => now()->toIso8601String()]);
    }
}
