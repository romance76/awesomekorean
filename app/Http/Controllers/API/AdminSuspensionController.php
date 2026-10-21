<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BoardSuspension;
use App\Models\Report;
use App\Models\User;
use App\Support\BoardSuspensions;
use App\Support\ReportTargets;
use Illuminate\Http\Request;

/**
 * 신고로 인한 게시판 정지 — 관리자 화면(신고 관리)용.
 *
 *  info    : 이 신고의 글쓴이·추천 게시판·누적 정지 횟수·게시판 선택지 (정지 창을 열 때)
 *  suspend : 글쓴이를 해당 게시판에서 정지하고 신고를 "처리 완료"로 닫는다 (3회 누적이면 계정 정지)
 *  index   : 지금 효력 있는 정지 목록
 *  lift    : 정지를 중간에 푼다
 */
class AdminSuspensionController extends Controller
{
    private const STAFF = ['moderator', 'admin', 'super_admin'];

    /** 신고 대상과 글쓴이 */
    private function resolve(Report $report): array
    {
        $target = class_exists((string) $report->reportable_type) ? $report->reportable_type::find($report->reportable_id) : null;
        $ownerId = $target ? ReportTargets::ownerId($target) : null;
        $owner = $ownerId ? User::find($ownerId) : null;
        return [$target, $owner];
    }

    public function info($id)
    {
        $report = Report::findOrFail($id);
        [$target, $owner] = $this->resolve($report);

        return response()->json(['success' => true, 'data' => [
            'owner' => $owner ? [
                'id' => $owner->id, 'name' => $owner->nickname ?: $owner->name, 'email' => $owner->email,
                'role' => $owner->role, 'is_banned' => (bool) $owner->is_banned,
                'strikes' => BoardSuspensions::strikes($owner->id),
            ] : null,
            'max_strikes' => BoardSuspensions::MAX_STRIKES,
            'suggested_board' => BoardSuspensions::keyForTarget($target),
            'boards' => BoardSuspensions::options(),
            'is_staff_target' => $owner ? in_array($owner->role, self::STAFF, true) : false,
        ]]);
    }

    public function suspend(Request $request, $id)
    {
        $data = $request->validate([
            'board_key' => 'required|string|max:40',
            'days' => 'nullable|integer|min:1|max:3650',      // 비우면 영구
            'note' => 'nullable|string|max:500',
        ], ['days.max' => '정지 기간은 최대 3650일(10년)까지예요. 더 길게 하려면 "영구"를 고르세요.']);

        $report = Report::findOrFail($id);
        [, $owner] = $this->resolve($report);
        if (!$owner) return response()->json(['success' => false, 'message' => '글쓴이를 찾을 수 없어요. 글이 이미 지워졌을 수 있어요.'], 422);
        if (in_array($owner->role, self::STAFF, true)) return response()->json(['success' => false, 'message' => '운영진 계정은 신고로 정지할 수 없어요.'], 422);
        if (!BoardSuspensions::isValidKey($data['board_key'])) return response()->json(['success' => false, 'message' => '알 수 없는 게시판이에요.'], 422);
        if (BoardSuspensions::blockFor($owner->id, $data['board_key'])) {
            return response()->json(['success' => false, 'message' => '이 회원은 이미 이 게시판에서 정지 중이에요.'], 409);
        }

        $actor = auth()->user();
        $days = isset($data['days']) ? (int) $data['days'] : null;
        $res = BoardSuspensions::issue($owner, $data['board_key'], $days, $report, $actor, $data['note'] ?? null);
        $s = $res['suspension'];

        // 신고는 "처리 완료"로 닫고, 이력에 정지 내용을 남긴다
        $statusBefore = $report->status;
        $report->applyUpdate('resolved', $data['note'] ?? null, $actor);
        $report->addLog('suspend', 'resolved', 'resolved', $actor,
            "게시판 정지: {$s->board_label} · " . ($days ? "{$days}일" : '영구') . " (누적 {$res['strikes']}/" . BoardSuspensions::MAX_STRIKES . '회)' . ($res['banned'] ? ' → 계정 이용 정지' : ''));

        // 신고자에게 처리 결과 알림 (일반 해결/기각과 같은 방식)
        if ($report->reporter_id && $statusBefore !== 'resolved') {
            try {
                \App\Models\Notification::create([
                    'user_id' => $report->reporter_id, 'type' => 'report_resolved', 'title' => '신고 처리 결과 안내',
                    'content' => '신고하신 건이 처리되었습니다 (조치: 해당 회원의 게시판 이용 정지)', 'data' => ['report_id' => $report->id],
                ]);
            } catch (\Throwable $e) {
            }
        }

        return response()->json([
            'success' => true,
            'message' => $res['banned']
                ? "정지했어요. 정지가 {$res['strikes']}회 쌓여 이 회원의 계정 이용도 정지됐어요."
                : "정지했어요. (이 회원 누적 {$res['strikes']}/" . BoardSuspensions::MAX_STRIKES . '회)',
            'data' => $report->fresh(['reporter:id,name,nickname', 'handler:id,name,nickname', 'logs']),
            'strikes' => $res['strikes'], 'banned' => $res['banned'],
        ]);
    }

    public function index()
    {
        $rows = BoardSuspension::with('user:id,name,nickname,email,is_banned')->active()->orderByDesc('id')->limit(200)->get();
        $strikes = BoardSuspension::whereIn('user_id', $rows->pluck('user_id')->unique())->whereNull('lifted_at')
            ->selectRaw('user_id, COUNT(*) AS c')->groupBy('user_id')->pluck('c', 'user_id');

        return response()->json(['success' => true, 'data' => $rows->map(fn ($s) => [
            'id' => $s->id, 'board' => $s->board_label, 'reason' => $s->reason, 'report_id' => $s->report_id,
            'created_at' => $s->created_at?->toIso8601String(), 'ends_at' => $s->ends_at?->toIso8601String(), 'permanent' => $s->ends_at === null,
            'user' => $s->user ? ['id' => $s->user->id, 'name' => $s->user->nickname ?: $s->user->name, 'email' => $s->user->email, 'is_banned' => (bool) $s->user->is_banned] : null,
            'strikes' => (int) ($strikes[$s->user_id] ?? 0),
        ])->values()]);
    }

    public function lift($id)
    {
        $s = BoardSuspension::findOrFail($id);
        if (!BoardSuspensions::lift($s, auth()->user())) {
            return response()->json(['success' => false, 'message' => '이미 풀린 정지예요.'], 409);
        }
        return response()->json(['success' => true, 'message' => '정지를 풀었어요.']);
    }
}
