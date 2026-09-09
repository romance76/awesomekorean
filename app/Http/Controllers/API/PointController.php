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

    // 일일 룰렛 기본 가중치 테이블 (DB `daily_spin_table` 미설정 시 폴백, 합계 100)
    private const DEFAULT_SPIN_TABLE = [
        ['value' => 0, 'weight' => 60],
        ['value' => 1, 'weight' => 20],
        ['value' => 2, 'weight' => 10],
        ['value' => 5, 'weight' => 6],
        ['value' => 10, 'weight' => 3],
        ['value' => 30, 'weight' => 1],
    ];

    /** 누적 가중치(cumulative weight) 방식으로 가중 랜덤 값을 뽑는다 */
    private function weightedSpinPick(array $table): int
    {
        $totalWeight = array_sum(array_map(fn($row) => (float) ($row['weight'] ?? 0), $table));
        if ($totalWeight <= 0) return 0;

        $rand = mt_rand() / mt_getrandmax() * $totalWeight;
        $cumulative = 0;
        foreach ($table as $row) {
            $cumulative += (float) ($row['weight'] ?? 0);
            if ($rand <= $cumulative) {
                return (int) ($row['value'] ?? 0);
            }
        }
        // 부동소수 오차 대비 폴백: 마지막 항목
        return (int) (end($table)['value'] ?? 0);
    }

    public function dailySpin() {
        $today = now()->toDateString();
        $userId = auth()->id();
        // P2B-2: 룰렛 보상 가중치 테이블 DB 동적 (`daily_spin_table` JSON: [{"value":..,"weight":..}, ...])
        $tableRaw = \App\Support\PointRules::raw('daily_spin_table', json_encode(self::DEFAULT_SPIN_TABLE));
        $table = json_decode($tableRaw, true);
        if (!is_array($table) || empty($table)) $table = self::DEFAULT_SPIN_TABLE;
        $won = $this->weightedSpinPick($table);

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
