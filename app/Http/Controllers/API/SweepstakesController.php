<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Sweepstakes;
use App\Models\SweepstakesEntry;
use App\Models\SweepstakesReminder;
use App\Models\SweepstakesWinnerAudit;
use App\Support\EntryService;
use App\Support\SweepstakesDrawReplay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SweepstakesController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'active');
        $query = Sweepstakes::query()->orderByDesc('start_at');
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        $items = $query->paginate(20);

        return response()->json(['success' => true, 'data' => $items]);
    }

    /**
     * 최근 당첨 기록 (공개). 표시이름/티켓번호만 노출한다.
     */
    public function recentWinners(Request $request)
    {
        $limit = max(1, min(20, (int) $request->query('limit', 8)));

        $data = Cache::remember("sweepstakes:recent-winners:{$limit}", 60, function () use ($limit) {
            $audits = SweepstakesWinnerAudit::with(['sweepstakes', 'winner'])
                ->whereNotNull('selected_at')
                ->whereNotNull('winning_index')
                ->orderByDesc('selected_at')
                ->limit($limit)
                ->get();

            return $audits->filter(fn ($a) => $a->sweepstakes)->map(fn ($a) => [
                'sweepstakes_id' => $a->sweepstakes_id,
                'event_id' => $a->sweepstakes->event_id ?? null,
                'title' => $a->sweepstakes->title ?? null,
                'prize_name' => $a->sweepstakes->prize_name ?? null,
                'drawn_at' => $a->selected_at ? $a->selected_at->toIso8601String() : null,
                'winning_ticket' => (int) $a->winning_index + 1,
                'winner_display_name' => (string) ($a->winner?->display_name ?? ''),
            ])->values()->all();
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function show(Request $request, Sweepstakes $sweepstakes)
    {
        $myEntries = 0;
        if (auth()->check()) {
            $myEntries = (int) (SweepstakesEntry::where('sweepstakes_id', $sweepstakes->id)
                ->where('user_id', auth()->id())
                ->value('entries_count') ?? 0);
        }

        $total = (int) $sweepstakes->total_entries;
        $probability = $total > 0 ? round(($myEntries / $total) * 100, 2) : 0.0;

        // 다른 참가자 개인정보는 공개 응답에 절대 포함하지 않음 — 당첨자만
        // 표시이름으로 공개(일반적인 Sweepstakes 결과 공지 관행). 실시간
        // 응모 현황 휠(원형 차트) 용도로는 "나를 제외한 참가자별 entries_count"
        // 숫자 목록만 익명으로 내려준다 — 누가 몇 개인지 신원과 연결되지 않음.
        $winnerName = null;
        if ($sweepstakes->status === 'winner_selected' && $sweepstakes->winner_user_id) {
            $winnerName = $sweepstakes->winner?->display_name;
        }

        $breakdownQuery = SweepstakesEntry::where('sweepstakes_id', $sweepstakes->id);
        if (auth()->check()) {
            $breakdownQuery->where('user_id', '!=', auth()->id());
        }
        $otherEntriesBreakdown = $breakdownQuery->orderByDesc('entries_count')->limit(100)->pluck('entries_count');

        return response()->json([
            'success' => true,
            'data' => array_merge($sweepstakes->toArray(), [
                'my_entries' => $myEntries,
                'my_win_probability_pct' => $probability,
                'winner_display_name' => $winnerName,
                'other_entries_breakdown' => $otherEntriesBreakdown,
                'my_reminder' => auth()->check()
                    ? SweepstakesReminder::where('sweepstakes_id', $sweepstakes->id)->where('user_id', auth()->id())->exists()
                    : false,
                // 추첨 연출 스킨/테마 + (당첨 확정 시) 재생용 draw 객체
                ...SweepstakesDrawReplay::extra($sweepstakes),
                // 참가 현황 기준 확률이며, 추가 응모가 들어오면 변경됨을 프론트에서
                // 반드시 함께 노출해야 함(요구사항 14) — 서버는 값만 계산해 전달.
            ]),
        ]);
    }

    public function enter(Request $request, Sweepstakes $sweepstakes)
    {
        $request->validate([
            'amount' => 'required|integer|min:1',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        if (!$sweepstakes->isOpenForEntries()) {
            return response()->json(['success' => false, 'message' => '지금은 참가할 수 없는 Sweepstakes입니다'], 422);
        }

        $user = auth()->user();

        // eligible_regions가 지정돼 있으면 회원 state와 대조(현재 저장된 필드
        // 기준 1차 체크 — 연령 제한은 생년월일 데이터가 아직 없어 추후 추가 예정).
        $regions = $sweepstakes->eligible_regions;
        if ($regions && is_array($regions) && count($regions) > 0) {
            $userState = $user->state ? strtoupper($user->state) : null;
            if (!$userState || !in_array($userState, array_map('strtoupper', $regions))) {
                return response()->json(['success' => false, 'message' => '거주 지역 조건상 참가할 수 없습니다'], 422);
            }
        }

        try {
            $entry = EntryService::spendOnSweepstakes(
                $user,
                $sweepstakes,
                (int) $request->amount,
                $request->idempotency_key
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $sweepstakes->refresh();
        $total = (int) $sweepstakes->total_entries;
        $probability = $total > 0 ? round(($entry->entries_count / $total) * 100, 2) : 0.0;

        return response()->json([
            'success' => true,
            'message' => "{$request->amount} Entry를 사용했습니다",
            'data' => [
                'my_entries' => $entry->entries_count,
                'total_entries' => $total,
                'my_win_probability_pct' => $probability,
                'remaining_entries' => $user->fresh()->entries,
            ],
        ]);
    }

    /**
     * 추첨 시작 5분 전 알림 신청 (응모한 회원만).
     */
    public function setReminder(Request $request, Sweepstakes $sweepstakes)
    {
        $userId = auth()->id();

        $entered = (int) (SweepstakesEntry::where('sweepstakes_id', $sweepstakes->id)
            ->where('user_id', $userId)
            ->value('entries_count') ?? 0);
        if ($entered < 1) {
            return response()->json(['success' => false, 'message' => '응모한 추첨에만 알림을 신청할 수 있어요'], 422);
        }
        if ($sweepstakes->status !== 'active' || !$sweepstakes->end_at || !$sweepstakes->end_at->isFuture()) {
            return response()->json(['success' => false, 'message' => '이미 마감되었거나 진행 중이 아닌 추첨이에요'], 422);
        }

        $remindAt = $sweepstakes->end_at->copy()->subMinutes(5);
        if ($remindAt->isPast()) {
            $remindAt = now();
        }

        SweepstakesReminder::updateOrCreate(
            ['user_id' => $userId, 'sweepstakes_id' => $sweepstakes->id],
            ['remind_at' => $remindAt, 'notified_at' => null, 'dismissed_at' => null]
        );

        return response()->json([
            'success' => true,
            'message' => '추첨 시작 5분 전에 알려드릴게요',
            'data' => ['my_reminder' => true, 'remind_at' => $remindAt->toIso8601String()],
        ]);
    }

    public function removeReminder(Request $request, Sweepstakes $sweepstakes)
    {
        SweepstakesReminder::where('user_id', auth()->id())
            ->where('sweepstakes_id', $sweepstakes->id)
            ->delete();

        return response()->json(['success' => true, 'data' => ['my_reminder' => false]]);
    }

    /**
     * 지금 화면 하단에 띄울 알림 목록 (내 알림 중 시간이 됐고, 아직 마감 전이고, 닫지 않은 것).
     */
    public function dueReminders(Request $request)
    {
        $rows = SweepstakesReminder::with('sweepstakes')
            ->where('user_id', auth()->id())
            ->where('remind_at', '<=', now())
            ->whereNull('dismissed_at')
            ->get();

        $items = $rows->filter(fn ($r) => $r->sweepstakes
                && $r->sweepstakes->status === 'active'
                && $r->sweepstakes->end_at
                && $r->sweepstakes->end_at->isFuture())
            ->map(fn ($r) => [
                'sweepstakes_id' => $r->sweepstakes_id,
                'event_id' => $r->sweepstakes->event_id,
                'prize_name' => $r->sweepstakes->prize_name,
                'end_at' => $r->sweepstakes->end_at->toIso8601String(),
                'seconds_left' => max(0, (int) floor(now()->diffInSeconds($r->sweepstakes->end_at, false))),
            ])->sortBy('seconds_left')->values()->all();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function dismissReminder(Request $request, $sweepstakesId)
    {
        SweepstakesReminder::where('user_id', auth()->id())
            ->where('sweepstakes_id', (int) $sweepstakesId)
            ->update(['dismissed_at' => now()]);

        return response()->json(['success' => true]);
    }
}
