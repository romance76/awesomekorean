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
        $player = GamePlayer::create([
            'game_room_id' => 0, // solo play
            'user_id' => $user->id,
            'score' => $score,
            'is_winner' => false,
            'bet_amount' => 0,
        ]);

        // 포인트 보상 계산 (점수 기반)
        $pointReward = 0;
        $pointPerGame = (int) (GameSetting::where('game_type', 'global')->where('key', 'point_per_game')->first()?->value ?? 5);

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

    // 리더보드
    public function leaderboard(Request $request, $gameType)
    {
        $period = $request->period ?? 'all'; // all, monthly, weekly

        $query = GamePlayer::select('user_id', DB::raw('MAX(score) as best_score'), DB::raw('COUNT(*) as play_count'))
            ->where('game_room_id', 0) // solo plays only
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
            ->where('game_room_id', 0)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['success' => true, 'data' => $scores]);
    }
}
