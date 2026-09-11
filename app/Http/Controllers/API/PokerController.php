<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PokerWallet;
use App\Models\PokerTransaction;
use App\Models\PokerGame;
use App\Models\PokerGamePlayer;
use App\Models\PokerStat;
use App\Models\GameSetting;
use Illuminate\Http\Request;

class PokerController extends Controller
{
    // ─── User: Wallet ───

    public function wallet(Request $request)
    {
        $wallet = PokerWallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['chips_balance' => 0, 'total_deposited' => 0, 'total_withdrawn' => 0]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'chips_balance' => $wallet->chips_balance,
                'total_deposited' => $wallet->total_deposited,
                'total_withdrawn' => $wallet->total_withdrawn,
                'points' => $request->user()->points,
            ],
        ]);
    }

    public function deposit(Request $request)
    {
        $request->validate(['amount' => 'required|integer|min:1000']);

        $user = $request->user();
        $amount = $request->amount;

        if ($user->points < $amount) {
            return response()->json(['success' => false, 'message' => '포인트가 부족합니다.'], 422);
        }

        $wallet = PokerWallet::firstOrCreate(
            ['user_id' => $user->id],
            ['chips_balance' => 0, 'total_deposited' => 0, 'total_withdrawn' => 0]
        );

        $user->addPoints(-$amount, '포커 칩 구매', 'spend');
        $wallet->deposit($amount, '포인트 전환');

        return response()->json([
            'success' => true,
            'data' => [
                'chips_balance' => $wallet->fresh()->chips_balance,
                'points' => $user->fresh()->points,
            ],
            'message' => "{$amount} 칩 충전 완료!",
        ]);
    }

    public function withdraw(Request $request)
    {
        $request->validate(['amount' => 'required|integer|min:1000']);

        $user = $request->user();
        $amount = $request->amount;
        $wallet = PokerWallet::where('user_id', $user->id)->first();

        if (!$wallet || $wallet->chips_balance < $amount) {
            return response()->json(['success' => false, 'message' => '칩이 부족합니다.'], 422);
        }

        $fee = (int) floor($amount * 0.05);
        $netAmount = $amount - $fee;

        $wallet->withdraw($amount, "포인트 전환 (수수료 {$fee})");
        $user->addPoints($netAmount, '포커 칩 환전', 'earn');

        return response()->json([
            'success' => true,
            'data' => [
                'chips_balance' => $wallet->fresh()->chips_balance,
                'points' => $user->fresh()->points,
                'fee' => $fee,
                'received' => $netAmount,
            ],
            'message' => "{$netAmount} 포인트 환전 완료! (수수료 {$fee})",
        ]);
    }

    public function transactions(Request $request)
    {
        $transactions = PokerTransaction::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $transactions]);
    }

    // ─── User: Games ───

    public function storeGame(Request $request)
    {
        $request->validate([
            'config' => 'required|array',
            'final_place' => 'required|integer|min:1',
            'hands_played' => 'required|integer|min:0',
            'elapsed_seconds' => 'required|integer|min:0',
            'prize_won' => 'integer|min:0',
            'bounties_earned' => 'integer|min:0',
            'bounty_amount' => 'integer|min:0',
            'blind_level' => 'integer|min:0',
            'players' => 'array',
        ]);

        $user = $request->user();

        $game = PokerGame::create([
            'user_id' => $user->id,
            'type' => 'solo',
            'status' => 'completed',
            'config' => $request->config,
            'blind_level' => $request->blind_level ?? 0,
            'hands_played' => $request->hands_played,
            'final_place' => $request->final_place,
            'prize_won' => $request->prize_won ?? 0,
            'bounties_earned' => $request->bounties_earned ?? 0,
            'bounty_amount' => $request->bounty_amount ?? 0,
            'elapsed_seconds' => $request->elapsed_seconds,
        ]);

        // Save players
        if ($request->players) {
            foreach ($request->players as $player) {
                PokerGamePlayer::create([
                    'game_id' => $game->id,
                    'seat_index' => $player['seat_index'] ?? 0,
                    'player_type' => $player['player_type'] ?? 'ai',
                    'player_name' => $player['player_name'] ?? '',
                    'ai_profile' => $player['ai_profile'] ?? null,
                    'starting_chips' => $player['starting_chips'] ?? 0,
                    'final_chips' => $player['final_chips'] ?? 0,
                    'status' => $player['status'] ?? 'eliminated',
                ]);
            }
        }

        // 이 엔드포인트는 서버가 진행을 검증하지 않는 솔로(1인 대 AI) 게임만
        // 생성하며(type='solo' 고정), 입장료도 서버에서 걷지 않음 — 클라이언트가
        // 보고하는 final_place/prize_won/bounty_amount를 그대로 실제 인출 가능한
        // 칩으로 지급하면 가짜 결과로 무한정 칩을 만들어 포인트로 출금할 수 있음
        // (실측 확인된 취약점). 통계(순위/핸드 수 등 아래 로직)는 그대로 두되,
        // 실제 화폐가치가 있는 칩 지급만 차단.
        $totalWinnings = 0;

        // Update stats
        $stat = PokerStat::firstOrCreate(
            ['user_id' => $user->id],
            ['games_played' => 0, 'hands_played' => 0, 'tournaments_won' => 0, 'in_the_money' => 0, 'total_prize_won' => 0, 'total_bounties' => 0, 'total_buy_ins' => 0, 'biggest_pot_won' => 0]
        );
        $paidSlots = max(1, floor(($request->config['totalPlayers'] ?? 90) * 0.15));
        $stat->increment('games_played');
        $stat->increment('hands_played', $request->hands_played);
        $stat->increment('total_buy_ins', $request->config['buyIn'] ?? 0);
        if ($request->final_place === 1) $stat->increment('tournaments_won');
        if ($request->final_place <= $paidSlots) {
            $stat->increment('in_the_money');
            $stat->increment('total_prize_won', $totalWinnings);
        }
        if ($request->bounties_earned > 0) $stat->increment('total_bounties', $request->bounties_earned);
        if (!$stat->best_place || $request->final_place < $stat->best_place) {
            $stat->update(['best_place' => $request->final_place]);
        }

        return response()->json([
            'success' => true,
            'data' => $game->load('players'),
            'message' => '게임 결과가 저장되었습니다.',
        ]);
    }

    public function stats(Request $request)
    {
        $stat = PokerStat::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['games_played' => 0, 'hands_played' => 0, 'tournaments_won' => 0, 'in_the_money' => 0, 'total_prize_won' => 0, 'total_bounties' => 0, 'total_buy_ins' => 0, 'biggest_pot_won' => 0]
        );

        return response()->json(['success' => true, 'data' => $stat]);
    }

    public function leaderboard()
    {
        $leaders = PokerStat::with('user:id,name,nickname,avatar')
            ->where('games_played', '>', 0)
            ->orderByDesc('total_prize_won')
            ->limit(50)
            ->get();

        return response()->json(['success' => true, 'data' => $leaders]);
    }

    public function history(Request $request)
    {
        $games = PokerGame::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $games]);
    }

    // ─── Admin ───

    public function adminOverview()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_games' => PokerGame::count(),
                'total_wallets' => PokerWallet::count(),
                'total_deposited' => PokerWallet::sum('total_deposited'),
                'total_withdrawn' => PokerWallet::sum('total_withdrawn'),
                'chips_in_circulation' => PokerWallet::sum('chips_balance'),
                'active_players' => PokerStat::where('games_played', '>', 0)->count(),
            ],
        ]);
    }

    public function adminWallets(Request $request)
    {
        $wallets = PokerWallet::with('user:id,name,nickname,email,avatar')
            ->orderByDesc('chips_balance')
            ->paginate(30);

        return response()->json(['success' => true, 'data' => $wallets]);
    }

    public function adminUpdateWallet(Request $request, $id)
    {
        $request->validate(['chips_balance' => 'required|integer|min:0']);

        $wallet = PokerWallet::findOrFail($id);
        $oldBalance = $wallet->chips_balance;
        $wallet->update(['chips_balance' => $request->chips_balance]);

        $diff = $request->chips_balance - $oldBalance;
        PokerTransaction::create([
            'user_id' => $wallet->user_id,
            'type' => $diff >= 0 ? 'deposit' : 'withdraw',
            'amount' => $diff,
            'balance_after' => $request->chips_balance,
            'description' => '관리자 수동 조정',
        ]);

        return response()->json(['success' => true, 'message' => '칩 잔고가 수정되었습니다.']);
    }

    public function adminSettings()
    {
        $settings = GameSetting::where('game_type', 'poker')->first();
        $defaults = [
            'min_deposit' => 1000,
            'min_withdraw' => 1000,
            'withdraw_fee_pct' => 5,
            'max_buy_in' => 10000,
            'enabled' => true,
        ];

        return response()->json([
            'success' => true,
            'data' => $settings ? json_decode($settings->settings, true) : $defaults,
        ]);
    }

    public function adminUpdateSettings(Request $request)
    {
        GameSetting::updateOrCreate(
            ['game_type' => 'poker'],
            ['settings' => json_encode($request->all())]
        );

        return response()->json(['success' => true, 'message' => '설정이 저장되었습니다.']);
    }
}
