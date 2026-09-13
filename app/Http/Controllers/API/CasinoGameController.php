<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * 슬롯머신·맞고(고스톱) 솔로 게임의 게임머니(users.game_points) 정산.
 * 둘 다 클라이언트 로컬 로직으로 진행되던 게임이라, 클라이언트가 보고하는
 * 결과를 그대로 믿을 수 없음 — 슬롯은 결과 자체를 서버가 뽑아 조작 여지를
 * 없애고, 고스톱은 판돈만 서버가 관리하고 정산액은 라운드당 배율 상한을
 * 둬(최대 3배) 클라이언트가 점수를 조작해도 손실 규모를 제한한다.
 */
class CasinoGameController extends Controller
{
    const SLOT_SYMBOLS = ['💎', '7️⃣', '⭐', '🔔', '🍒', '🍋', '🍉'];
    const SLOT_PAYOUTS = ['💎' => 100, '7️⃣' => 50, '⭐' => 20, '🔔' => 15, '🍒' => 10, '🍋' => 8, '🍉' => 6];
    const SLOT_BETS = [10, 50, 100, 500];
    const GOSTOP_BETS = [50, 100, 500, 1000];

    public function slotsSpin(Request $request)
    {
        $user = $request->user();
        $bet = (int) $request->input('bet');

        if (!in_array($bet, self::SLOT_BETS, true)) {
            return response()->json(['success' => false, 'message' => '잘못된 베팅 금액입니다'], 422);
        }
        if ($user->game_points < $bet) {
            return response()->json(['success' => false, 'message' => '게임머니가 부족합니다'], 422);
        }

        $symbols = [
            self::SLOT_SYMBOLS[array_rand(self::SLOT_SYMBOLS)],
            self::SLOT_SYMBOLS[array_rand(self::SLOT_SYMBOLS)],
            self::SLOT_SYMBOLS[array_rand(self::SLOT_SYMBOLS)],
        ];
        $win = $this->calcSlotWin($symbols, $bet);
        $net = $win - $bet;

        $user->increment('game_points', $net);
        $user->pointLogs()->create([
            'amount' => $net,
            'type' => 'slots',
            'reason' => $net >= 0 ? "슬롯머신 당첨 (베팅 {$bet} → 당첨 {$win})" : "슬롯머신 낙첨 (베팅 {$bet})",
            'balance_after' => $user->fresh()->game_points,
            'related_type' => 'game_money',
        ]);

        return response()->json(['success' => true, 'data' => [
            'symbols' => $symbols,
            'win' => $win,
            'game_points' => (int) $user->fresh()->game_points,
        ]]);
    }

    private function calcSlotWin(array $symbols, int $bet): int
    {
        [$a, $b, $c] = $symbols;
        if ($a === $b && $b === $c) {
            return (self::SLOT_PAYOUTS[$a] ?? 1) * $bet;
        }
        if ($a === $b || $b === $c || $a === $c) {
            return 2 * $bet;
        }
        return 0;
    }

    /** 맞고 한 판 시작 — 판돈(게임머니)을 즉시 차감하고 정산용 라운드 토큰 발급 */
    public function gostopStart(Request $request)
    {
        $user = $request->user();
        $bet = (int) $request->input('bet');

        if (!in_array($bet, self::GOSTOP_BETS, true)) {
            return response()->json(['success' => false, 'message' => '잘못된 베팅 금액입니다'], 422);
        }
        if ($user->game_points < $bet) {
            return response()->json(['success' => false, 'message' => '게임머니가 부족합니다'], 422);
        }

        $user->decrement('game_points', $bet);
        $user->pointLogs()->create([
            'amount' => -$bet,
            'type' => 'gostop_bet',
            'reason' => "맞고 판돈 ({$bet})",
            'balance_after' => $user->fresh()->game_points,
            'related_type' => 'game_money',
        ]);

        $roundId = (string) Str::uuid();
        Cache::put("gostop_round:{$roundId}", [
            'user_id' => $user->id,
            'bet' => $bet,
            'settled' => false,
        ], now()->addMinutes(20));

        return response()->json(['success' => true, 'data' => [
            'round_id' => $roundId,
            'game_points' => (int) $user->fresh()->game_points,
        ]]);
    }

    /**
     * 맞고 한 판 정산 — 점수는 클라이언트가 로컬로 계산한 값을 보내오므로
     * (게임 로직 자체가 서버에 없어 완전 검증 불가) 0~20점으로 잘라 배율을
     * 최대 3배로 제한, 라운드 토큰을 1회용으로 소모해 중복 정산을 막는다.
     */
    public function gostopSettle(Request $request)
    {
        $user = $request->user();
        $roundId = (string) $request->input('round_id');
        $myScore = max(0, min(20, (int) $request->input('my_score', 0)));
        $botScore = max(0, min(20, (int) $request->input('bot_score', 0)));

        $round = Cache::get("gostop_round:{$roundId}");
        if (!$round || $round['user_id'] !== $user->id || $round['settled']) {
            return response()->json(['success' => false, 'message' => '유효하지 않은 라운드입니다'], 422);
        }

        $bet = $round['bet'];
        if ($myScore >= $botScore) {
            $payout = (int) round($bet * (1 + $myScore / 10)); // 최대 3배(무승부 포함 승리)
        } else {
            $payout = (int) round($bet * max(0, 1 - $botScore / 20)); // 최소 0배(완패)
        }

        $round['settled'] = true;
        Cache::put("gostop_round:{$roundId}", $round, now()->addMinutes(20));

        if ($payout > 0) {
            $user->increment('game_points', $payout);
        }
        $user->pointLogs()->create([
            'amount' => $payout,
            'type' => 'gostop_win',
            'reason' => "맞고 정산 (판돈 {$bet}, 내 {$myScore}점 · 상대 {$botScore}점 → 지급 {$payout})",
            'balance_after' => $user->fresh()->game_points,
            'related_type' => 'game_money',
        ]);

        return response()->json(['success' => true, 'data' => [
            'payout' => $payout,
            'game_points' => (int) $user->fresh()->game_points,
        ]]);
    }
}
