<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameMoneyController extends Controller
{
    /**
     * 설정값 (point_settings 테이블)
     * key = game_money.*
     */
    protected function settings(): array
    {
        $rows = DB::table('point_settings')->where('key', 'like', 'game_money.%')->get();
        $map = [];
        foreach ($rows as $r) $map[$r->key] = $r->value;

        return [
            'rate_to_game'      => (int)($map['game_money.rate_to_game'] ?? 10000),   // 1 P → 10000 게임머니
            'withdraw_fee_pct'  => (int)($map['game_money.withdraw_fee_pct'] ?? 10),  // 역환전 10% 수수료
            'min_exchange_p'    => (int)($map['game_money.min_exchange_p'] ?? 10),    // 최소 환전 10P
            'min_withdraw_gm'   => (int)($map['game_money.min_withdraw_gm'] ?? 100000), // 최소 역환전 10만 게임머니
            'enabled'           => ($map['game_money.enabled'] ?? '1') == '1',
            // 포인트 → 게임머니 방향만 별도로 막는 스위치 (역환전은 이 값과 무관)
            'points_to_game_enabled' => \App\Support\PointRules::get('points_to_game_enabled', 0) === 1,
        ];
    }

    /**
     * 잔액 + 환율 조회
     * GET /api/game-money
     */
    public function index(Request $request)
    {
        $user = $request->user();
        return response()->json(['success' => true, 'data' => [
            'points'       => (int)$user->points,
            'game_points'  => (int)$user->game_points,
            'settings'     => $this->settings(),
        ]]);
    }

    /**
     * 포인트 → 게임머니 환전
     * POST /api/game-money/exchange { amount_p: 100 }
     */
    public function exchange(Request $request)
    {
        $user = $request->user();
        $s = $this->settings();
        if (!$s['enabled']) {
            return response()->json(['success' => false, 'message' => '환전 기능이 비활성화되었습니다'], 403);
        }
        if (!$s['points_to_game_enabled']) {
            return response()->json(['success' => false, 'message' => '포인트로는 게임머니를 살 수 없습니다. (보유 중인 게임머니는 포인트로 되돌릴 수 있어요)'], 403);
        }

        $amountP = (int)$request->input('amount_p', 0);
        if ($amountP < $s['min_exchange_p']) {
            return response()->json(['success' => false, 'message' => "최소 {$s['min_exchange_p']}P 이상 환전 가능합니다"], 422);
        }
        if ($user->points < $amountP) {
            return response()->json(['success' => false, 'message' => '포인트 부족'], 422);
        }

        $gmAmount = $amountP * $s['rate_to_game'];

        DB::transaction(function () use ($user, $amountP, $gmAmount) {
            // 포인트 차감 + 히스토리
            $user->addPoints(-$amountP, "게임머니 환전 ({$gmAmount} 게임머니)", 'game_exchange');
            // 게임머니 증가
            $user->increment('game_points', $gmAmount);
            // game_points 변동도 로그 남기기 (related 로 표시)
            $user->pointLogs()->create([
                'amount' => $gmAmount,
                'type' => 'game_money_in',
                'reason' => "포인트→게임머니 환전 (+{$gmAmount})",
                'balance_after' => $user->fresh()->game_points,
                'related_type' => 'game_money',
            ]);
        });

        $fresh = $user->fresh();
        return response()->json(['success' => true, 'message' => "{$amountP}P → {$gmAmount} 게임머니 환전 완료", 'data' => [
            'points' => (int)$fresh->points,
            'game_points' => (int)$fresh->game_points,
            'exchanged' => $gmAmount,
        ]]);
    }

    /**
     * 게임머니 → 포인트 역환전 (수수료 차감)
     * POST /api/game-money/withdraw { amount_gm: 100000 }
     */
    public function withdraw(Request $request)
    {
        $user = $request->user();
        $s = $this->settings();
        if (!$s['enabled']) {
            return response()->json(['success' => false, 'message' => '환전 기능이 비활성화되었습니다'], 403);
        }

        $amountGm = (int)$request->input('amount_gm', 0);
        if ($amountGm < $s['min_withdraw_gm']) {
            return response()->json(['success' => false, 'message' => "최소 " . number_format($s['min_withdraw_gm']) . " 게임머니 이상 역환전 가능"], 422);
        }
        if ($user->game_points < $amountGm) {
            return response()->json(['success' => false, 'message' => '게임머니 부족'], 422);
        }

        // 수수료 차감 후 포인트 계산 (내림)
        $netGm = $amountGm * (100 - $s['withdraw_fee_pct']) / 100;
        $amountP = (int)floor($netGm / $s['rate_to_game']);
        if ($amountP <= 0) {
            return response()->json(['success' => false, 'message' => '역환전 금액이 1P 미만이라 불가'], 422);
        }
        $feeGm = $amountGm - $netGm;

        DB::transaction(function () use ($user, $amountGm, $amountP, $feeGm) {
            // 게임머니 차감
            $user->decrement('game_points', $amountGm);
            $user->pointLogs()->create([
                'amount' => -$amountGm,
                'type' => 'game_money_out',
                'reason' => "게임머니→포인트 역환전 (수수료 {$feeGm} GM)",
                'balance_after' => $user->fresh()->game_points,
                'related_type' => 'game_money',
            ]);
            // 포인트 적립
            $user->addPoints($amountP, "게임머니 역환전 ({$amountGm} GM → {$amountP}P)", 'game_withdraw');
        });

        $fresh = $user->fresh();
        return response()->json(['success' => true, 'message' => number_format($amountGm) . " GM → {$amountP}P 역환전 완료 (수수료 " . number_format($feeGm) . " GM)", 'data' => [
            'points' => (int)$fresh->points,
            'game_points' => (int)$fresh->game_points,
            'withdrawn_p' => $amountP,
            'fee_gm' => (int)$feeGm,
        ]]);
    }

    /**
     * 환전 히스토리
     * GET /api/game-money/history
     */
    public function history(Request $request)
    {
        $user = $request->user();
        $logs = $user->pointLogs()
            ->whereIn('type', ['game_exchange', 'game_withdraw', 'game_money_in', 'game_money_out', 'poker7_bet', 'poker7_win'])
            ->orderByDesc('created_at')
            ->paginate(30);
        return response()->json(['success' => true, 'data' => $logs]);
    }
}
