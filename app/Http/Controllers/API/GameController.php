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

class GameController extends Controller
{
    // ── 화투 48장 덱 생성 ─────────────────────────────────────────────────────
    private function buildDeck(): array
    {
        $deck = [];
        $id   = 0;
        $months = [
            ['name'=>'솔',  'bg'=>'#15803d', 'cards'=>[['gwang','솔학'],   ['tti','솔홍띠','red'],   ['pi','솔피'],  ['pi','솔피']]],
            ['name'=>'매',  'bg'=>'#ec4899', 'cards'=>[['yeol','매조'],    ['tti','매홍띠','red'],   ['pi','매피'],  ['pi','매피']]],
            ['name'=>'벚',  'bg'=>'#f9a8d4', 'cards'=>[['gwang','벚막'],   ['tti','벚홍띠','red'],   ['pi','벚피'],  ['pi','벚피']]],
            ['name'=>'등',  'bg'=>'#7c3aed', 'cards'=>[['yeol','등두견'],  ['tti','등청띠','blue'],  ['pi','등피'],  ['pi','등피']]],
            ['name'=>'창',  'bg'=>'#a78bfa', 'cards'=>[['yeol','창다리'],  ['tti','창청띠','blue'],  ['pi','창피'],  ['pi','창피']]],
            ['name'=>'모',  'bg'=>'#dc2626', 'cards'=>[['yeol','모나비'],  ['tti','모청띠','blue'],  ['pi','모피'],  ['pi','모피']]],
            ['name'=>'홍',  'bg'=>'#f87171', 'cards'=>[['yeol','홍멧돼지'],['tti','홍띠','plain'],   ['pi','홍피'],  ['pi','홍피']]],
            ['name'=>'공',  'bg'=>'#ca8a04', 'cards'=>[['gwang','공월'],   ['yeol','공기러기'],       ['pi','공피'],  ['pi','공피']]],
            ['name'=>'국',  'bg'=>'#eab308', 'cards'=>[['yeol','국진'],    ['tti','국청띠','blue'],  ['pi','국피'],  ['pi','국피']]],
            ['name'=>'단',  'bg'=>'#ea580c', 'cards'=>[['yeol','단사슴'],  ['tti','단청띠','blue'],  ['pi','단피'],  ['pi','단피']]],
            ['name'=>'비',  'bg'=>'#475569', 'cards'=>[['gwang','비소야'], ['yeol','비제비'],         ['tti','비홍띠','plain'],['ssang','비쌍피']]],
            ['name'=>'동',  'bg'=>'#94a3b8', 'cards'=>[['gwang','동봉황'],['ssang','동쌍피'],        ['pi','동피'],  ['pi','동피']]],
        ];

        foreach ($months as $m => $month) {
            foreach ($month['cards'] as $c) {
                $card = ['id' => ++$id, 'month' => $m + 1, 'month_name' => $month['name'],
                         'bg' => $month['bg'], 'type' => $c[0], 'name' => $c[1], 'is_ssang' => ($c[0] === 'ssang')];
                if (isset($c[2])) $card['tti_type'] = $c[2];
                $deck[] = $card;
            }
        }
        return $deck;
    }

    // ── 점수 계산 ─────────────────────────────────────────────────────────────
    private function calcScore(array $captured): int
    {
        $gwang = count($captured['gwang']);
        $yeol  = count($captured['yeol']);
        $tti   = count($captured['tti']);
        $pi    = 0;
        foreach ($captured['pi'] as $c) {
            $pi += ($c['is_ssang'] ?? false) ? 2 : 1;
        }

        $score = 0;
        if ($gwang >= 5)     $score += 15;
        elseif ($gwang >= 4) $score += 4;
        elseif ($gwang >= 3) $score += 3;

        if ($yeol >= 5) $score += 1 + ($yeol - 5);

        if ($tti >= 5) $score += 1 + ($tti - 5);
        // 홍단
        $redM = array_unique(array_column(array_filter($captured['tti'], fn($c) => ($c['tti_type'] ?? '') === 'red'), 'month'));
        if (in_array(1,$redM) && in_array(2,$redM) && in_array(3,$redM)) $score += 3;
        // 청단
        $blueM = array_unique(array_column(array_filter($captured['tti'], fn($c) => ($c['tti_type'] ?? '') === 'blue'), 'month'));
        if (in_array(4,$blueM) && in_array(5,$blueM) && in_array(6,$blueM)) $score += 3;
        // 초단
        if (in_array(7,$blueM) && in_array(9,$blueM) && in_array(10,$blueM)) $score += 3;

        if ($pi >= 10) $score += 1 + ($pi - 10);

        return $score;
    }

    private function findMatches(array $card, array $table): array
    {
        return array_values(array_filter($table, fn($t) => $t['month'] === $card['month']));
    }

    // ── API: 방 목록 ──────────────────────────────────────────────────────────
    public function index()
    {
        return response()->json(
            GameRoom::where('status','waiting')
                ->withCount('players')
                ->with('creator:id,username,name')
                ->latest()->limit(20)->get()
        );
    }

    // ── API: 방 생성 ──────────────────────────────────────────────────────────
    public function create(Request $request)
    {
        $request->validate(['bet_points'=>'integer|min:0|max:10000','max_players'=>'integer|in:2,3']);
        $user = $request->user();

        $room = GameRoom::create([
            'code'        => strtoupper(Str::random(6)),
            'type'        => 'go_stop',
            'status'      => 'waiting',
            'min_players' => 2,
            'max_players' => $request->input('max_players', 3),
            'bet_points'  => $request->input('bet_points', 100),
            'created_by'  => $user->id,
        ]);
        GamePlayer::create(['game_room_id'=>$room->id,'user_id'=>$user->id,'seat'=>1]);

        return response()->json($room->load('players.user:id,username,name'));
    }

    // ── API: 방 입장 ──────────────────────────────────────────────────────────
    public function join(Request $request, $id)
    {
        $room = GameRoom::findOrFail($id);
        $user = $request->user();

        if ($room->status !== 'waiting')
            return response()->json(['message'=>'이미 시작된 방입니다.'], 422);
        if ($room->players()->count() >= $room->max_players)
            return response()->json(['message'=>'방이 가득 찼습니다.'], 422);

        if (!$room->players()->where('user_id',$user->id)->exists()) {
            GamePlayer::create([
                'game_room_id' => $room->id,
                'user_id'      => $user->id,
                'seat'         => $room->players()->count() + 1,
            ]);
        }
        broadcast(new GameStateChanged($room->id, 'player_joined', ['user_id'=>$user->id,'username'=>$user->username]));
        return response()->json($room->load('players.user:id,username,name'));
    }

    // ── API: 준비 ─────────────────────────────────────────────────────────────
    public function ready(Request $request, $id)
    {
        $room   = GameRoom::findOrFail($id);
        $user   = $request->user();
        $player = $room->players()->where('user_id',$user->id)->firstOrFail();
        $player->update(['is_ready'=>true]);

        $players   = $room->players()->get();
        $allReady  = $players->every(fn($p) => $p->is_ready);
        $enough    = $players->count() >= $room->min_players;

        if ($allReady && $enough) {
            $this->startGame($room, $players->pluck('user_id')->toArray());
        } else {
            broadcast(new GameStateChanged($room->id,'player_ready',['user_id'=>$user->id]));
        }
        return response()->json(['started' => $allReady && $enough]);
    }

    private function startGame(GameRoom $room, array $uids): void
    {
        $deck = $this->buildDeck();
        shuffle($deck);

        $hands = $captured = [];
        foreach ($uids as $uid) {
            $hands[$uid]    = array_splice($deck, 0, 7);
            $captured[$uid] = ['gwang'=>[],'yeol'=>[],'tti'=>[],'pi'=>[]];
        }
        $table = array_splice($deck, 0, 3);

        $state = [
            'phase'          => 'playing',
            'deck'           => $deck,
            'table'          => $table,
            'hands'          => $hands,
            'captured'       => $captured,
            'current_player' => $uids[0],
            'player_order'   => $uids,
            'round'          => 1,
            'scores'         => array_fill_keys($uids, 0),
            'go_count'       => array_fill_keys($uids, 0),
            'last_drawn'     => null,
            'winner'         => null,
        ];
        $room->update(['status'=>'playing','state'=>$state]);
        broadcast(new GameStateChanged($room->id,'game_started',$this->publicState($state)));
    }

    // ── API: 상태 조회 ────────────────────────────────────────────────────────
    public function state(Request $request, $id)
    {
        $room  = GameRoom::findOrFail($id);
        $user  = $request->user();
        $state = $room->state;

        return response()->json([
            'room'    => $room->only(['id','code','type','status','bet_points','max_players','min_players']),
            'state'   => $state ? $this->playerState($state, $user->id) : null,
            'players' => $room->players()->with('user:id,username,name')->get(),
        ]);
    }

    // ── API: 카드 내기 ────────────────────────────────────────────────────────
    public function play(Request $request, $id)
    {
        $request->validate(['card_id'=>'required|integer']);
        $room  = GameRoom::findOrFail($id);
        $user  = $request->user();
        $state = $room->state;

        if (!$state || $state['phase'] !== 'playing')
            return response()->json(['message'=>'지금은 카드를 낼 수 없습니다.'], 422);
        if ((int)$state['current_player'] !== $user->id)
            return response()->json(['message'=>'당신의 차례가 아닙니다.'], 422);

        $cardId  = (int)$request->input('card_id');
        $hand    = $state['hands'][$user->id] ?? [];
        $cardIdx = array_search($cardId, array_column($hand,'id'));

        if ($cardIdx === false)
            return response()->json(['message'=>'가지고 있지 않은 카드입니다.'], 422);

        $card  = $hand[$cardIdx];
        $state = $this->processPlay($state, $user->id, $card, $cardIdx);
        $room->update(['state'=>$state]);

        if ($state['phase'] === 'finished') {
            $room->update(['status'=>'finished']);
            $this->distributePoints($room, $state);
        }
        broadcast(new GameStateChanged($room->id,'card_played',$this->publicState($state)));
        return response()->json($this->playerState($state, $user->id));
    }

    private function processPlay(array $state, int $uid, array $card, int $idx): array
    {
        array_splice($state['hands'][$uid], $idx, 1);
        $matches = $this->findMatches($card, $state['table']);

        if (count($matches) === 0) {
            $state['table'][] = $card;
        } elseif (count($matches) === 1) {
            $state = $this->captureCards($state, $uid, [$card, $matches[0]]);
            $state['table'] = array_values(array_filter($state['table'], fn($t)=>$t['id']!==$matches[0]['id']));
        } else {
            $state = $this->captureCards($state, $uid, array_merge([$card],$matches));
            foreach ($matches as $m)
                $state['table'] = array_values(array_filter($state['table'], fn($t)=>$t['id']!==$m['id']));
        }

        // 덱에서 카드 뽑기
        if (!empty($state['deck'])) {
            $drawn = array_shift($state['deck']);
            $state['last_drawn'] = $drawn;
            $dm = $this->findMatches($drawn, $state['table']);

            if (count($dm) === 0) {
                $state['table'][] = $drawn;
            } elseif (count($dm) === 1) {
                $state = $this->captureCards($state, $uid, [$drawn, $dm[0]]);
                $state['table'] = array_values(array_filter($state['table'], fn($t)=>$t['id']!==$dm[0]['id']));
            } else {
                $state = $this->captureCards($state, $uid, array_merge([$drawn],$dm));
                foreach ($dm as $m)
                    $state['table'] = array_values(array_filter($state['table'], fn($t)=>$t['id']!==$m['id']));
            }
        }

        $score = $this->calcScore($state['captured'][$uid]);
        $state['scores'][$uid] = $score;

        if ($score >= 3) {
            $state['phase'] = 'go_decision';
        } else {
            $state = $this->nextTurn($state);
        }

        if (empty($state['deck']) && $state['phase'] === 'playing') {
            $state = $this->finishGame($state);
        }

        return $state;
    }

    private function captureCards(array $state, int $uid, array $cards): array
    {
        foreach ($cards as $c) {
            $type = ($c['type'] === 'ssang') ? 'pi' : $c['type'];
            $state['captured'][$uid][$type][] = $c;
        }
        return $state;
    }

    private function nextTurn(array $state): array
    {
        $order = $state['player_order'];
        $idx   = array_search($state['current_player'], $order);
        $state['current_player'] = $order[($idx + 1) % count($order)];
        $state['round']++;
        return $state;
    }

    // ── API: 고 ───────────────────────────────────────────────────────────────
    public function go(Request $request, $id)
    {
        $room  = GameRoom::findOrFail($id);
        $user  = $request->user();
        $state = $room->state;

        if ($state['phase'] !== 'go_decision' || (int)$state['current_player'] !== $user->id)
            return response()->json(['message'=>'고를 선언할 수 없습니다.'], 422);

        $state['go_count'][$user->id]++;
        $state = $this->nextTurn($state);
        $state['phase'] = 'playing';

        $room->update(['state'=>$state]);
        broadcast(new GameStateChanged($room->id,'player_go',['user_id'=>$user->id,'go_count'=>$state['go_count']]));
        return response()->json($this->playerState($state, $user->id));
    }

    // ── API: 스톱 ─────────────────────────────────────────────────────────────
    public function stop(Request $request, $id)
    {
        $room  = GameRoom::findOrFail($id);
        $user  = $request->user();
        $state = $room->state;

        if ($state['phase'] !== 'go_decision' || (int)$state['current_player'] !== $user->id)
            return response()->json(['message'=>'스톱을 선언할 수 없습니다.'], 422);

        $state['winner'] = $user->id;
        $state = $this->finishGame($state);
        $room->update(['status'=>'finished','state'=>$state]);
        $this->distributePoints($room, $state);
        broadcast(new GameStateChanged($room->id,'game_over',$this->publicState($state)));
        return response()->json($this->playerState($state, $user->id));
    }

    private function finishGame(array $state): array
    {
        $state['phase'] = 'finished';
        if (!$state['winner']) {
            $max = -1; $winner = null;
            foreach ($state['scores'] as $uid => $s) {
                if ($s > $max) { $max = $s; $winner = $uid; }
            }
            $state['winner'] = $winner;
        }
        return $state;
    }

    private function distributePoints(GameRoom $room, array $state): void
    {
        $winner  = $state['winner'];
        if (!$winner) return;
        $bet     = $room->bet_points;
        $players = $room->players()->get();
        $goBonus = $state['go_count'][$winner] ?? 0;

        foreach ($players as $p) {
            if ((int)$p->user_id === (int)$winner) {
                $earned = $bet * ($players->count() - 1) * (1 + $goBonus * 0.1);
                $earned = (int)$earned;
                $p->update(['points_result'=>$earned]);
                User::where('id',$winner)->increment('points_total',$earned);
                DB::table('point_logs')->insert([
                    'user_id'=>$winner,'type'=>'earn','action'=>'game_win',
                    'amount'=>$earned,'balance_after'=>User::find($winner)?->points_total ?? $earned,
                    'ref_id'=>$room->id,'memo'=>"고스톱 승리 ({$room->code})",'created_at'=>now(),'updated_at'=>now(),
                ]);
            } else {
                $p->update(['points_result'=>-$bet]);
                User::where('id',$p->user_id)->decrement('points_total',$bet);
            }
        }
    }

    // ── State helpers ─────────────────────────────────────────────────────────
    private function playerState(array $state, int $uid): array
    {
        $s = $state;
        foreach ($state['hands'] as $huid => $hand) {
            if ((int)$huid !== $uid) $s['hands'][$huid] = count($hand);
        }
        return $s;
    }

    private function publicState(array $state): array
    {
        $s = $state;
        foreach ($state['hands'] as $huid => $hand) {
            $s['hands'][$huid] = count($hand);
        }
        return $s;
    }

    // ── API: 리더보드 ─────────────────────────────────────────────────────────
    public function leaderboard(Request $request)
    {
        $type = $request->input('type','points');

        if ($type === 'posts') {
            $data = DB::table('users')
                ->join('posts','users.id','=','posts.user_id')
                ->where('users.status','active')
                ->select('users.id','users.username','users.name','users.level',
                    DB::raw('COUNT(posts.id) as value'))
                ->groupBy('users.id','users.username','users.name','users.level')
                ->orderByDesc('value')->limit(20)->get();
        } elseif ($type === 'quiz') {
            $data = DB::table('users')
                ->join('quiz_attempts','users.id','=','quiz_attempts.user_id')
                ->where('quiz_attempts.is_correct',true)
                ->where('users.status','active')
                ->select('users.id','users.username','users.name','users.level',
                    DB::raw('SUM(quiz_attempts.points_earned) as value'),
                    DB::raw('COUNT(quiz_attempts.id) as correct_count'))
                ->groupBy('users.id','users.username','users.name','users.level')
                ->orderByDesc('value')->limit(20)->get();
        } else {
            $data = DB::table('users')
                ->where('status','active')
                ->select('id','username','name','level','points_total as value')
                ->orderByDesc('points_total')->limit(20)->get();
        }

        return response()->json(['type'=>$type,'data'=>$data]);
    }

    // ── API: 포인트샵 아이템 ──────────────────────────────────────────────────
    public function shopItems()
    {
        $items = [
            ['id'=>1,'name'=>'VIP 칭호 (30일)','description'=>'프로필에 VIP 배지가 붙습니다.','cost'=>5000,'type'=>'title','icon'=>'👑'],
            ['id'=>2,'name'=>'황금 닉네임 (30일)','description'=>'닉네임이 황금색으로 표시됩니다.','cost'=>3000,'type'=>'style','icon'=>'✨'],
            ['id'=>3,'name'=>'커뮤니티 게시글 상단 고정 (7일)','description'=>'내 최신 게시글을 7일간 상단에 고정합니다.','cost'=>2000,'type'=>'feature','icon'=>'📌'],
            ['id'=>4,'name'=>'구인구직 상단 고정 (7일)','description'=>'구직/채용 공고를 7일간 상단에 노출합니다.','cost'=>2000,'type'=>'feature','icon'=>'💼'],
            ['id'=>5,'name'=>'매칭 프로필 부스트 (24시간)','description'=>'매칭 탐색에서 내 프로필이 우선 노출됩니다.','cost'=>1500,'type'=>'match','icon'=>'💘'],
            ['id'=>6,'name'=>'매칭 무제한 좋아요 (30일)','description'=>'매칭에서 하루 횟수 제한 없이 좋아요를 보낼 수 있습니다.','cost'=>8000,'type'=>'match','icon'=>'💝'],
            ['id'=>7,'name'=>'업소록 스폰서 배지 (30일)','description'=>'내 업소에 스폰서 배지가 표시됩니다.','cost'=>10000,'type'=>'business','icon'=>'🏆'],
            ['id'=>8,'name'=>'Amazon $5 기프트카드','description'=>'포인트를 Amazon 기프트카드로 교환합니다.','cost'=>6000,'type'=>'giftcard','icon'=>'🎁'],
            ['id'=>9,'name'=>'Starbucks $5 기프트카드','description'=>'포인트를 Starbucks 기프트카드로 교환합니다.','cost'=>6000,'type'=>'giftcard','icon'=>'☕'],
            ['id'=>10,'name'=>'추가 채팅방 생성권','description'=>'나만의 테마 채팅방을 1개 생성할 수 있습니다.','cost'=>3000,'type'=>'feature','icon'=>'💬'],
        ];
        return response()->json($items);
    }

    // ── API: 아이템 구매 ──────────────────────────────────────────────────────
    public function redeem(Request $request)
    {
        $request->validate(['item_id'=>'required|integer|between:1,10']);
        $user = $request->user();

        $costs = [1=>5000,2=>3000,3=>2000,4=>2000,5=>1500,6=>8000,7=>10000,8=>6000,9=>6000,10=>3000];
        $names = [1=>'VIP 칭호',2=>'황금 닉네임',3=>'게시글 고정',4=>'구인구직 고정',5=>'매칭 부스트',
                  6=>'매칭 무제한',7=>'스폰서 배지',8=>'Amazon 기프트카드',9=>'Starbucks 기프트카드',10=>'채팅방 생성권'];

        $itemId = (int)$request->input('item_id');
        $cost   = $costs[$itemId];

        if ($user->points_total < $cost) {
            return response()->json(['message'=>"포인트가 부족합니다. 필요: {$cost}P, 보유: {$user->points_total}P"], 422);
        }

        $user->decrement('points_total', $cost);
        $bal = $user->fresh()->points_total;

        DB::table('point_logs')->insert([
            'user_id'       => $user->id,
            'type'          => 'spend',
            'action'        => 'shop_redeem',
            'amount'        => $cost,
            'balance_after' => $bal,
            'ref_id'        => $itemId,
            'memo'          => "포인트샵: {$names[$itemId]}",
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json([
            'message'        => "{$names[$itemId]} 구매 완료!",
            'points_total'   => $bal,
            'item_purchased' => $names[$itemId],
        ]);
    }
}
