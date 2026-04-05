<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PointLog;
use App\Models\UserDailySpin;
use Illuminate\Http\Request;

class PointController extends Controller
{
    /**
     * GET /api/points/history
     * Paginated point transaction logs.
     */
    public function history(Request $request)
    {
        $logs = PointLog::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $logs,
        ]);
    }

    /**
     * GET /api/points/balance
     * Current points and game_points balance.
     */
    public function balance()
    {
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data'    => [
                'points'       => $user->points ?? 0,
                'game_points'  => $user->game_points ?? 0,
                'role'         => $user->role ?? 'user',
            ],
        ]);
    }

    /**
     * POST /api/points/daily-spin
     * Once per day, random 0-300 points.
     */
    public function dailySpin(Request $request)
    {
        $user = auth()->user();
        $today = today()->toDateString();

        // Check if already spun today
        $alreadySpun = UserDailySpin::where('user_id', $user->id)
            ->where('spin_date', $today)
            ->exists();

        if ($alreadySpun) {
            return response()->json([
                'success' => false,
                'message' => '오늘은 이미 룰렛을 돌렸습니다.',
            ], 400);
        }

        // Random points: 0 to 300
        $points = rand(0, 300);

        // Record the spin
        UserDailySpin::create([
            'user_id'     => $user->id,
            'spin_date'   => $today,
            'points_won'  => $points,
        ]);

        // Add points
        if ($points > 0) {
            $user->increment('points', $points);

            PointLog::create([
                'user_id'       => $user->id,
                'type'          => 'daily_spin',
                'action'        => 'earn',
                'amount'        => $points,
                'balance_after' => $user->fresh()->points,
                'memo'          => "일일 룰렛 +{$points}P",
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $points > 0 ? "축하합니다! {$points}P를 획득했습니다!" : '아쉽게도 0P입니다. 내일 다시 도전하세요!',
            'data'    => [
                'points_won' => $points,
                'balance'    => $user->fresh()->points,
            ],
        ]);
    }
}
