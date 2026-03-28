<?php
namespace App\Http\Controllers\API;

use App\Events\GameStateChanged;
use App\Http\Controllers\Controller;
use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PokerController extends Controller
{
    // ── 52장 덱 생성 ─────────────────────────────────────────────────────────
    private function buildDeck(): array
    {
        $deck = [];
        $id   = 0;
        $suitColors = ['♠' => '#1e293b', '♥' => '#dc2626', '♦' => '#dc2626', '♣' => '#1e293b'];
        $ranks = ['2','3','4','5','6','7','8','9','10','J','Q','K','A'];
        $vals  = array_combine($ranks, [2,3,4,5,6,7,8,9,10,11,12,13,14]);

        foreach ($suitColors as $suit => $color) {
            foreach ($ranks as $rank) {
                $deck[] = [
                    'id'    => ++$id,
                    'suit'  => $suit,
                    'rank'  => $rank,
                    'rv'    => $vals[$rank], // rank value
                    'color' => $color,
                ];
            }
        }
        shuffle($deck);
        return $deck;
    }

    // ── 핸드 평가 (5장) ───────────────────────────────────────────────────────
    private function rankFive(array $cards): array
    {
        $rv     = array_column($cards, 'rv');
        $suits  = array_column($cards, 'suit');
        sort($rv);
        $flush  = count(array_unique($suits)) === 1;
        $counts = array_count_values($rv);
        arsort($counts);
        $freqs  = array_values($counts);
        $grpV   = array_keys($counts);

        // Straight check (including A-low)
        $straight = false; $hiCard = $rv[4];
        if (count($counts) === 5) {
            if ($rv[4] - $rv[0] === 4) { $straight = true; }
            elseif ($rv === [2,3,4,5,14]) { $straight = true; $hiCard = 5; }
        }

        if ($flush && $straight) {
            $val  = $hiCard === 14 && $rv[3] === 13 ? 9000000 + $hiCard : 8000000 + $hiCard;
            $rank = $hiCard === 14 && $rv[3] === 13 ? 'Royal Flush' : 'Straight Flush';
            return ['rank' => $rank, 'value' => $val];
        }
        if ($freqs[0] === 4) return ['rank' => 'Four of a Kind',  'value' => 7000000 + $grpV[0] * 100 + ($grpV[1] ?? 0)];
        if ($freqs[0] === 3 && ($freqs[1] ?? 0) === 2) return ['rank' => 'Full House', 'value' => 6000000 + $grpV[0] * 100 + ($grpV[1] ?? 0)];
        if ($flush)    return ['rank' => 'Flush',         'value' => 5000000 + $rv[4]*10000 + $rv[3]*1000 + $rv[2]*100 + $rv[1]*10 + $rv[0]];
        if ($straight) return ['rank' => 'Straight',      'value' => 4000000 + $hiCard];
        if ($freqs[0] === 3) return ['rank' => 'Three of a Kind', 'value' => 3000000 + $grpV[0] * 10000];
        if ($freqs[0] === 2 && ($freqs[1] ?? 0) === 2) return ['rank' => 'Two Pair',   'value' => 2000000 + max($grpV[0],$grpV[1])*100 + min($grpV[0],$grpV[1])];
        if ($freqs[0] === 2) return ['rank' => 'One Pair',   'value' => 1000000 + $grpV[0] * 10000 + $rv[4]*10 + $rv[3]];
        return ['rank' => 'High Card', 'value' => $rv[4]*10000 + $rv[3]*1000 + $rv[2]*100 + $rv[1]*10 + $rv[0]];
    }

    private function combos(array $arr, int $n): array
    {
        if ($n === 0) return [[]];
        if (empty($arr)) return [];
        $first  = array_shift($arr);
        $withF  = array_map(fn($c) => array_merge([$first], $c), $this->combos($arr, $n - 1));
        return array_merge($withF, $this->combos($arr, $n));
    }

    private function bestHand(array $hole, array $community): array
    {
        $all  = array_merge($hole, $community);
        $best = null;
        foreach ($this->combos($all, 5) as $five) {
            $r = $this->rankFive($five);
            if (!$best || $r['value'] > $best['value']) $best = $r;
        }
        return $best ?? ['rank' => 'High Card', 'value' => 0];
    }

    // ── API: 방 목록 ──────────────────────────────────────────────────────────
    public function index()
    {
        return response()->json(
            GameRoom::where('type', 'poker')->where('status', 'waiting')
                ->withCount('players')->with('creator:id,username,name')
                ->latest()->limit(20)->get()
        );
    }

    // ── API: 방 생성 ──────────────────────────────────────────────────────────
    public function create(Request $request)
    {
        $request->validate(['buy_in' => 'integer|min:100|max:10000', 'max_players' => 'integer|between:2,6']);
        $user = $request->user();
        $buyIn = $request->input('buy_in', 500);

        if ($user->points_total < $buyIn)
            return response()->json(['message' => "포인트 부족. 필요: {$buyIn}P"], 422);

        $room = GameRoom::create([
            'code'        => strtoupper(Str::random(6)),
            'type'        => 'poker',
            'status'      => 'waiting',
            'min_players' => 2,
            'max_players' => $request->input('max_players', 4),
            'bet_points'  => $buyIn,
            'created_by'  => $user->id,
        ]);
        GamePlayer::create(['game_room_id' => $room->id, 'user_id' => $user->id, 'seat' => 1]);

        return response()->json($room->load('players.user:id,username,name'));
    }

    // ── API: 입장 ─────────────────────────────────────────────────────────────
    public function join(Request $request, $id)
    {
        $room = GameRoom::findOrFail($id);
        $user = $request->user();
        if ($room->status !== 'waiting') return response()->json(['message' => '이미 시작됨'], 422);
        if ($room->players()->count() >= $room->max_players) return response()->json(['message' => '방 꽉참'], 422);
        if ($user->points_total < $room->bet_points) return response()->json(['message' => '포인트 부족'], 422);

        if (!$room->players()->where('user_id', $user->id)->exists()) {
            GamePlayer::create(['game_room_id' => $room->id, 'user_id' => $user->id, 'seat' => $room->players()->count() + 1]);
        }
        broadcast(new GameStateChanged($room->id, 'player_joined', ['user_id' => $user->id]));
        return response()->json($room->load('players.user:id,username,name'));
    }

    // ── API: 준비 → 게임 시작 ─────────────────────────────────────────────────
    public function ready(Request $request, $id)
    {
        $room   = GameRoom::findOrFail($id);
        $user   = $request->user();
        $player = $room->players()->where('user_id', $user->id)->firstOrFail();
        $player->update(['is_ready' => true]);

        $players  = $room->players()->get();
        $allReady = $players->every(fn($p) => $p->is_ready);
        $enough   = $players->count() >= $room->min_players;

        if ($allReady && $enough) {
            $this->startGame($room, $players->pluck('user_id')->toArray());
        } else {
            broadcast(new GameStateChanged($room->id, 'player_ready', ['user_id' => $user->id]));
        }
        return response()->json(['started' => $allReady && $enough]);
    }

    private function startGame(GameRoom $room, array $uids): void
    {
        $buyIn  = $room->bet_points;
        $sb     = max(1, (int)($buyIn / 20));
        $bb     = $sb * 2;
        $deck   = $this->buildDeck();

        $chips  = array_fill_keys($uids, $buyIn);
        $hands  = [];
        foreach ($uids as $uid) {
            $hands[$uid] = [array_shift($deck), array_shift($deck)];
        }

        // 블라인드 처리
        $bets       = array_fill_keys($uids, 0);
        $sbUid      = $uids[0];
        $bbUid      = $uids[1 % count($uids)];
        $chips[$sbUid]  -= $sb; $bets[$sbUid] = $sb;
        $chips[$bbUid]  -= $bb; $bets[$bbUid] = $bb;
        $startIdx       = 2 % count($uids);

        $state = [
            'phase'          => 'preflop',
            'deck'           => $deck,
            'community'      => [],
            'hands'          => $hands,
            'pot'            => $sb + $bb,
            'chips'          => $chips,
            'bets'           => $bets,
            'current_bet'    => $bb,
            'folded'         => [],
            'all_in'         => [],
            'player_order'   => $uids,
            'current_player' => $uids[$startIdx],
            'dealer_idx'     => 0,
            'small_blind'    => $sb,
            'big_blind'      => $bb,
            'round_actions'  => 0,
            'last_action'    => null,
            'winner'         => null,
            'winner_hand'    => null,
        ];

        $room->update(['status' => 'playing', 'state' => $state]);
        broadcast(new GameStateChanged($room->id, 'game_started', $this->publicState($state)));
    }

    // ── API: 상태 ─────────────────────────────────────────────────────────────
    public function state(Request $request, $id)
    {
        $room = GameRoom::findOrFail($id);
        $user = $request->user();
        return response()->json([
            'room'    => $room->only(['id','code','type','status','bet_points','max_players','min_players']),
            'state'   => $room->state ? $this->playerState($room->state, $user->id) : null,
            'players' => $room->players()->with('user:id,username,name')->get(),
        ]);
    }

    // ── API: 액션 (fold/check/call/raise) ───────────────────────────────────
    public function action(Request $request, $id)
    {
        $request->validate(['action' => 'required|in:fold,check,call,raise,allin', 'amount' => 'integer|min:1']);
        $room   = GameRoom::findOrFail($id);
        $user   = $request->user();
        $state  = $room->state;

        if (!$state || $state['phase'] === 'waiting' || $state['phase'] === 'finished')
            return response()->json(['message' => '게임 중이 아닙니다.'], 422);
        if ((int)$state['current_player'] !== $user->id)
            return response()->json(['message' => '당신의 차례가 아닙니다.'], 422);
        if (in_array($user->id, $state['folded']))
            return response()->json(['message' => '이미 폴드했습니다.'], 422);

        $act = $request->input('action');
        $uid = $user->id;

        switch ($act) {
            case 'fold':
                $state['folded'][] = $uid;
                $state['last_action'] = ['uid' => $uid, 'action' => 'fold'];
                break;

            case 'check':
                if ($state['bets'][$uid] < $state['current_bet'])
                    return response()->json(['message' => '체크할 수 없습니다. 콜 또는 레이즈하세요.'], 422);
                $state['last_action'] = ['uid' => $uid, 'action' => 'check'];
                break;

            case 'call':
                $toCall = $state['current_bet'] - $state['bets'][$uid];
                $toCall = min($toCall, $state['chips'][$uid]);
                $state['chips'][$uid] -= $toCall;
                $state['bets'][$uid]  += $toCall;
                $state['pot']         += $toCall;
                $state['last_action'] = ['uid' => $uid, 'action' => 'call', 'amount' => $toCall];
                if ($state['chips'][$uid] === 0) $state['all_in'][] = $uid;
                break;

            case 'raise':
                $amount = (int)$request->input('amount', $state['current_bet'] * 2);
                $amount = max($amount, $state['current_bet'] + $state['big_blind']);
                $toPut  = $amount - $state['bets'][$uid];
                $toPut  = min($toPut, $state['chips'][$uid]);
                $state['chips'][$uid]    -= $toPut;
                $state['bets'][$uid]     += $toPut;
                $state['pot']            += $toPut;
                $state['current_bet']     = $state['bets'][$uid];
                $state['round_actions']   = 0; // reset so others can act
                $state['last_action'] = ['uid' => $uid, 'action' => 'raise', 'amount' => $state['bets'][$uid]];
                if ($state['chips'][$uid] === 0) $state['all_in'][] = $uid;
                break;

            case 'allin':
                $chips  = $state['chips'][$uid];
                $state['bets'][$uid]  += $chips;
                $state['pot']         += $chips;
                $state['chips'][$uid]  = 0;
                if ($state['bets'][$uid] > $state['current_bet'])
                    $state['current_bet'] = $state['bets'][$uid];
                $state['all_in'][] = $uid;
                $state['last_action'] = ['uid' => $uid, 'action' => 'allin', 'amount' => $chips];
                break;
        }

        $state['round_actions']++;
        $state = $this->nextPokerTurn($state);
        $room->update(['state' => $state]);

        if ($state['phase'] === 'finished') {
            $room->update(['status' => 'finished']);
            $this->distributePokerPot($room, $state);
        }

        broadcast(new GameStateChanged($room->id, 'action_taken', $this->publicState($state)));
        return response()->json($this->playerState($state, $user->id));
    }

    private function nextPokerTurn(array $state): array
    {
        $active = array_values(array_filter(
            $state['player_order'],
            fn($uid) => !in_array($uid, $state['folded']) && !in_array($uid, $state['all_in'])
        ));

        // 한 명만 남으면 그 사람 승리
        $nonFolded = array_values(array_filter($state['player_order'], fn($uid) => !in_array($uid, $state['folded'])));
        if (count($nonFolded) === 1) {
            $state['winner'] = $nonFolded[0];
            return $this->finishPoker($state);
        }

        // 모두 all-in or 베팅 라운드 완료 여부 체크
        $allCalled = true;
        foreach ($nonFolded as $uid) {
            if (!in_array($uid, $state['all_in']) && $state['bets'][$uid] < $state['current_bet']) {
                $allCalled = false;
                break;
            }
        }
        $activeCnt = count($active);

        if ($allCalled && ($state['round_actions'] >= $activeCnt || $activeCnt === 0)) {
            return $this->advancePhase($state);
        }

        // 다음 플레이어
        $order  = $state['player_order'];
        $curIdx = array_search($state['current_player'], $order);
        $next   = null;
        for ($i = 1; $i <= count($order); $i++) {
            $candidate = $order[($curIdx + $i) % count($order)];
            if (!in_array($candidate, $state['folded']) && !in_array($candidate, $state['all_in'])) {
                $next = $candidate;
                break;
            }
        }
        $state['current_player'] = $next ?? $state['current_player'];
        return $state;
    }

    private function advancePhase(array $state): array
    {
        // 베팅 초기화
        $state['bets']         = array_fill_keys($state['player_order'], 0);
        $state['current_bet']  = 0;
        $state['round_actions']= 0;

        // 딜러 다음 활성 플레이어부터 시작
        $nonFolded  = array_values(array_filter($state['player_order'], fn($u) => !in_array($u, $state['folded'])));
        $firstActor = $nonFolded[0] ?? $state['player_order'][0];
        $state['current_player'] = $firstActor;

        switch ($state['phase']) {
            case 'preflop':
                $state['community'][] = array_shift($state['deck']);
                $state['community'][] = array_shift($state['deck']);
                $state['community'][] = array_shift($state['deck']);
                $state['phase'] = 'flop';
                break;
            case 'flop':
                $state['community'][] = array_shift($state['deck']);
                $state['phase'] = 'turn';
                break;
            case 'turn':
                $state['community'][] = array_shift($state['deck']);
                $state['phase'] = 'river';
                break;
            case 'river':
                return $this->showdown($state);
        }
        return $state;
    }

    private function showdown(array $state): array
    {
        $nonFolded = array_filter($state['player_order'], fn($u) => !in_array($u, $state['folded']));
        $best = null; $winner = null; $winnerHand = null;

        foreach ($nonFolded as $uid) {
            $hole = $state['hands'][$uid];
            $h    = $this->bestHand($hole, $state['community']);
            if (!$best || $h['value'] > $best) {
                $best = $h['value'];
                $winner = $uid;
                $winnerHand = $h['rank'];
            }
        }
        $state['winner']      = $winner;
        $state['winner_hand'] = $winnerHand;
        return $this->finishPoker($state);
    }

    private function finishPoker(array $state): array
    {
        $state['phase'] = 'finished';
        return $state;
    }

    private function distributePokerPot(GameRoom $room, array $state): void
    {
        $winner = $state['winner'];
        if (!$winner) return;

        $pot    = $state['pot'];
        $players = $room->players()->get();

        $room->players()->where('user_id', $winner)->update(['points_result' => $pot]);
        User::where('id', $winner)->increment('points_total', $pot);

        foreach ($players as $p) {
            if ((int)$p->user_id !== (int)$winner) {
                $lost = $room->bet_points - ($state['chips'][$p->user_id] ?? 0);
                $p->update(['points_result' => -$lost]);
                User::where('id', $p->user_id)->decrement('points_total', $lost);
            }
        }

        DB::table('point_logs')->insert([
            'user_id'      => $winner,
            'type'         => 'earn',
            'action'       => 'game_win',
            'amount'       => $pot,
            'balance_after'=> User::find($winner)?->points_total ?? $pot,
            'ref_id'       => $room->id,
            'memo'         => "포커 승리 ({$room->code}) - {$state['winner_hand']}",
            'created_at'   => now(), 'updated_at' => now(),
        ]);
    }

    private function playerState(array $state, int $uid): array
    {
        $s = $state;
        foreach ($state['hands'] as $huid => $hand) {
            if ((int)$huid !== $uid && $state['phase'] !== 'finished') {
                $s['hands'][$huid] = ['hidden' => true, 'count' => count($hand)];
            }
        }
        return $s;
    }

    private function publicState(array $state): array
    {
        $s = $state;
        foreach ($state['hands'] as $huid => $hand) {
            if ($state['phase'] !== 'finished') {
                $s['hands'][$huid] = ['hidden' => true, 'count' => count($hand)];
            }
        }
        return $s;
    }
}
