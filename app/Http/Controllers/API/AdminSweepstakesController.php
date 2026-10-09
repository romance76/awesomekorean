<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EntryTransaction;
use App\Models\Sweepstakes;
use App\Models\SweepstakesEntry;
use App\Models\SweepstakesWinner;
use App\Models\User;
use App\Support\EntryService;
use App\Support\SweepstakesDrawReplay;
use App\Support\SweepstakesWinnerService;
use App\Traits\CompressesUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminSweepstakesController extends Controller
{
    use CompressesUploads;

    // 경품 추첨 생성/수정/삭제/당첨자 선정/참가현황은 사이트 최고관리자만 접근 가능
    private function requireSuperAdmin()
    {
        if (auth()->user()->role !== 'super_admin') {
            abort(response()->json(['success' => false, 'message' => '경품 추첨 관리는 사이트 최고관리자만 접근할 수 있습니다'], 403));
        }
    }

    public function index()
    {
        $this->requireSuperAdmin();

        $items = Sweepstakes::withCount('entries as unique_participants')
            ->with(['winner:id,name,nickname,email'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request)
    {
        $this->requireSuperAdmin();

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'prize_name' => 'required|string|max:255',
            'prize_value' => 'nullable|numeric|min:0',
            'prize_image' => 'nullable|string|max:255',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'status' => 'nullable|in:draft,active,ended,cancelled',
            'minimum_age' => 'nullable|integer|min:0|max:120',
            'eligible_regions' => 'nullable|array',
            'official_rules_url' => 'nullable|string|max:255',
            'no_purchase_required_text' => 'nullable|string',
            'terms_version' => 'nullable|string|max:50',
            'draw_style' => 'nullable|string|max:20',
            'theme' => 'nullable|array',
            'winner_count' => 'nullable|integer|min:1|max:10',
            'prize_tiers' => 'nullable|array|max:10',
            'prize_tiers.*.rank' => 'required_with:prize_tiers|integer|min:1|max:10',
            'prize_tiers.*.prize_name' => 'required_with:prize_tiers|string|max:255',
        ]);
        $data['status'] = $data['status'] ?? 'draft';
        $data['draw_style'] = 'lottery3d'; // 2D 휠 폐지 — 항상 3D 추첨기
        $data['theme'] = SweepstakesDrawReplay::sanitizeTheme($data['theme'] ?? null);
        [$data['winner_count'], $data['prize_tiers']] = Sweepstakes::sanitizeWinnerConfig(
            $data['winner_count'] ?? 1,
            $data['prize_tiers'] ?? null
        );

        $sweepstakes = Sweepstakes::create($data);

        return response()->json(['success' => true, 'data' => $sweepstakes]);
    }

    public function update(Request $request, Sweepstakes $sweepstakes)
    {
        $this->requireSuperAdmin();

        if ($sweepstakes->status === 'winner_selected') {
            return response()->json(['success' => false, 'message' => '당첨자가 이미 선정된 Sweepstakes는 수정할 수 없습니다'], 422);
        }

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'prize_name' => 'sometimes|string|max:255',
            'prize_value' => 'nullable|numeric|min:0',
            'prize_image' => 'nullable|string|max:255',
            'start_at' => 'sometimes|date',
            'end_at' => 'sometimes|date',
            'status' => 'nullable|in:draft,active,ended,cancelled',
            'minimum_age' => 'nullable|integer|min:0|max:120',
            'eligible_regions' => 'nullable|array',
            'official_rules_url' => 'nullable|string|max:255',
            'no_purchase_required_text' => 'nullable|string',
            'terms_version' => 'nullable|string|max:50',
            'draw_style' => 'nullable|string|max:20',
            'theme' => 'nullable|array',
            'winner_count' => 'nullable|integer|min:1|max:10',
            'prize_tiers' => 'nullable|array|max:10',
            'prize_tiers.*.rank' => 'required_with:prize_tiers|integer|min:1|max:10',
            'prize_tiers.*.prize_name' => 'required_with:prize_tiers|string|max:255',
        ]);

        if (array_key_exists('draw_style', $data)) {
            $data['draw_style'] = 'lottery3d'; // 2D 휠 폐지 — 요청값과 무관하게 정규화
        }
        if (array_key_exists('theme', $data)) {
            $data['theme'] = SweepstakesDrawReplay::sanitizeTheme($data['theme']);
        }
        // 등수 설정 — winner_selected 이후에는 위 가드에서 이미 수정 불가
        if (array_key_exists('winner_count', $data) || array_key_exists('prize_tiers', $data)) {
            $count = array_key_exists('winner_count', $data) && $data['winner_count'] !== null
                ? $data['winner_count']
                : ($sweepstakes->winner_count ?: 1);
            $tiers = array_key_exists('prize_tiers', $data) ? $data['prize_tiers'] : $sweepstakes->prize_tiers;
            [$data['winner_count'], $data['prize_tiers']] = Sweepstakes::sanitizeWinnerConfig($count, $tiers);
        }

        $sweepstakes->update($data);

        return response()->json(['success' => true, 'data' => $sweepstakes]);
    }

    // 추첨 연출용 이미지 업로드 (경품/배경/로고) — public/sweepstakes/ 에 압축 저장
    public function uploadImage(Request $request)
    {
        $this->requireSuperAdmin();

        $request->validate([
            'image' => 'required|file|image|mimes:jpg,jpeg,png,webp,gif|max:8192',
            'kind' => ['required', Rule::in(['prize', 'background', 'logo'])],
        ]);

        $maxWidth = $request->kind === 'background' ? 1920 : 1200;
        $url = $this->storeCompressedImage($request->file('image'), 'sweepstakes', $maxWidth, 85);

        return response()->json(['success' => true, 'url' => $url, 'kind' => $request->kind]);
    }

    public function destroy(Sweepstakes $sweepstakes)
    {
        $this->requireSuperAdmin();

        if ($sweepstakes->total_entries > 0) {
            return response()->json(['success' => false, 'message' => '이미 참가 Entry가 있는 Sweepstakes는 삭제할 수 없습니다. 취소(cancelled) 처리를 이용하세요.'], 422);
        }
        $sweepstakes->delete();

        return response()->json(['success' => true, 'message' => '삭제되었습니다']);
    }

    public function participants(Sweepstakes $sweepstakes)
    {
        $this->requireSuperAdmin();

        $entries = SweepstakesEntry::with('user:id,name,nickname,email')
            ->where('sweepstakes_id', $sweepstakes->id)
            ->orderByDesc('entries_count')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $entries,
            'winner_count' => max(1, (int) ($sweepstakes->winner_count ?? 1)),
            'winners' => $this->winnersPayload($sweepstakes),
        ]);
    }

    /** 관리자용 등수별 당첨자 목록 (rank 오름차순). 다중 추첨 행이 없으면 1등 한 건을 합성 */
    private function winnersPayload(Sweepstakes $sweepstakes): array
    {
        $out = [];
        if (Schema::hasTable('sweepstakes_winners')) {
            $rows = SweepstakesWinner::with('user:id,name,nickname,email')
                ->where('sweepstakes_id', $sweepstakes->id)
                ->orderBy('rank')->get();
            foreach ($rows as $w) {
                $out[] = [
                    'rank' => (int) $w->rank,
                    'user_id' => $w->user_id,
                    'user' => $w->user,
                    'winning_ticket' => (int) $w->winning_index + 1,
                    'prize_label' => $w->prize_label,
                    'selected_at' => $w->selected_at ? $w->selected_at->toIso8601String() : null,
                ];
            }
        }
        if (!$out && $sweepstakes->winner_user_id) {
            $audit = $sweepstakes->winnerAudit;
            $out[] = [
                'rank' => 1,
                'user_id' => $sweepstakes->winner_user_id,
                'user' => $sweepstakes->winner()->select('id', 'name', 'nickname', 'email')->first(),
                'winning_ticket' => $audit && $audit->winning_index !== null ? (int) $audit->winning_index + 1 : null,
                'prize_label' => $sweepstakes->prizeLabelForRank(1),
                'selected_at' => $sweepstakes->winner_selected_at ? $sweepstakes->winner_selected_at->toIso8601String() : null,
            ];
        }

        return $out;
    }

    public function selectWinner(Sweepstakes $sweepstakes)
    {
        $this->requireSuperAdmin();

        try {
            if ((int) ($sweepstakes->winner_count ?? 1) > 1) {
                $result = SweepstakesWinnerService::selectWinners($sweepstakes);
                $audit = $result['audit'];
            } else {
                $audit = SweepstakesWinnerService::selectWinner($sweepstakes);
            }
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $fresh = Sweepstakes::find($sweepstakes->id);

        return response()->json([
            'success' => true,
            'message' => '당첨자가 선정되었습니다',
            // 기존 형태 유지: 1등 감사 레코드(+winner)
            'data' => $audit->load('winner:id,name,nickname,email'),
            'winner_count' => max(1, (int) ($fresh->winner_count ?? 1)),
            'winners' => $this->winnersPayload($fresh),
        ]);
    }

    // Entry 트랜잭션 전체 조회 (관리자) — 특정 유저로 필터 가능
    public function entryTransactions(Request $request)
    {
        $query = EntryTransaction::with('user:id,name,nickname,email')->orderByDesc('created_at');
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        $items = $query->paginate(50);

        return response()->json(['success' => true, 'data' => $items]);
    }

    // 관리자 수동 Entry 지급/차감
    public function adjustUserEntries(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|integer|not_in:0',
            'description' => 'required|string|max:255',
        ]);

        $user = User::findOrFail($data['user_id']);

        try {
            $tx = EntryService::award(
                $user,
                $data['amount'],
                'ADMIN_ADJUSTMENT',
                $data['description'],
                'admin:' . auth()->id()
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $tx]);
    }

    // 특정 유저의 Entry 잔액 조회 (관리자)
    public function userBalance(int $userId)
    {
        $user = User::findOrFail($userId);

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'name' => $user->display_name,
                'entries' => $user->entries,
                'checkin_progress' => $user->entry_checkin_progress,
            ],
        ]);
    }
}
