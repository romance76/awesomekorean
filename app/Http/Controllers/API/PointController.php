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
        return response()->json(['success' => true, 'data' => ['points' => $u->points, 'game_points' => $u->game_points]]);
    }

    public function dailySpin() {
        $today = now()->toDateString();
        if (UserDailySpin::where('user_id', auth()->id())->whereDate('spun_at', $today)->exists()) {
            return response()->json(['success' => false, 'message' => '오늘 이미 룰렛을 돌렸습니다'], 400);
        }
        $won = collect([0,0,10,10,20,30,50,100,200,300])->random();
        UserDailySpin::create(['user_id' => auth()->id(), 'spun_at' => now(), 'points_won' => $won]);
        if ($won > 0) auth()->user()->addPoints($won, '일일 룰렛');
        return response()->json(['success' => true, 'data' => ['points_won' => $won]]);
    }
}
