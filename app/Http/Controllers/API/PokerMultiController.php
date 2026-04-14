<?php

namespace App\Http\Controllers\API;

use App\Events\PokerAction;
use App\Events\PokerChat;
use App\Events\PokerTournamentUpdate;
use App\Http\Controllers\Controller;
use App\Models\PokerGame;
use App\Models\PokerStat;
use App\Models\PokerTournament;
use App\Models\PokerTournamentEntry;
use App\Models\PokerWallet;
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

        // 2명 이상이면 즉시, 1명이면 10초 후 AI로 채워서 시작
        $firstJoin = collect($queue)->min('joinedAt') ?? time();
        $waited = time() - $firstJoin;
        $shouldStart = count($queue) >= 2 || (count($queue) >= 1 && $waited >= 10);

        if ($shouldStart) {
            $players = array_slice($queue, 0, min(count($queue), 9));

            // AI로 빈 자리 채우기 (최소 2명 테이블)
            $aiNames = [
                ['name' => '대니얼', 'style' => 'TAG'], ['name' => '소피아', 'style' => 'LAG'],
                ['name' => '재민', 'style' => 'TP'], ['name' => '린다', 'style' => 'LP'],
                ['name' => '마이크', 'style' => 'maniac'], ['name' => '유나', 'style' => 'balanced'],
                ['name' => '빅터', 'style' => 'nit'], ['name' => '하나', 'style' => 'tricky'],
            ];
            shuffle($aiNames);
            $aiIdx = 0;
            $targetCount = max(count($players) + 1, 4); // 최소 4명 테이블
            while (count($players) < $targetCount && $aiIdx < count($aiNames)) {
                $ai = $aiNames[$aiIdx++];
                $players[] = ['id' => -$aiIdx, 'name' => $ai['name'] . ' (AI)', 'chips' => 15000, 'isAI' => true, 'style' => $ai['style']];
            }
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
            // 쇼다운 후 토너먼트면 → 자동 다음 핸드
            if ($state && $state['status'] === 'showdown' && ($state['config']['type'] ?? '') === 'tournament') {
                return $this->handleTournamentShowdown($gameId, $state);
            }
            return response()->json(['success' => true, 'timeout' => false, 'state' => $state ? PokerGameEngine::getPlayerView($state, auth()->id()) : null]);
        }

        $actIdx = $state['actIdx'];
        $seat = $state['seats'][$actIdx] ?? null;

        // AI 턴이면 서버에서 자동 처리
        if ($seat && $seat['id'] < 0) {
            $result = PokerGameEngine::processAITurn($gameId);
            if ($result && !isset($result['error'])) {
                // AI 처리 후 다음도 AI면 연속 처리 (최대 8번)
                for ($i = 0; $i < 8; $i++) {
                    $nextState = PokerGameEngine::getGameState($gameId);
                    if (!$nextState || $nextState['status'] !== 'playing') break;
                    $nextSeat = $nextState['seats'][$nextState['actIdx']] ?? null;
                    if (!$nextSeat || $nextSeat['id'] > 0) break; // 실제 유저 턴
                    usleep(500000); // 0.5초 딜레이
                    PokerGameEngine::processAITurn($gameId);
                }

                $finalState = PokerGameEngine::getGameState($gameId);

                // 쇼다운 체크 → 토너먼트면 자동 다음 핸드
                if ($finalState && $finalState['status'] === 'showdown' && ($finalState['config']['type'] ?? '') === 'tournament') {
                    return $this->handleTournamentShowdown($gameId, $finalState);
                }

                return response()->json([
                    'success' => true,
                    'timeout' => false,
                    'ai_acted' => true,
                    'state' => $finalState ? PokerGameEngine::getPlayerView($finalState, auth()->id()) : null,
                    'remaining' => ($finalState['turnDeadline'] ?? time()) - time(),
                ]);
            }
        }

        // 유저 타임아웃
        if (time() > $state['turnDeadline'] && $seat && $seat['id'] > 0) {
            $toCall = max(0, $state['betLevel'] - $seat['bet']);
            $action = $toCall === 0 ? 'check' : 'fold';
            $result = PokerGameEngine::processAction($gameId, $seat['id'], $action);

            // 쇼다운 체크
            $freshState = PokerGameEngine::getGameState($gameId);
            if ($freshState && $freshState['status'] === 'showdown' && ($freshState['config']['type'] ?? '') === 'tournament') {
                return $this->handleTournamentShowdown($gameId, $freshState);
            }

            return response()->json(['success' => true, 'timeout' => true, 'action' => $action]);
        }

        return response()->json(['success' => true, 'timeout' => false, 'remaining' => max(0, $state['turnDeadline'] - time())]);
    }

    // ── 토너먼트 쇼다운 처리 → 다음 핸드 or 토너먼트 종료 ──
    private function handleTournamentShowdown(string $gameId, array $state): \Illuminate\Http\JsonResponse
    {
        $tournamentId = $state['config']['tournamentId'] ?? null;

        // 3초 대기 (쇼다운 결과 보여주기)
        $showdownAt = Cache::get("poker_showdown_{$gameId}");
        if (!$showdownAt) {
            Cache::put("poker_showdown_{$gameId}", time(), 30);
            return response()->json([
                'success' => true,
                'timeout' => false,
                'state' => PokerGameEngine::getPlayerView($state, auth()->id()),
                'showdown_wait' => 3,
            ]);
        }

        if (time() - $showdownAt < 3) {
            return response()->json([
                'success' => true,
                'timeout' => false,
                'state' => PokerGameEngine::getPlayerView($state, auth()->id()),
                'showdown_wait' => max(0, 3 - (time() - $showdownAt)),
            ]);
        }

        Cache::forget("poker_showdown_{$gameId}");

        // 다음 핸드 딜
        $nextState = PokerGameEngine::nextHand($gameId);
        if (!$nextState) {
            return response()->json(['success' => false, 'message' => '게임 상태 오류']);
        }

        // 탈락자 DB 업데이트
        if ($tournamentId) {
            $this->updateTournamentEliminations($tournamentId, $nextState);
        }

        // 토너먼트 종료?
        if ($nextState['status'] === 'finished') {
            if ($tournamentId) {
                $this->finishTournament($tournamentId, $nextState);
            }
            return response()->json([
                'success' => true,
                'tournament_finished' => true,
                'state' => PokerGameEngine::getPlayerView($nextState, auth()->id()),
                'ranking' => $nextState['finalRanking'] ?? [],
            ]);
        }

        // 브로드캐스트 (새 핸드 시작)
        foreach ($nextState['seats'] as $seat) {
            if (isset($seat['id']) && $seat['id'] > 0 && !$seat['isOut']) {
                broadcast(new PokerAction($gameId, PokerGameEngine::getPlayerView($nextState, $seat['id']), ['type' => 'new_hand']))->toOthers();
            }
        }

        return response()->json([
            'success' => true,
            'new_hand' => true,
            'hand_num' => $nextState['handNum'] ?? 0,
            'blind_level_up' => $nextState['blindLevelUp'] ?? false,
            'state' => PokerGameEngine::getPlayerView($nextState, auth()->id()),
        ]);
    }

    // ── 탈락자 DB 업데이트 ──
    private function updateTournamentEliminations(int $tournamentId, array $state): void
    {
        $alive = array_filter($state['seats'], fn($s) => !$s['isOut']);
        $aliveCount = count($alive);

        foreach ($state['seats'] as $seat) {
            if ($seat['isOut'] && $seat['id'] > 0) {
                $entry = PokerTournamentEntry::where('tournament_id', $tournamentId)
                    ->where('user_id', $seat['id'])
                    ->whereNull('finish_position')
                    ->first();

                if ($entry) {
                    $entry->update([
                        'status' => 'eliminated',
                        'finish_position' => $aliveCount + 1, // 남은 인원 + 1 = 내 순위
                        'chips' => 0,
                        'eliminated_at' => now(),
                    ]);
                }
            }
        }

        // 살아있는 플레이어 칩 업데이트
        foreach ($alive as $seat) {
            if ($seat['id'] > 0) {
                PokerTournamentEntry::where('tournament_id', $tournamentId)
                    ->where('user_id', $seat['id'])
                    ->update(['chips' => $seat['chips']]);
            }
        }
    }

    // ── 토너먼트 종료 + 상금 지급 ──
    private function finishTournament(int $tournamentId, array $state): void
    {
        $tournament = PokerTournament::find($tournamentId);
        if (!$tournament) return;

        $humanEntries = PokerTournamentEntry::where('tournament_id', $tournamentId)->get();
        $playerCount = $humanEntries->count();
        $totalBuyIn = $tournament->buy_in * $playerCount;

        $prizeInfo = PokerGameEngine::calculatePrizes($totalBuyIn, $playerCount, $tournament->bounty_pct ?? 10);

        // 최종 랭킹에서 상금 지급
        $ranking = $state['finalRanking'] ?? [];

        // 우승자 (1명 남은 사람) DB 업데이트
        foreach ($ranking as $r) {
            if ($r['id'] > 0) {
                $entry = PokerTournamentEntry::where('tournament_id', $tournamentId)
                    ->where('user_id', $r['id'])
                    ->first();
                if ($entry) {
                    $prize = $prizeInfo['prizes'][$r['place']] ?? 0;
                    $entry->update([
                        'status' => 'finished',
                        'finish_position' => $r['place'],
                        'prize_won' => $prize,
                        'chips' => $r['chips'],
                    ]);

                    // 칩 지갑에 상금 입금
                    if ($prize > 0) {
                        $wallet = PokerWallet::firstOrCreate(
                            ['user_id' => $r['id']],
                            ['chips_balance' => 0, 'total_deposited' => 0, 'total_withdrawn' => 0]
                        );
                        $wallet->deposit($prize, "토너먼트 상금 ({$r['place']}등): {$tournament->title}");
                    }

                    // 통계 업데이트
                    $stat = PokerStat::firstOrCreate(['user_id' => $r['id']], [
                        'games_played' => 0, 'hands_played' => 0, 'tournaments_won' => 0,
                        'in_the_money' => 0, 'best_place' => 999, 'total_prize_won' => 0,
                        'total_bounties' => 0, 'total_buy_ins' => 0, 'biggest_pot_won' => 0,
                    ]);
                    $stat->increment('games_played');
                    if ($r['place'] === 1) $stat->increment('tournaments_won');
                    if ($prize > 0) $stat->increment('in_the_money');
                    $stat->increment('total_prize_won', $prize);
                    $stat->increment('total_buy_ins', $tournament->buy_in);
                    if ($r['place'] < $stat->best_place) $stat->update(['best_place' => $r['place']]);
                }
            }
        }

        // 탈락자들도 통계 업데이트 (상금 없는 사람)
        $rankedIds = collect($ranking)->pluck('id')->filter(fn($id) => $id > 0)->toArray();
        $eliminatedEntries = $humanEntries->filter(fn($e) => !in_array($e->user_id, $rankedIds));
        foreach ($eliminatedEntries as $entry) {
            $prize = $prizeInfo['prizes'][$entry->finish_position] ?? 0;
            if ($prize > 0) {
                $wallet = PokerWallet::firstOrCreate(
                    ['user_id' => $entry->user_id],
                    ['chips_balance' => 0, 'total_deposited' => 0, 'total_withdrawn' => 0]
                );
                $wallet->deposit($prize, "토너먼트 상금 ({$entry->finish_position}등): {$tournament->title}");
                $entry->update(['prize_won' => $prize]);
            }

            $stat = PokerStat::firstOrCreate(['user_id' => $entry->user_id], [
                'games_played' => 0, 'hands_played' => 0, 'tournaments_won' => 0,
                'in_the_money' => 0, 'best_place' => 999, 'total_prize_won' => 0,
                'total_bounties' => 0, 'total_buy_ins' => 0, 'biggest_pot_won' => 0,
            ]);
            $stat->increment('games_played');
            if ($prize > 0) $stat->increment('in_the_money');
            $stat->increment('total_prize_won', $prize);
            $stat->increment('total_buy_ins', $tournament->buy_in);
            if (($entry->finish_position ?? 999) < $stat->best_place) {
                $stat->update(['best_place' => $entry->finish_position]);
            }
        }

        // 토너먼트 종료
        $tournament->update([
            'status' => 'finished',
            'finished_at' => now(),
            'prize_pool' => $prizeInfo,
        ]);

        broadcast(new PokerTournamentUpdate($tournament->fresh()->loadCount([
            'entries as registered_count',
        ])));
    }

    // ── 토너먼트 게임 상태 조회 (특수: 토너먼트 메타 포함) ──
    public function tournamentGameState($tournamentId)
    {
        $gameId = Cache::get("poker_tournament_game_{$tournamentId}");
        if (!$gameId) {
            return response()->json(['success' => false, 'message' => '게임이 아직 시작되지 않았습니다.'], 404);
        }

        $state = PokerGameEngine::getGameState($gameId);
        if (!$state) {
            return response()->json(['success' => false, 'message' => '게임을 찾을 수 없습니다.'], 404);
        }

        $tournament = PokerTournament::find($tournamentId);
        $alive = array_filter($state['seats'], fn($s) => !$s['isOut']);

        return response()->json([
            'success' => true,
            'data' => [
                'gameId' => $gameId,
                'state' => PokerGameEngine::getPlayerView($state, auth()->id()),
                'tournament' => [
                    'id' => $tournament?->id,
                    'title' => $tournament?->title,
                    'status' => $tournament?->status,
                    'blind_level' => $state['config']['blindLevel'] ?? 0,
                    'hand_num' => $state['handNum'] ?? 1,
                    'players_alive' => count($alive),
                    'players_total' => count($state['seats']),
                ],
            ],
        ]);
    }
}
