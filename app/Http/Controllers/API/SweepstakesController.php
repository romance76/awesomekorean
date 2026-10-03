<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Sweepstakes;
use App\Models\SweepstakesEntry;
use App\Support\EntryService;
use Illuminate\Http\Request;

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
}
