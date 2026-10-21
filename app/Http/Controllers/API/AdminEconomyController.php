<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 포인트·Entry 이코노미 정리 — 운영 시작 전 시험·더미 계정에 쌓인 포인트/Entry 를 비운다. 최고관리자 + 재확인(reauth) 전용.
 *
 *  preview : 지킬 계정과 정리 대상(계정 수·포인트·Entry·기록 건수)을 보여 준다. 아무것도 바꾸지 않는다.
 *  run     : 대상 계정의 잔액과 기록을 JSON 으로 백업(storage/app/economy_cleanup)한 뒤, 한 트랜잭션으로 잔액을 0 으로 만들고 포인트·Entry 기록을 지운다.
 *
 * 지키는 계정: PROTECTED_USER_IDS(실제 운영 계정) + 역할이 슈퍼관리자인 모든 계정. 이 계정들은 값도 기록도 건드리지 않는다.
 * 결제(매출) 기록은 건드리지 않는다. 등급(누적 획득 포인트)도 그대로 둔다.
 */
class AdminEconomyController extends Controller
{
    /** 실제 운영·시험에 쓰는 계정 — 이 계정들의 포인트·Entry 는 절대 정리하지 않는다 */
    private const PROTECTED_USER_IDS = [202, 2901, 2902, 2903, 2904, 2905, 2908, 2909, 2910];

    /** 정리 대상 계정 번호들 (지킬 계정·슈퍼관리자 제외, 포인트/Entry 가 0 이 아닌 계정만) */
    private function targetIds(): array
    {
        $keep = array_merge(self::PROTECTED_USER_IDS, DB::table('users')->where('role', 'super_admin')->pluck('id')->all());
        return DB::table('users')->whereNotIn('id', array_values(array_unique($keep)))
            ->where(fn ($q) => $q->where('points', '!=', 0)->orWhere('game_points', '!=', 0)->orWhere('entries', '!=', 0))
            ->pluck('id')->all();
    }

    private function summary(array $ids): array
    {
        $u = DB::table('users')->whereIn('id', $ids);
        return [
            'accounts' => count($ids),
            'points' => (int) (clone $u)->sum('points'),
            'game_points' => (int) (clone $u)->sum('game_points'),
            'entries' => (int) (clone $u)->sum('entries'),
            'point_logs' => $ids ? DB::table('point_logs')->whereIn('user_id', $ids)->count() : 0,
            'entry_logs' => $ids ? DB::table('entry_transactions')->whereIn('user_id', $ids)->count() : 0,
        ];
    }

    public function preview()
    {
        $ids = $this->targetIds();
        $keep = DB::table('users')
            ->where(fn ($q) => $q->whereIn('id', self::PROTECTED_USER_IDS)->orWhere('role', 'super_admin'))
            ->orderBy('id')->get(['id', 'name', 'email', 'role', 'points', 'entries'])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'email' => $r->email, 'super' => $r->role === 'super_admin', 'points' => (int) $r->points, 'entries' => (int) $r->entries]);

        return response()->json(['success' => true, 'data' => [
            'target' => $this->summary($ids),
            'sample' => DB::table('users')->whereIn('id', $ids)->orderByDesc('points')->limit(8)->get(['id', 'name', 'email', 'points', 'entries']),
            'protected' => $keep,
        ]]);
    }

    public function run(Request $request)
    {
        $request->validate(['confirm' => 'required|string']);
        if (trim((string) $request->input('confirm')) !== '정리') {
            return response()->json(['success' => false, 'message' => '확인 칸에 "정리"라고 정확히 입력해 주세요.'], 422);
        }
        $ids = $this->targetIds();
        if (!$ids) return response()->json(['success' => true, 'message' => '정리할 계정이 없어요.', 'data' => ['accounts' => 0]]);
        $before = $this->summary($ids);

        // 1) 백업 — 지우기 전에 잔액과 기록을 통째로 JSON 으로 저장한다 (되돌릴 때 쓴다)
        $backup = [
            'cleaned_at' => now()->toDateTimeString(), 'by_admin' => auth()->id(), 'summary' => $before,
            'users' => DB::table('users')->whereIn('id', $ids)->get(['id', 'email', 'points', 'game_points', 'entries'])->all(),
            'point_logs' => DB::table('point_logs')->whereIn('user_id', $ids)->get()->all(),
            'entry_transactions' => DB::table('entry_transactions')->whereIn('user_id', $ids)->get()->all(),
        ];
        $file = 'economy_cleanup/economy_' . now()->format('Ymd_His') . '_u' . auth()->id() . '.json';
        Storage::disk('local')->put($file, json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        if (!Storage::disk('local')->exists($file)) {
            return response()->json(['success' => false, 'message' => '백업 파일을 만들지 못해서 정리를 시작하지 않았어요. 아무것도 바뀌지 않았어요.'], 500);
        }

        // 2) 한 트랜잭션으로 정리 (중간에 실패하면 전부 되돌아간다)
        try {
            DB::transaction(function () use ($ids) {
                foreach (array_chunk($ids, 500) as $chunk) {
                    DB::table('point_logs')->whereIn('user_id', $chunk)->delete();
                    DB::table('entry_transactions')->whereIn('user_id', $chunk)->delete();
                    DB::table('users')->whereIn('id', $chunk)->update(['points' => 0, 'game_points' => 0, 'entries' => 0]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('[이코노미 정리] 실패: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => '정리하지 못했어요. 아무것도 바뀌지 않았어요. (' . mb_substr($e->getMessage(), 0, 120) . ')'], 500);
        }

        Log::warning('[이코노미 정리] 최고관리자 ' . auth()->id() . ' 가 ' . count($ids) . '개 계정 정리 (포인트 ' . $before['points'] . ', Entry ' . $before['entries'] . ', 백업=' . $file . ')');
        return response()->json(['success' => true, 'message' => count($ids) . '개 계정의 포인트·Entry를 정리했어요.', 'data' => $before + ['backup' => $file]]);
    }
}
