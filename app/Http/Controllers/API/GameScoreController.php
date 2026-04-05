<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\GameSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class GameScoreController extends Controller
{
    // POST /api/games/{id}/score — 게임 점수 저장
    public function saveScore(Request $req, $gameId)
    {
        $user = Auth::user();
        $score    = (int) $req->input('score', 0);
        $duration = (int) $req->input('duration', 0);
        $result   = $req->input('result', 'win'); // win/lose/draw
        $level    = (int) $req->input('level', 1);

        // 게임 정보 조회
        $game = DB::table('games')->where('id', $gameId)->first();
        if (!$game) {
            return response()->json(['error' => '게임을 찾을 수 없습니다'], 404);
        }

        // 게임 타입별 포인트 차등화
        $gameType = $game->type ?? 'single';
        $isBettingGame = in_array($gameType, ['betting', 'multi']);
        $isNormalGame = in_array($gameType, ['educational', 'puzzle', 'arcade', 'single']);

        if ($isBettingGame) {
            // 베팅 게임: 게임머니(CHIP) 기반 — 별도 처리 (프론트에서 직접 관리)
            // 베팅 게임은 saveScore를 통한 보상 지급 없음
            $reward = 0;
        } else {
            // 일반 게임 (교육/퍼즐/아케이드 등): 매우 소량 (1~5 COIN)
            $normalMultiplier = (float) GameSetting::get('normal_game_reward_multiplier', 0.01);
            $reward = max(1, min(5, (int)($score * $normalMultiplier) + 1));
            if ($result === 'lose') $reward = max(1, (int)($reward * 0.3));
        }

        // game_sessions 저장
        $sessionId = DB::table('game_sessions')->insertGetId([
            'game_id'    => $gameId,
            'user_id'    => $user->id,
            'score'      => $score,
            'result'     => $result,
            'duration'   => $duration,
            'reward'     => $reward,
            'meta'       => json_encode(['level' => $level, 'game_type' => $gameType]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 일반 게임만 코인 지급
        if ($reward > 0) {
            DB::table('user_wallets')
                ->where('user_id', $user->id)
                ->increment('coin_balance', $reward);
            DB::table('user_wallets')
                ->where('user_id', $user->id)
                ->increment('lifetime_earned', $reward);
        }

        // play_count 증가
        DB::table('games')->where('id', $gameId)->increment('play_count');

        $currencyLabel = $isBettingGame ? '게임머니' : '코인';
        return response()->json([
            'success'    => true,
            'session_id' => $sessionId,
            'score'      => $score,
            'reward'     => $reward,
            'is_betting' => $isBettingGame,
            'message'    => $reward > 0 ? "{$reward}{$currencyLabel}을 받았어요!" : '게임 기록이 저장되었습니다.',
        ]);
    }

    // POST /api/games/convert-to-game-money — COIN을 게임머니(CHIP)로 변환 (일방통행)
    public function convertToGameMoney(Request $req)
    {
        $user = Auth::user();
        $amount = (int) $req->input('amount', 0);
        $rate = (int) GameSetting::get('coin_to_game_money_rate', 50);

        if ($amount <= 0) {
            return response()->json(['error' => '변환할 코인 수를 입력하세요'], 400);
        }

        $wallet = DB::table('user_wallets')->where('user_id', $user->id)->first();
        if (!$wallet || (int) $wallet->coin_balance < $amount) {
            return response()->json(['error' => '코인이 부족합니다'], 400);
        }

        $gameMoney = $amount * $rate;

        DB::table('user_wallets')->where('user_id', $user->id)->update([
            'coin_balance' => DB::raw("coin_balance - {$amount}"),
            'chip_balance' => DB::raw("chip_balance + {$gameMoney}"),
            'updated_at' => now(),
        ]);

        DB::table('wallet_transactions')->insert([
            'user_id' => $user->id,
            'type' => 'convert',
            'currency' => 'chip',
            'amount' => $gameMoney,
            'balance_after' => (int) $wallet->chip_balance + $gameMoney,
            'description' => "{$amount} COIN → {$gameMoney} 게임머니 변환 (x{$rate})",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newWallet = DB::table('user_wallets')->where('user_id', $user->id)->first();

        return response()->json([
            'success' => true,
            'converted' => $gameMoney,
            'rate' => $rate,
            'coin_balance' => (int) $newWallet->coin_balance,
            'chip_balance' => (int) $newWallet->chip_balance,
            'message' => "{$amount} COIN → {$gameMoney} 게임머니로 변환되었습니다!",
        ]);
    }

    // GET /api/games/my-scores — 내 게임 기록
    public function myScores(Request $req)
    {
        $user = Auth::user();
        $gameId = $req->query('game_id');

        $query = DB::table('game_sessions as gs')
            ->join('games as g', 'gs.game_id', '=', 'g.id')
            ->select('gs.id', 'gs.game_id', 'g.name as game_name', 'gs.score',
                     'gs.result', 'gs.reward', 'gs.duration', 'gs.created_at')
            ->where('gs.user_id', $user->id)
            ->orderByDesc('gs.created_at')
            ->limit(50);

        if ($gameId) {
            $query->where('gs.game_id', $gameId);
        }

        $scores = $query->get();

        // 게임별 최고점수 요약
        $summary = DB::table('game_sessions')
            ->select('game_id', DB::raw('MAX(score) as best_score'), DB::raw('COUNT(*) as play_count'), DB::raw('SUM(reward) as total_reward'))
            ->where('user_id', $user->id)
            ->groupBy('game_id')
            ->get();

        return response()->json([
            'scores'  => $scores,
            'summary' => $summary,
        ]);
    }

    // GET /api/games/leaderboard/{gameId} — 특정 게임 리더보드
    public function gameLeaderboard($gameId)
    {
        $leaders = DB::table('game_sessions as gs')
            ->join('users as u', 'gs.user_id', '=', 'u.id')
            ->select('u.id', 'u.nickname', 'u.nickname',
                     DB::raw('MAX(gs.score) as best_score'),
                     DB::raw('COUNT(gs.id) as play_count'))
            ->where('gs.game_id', $gameId)
            ->groupBy('u.id', 'u.nickname', 'u.nickname')
            ->orderByDesc('best_score')
            ->limit(20)
            ->get();

        return response()->json($leaders);
    }

    // ---- 관리자 전용 ----

    // GET /api/admin/games — 전체 게임 목록 (관리자)
    public function adminList()
    {
        $games = DB::table('games as g')
            ->leftJoin('game_categories as gc', 'g.category_id', '=', 'gc.id')
            ->select('g.*', 'gc.name as category_name',
                     DB::raw('(SELECT COUNT(*) FROM game_sessions WHERE game_id=g.id) as session_count'),
                     DB::raw('(SELECT MAX(score) FROM game_sessions WHERE game_id=g.id) as top_score'))
            ->orderBy('g.category_id')
            ->orderBy('g.sort_order')
            ->get();

        return response()->json($games);
    }

    // PUT /api/admin/games/{id}/toggle — 게임 활성/비활성
    public function toggleActive($id)
    {
        $game = DB::table('games')->where('id', $id)->first();
        if (!$game) return response()->json(['error' => '게임 없음'], 404);
        DB::table('games')->where('id', $id)->update(['is_active' => !$game->is_active]);
        return response()->json(['success' => true, 'is_active' => !$game->is_active]);
    }

    // GET /api/admin/games/{id}/questions — 게임 문제 목록
    public function getQuestions($gameId)
    {
        $questions = DB::table('game_questions')
            ->where('game_id', $gameId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        return response()->json($questions);
    }

    // POST /api/admin/games/{id}/questions — 문제 추가
    public function addQuestion(Request $req, $gameId)
    {
        $id = DB::table('game_questions')->insertGetId([
            'game_id'    => $gameId,
            'question'   => $req->input('question'),
            'answer'     => $req->input('answer'),
            'options'    => json_encode($req->input('options', [])),
            'image_url'  => $req->input('image_url'),
            'difficulty' => $req->input('difficulty', 1),
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['success' => true, 'id' => $id]);
    }

    // DELETE /api/admin/questions/{id} — 문제 삭제
    public function deleteQuestion($id)
    {
        DB::table('game_questions')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }
}
