<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\PointLog;
use App\Models\UserDailySpin;
use Illuminate\Http\Request;

class PointController extends Controller
{
    public function history() {
        $logs = PointLog::where('user_id', auth()->id())->orderByDesc('created_at')->paginate(20);
        return response()->json(['success' => true, 'data' => $logs]);
    }

    public function balance() {
        $u = auth()->user();
        $spunToday = UserDailySpin::where('user_id', $u->id)->whereDate('spun_at', now()->toDateString())->exists();
        return response()->json(['success' => true, 'data' => ['points' => $u->points, 'game_points' => $u->game_points], 'daily_spin_done' => $spunToday]);
    }

    public function dailySpin() {
        $today = now()->toDateString();
        $userId = auth()->id();
        $won = collect([0,0,10,10,20,30,50,100,200,300])->random();

        // Issue #14: DB UNIQUE(user_id, spun_date) + 트랜잭션으로 race 완전 방어
        try {
            \DB::transaction(function () use ($userId, $today, $won) {
                UserDailySpin::create([
                    'user_id' => $userId,
                    'spun_at' => now(),
                    'spun_date' => $today,
                    'points_won' => $won,
                ]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // UNIQUE 제약 위반 → 오늘 이미 돌림
            if (in_array($e->errorInfo[1] ?? 0, [1062, 19])) {
                return response()->json(['success' => false, 'message' => '오늘 이미 룰렛을 돌렸습니다'], 400);
            }
            throw $e;
        }

        if ($won > 0) auth()->user()->addPoints($won, '일일 룰렛');
        return response()->json(['success' => true, 'data' => ['points_won' => $won]]);
    }
}
