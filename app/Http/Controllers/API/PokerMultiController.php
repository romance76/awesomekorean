<?php

namespace App\Http\Controllers\API;

use App\Events\PokerAction;
use App\Events\PokerChat;
use App\Http\Controllers\Controller;
use App\Models\PokerGame;
use App\Models\PokerTournament;
use App\Services\PokerGameEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PokerMultiController extends Controller
{
    // ── 토너먼트 참가 ──
    public function joinTournament(Request $request, $tournamentId)
    {
        $tournament = PokerTournament::findOrFail($tournamentId);
        $user = auth()->user();

        // 이미 참가 확인
        $existing = \DB::table('poker_tournament_entries')
            ->where('tournament_id', $tournamentId)
            ->where('user_id', $user->id)
            ->first();
        if ($existing) return response()->json(['success' => false, 'message' => '이미 참가 중입니다.'], 422);

        // 바이인 포인트 확인
        $wallet = $user->pokerWallet;
        if (!$wallet || $wallet->chips_balance < $tournament->buy_in) {
            return response()->json(['success' => false, 'message' => "칩 부족. 필요: {$tournament->buy_in}, 보유: " . ($wallet->chips_balance ?? 0)], 422);
        }

        // 칩 차감 + 엔트리 생성
        $wallet->decrement('chips_balance', $tournament->buy_in);
        \DB::table('poker_tournament_entries')->insert([
            'tournament_id' => $tournamentId,
            'user_id' => $user->id,
            'status' => 'registered',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $entryCount = \DB::table('poker_tournament_entries')
            ->where('tournament_id', $tournamentId)
            ->where('status', 'registered')
            ->count();

        return response()->json([
            'success' => true,
            'message' => '토너먼트 참가 완료!',
            'entry_count' => $entryCount,
        ]);
    }

    // ── 대기실 상태 ──
    public function waitingRoom($tournamentId)
    {
        $tournament = PokerTournament::findOrFail($tournamentId);
        $entries = \DB::table('poker_tournament_entries')
            ->where('tournament_id', $tournamentId)
            ->where('status', 'registered')
            ->join('users', 'users.id', '=', 'poker_tournament_entries.user_id')
            ->select('users.id', 'users.name', 'users.nickname', 'users.avatar')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'tournament' => $tournament,
                'players' => $entries,
                'count' => $entries->count(),
                'min_players' => $tournament->min_players ?? 2,
                'max_players' => $tournament->max_players ?? 9,
                'can_start' => $entries->count() >= ($tournament->min_players ?? 2),
            ],
        ]);
    }

    // ── 캐시 게임 (빠른 매칭) ──
    public function quickMatch(Request $request)
    {
        $user = auth()->user();
        $gameType = $request->type ?? 'normal'; // normal(15초) or speed(10초)
        $turnTime = $gameType === 'speed' ? 10 : 15;
        $bb = $request->bb ?? 20;

        // 대기열에 추가
        $queueKey = "poker_queue_{$gameType}_{$bb}";
        $queue = Cache::get($queueKey, []);

        // 이미 대기 중인지 확인
        if (collect($queue)->where('id', $user->id)->count()) {
            return response()->json(['success' => true, 'message' => '매칭 대기 중...', 'status' => 'waiting']);
        }

        $queue[] = [
            'id' => $user->id,
            'name' => $user->nickname ?? $user->name,
            'chips' => $user->pokerWallet?->chips_balance ?? 15000,
            'joinedAt' => time(),
        ];

        Cache::put($queueKey, $queue, 300); // 5분 TTL

        // 인원 충족 시 게임 시작 (최소 2명, 최대 9명)
        if (count($queue) >= 2) {
            $players = array_slice($queue, 0, min(count($queue), 9));
            Cache::forget($queueKey);

            $state = PokerGameEngine::createGame($players, [
                'bb' => $bb,
                'sb' => intdiv($bb, 2),
                'turnTime' => $turnTime,
                'type' => $gameType,
            ]);

            // DB 기록
            PokerGame::create([
                'game_id' => $state['gameId'],
                'type' => 'cash',
                'status' => 'playing',
                'config' => json_encode($state['config']),
                'player_count' => count($players),
            ]);

            // 각 플레이어에게 브로드캐스트
            foreach ($players as $p) {
                $view = PokerGameEngine::getPlayerView($state, $p['id']);
                broadcast(new PokerAction($state['gameId'], $view, ['type' => 'game_start']))->toOthers();
            }

            return response()->json([
                'success' => true,
                'message' => '게임 시작!',
                'status' => 'started',
                'gameId' => $state['gameId'],
                'state' => PokerGameEngine::getPlayerView($state, $user->id),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => '매칭 대기 중... (' . count($queue) . '명)',
            'status' => 'waiting',
            'queue_count' => count($queue),
        ]);
    }

    // ── 게임 상태 조회 ──
    public function getState($gameId)
    {
        $state = PokerGameEngine::getGameState($gameId);
        if (!$state) return response()->json(['success' => false, 'message' => '게임을 찾을 수 없습니다.'], 404);

        return response()->json([
            'success' => true,
            'data' => PokerGameEngine::getPlayerView($state, auth()->id()),
        ]);
    }

    // ── 플레이어 액션 ──
    public function action(Request $request, $gameId)
    {
        $request->validate([
            'action' => 'required|in:fold,check,call,raise,allin',
            'amount' => 'nullable|integer|min:0',
        ]);

        $result = PokerGameEngine::processAction($gameId, auth()->id(), $request->action, $request->amount ?? 0);

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'message' => $result['error']], 422);
        }

        // 모든 플레이어에게 업데이트 브로드캐스트
        // 각 플레이어는 자기 카드만 볼 수 있게 개별 전송
        foreach ($result['seats'] as $seat) {
            if (isset($seat['id']) && $seat['id'] > 0) {
                $view = PokerGameEngine::getPlayerView($result, $seat['id']);
                // 개별 유저 채널로 전송
                broadcast(new PokerAction($gameId, $view, $result['lastAction']))->toOthers();
            }
        }

        return response()->json([
            'success' => true,
            'data' => PokerGameEngine::getPlayerView($result, auth()->id()),
        ]);
    }

    // ── 인게임 채팅 ──
    public function chat(Request $request, $gameId)
    {
        $request->validate(['message' => 'required|max:200']);
        $user = auth()->user();

        broadcast(new PokerChat($gameId, $user->id, $user->nickname ?? $user->name, $request->message));

        return response()->json(['success' => true]);
    }

    // ── 타임아웃 체크 (cron으로 매초 실행 or 클라이언트 폴링) ──
    public function checkTimeout($gameId)
    {
        $state = PokerGameEngine::getGameState($gameId);
        if (!$state || $state['status'] !== 'playing') {
            return response()->json(['success' => true, 'timeout' => false]);
        }

        if (time() > $state['turnDeadline']) {
            // 시간 초과 → 자동 체크/폴드
            $actIdx = $state['actIdx'];
            $seat = $state['seats'][$actIdx];
            $toCall = max(0, $state['betLevel'] - $seat['bet']);
            $action = $toCall === 0 ? 'check' : 'fold';

            $result = PokerGameEngine::processAction($gameId, $seat['id'], $action);

            if (!isset($result['error'])) {
                foreach ($result['seats'] as $s) {
                    if (isset($s['id']) && $s['id'] > 0) {
                        broadcast(new PokerAction($gameId, PokerGameEngine::getPlayerView($result, $s['id']), ['type' => 'timeout', 'action' => $action]));
                    }
                }
            }

            return response()->json(['success' => true, 'timeout' => true, 'action' => $action]);
        }

        return response()->json(['success' => true, 'timeout' => false, 'remaining' => $state['turnDeadline'] - time()]);
    }
}
