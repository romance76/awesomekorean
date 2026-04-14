<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * PokerGameEngine — 서버사이드 텍사스 홀덤 엔진
 *
 * 모든 게임 상태를 서버에서 관리 (치팅 방지)
 * 클라이언트는 자신의 카드 + 커뮤니티 카드만 받음
 */
class PokerGameEngine
{
    // 카드 덱 생성
    public static function createDeck(): array
    {
        $suits = ['s', 'h', 'd', 'c']; // spade, heart, diamond, club
        $ranks = ['2','3','4','5','6','7','8','9','T','J','Q','K','A'];
        $deck = [];
        foreach ($suits as $s) {
            foreach ($ranks as $r) {
                $deck[] = $r . $s;
            }
        }
        shuffle($deck);
        return $deck;
    }

    // 핸드 평가 (7장 중 최고 5장)
    public static function evalHand(array $cards): array
    {
        if (count($cards) < 5) return ['rank' => 0, 'name' => '-', 'score' => 0];

        $combos = self::combinations($cards, 5);
        $best = ['rank' => 0, 'name' => '-', 'score' => 0];

        foreach ($combos as $combo) {
            $eval = self::eval5($combo);
            if ($eval['score'] > $best['score']) {
                $best = $eval;
            }
        }
        return $best;
    }

    private static function combinations(array $arr, int $k): array
    {
        if ($k === 0) return [[]];
        if (count($arr) === 0) return [];
        $first = $arr[0];
        $rest = array_slice($arr, 1);
        $withFirst = array_map(fn($c) => array_merge([$first], $c), self::combinations($rest, $k - 1));
        $withoutFirst = self::combinations($rest, $k);
        return array_merge($withFirst, $withoutFirst);
    }

    private static function rankValue(string $r): int
    {
        return match($r) {
            '2'=>2,'3'=>3,'4'=>4,'5'=>5,'6'=>6,'7'=>7,'8'=>8,'9'=>9,
            'T'=>10,'J'=>11,'Q'=>12,'K'=>13,'A'=>14, default=>0
        };
    }

    private static function eval5(array $cards): array
    {
        $ranks = array_map(fn($c) => self::rankValue($c[0]), $cards);
        $suits = array_map(fn($c) => $c[strlen($c)-1], $cards);
        rsort($ranks);

        $isFlush = count(array_unique($suits)) === 1;
        $isStraight = false;
        $straightHigh = 0;

        $unique = array_unique($ranks);
        sort($unique);
        if (count($unique) === 5) {
            if ($unique[4] - $unique[0] === 4) {
                $isStraight = true;
                $straightHigh = $unique[4];
            }
            // A-2-3-4-5 (wheel)
            if ($unique === [2,3,4,5,14]) {
                $isStraight = true;
                $straightHigh = 5;
            }
        }

        $counts = array_count_values($ranks);
        arsort($counts);
        $groups = array_values($counts);
        $groupRanks = array_keys($counts);

        // 핸드 랭크 계산
        if ($isFlush && $isStraight) {
            $base = $straightHigh === 14 ? 9 : 8; // Royal or Straight Flush
            return ['rank' => $base, 'name' => $base === 9 ? '로열플러시' : '스트레이트 플러시', 'score' => $base * 10000000 + $straightHigh];
        }
        if ($groups[0] === 4) return ['rank' => 7, 'name' => '포카드', 'score' => 70000000 + $groupRanks[0] * 100 + $groupRanks[1]];
        if ($groups[0] === 3 && $groups[1] === 2) return ['rank' => 6, 'name' => '풀하우스', 'score' => 60000000 + $groupRanks[0] * 100 + $groupRanks[1]];
        if ($isFlush) return ['rank' => 5, 'name' => '플러시', 'score' => 50000000 + $ranks[0]*10000+$ranks[1]*1000+$ranks[2]*100+$ranks[3]*10+$ranks[4]];
        if ($isStraight) return ['rank' => 4, 'name' => '스트레이트', 'score' => 40000000 + $straightHigh];
        if ($groups[0] === 3) return ['rank' => 3, 'name' => '트리플', 'score' => 30000000 + $groupRanks[0] * 10000 + $ranks[0]*100+$ranks[1]];
        if ($groups[0] === 2 && $groups[1] === 2) return ['rank' => 2, 'name' => '투페어', 'score' => 20000000 + max($groupRanks[0],$groupRanks[1])*10000+min($groupRanks[0],$groupRanks[1])*100+$ranks[4]];
        if ($groups[0] === 2) return ['rank' => 1, 'name' => '원페어', 'score' => 10000000 + $groupRanks[0]*10000+$ranks[0]*100+$ranks[1]*10+$ranks[2]];
        return ['rank' => 0, 'name' => '하이카드', 'score' => $ranks[0]*10000+$ranks[1]*1000+$ranks[2]*100+$ranks[3]*10+$ranks[4]];
    }

    // ── 게임 상태 관리 (Redis Cache) ──

    public static function getGameState(string $gameId): ?array
    {
        return Cache::get("poker_game_{$gameId}");
    }

    public static function saveGameState(string $gameId, array $state): void
    {
        Cache::put("poker_game_{$gameId}", $state, 7200); // 2시간 TTL
    }

    public static function deleteGameState(string $gameId): void
    {
        Cache::forget("poker_game_{$gameId}");
    }

    // ── 새 게임 생성 ──
    public static function createGame(array $players, array $config = []): array
    {
        $gameId = 'pg_' . uniqid();
        $deck = self::createDeck();
        $bb = $config['bb'] ?? 20;
        $sb = $config['sb'] ?? 10;
        $startChips = $config['startChips'] ?? 15000;
        $turnTime = $config['turnTime'] ?? 15; // 초

        $seats = [];
        foreach ($players as $i => $p) {
            $seats[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'chips' => $p['chips'] ?? $startChips,
                'cards' => [array_shift($deck), array_shift($deck)],
                'bet' => 0,
                'folded' => false,
                'allIn' => false,
                'isOut' => false,
                'seatIdx' => $i,
            ];
        }

        // SB/BB 강제 베팅
        $sbIdx = 0;
        $bbIdx = 1 % count($seats);
        $seats[$sbIdx]['bet'] = min($sb, $seats[$sbIdx]['chips']);
        $seats[$sbIdx]['chips'] -= $seats[$sbIdx]['bet'];
        $seats[$bbIdx]['bet'] = min($bb, $seats[$bbIdx]['chips']);
        $seats[$bbIdx]['chips'] -= $seats[$bbIdx]['bet'];

        $state = [
            'gameId' => $gameId,
            'deck' => $deck,
            'seats' => $seats,
            'community' => [],
            'pot' => $sb + $bb,
            'stage' => 'preflop',
            'betLevel' => $bb,
            'dealerIdx' => 0,
            'actIdx' => 2 % count($seats), // UTG starts
            'sb' => $sb,
            'bb' => $bb,
            'turnTime' => $turnTime,
            'turnDeadline' => time() + $turnTime,
            'lastAction' => null,
            'handNum' => 1,
            'config' => $config,
            'status' => 'playing', // playing, showdown, finished
            'createdAt' => now()->toISOString(),
        ];

        self::saveGameState($gameId, $state);
        return $state;
    }

    // ── 플레이어 액션 처리 ──
    public static function processAction(string $gameId, int $userId, string $action, int $amount = 0): array
    {
        $state = self::getGameState($gameId);
        if (!$state || $state['status'] !== 'playing') {
            return ['error' => '게임이 진행 중이 아닙니다.'];
        }

        $actIdx = $state['actIdx'];
        if ($state['seats'][$actIdx]['id'] !== $userId) {
            return ['error' => '당신의 턴이 아닙니다.'];
        }

        $seat = &$state['seats'][$actIdx];
        $toCall = max(0, $state['betLevel'] - $seat['bet']);

        switch ($action) {
            case 'fold':
                $seat['folded'] = true;
                $state['lastAction'] = ['player' => $seat['name'], 'action' => 'fold'];
                break;

            case 'check':
                if ($toCall > 0) return ['error' => '체크할 수 없습니다. 콜 또는 폴드하세요.'];
                $state['lastAction'] = ['player' => $seat['name'], 'action' => 'check'];
                break;

            case 'call':
                $cost = min($toCall, $seat['chips']);
                $seat['chips'] -= $cost;
                $seat['bet'] += $cost;
                $state['pot'] += $cost;
                if ($seat['chips'] <= 0) $seat['allIn'] = true;
                $state['lastAction'] = ['player' => $seat['name'], 'action' => 'call', 'amount' => $cost];
                break;

            case 'raise':
                $raiseTotal = max($amount, $state['betLevel'] * 2);
                $cost = min($raiseTotal - $seat['bet'], $seat['chips']);
                $seat['chips'] -= $cost;
                $seat['bet'] += $cost;
                $state['pot'] += $cost;
                $state['betLevel'] = $seat['bet'];
                if ($seat['chips'] <= 0) $seat['allIn'] = true;
                $state['lastAction'] = ['player' => $seat['name'], 'action' => 'raise', 'amount' => $seat['bet']];
                break;

            case 'allin':
                $cost = $seat['chips'];
                $seat['chips'] = 0;
                $seat['bet'] += $cost;
                $state['pot'] += $cost;
                $seat['allIn'] = true;
                if ($seat['bet'] > $state['betLevel']) $state['betLevel'] = $seat['bet'];
                $state['lastAction'] = ['player' => $seat['name'], 'action' => 'allin', 'amount' => $seat['bet']];
                break;

            default:
                return ['error' => '잘못된 액션입니다.'];
        }

        // 다음 액터 찾기
        $nextIdx = self::findNextActor($state['seats'], $actIdx);
        $roundComplete = self::isRoundComplete($state['seats'], $state['betLevel']);

        if ($nextIdx === null || $roundComplete) {
            $state = self::advanceStage($state);
        } else {
            $state['actIdx'] = $nextIdx;
            $state['turnDeadline'] = time() + $state['turnTime'];
        }

        self::saveGameState($gameId, $state);
        return $state;
    }

    private static function findNextActor(array $seats, int $fromIdx): ?int
    {
        $total = count($seats);
        for ($i = 1; $i < $total; $i++) {
            $idx = ($fromIdx + $i) % $total;
            if (!$seats[$idx]['isOut'] && !$seats[$idx]['folded'] && !$seats[$idx]['allIn']) {
                return $idx;
            }
        }
        return null;
    }

    private static function isRoundComplete(array $seats, int $betLevel): bool
    {
        $active = array_filter($seats, fn($s) => !$s['isOut'] && !$s['folded'] && !$s['allIn']);
        foreach ($active as $s) {
            if ($s['bet'] !== $betLevel) return false;
        }
        return true;
    }

    private static function advanceStage(array $state): array
    {
        // 베팅 리셋
        foreach ($state['seats'] as &$s) { $s['bet'] = 0; }
        $state['betLevel'] = 0;

        $notFolded = array_filter($state['seats'], fn($s) => !$s['isOut'] && !$s['folded']);

        // 1명만 남으면 즉시 승리
        if (count($notFolded) <= 1) {
            return self::resolveHand($state);
        }

        $stages = ['preflop', 'flop', 'turn', 'river'];
        $si = array_search($state['stage'], $stages);

        if ($si >= 3) {
            return self::resolveHand($state);
        }

        // 카드 오픈
        $deck = $state['deck'];
        if ($si === 0) { // → flop
            $state['community'] = [array_shift($deck), array_shift($deck), array_shift($deck)];
            $state['stage'] = 'flop';
        } else { // → turn or river
            $state['community'][] = array_shift($deck);
            $state['stage'] = $stages[$si + 1];
        }
        $state['deck'] = $deck;

        // 액션 가능한 사람이 있는지
        $canAct = array_filter($notFolded, fn($s) => !$s['allIn']);
        if (count($canAct) <= 1) {
            // 모두 올인 → 보드 끝까지 오픈 후 쇼다운
            while (count($state['community']) < 5) {
                $state['community'][] = array_shift($state['deck']);
            }
            $state['stage'] = 'river';
            return self::resolveHand($state);
        }

        // 딜러 다음 사람부터 액션
        $firstAct = self::findNextActor($state['seats'], $state['dealerIdx']);
        $state['actIdx'] = $firstAct ?? 0;
        $state['turnDeadline'] = time() + $state['turnTime'];

        return $state;
    }

    private static function resolveHand(array $state): array
    {
        $notFolded = [];
        foreach ($state['seats'] as $i => $s) {
            if (!$s['isOut'] && !$s['folded']) {
                $notFolded[$i] = $s;
            }
        }

        if (count($notFolded) === 1) {
            $winnerIdx = array_key_first($notFolded);
            $state['seats'][$winnerIdx]['chips'] += $state['pot'];
            $state['result'] = [
                'winners' => [['seatIdx' => $winnerIdx, 'name' => $state['seats'][$winnerIdx]['name'], 'pot' => $state['pot'], 'hand' => '상대 폴드']],
            ];
        } else {
            $evals = [];
            foreach ($notFolded as $i => $s) {
                $allCards = array_merge($s['cards'], $state['community']);
                $eval = self::evalHand($allCards);
                $evals[$i] = $eval;
            }

            // 최고 스코어 찾기
            $maxScore = 0;
            foreach ($evals as $e) { if ($e['score'] > $maxScore) $maxScore = $e['score']; }

            $winners = [];
            foreach ($evals as $i => $e) {
                if ($e['score'] === $maxScore) {
                    $winners[] = $i;
                }
            }

            $share = intdiv($state['pot'], count($winners));
            foreach ($winners as $wi) {
                $state['seats'][$wi]['chips'] += $share;
            }

            $state['result'] = [
                'winners' => array_map(fn($wi) => [
                    'seatIdx' => $wi,
                    'name' => $state['seats'][$wi]['name'],
                    'hand' => $evals[$wi]['name'],
                    'pot' => $share,
                ], $winners),
                'showdown' => array_map(fn($i) => [
                    'seatIdx' => $i,
                    'name' => $state['seats'][$i]['name'],
                    'cards' => $state['seats'][$i]['cards'],
                    'hand' => $evals[$i]['name'],
                    'score' => $evals[$i]['score'],
                ], array_keys($notFolded)),
            ];
        }

        $state['pot'] = 0;
        $state['status'] = 'showdown';
        $state['stage'] = 'result';

        return $state;
    }

    // ── AI 자동 액션 (서버에서 처리) ──
    public static function processAITurn(string $gameId): ?array
    {
        $state = self::getGameState($gameId);
        if (!$state || $state['status'] !== 'playing') return null;

        $actIdx = $state['actIdx'];
        $seat = $state['seats'][$actIdx] ?? null;
        if (!$seat || $seat['id'] > 0) return null; // 실제 유저면 스킵

        // 간단한 AI 로직
        $toCall = max(0, $state['betLevel'] - $seat['bet']);
        $rand = mt_rand(1, 100);

        if ($toCall === 0) {
            // 체크 또는 레이즈
            $action = $rand <= 70 ? 'check' : 'raise';
            $amount = $state['bb'] * mt_rand(2, 4);
        } elseif ($toCall > $seat['chips'] * 0.5) {
            // 큰 베팅 → 60% 폴드, 30% 콜, 10% 올인
            $action = $rand <= 60 ? 'fold' : ($rand <= 90 ? 'call' : 'allin');
            $amount = 0;
        } else {
            // 보통 → 20% 폴드, 50% 콜, 30% 레이즈
            $action = $rand <= 20 ? 'fold' : ($rand <= 70 ? 'call' : 'raise');
            $amount = $state['betLevel'] * mt_rand(2, 3);
        }

        return self::processAction($gameId, $seat['id'], $action, $amount);
    }

    // ── 토너먼트: 다음 핸드 딜 ──
    public static function nextHand(string $gameId): ?array
    {
        $state = self::getGameState($gameId);
        if (!$state) return null;

        // 탈락자 처리 (칩 0 → isOut)
        $eliminated = [];
        foreach ($state['seats'] as $i => &$seat) {
            if (!$seat['isOut'] && $seat['chips'] <= 0) {
                $seat['isOut'] = true;
                $eliminated[] = $seat;
            }
        }
        unset($seat);

        // 남은 플레이어 수
        $alive = array_filter($state['seats'], fn($s) => !$s['isOut']);
        $aliveCount = count($alive);

        // 1명 남으면 토너먼트 종료
        if ($aliveCount <= 1) {
            $state['status'] = 'finished';
            $state['stage'] = 'tournament_end';

            // 순위 계산 (칩 많은 순)
            $ranked = $alive;
            usort($ranked, fn($a, $b) => $b['chips'] - $a['chips']);
            $state['finalRanking'] = array_map(function ($s, $i) {
                return ['seatIdx' => $s['seatIdx'], 'name' => $s['name'], 'id' => $s['id'], 'chips' => $s['chips'], 'place' => $i + 1];
            }, $ranked, array_keys($ranked));

            self::saveGameState($gameId, $state);
            return $state;
        }

        // 블라인드 레벨 업 체크
        $config = $state['config'] ?? [];
        $blindSchedule = $config['blindSchedule'] ?? [];
        $currentLevel = $config['blindLevel'] ?? 0;
        $blindStartedAt = $config['blindStartedAt'] ?? time();

        if (!empty($blindSchedule) && isset($blindSchedule[$currentLevel])) {
            $duration = ($blindSchedule[$currentLevel]['duration'] ?? 10) * 60; // 분→초
            if (time() - $blindStartedAt >= $duration && isset($blindSchedule[$currentLevel + 1])) {
                $currentLevel++;
                $state['config']['blindLevel'] = $currentLevel;
                $state['config']['blindStartedAt'] = time();
                $state['sb'] = $blindSchedule[$currentLevel]['sb'];
                $state['bb'] = $blindSchedule[$currentLevel]['bb'];
                $state['blindLevelUp'] = true; // 프론트엔드 알림용
            }
        }

        // 덱 새로 섞기
        $deck = self::createDeck();

        // 딜러 이동 (다음 살아있는 사람)
        $dealerIdx = $state['dealerIdx'];
        $total = count($state['seats']);
        for ($i = 1; $i <= $total; $i++) {
            $next = ($dealerIdx + $i) % $total;
            if (!$state['seats'][$next]['isOut']) {
                $dealerIdx = $next;
                break;
            }
        }
        $state['dealerIdx'] = $dealerIdx;

        // SB/BB 위치
        $sbIdx = null;
        $bbIdx = null;
        $pos = $dealerIdx;
        for ($i = 1; $i <= $total; $i++) {
            $pos = ($dealerIdx + $i) % $total;
            if (!$state['seats'][$pos]['isOut']) {
                if ($sbIdx === null) $sbIdx = $pos;
                elseif ($bbIdx === null) { $bbIdx = $pos; break; }
            }
        }

        // 헤즈업(2명)일 때 딜러=SB
        if ($aliveCount === 2) {
            $sbIdx = $dealerIdx;
            for ($i = 1; $i <= $total; $i++) {
                $pos = ($dealerIdx + $i) % $total;
                if (!$state['seats'][$pos]['isOut']) { $bbIdx = $pos; break; }
            }
        }

        // 카드 딜 + 리셋
        foreach ($state['seats'] as $i => &$seat) {
            if (!$seat['isOut']) {
                $seat['cards'] = [array_shift($deck), array_shift($deck)];
                $seat['bet'] = 0;
                $seat['folded'] = false;
                $seat['allIn'] = false;
            } else {
                $seat['cards'] = [];
                $seat['bet'] = 0;
                $seat['folded'] = true;
            }
        }
        unset($seat);

        // SB/BB 강제 베팅
        $sb = $state['sb'];
        $bb = $state['bb'];
        $state['seats'][$sbIdx]['bet'] = min($sb, $state['seats'][$sbIdx]['chips']);
        $state['seats'][$sbIdx]['chips'] -= $state['seats'][$sbIdx]['bet'];
        if ($state['seats'][$sbIdx]['chips'] <= 0) $state['seats'][$sbIdx]['allIn'] = true;

        $state['seats'][$bbIdx]['bet'] = min($bb, $state['seats'][$bbIdx]['chips']);
        $state['seats'][$bbIdx]['chips'] -= $state['seats'][$bbIdx]['bet'];
        if ($state['seats'][$bbIdx]['chips'] <= 0) $state['seats'][$bbIdx]['allIn'] = true;

        // UTG (BB 다음 살아있는 사람)
        $utg = null;
        for ($i = 1; $i <= $total; $i++) {
            $pos = ($bbIdx + $i) % $total;
            if (!$state['seats'][$pos]['isOut'] && !$state['seats'][$pos]['allIn']) {
                $utg = $pos;
                break;
            }
        }

        $state['deck'] = $deck;
        $state['community'] = [];
        $state['pot'] = $state['seats'][$sbIdx]['bet'] + $state['seats'][$bbIdx]['bet'];
        $state['stage'] = 'preflop';
        $state['betLevel'] = $bb;
        $state['actIdx'] = $utg ?? $sbIdx;
        $state['turnDeadline'] = time() + ($state['turnTime'] ?? 15);
        $state['lastAction'] = null;
        $state['status'] = 'playing';
        $state['handNum'] = ($state['handNum'] ?? 1) + 1;
        unset($state['result'], $state['blindLevelUp']);

        self::saveGameState($gameId, $state);
        return $state;
    }

    // ── 토너먼트 상금 계산 ──
    public static function calculatePrizes(int $totalBuyIn, int $playerCount, int $bountyPct = 10): array
    {
        $bountyPool = intdiv($totalBuyIn * $bountyPct, 100);
        $prizePool = $totalBuyIn - $bountyPool;

        // 상금 구조 (참가자 수에 따라)
        if ($playerCount <= 4) {
            $structure = [0.60, 0.40];
        } elseif ($playerCount <= 6) {
            $structure = [0.50, 0.30, 0.20];
        } elseif ($playerCount <= 9) {
            $structure = [0.40, 0.25, 0.18, 0.17];
        } else {
            $structure = [0.35, 0.22, 0.15, 0.10, 0.08, 0.05, 0.05];
        }

        $prizes = [];
        foreach ($structure as $i => $pct) {
            $prizes[$i + 1] = (int) round($prizePool * $pct);
        }

        return [
            'prizePool' => $prizePool,
            'bountyPool' => $bountyPool,
            'bountyPerKill' => $playerCount > 1 ? intdiv($bountyPool, $playerCount - 1) : 0,
            'prizes' => $prizes,
        ];
    }

    // ── 유저에게 보낼 상태 (자기 카드만 보이게) ──
    public static function getPlayerView(array $state, int $userId): array
    {
        $view = $state;
        unset($view['deck']); // 덱은 절대 노출 안 함

        foreach ($view['seats'] as &$seat) {
            if ($seat['id'] !== $userId && $view['status'] !== 'showdown') {
                $seat['cards'] = ['??', '??']; // 다른 사람 카드 숨기기
            }
        }

        return $view;
    }
}
