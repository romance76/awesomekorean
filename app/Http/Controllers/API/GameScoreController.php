<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GamePlayer;
use App\Models\GameSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameScoreController extends Controller
{
    // 게임 점수 저장
    public function store(Request $request)
    {
        $request->validate([
            'game_type' => 'required|string|max:30',
            'score' => 'required|integer|min:0',
        ]);

        $user = auth()->user();
        $gameType = $request->game_type;
        $score = $request->score;

        // 점수 저장
        // game_room_id는 game_rooms를 참조하는 FK라 존재하지 않는 0을 넣으면
        // 제약 위반으로 500 에러가 발생했음(실측 확인) — 솔로 플레이는 NULL로 저장.
        $player = GamePlayer::create([
            'game_room_id' => null, // solo play
            'user_id' => $user->id,
            'score' => $score,
            'is_winner' => false,
            'bet_amount' => 0,
        ]);

        // 포인트 보상 계산 (점수 기반)
        // 관리자 게임별 설정 화면(GameController::saveSettings)은 game_type을 각
        // 게임의 slug로 저장하는데, 여기선 항상 game_type='global'만 읽어 게임별로
        // 설정한 point_per_game 값이 전부 무시되고 있었음(실측 확인) — 게임별 설정을
        // 우선 조회하고, 없으면 전역 기본값(game_type='global')으로 폴백.
        $pointReward = 0;
        $perGame = GameSetting::where('game_type', $gameType)->where('key', 'point_per_game')->first()?->value;
        $pointPerGame = (int) ($perGame ?? GameSetting::where('game_type', 'global')->where('key', 'point_per_game')->first()?->value ?? 5);

        if ($score > 0) {
            $pointReward = min($score, $pointPerGame); // 최대 point_per_game
            $user->addPoints($pointReward, "게임 보상 ({$gameType}): {$score}점");
        }

        return response()->json([
            'success' => true,
            'data' => [
                'score' => $score,
                'points_earned' => $pointReward,
                'total_points' => $user->fresh()->points,
            ],
        ]);
    }

    /** 포인트 → 레벨 이름 매핑 */
    private function levelName(int $points): string
    {
        if ($points >= 50000) return '참나무';
        if ($points >= 10000) return '숲';
        if ($points >= 3000)  return '나무';
        if ($points >= 500)   return '새싹';
        return '씨앗';
    }

    /** 통합 리더보드 (타입별): points / posts / quiz */
    public function overallLeaderboard(Request $request)
    {
        $type = $request->query('type', 'points');
        $limit = 20;

        if ($type === 'points') {
            $users = \App\Models\User::select('id', 'name', 'nickname', 'email', 'avatar', 'points')
                ->orderByDesc('points')
                ->limit($limit)
                ->get()
                ->map(fn($u) => [
                    'id' => $u->id,
                    'username' => $u->nickname ?: (explode('@', $u->email ?? '')[0] ?: $u->name),
                    'name' => $u->name,
                    'avatar' => $u->avatar,
                    'level' => $this->levelName((int) ($u->points ?? 0)),
                    'value' => (int) ($u->points ?? 0),
                ]);
        } elseif ($type === 'posts') {
            // 커뮤니티 게시글 수 기준
            $rows = DB::table('posts')
                ->select('user_id', DB::raw('COUNT(*) as post_count'))
                ->groupBy('user_id')
                ->orderByDesc('post_count')
                ->limit($limit)
                ->get();
            $userIds = $rows->pluck('user_id')->filter()->values();
            $usersById = \App\Models\User::select('id', 'name', 'nickname', 'email', 'avatar', 'points')
                ->whereIn('id', $userIds)->get()->keyBy('id');
            $users = $rows->map(function ($r) use ($usersById) {
                $u = $usersById[$r->user_id] ?? null;
                if (!$u) return null;
                return [
                    'id' => $u->id,
                    'username' => $u->nickname ?: (explode('@', $u->email ?? '')[0] ?: $u->name),
                    'name' => $u->name,
                    'avatar' => $u->avatar,
                    'level' => $this->levelName((int) ($u->points ?? 0)),
                    'value' => (int) $r->post_count,
                ];
            })->filter()->values();
        } else { // quiz
            // 솔로 게임 플레이의 최고 누적 점수 기준
            $rows = DB::table('game_players')
                ->select('user_id', DB::raw('SUM(score) as total_score'))
                ->whereNull('game_room_id')
                ->groupBy('user_id')
                ->orderByDesc('total_score')
                ->limit($limit)
                ->get();
            $userIds = $rows->pluck('user_id')->filter()->values();
            $usersById = \App\Models\User::select('id', 'name', 'nickname', 'email', 'avatar', 'points')
                ->whereIn('id', $userIds)->get()->keyBy('id');
            $users = $rows->map(function ($r) use ($usersById) {
                $u = $usersById[$r->user_id] ?? null;
                if (!$u) return null;
                return [
                    'id' => $u->id,
                    'username' => $u->nickname ?: (explode('@', $u->email ?? '')[0] ?: $u->name),
                    'name' => $u->name,
                    'avatar' => $u->avatar,
                    'level' => $this->levelName((int) ($u->points ?? 0)),
                    'value' => (int) $r->total_score,
                ];
            })->filter()->values();
        }

        return response()->json(['success' => true, 'data' => $users]);
    }

    // 리더보드
    public function leaderboard(Request $request, $gameType)
    {
        $period = $request->period ?? 'all'; // all, monthly, weekly

        $query = GamePlayer::select('user_id', DB::raw('MAX(score) as best_score'), DB::raw('COUNT(*) as play_count'))
            ->whereNull('game_room_id') // solo plays only
            ->groupBy('user_id')
            ->orderByDesc('best_score')
            ->limit(50);

        if ($period === 'weekly') {
            $query->where('created_at', '>=', now()->subWeek());
        } elseif ($period === 'monthly') {
            $query->where('created_at', '>=', now()->subMonth());
        }

        $leaders = $query->get()->map(function ($l) {
            $user = \App\Models\User::select('id', 'name', 'nickname', 'avatar')->find($l->user_id);
            return [
                'user' => $user,
                'best_score' => $l->best_score,
                'play_count' => $l->play_count,
            ];
        });

        return response()->json(['success' => true, 'data' => $leaders]);
    }

    // 내 점수 기록
    public function myScores(Request $request)
    {
        $scores = GamePlayer::where('user_id', auth()->id())
            ->whereNull('game_room_id')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['success' => true, 'data' => $scores]);
    }
}
