<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * PokerGameEngine — 서버사이드 텍사스 홀덤 엔진 (정식 규칙)
 *
 * 수정된 규칙:
 * - 딜러/SB/BB/UTG 올바른 위치 계산
 * - 프리플랍: UTG(BB+1)부터, 포스트플랍: SB부터 액션
 * - 베팅 라운드 완료: 모두 같은 금액 + 전원 액션 완료 + BB 옵션
 * - 사이드팟 계산
 * - 레이즈 최소금액 검증
 * - 헤즈업 블라인드 구조
 */
class PokerGameEngine
{
    // ── 카드 덱 생성 ──
    public static function createDeck(): array
    {
        $suits = ['s', 'h', 'd', 'c'];
        $ranks = ['2','3','4','5','6','7','8','9','T','J','Q','K','A'];
        $deck = [];
        foreach ($suits as $s) foreach ($ranks as $r) $deck[] = $r . $s;
        shuffle($deck);
        return $deck;
    }

    // ── 핸드 평가 (7장 중 최고 5장) ──
    public static function evalHand(array $cards): array
    {
        if (count($cards) < 5) return ['rank' => 0, 'name' => '-', 'score' => 0];
        $combos = self::combinations($cards, 5);
        $best = ['rank' => 0, 'name' => '-', 'score' => 0];
        foreach ($combos as $combo) {
            $eval = self::eval5($combo);
            if ($eval['score'] > $best['score']) $best = $eval;
        }
        return $best;
    }

    private static function combinations(array $arr, int $k): array
    {
        if ($k === 0) return [[]];
        if (count($arr) === 0) return [];
        $first = $arr[0];
        $rest = array_slice($arr, 1);
        return array_merge(
            array_map(fn($c) => array_merge([$first], $c), self::combinations($rest, $k - 1)),
            self::combinations($rest, $k)
        );
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
            if ($unique[4] - $unique[0] === 4) { $isStraight = true; $straightHigh = $unique[4]; }
            if ($unique === [2,3,4,5,14]) { $isStraight = true; $straightHigh = 5; }
        }

        $counts = array_count_values($ranks);
        arsort($counts);
        $groups = array_values($counts);
        $groupRanks = array_keys($counts);

        if ($isFlush && $isStraight) {
            $base = $straightHigh === 14 ? 9 : 8;
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
    public static function getGameState(string $gameId): ?array { return Cache::get("poker_game_{$gameId}"); }
    public static function saveGameState(string $gameId, array $state): void { Cache::put("poker_game_{$gameId}", $state, 7200); }
    public static function deleteGameState(string $gameId): void { Cache::forget("poker_game_{$gameId}"); }

    // ── 살아있는 다음 좌석 찾기 (시계방향) ──
    private static function findNextAlive(array $seats, int $fromIdx, bool $includeSelf = false): ?int
    {
        $total = count($seats);
        $start = $includeSelf ? 0 : 1;
        for ($i = $start; $i < $total; $i++) {
            $idx = ($fromIdx + $i) % $total;
            if (!$seats[$idx]['isOut']) return $idx;
        }
        return null;
    }

    // ── 액션 가능한 다음 좌석 찾기 (folded/allIn 제외) ──
    private static function findNextActor(array $seats, int $fromIdx): ?int
    {
        $total = count($seats);
        for ($i = 1; $i < $total; $i++) {
            $idx = ($fromIdx + $i) % $total;
            if (!$seats[$idx]['isOut'] && !$seats[$idx]['folded'] && !$seats[$idx]['allIn']) return $idx;
        }
        return null;
    }

    // ── 딜러/SB/BB 위치 계산 ──
    private static function calculatePositions(array $seats, int $dealerIdx): array
    {
        $alive = array_filter($seats, fn($s) => !$s['isOut']);
        $aliveCount = count($alive);

        if ($aliveCount <= 1) return ['dealer' => $dealerIdx, 'sb' => $dealerIdx, 'bb' => $dealerIdx];

        if ($aliveCount === 2) {
            // 헤즈업: 딜러 = SB, 상대 = BB
            $sbIdx = $dealerIdx;
            $bbIdx = self::findNextAlive($seats, $dealerIdx);
            return ['dealer' => $dealerIdx, 'sb' => $sbIdx, 'bb' => $bbIdx];
        }

        // 3명 이상: 딜러 다음 = SB, 그 다음 = BB
        $sbIdx = self::findNextAlive($seats, $dealerIdx);
        $bbIdx = self::findNextAlive($seats, $sbIdx);
        return ['dealer' => $dealerIdx, 'sb' => $sbIdx, 'bb' => $bbIdx];
    }

    // ── 새 게임 생성 (정식 규칙) ──
    public static function createGame(array $players, array $config = []): array
    {
        $gameId = 'pg_' . uniqid();
        $deck = self::createDeck();
        $bb = $config['bb'] ?? 20;
        $sb = $config['sb'] ?? 10;
        $startChips = $config['startChips'] ?? 15000;
        $turnTime = $config['turnTime'] ?? 15;
        $dealerIdx = $config['dealerIdx'] ?? 0;

        $seats = [];
        foreach ($players as $i => $p) {
            $seats[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'chips' => $p['chips'] ?? $startChips,
                'cards' => [array_shift($deck), array_shift($deck)],
                'bet' => 0,
                'totalBet' => 0,
                'folded' => false,
                'allIn' => false,
                'isOut' => false,
                'seatIdx' => $i,
            ];
        }

        // 딜러/SB/BB 위치 계산
        $pos = self::calculatePositions($seats, $dealerIdx);
        $sbIdx = $pos['sb'];
        $bbIdx = $pos['bb'];

        // SB 강제 베팅
        $sbAmt = min($sb, $seats[$sbIdx]['chips']);
        $seats[$sbIdx]['bet'] = $sbAmt;
        $seats[$sbIdx]['totalBet'] = $sbAmt;
        $seats[$sbIdx]['chips'] -= $sbAmt;
        if ($seats[$sbIdx]['chips'] <= 0) $seats[$sbIdx]['allIn'] = true;

        // BB 강제 베팅
        $bbAmt = min($bb, $seats[$bbIdx]['chips']);
        $seats[$bbIdx]['bet'] = $bbAmt;
        $seats[$bbIdx]['totalBet'] = $bbAmt;
        $seats[$bbIdx]['chips'] -= $bbAmt;
        if ($seats[$bbIdx]['chips'] <= 0) $seats[$bbIdx]['allIn'] = true;

        // 프리플랍 첫 액터: 헤즈업이면 딜러(SB), 아니면 BB 다음
        $aliveCount = count(array_filter($seats, fn($s) => !$s['isOut']));
        if ($aliveCount === 2) {
            $firstAct = self::findNextActor($seats, $bbIdx) ?? $sbIdx;
        } else {
            $firstAct = self::findNextActor($seats, $bbIdx);
            if ($firstAct === null) $firstAct = self::findNextActor($seats, $sbIdx) ?? 0;
        }

        $state = [
            'gameId' => $gameId,
            'deck' => $deck,
            'seats' => $seats,
            'community' => [],
            'pot' => $sbAmt + $bbAmt,
            'stage' => 'preflop',
            'betLevel' => $bb,
            'dealerIdx' => $dealerIdx,
            'sbIdx' => $sbIdx,
            'bbIdx' => $bbIdx,
            'actIdx' => $firstAct,
            'sb' => $sb,
            'bb' => $bb,
            'turnTime' => $turnTime,
            'turnDeadline' => time() + $turnTime,
            'lastAction' => null,
            'lastRaiserIdx' => $bbIdx, // BB가 초기 "레이저" (프리플랍)
            'lastRaiseSize' => $bb,
            'actedThisRound' => [], // 이번 라운드 액션한 좌석
            'bbHasActed' => false, // BB 옵션 트래킹
            'handNum' => 1,
            'config' => $config,
            'status' => 'playing',
            'createdAt' => now()->toISOString(),
        ];

        self::saveGameState($gameId, $state);
        return $state;
    }

    // ── 베팅 라운드 완료 판정 (정식 규칙) ──
    private static function isRoundComplete(array $state): bool
    {
        $seats = $state['seats'];
        $betLevel = $state['betLevel'];
        $stage = $state['stage'];
        $actedThisRound = $state['actedThisRound'] ?? [];
        $bbIdx = $state['bbIdx'] ?? -1;
        $bbHasActed = $state['bbHasActed'] ?? false;
        $lastRaiserIdx = $state['lastRaiserIdx'] ?? -1;

        $active = [];
        foreach ($seats as $i => $s) {
            if (!$s['isOut'] && !$s['folded'] && !$s['allIn']) $active[$i] = $s;
        }

        // 액션 가능한 사람이 0명 → 완료
        if (count($active) === 0) return true;

        // 모든 액티브 플레이어 베팅이 같은지
        foreach ($active as $s) {
            if ($s['bet'] !== $betLevel) return false;
        }

        // 프리플랍: BB가 아직 액션 안 했으면 미완료 (BB 옵션)
        if ($stage === 'preflop' && !$bbHasActed && isset($active[$bbIdx])) {
            return false;
        }

        // 모든 액티브 플레이어가 마지막 레이즈 이후 액션했는지
        foreach ($active as $i => $s) {
            if (!in_array($i, $actedThisRound)) return false;
        }

        return true;
    }

    // ── 플레이어 액션 처리 (정식 규칙) ──
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
        $bbIdx = $state['bbIdx'] ?? -1;

        switch ($action) {
            case 'fold':
                $seat['folded'] = true;
                $state['lastAction'] = ['player' => $seat['name'], 'action' => 'fold'];
                break;

            case 'check':
                if ($toCall > 0) return ['error' => '체크할 수 없습니다.'];
                $state['lastAction'] = ['player' => $seat['name'], 'action' => 'check'];
                break;

            case 'call':
                $cost = min($toCall, $seat['chips']);
                $seat['chips'] -= $cost;
                $seat['bet'] += $cost;
                $seat['totalBet'] = ($seat['totalBet'] ?? 0) + $cost;
                $state['pot'] += $cost;
                if ($seat['chips'] <= 0) $seat['allIn'] = true;
                $state['lastAction'] = ['player' => $seat['name'], 'action' => 'call', 'amount' => $cost];
                break;

            case 'raise':
                $minRaise = $state['betLevel'] + ($state['lastRaiseSize'] ?? $state['bb']);
                $raiseTotal = max($amount, $minRaise);
                $cost = min($raiseTotal - $seat['bet'], $seat['chips']);
                $newBet = $seat['bet'] + $cost;
                $raiseIncrement = $newBet - $state['betLevel'];

                $seat['chips'] -= $cost;
                $seat['bet'] = $newBet;
                $seat['totalBet'] = ($seat['totalBet'] ?? 0) + $cost;
                $state['pot'] += $cost;

                // 진짜 레이즈인지 (최소 레이즈 이상)
                if ($raiseIncrement >= ($state['lastRaiseSize'] ?? $state['bb'])) {
                    $state['lastRaiserIdx'] = $actIdx;
                    $state['lastRaiseSize'] = $raiseIncrement;
                    $state['actedThisRound'] = [$actIdx]; // 레이즈 후 다른 모두 다시 액션
                }

                $state['betLevel'] = $newBet;
                if ($seat['chips'] <= 0) $seat['allIn'] = true;
                $state['lastAction'] = ['player' => $seat['name'], 'action' => 'raise', 'amount' => $newBet];
                break;

            case 'allin':
                $cost = $seat['chips'];
                $seat['chips'] = 0;
                $seat['bet'] += $cost;
                $seat['totalBet'] = ($seat['totalBet'] ?? 0) + $cost;
                $state['pot'] += $cost;
                $seat['allIn'] = true;

                // 올인이 기존 베팅레벨보다 높으면 레이즈 효과
                if ($seat['bet'] > $state['betLevel']) {
                    $raiseIncrement = $seat['bet'] - $state['betLevel'];
                    $state['betLevel'] = $seat['bet'];

                    // 최소 레이즈 이상이면 액션 리오픈
                    if ($raiseIncrement >= ($state['lastRaiseSize'] ?? $state['bb'])) {
                        $state['lastRaiserIdx'] = $actIdx;
                        $state['lastRaiseSize'] = $raiseIncrement;
                        $state['actedThisRound'] = [$actIdx];
                    }
                }
                $state['lastAction'] = ['player' => $seat['name'], 'action' => 'allin', 'amount' => $seat['bet']];
                break;

            default:
                return ['error' => '잘못된 액션입니다.'];
        }

        // BB 액션 트래킹
        if ($actIdx === $bbIdx && $state['stage'] === 'preflop') {
            $state['bbHasActed'] = true;
        }

        // 액션 기록 (레이즈에서 리셋 안 된 경우)
        if (!in_array($actIdx, $state['actedThisRound'])) {
            $state['actedThisRound'][] = $actIdx;
        }

        // 라운드 완료 체크
        $roundComplete = self::isRoundComplete($state);
        $nextIdx = self::findNextActor($state['seats'], $actIdx);

        // 1명만 남았으면 (나머지 전원 폴드)
        $notFolded = array_filter($state['seats'], fn($s) => !$s['isOut'] && !$s['folded']);
        if (count($notFolded) <= 1) {
            $state = self::resolveHand($state);
        } elseif ($nextIdx === null || $roundComplete) {
            $state = self::advanceStage($state);
        } else {
            $state['actIdx'] = $nextIdx;
            $state['turnDeadline'] = time() + $state['turnTime'];
        }

        self::saveGameState($gameId, $state);
        return $state;
    }

    // ── 스테이지 진행 ──
    private static function advanceStage(array $state): array
    {
        // 베팅 리셋 (totalBet은 유지!)
        foreach ($state['seats'] as &$s) { $s['bet'] = 0; }
        unset($s);
        $state['betLevel'] = 0;
        $state['lastRaiseSize'] = $state['bb'];
        $state['actedThisRound'] = [];
        $state['bbHasActed'] = true; // 포스트플랍에서는 BB 옵션 없음

        $notFolded = array_filter($state['seats'], fn($s) => !$s['isOut'] && !$s['folded']);

        if (count($notFolded) <= 1) return self::resolveHand($state);

        $stages = ['preflop', 'flop', 'turn', 'river'];
        $si = array_search($state['stage'], $stages);

        if ($si >= 3) return self::resolveHand($state);

        // 커뮤니티 카드 오픈
        $deck = $state['deck'];
        if ($si === 0) {
            $state['community'] = [array_shift($deck), array_shift($deck), array_shift($deck)];
            $state['stage'] = 'flop';
        } else {
            $state['community'][] = array_shift($deck);
            $state['stage'] = $stages[$si + 1];
        }
        $state['deck'] = $deck;

        // 액션 가능한 사람 체크
        $canAct = array_filter($notFolded, fn($s) => !$s['allIn']);
        if (count($canAct) <= 1) {
            // 모두 올인 → 보드 끝까지
            while (count($state['community']) < 5) {
                $state['community'][] = array_shift($state['deck']);
            }
            $state['stage'] = 'river';
            return self::resolveHand($state);
        }

        // 포스트플랍: SB부터 (또는 딜러 다음 살아있는 사람부터)
        $dealerIdx = $state['dealerIdx'];
        $firstAct = null;
        $total = count($state['seats']);
        for ($i = 1; $i <= $total; $i++) {
            $idx = ($dealerIdx + $i) % $total;
            $s = $state['seats'][$idx];
            if (!$s['isOut'] && !$s['folded'] && !$s['allIn']) {
                $firstAct = $idx;
                break;
            }
        }

        $state['actIdx'] = $firstAct ?? 0;
        $state['lastRaiserIdx'] = -1; // 라운드 시작 시 레이저 없음
        $state['turnDeadline'] = time() + $state['turnTime'];

        return $state;
    }

    // ── 핸드 결과 판정 (사이드팟 포함) ──
    private static function resolveHand(array $state): array
    {
        $notFolded = [];
        foreach ($state['seats'] as $i => $s) {
            if (!$s['isOut'] && !$s['folded']) $notFolded[$i] = $s;
        }

        if (count($notFolded) === 1) {
            // 전원 폴드 → 남은 1명 승리
            $winnerIdx = array_key_first($notFolded);
            $state['seats'][$winnerIdx]['chips'] += $state['pot'];
            $state['result'] = [
                'winners' => [['seatIdx' => $winnerIdx, 'name' => $state['seats'][$winnerIdx]['name'], 'pot' => $state['pot'], 'hand' => '상대 폴드']],
            ];
        } else {
            // 핸드 평가
            $evals = [];
            foreach ($notFolded as $i => $s) {
                $evals[$i] = self::evalHand(array_merge($s['cards'], $state['community']));
            }

            // 사이드팟 계산
            $pots = self::calculateSidePots($state['seats']);
            $allWinners = [];
            $totalWon = [];

            foreach ($pots as $pot) {
                if ($pot['amount'] <= 0) continue;

                // 이 팟에 참여 가능한 플레이어 중 최고 핸드
                $eligible = array_intersect_key($evals, array_flip($pot['eligible']));
                if (empty($eligible)) continue;

                $maxScore = max(array_column($eligible, 'score'));
                $potWinners = array_keys(array_filter($eligible, fn($e) => $e['score'] === $maxScore));

                $share = intdiv($pot['amount'], count($potWinners));
                foreach ($potWinners as $wi) {
                    $state['seats'][$wi]['chips'] += $share;
                    $totalWon[$wi] = ($totalWon[$wi] ?? 0) + $share;
                    if (!in_array($wi, $allWinners)) $allWinners[] = $wi;
                }
            }

            $state['result'] = [
                'winners' => array_map(fn($wi) => [
                    'seatIdx' => $wi,
                    'name' => $state['seats'][$wi]['name'],
                    'hand' => $evals[$wi]['name'],
                    'pot' => $totalWon[$wi] ?? 0,
                ], $allWinners),
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

    // ── 사이드팟 계산 ──
    private static function calculateSidePots(array $seats): array
    {
        // 모든 참가자(폴드 포함)의 totalBet 수집
        $contributions = [];
        foreach ($seats as $i => $s) {
            if ($s['isOut']) continue;
            $tb = $s['totalBet'] ?? $s['bet'] ?? 0;
            if ($tb > 0) {
                $contributions[] = ['idx' => $i, 'totalBet' => $tb, 'folded' => $s['folded']];
            }
        }

        if (empty($contributions)) return [['amount' => 0, 'eligible' => []]];

        // totalBet 기준 정렬
        usort($contributions, fn($a, $b) => $a['totalBet'] - $b['totalBet']);

        $pots = [];
        $prevLevel = 0;
        $levels = array_unique(array_column($contributions, 'totalBet'));
        sort($levels);

        foreach ($levels as $level) {
            $increment = $level - $prevLevel;
            $playersAtLevel = count(array_filter($contributions, fn($c) => $c['totalBet'] >= $level));
            $potAmount = $increment * $playersAtLevel;

            // 이 팟에 참여 가능한 플레이어 (폴드 안 한 + 이 레벨 이상 베팅)
            $eligible = array_column(
                array_filter($contributions, fn($c) => !$c['folded'] && $c['totalBet'] >= $level),
                'idx'
            );

            $pots[] = ['amount' => $potAmount, 'eligible' => $eligible];
            $prevLevel = $level;
        }

        return $pots;
    }

    // ── AI 자동 액션 ──
    public static function processAITurn(string $gameId): ?array
    {
        $state = self::getGameState($gameId);
        if (!$state || $state['status'] !== 'playing') return null;

        $actIdx = $state['actIdx'];
        $seat = $state['seats'][$actIdx] ?? null;
        if (!$seat || $seat['id'] > 0) return null;

        $toCall = max(0, $state['betLevel'] - $seat['bet']);
        $rand = mt_rand(1, 100);

        if ($toCall === 0) {
            $action = $rand <= 70 ? 'check' : 'raise';
            $amount = $state['bb'] * mt_rand(2, 4);
        } elseif ($toCall > $seat['chips'] * 0.5) {
            $action = $rand <= 60 ? 'fold' : ($rand <= 90 ? 'call' : 'allin');
            $amount = 0;
        } else {
            $action = $rand <= 20 ? 'fold' : ($rand <= 70 ? 'call' : 'raise');
            $amount = $state['betLevel'] * mt_rand(2, 3);
        }

        return self::processAction($gameId, $seat['id'], $action, $amount);
    }

    // ── 토너먼트: 다음 핸드 ──
    public static function nextHand(string $gameId): ?array
    {
        $state = self::getGameState($gameId);
        if (!$state) return null;

        // 탈락자 처리
        foreach ($state['seats'] as $i => &$seat) {
            if (!$seat['isOut'] && $seat['chips'] <= 0) {
                $seat['isOut'] = true;
            }
        }
        unset($seat);

        $alive = array_filter($state['seats'], fn($s) => !$s['isOut']);
        $aliveCount = count($alive);

        // 1명 남으면 종료
        if ($aliveCount <= 1) {
            $state['status'] = 'finished';
            $state['stage'] = 'tournament_end';
            $ranked = array_values($alive);
            usort($ranked, fn($a, $b) => $b['chips'] - $a['chips']);
            $state['finalRanking'] = array_map(fn($s, $i) => [
                'seatIdx' => $s['seatIdx'], 'name' => $s['name'], 'id' => $s['id'],
                'chips' => $s['chips'], 'place' => $i + 1
            ], $ranked, array_keys($ranked));
            self::saveGameState($gameId, $state);
            return $state;
        }

        // 블라인드 레벨 업
        $config = $state['config'] ?? [];
        $blindSchedule = $config['blindSchedule'] ?? [];
        $currentLevel = $config['blindLevel'] ?? 0;
        $blindStartedAt = $config['blindStartedAt'] ?? time();

        if (!empty($blindSchedule) && isset($blindSchedule[$currentLevel])) {
            $duration = ($blindSchedule[$currentLevel]['duration'] ?? 10) * 60;
            if (time() - $blindStartedAt >= $duration && isset($blindSchedule[$currentLevel + 1])) {
                $currentLevel++;
                $state['config']['blindLevel'] = $currentLevel;
                $state['config']['blindStartedAt'] = time();
                $state['sb'] = $blindSchedule[$currentLevel]['sb'];
                $state['bb'] = $blindSchedule[$currentLevel]['bb'];
                $state['blindLevelUp'] = true;
            }
        }

        // 딜러 이동 (시계방향 다음 살아있는 사람)
        $dealerIdx = self::findNextAlive($state['seats'], $state['dealerIdx']);
        $state['dealerIdx'] = $dealerIdx;

        // SB/BB 계산
        $pos = self::calculatePositions($state['seats'], $dealerIdx);
        $sbIdx = $pos['sb'];
        $bbIdx = $pos['bb'];

        // 덱 + 카드 딜
        $deck = self::createDeck();
        $total = count($state['seats']);

        foreach ($state['seats'] as $i => &$seat) {
            $seat['bet'] = 0;
            $seat['totalBet'] = 0;
            $seat['folded'] = $seat['isOut'];
            $seat['allIn'] = false;
            $seat['cards'] = $seat['isOut'] ? [] : [array_shift($deck), array_shift($deck)];
        }
        unset($seat);

        // SB/BB 베팅
        $sb = $state['sb'];
        $bb = $state['bb'];

        $sbAmt = min($sb, $state['seats'][$sbIdx]['chips']);
        $state['seats'][$sbIdx]['bet'] = $sbAmt;
        $state['seats'][$sbIdx]['totalBet'] = $sbAmt;
        $state['seats'][$sbIdx]['chips'] -= $sbAmt;
        if ($state['seats'][$sbIdx]['chips'] <= 0) $state['seats'][$sbIdx]['allIn'] = true;

        $bbAmt = min($bb, $state['seats'][$bbIdx]['chips']);
        $state['seats'][$bbIdx]['bet'] = $bbAmt;
        $state['seats'][$bbIdx]['totalBet'] = $bbAmt;
        $state['seats'][$bbIdx]['chips'] -= $bbAmt;
        if ($state['seats'][$bbIdx]['chips'] <= 0) $state['seats'][$bbIdx]['allIn'] = true;

        // UTG (프리플랍 첫 액터)
        if ($aliveCount === 2) {
            $firstAct = self::findNextActor($state['seats'], $bbIdx) ?? $sbIdx;
        } else {
            $firstAct = self::findNextActor($state['seats'], $bbIdx);
            if ($firstAct === null) $firstAct = self::findNextActor($state['seats'], $sbIdx) ?? 0;
        }

        $state['deck'] = $deck;
        $state['community'] = [];
        $state['pot'] = $sbAmt + $bbAmt;
        $state['stage'] = 'preflop';
        $state['betLevel'] = $bb;
        $state['sbIdx'] = $sbIdx;
        $state['bbIdx'] = $bbIdx;
        $state['actIdx'] = $firstAct;
        $state['turnDeadline'] = time() + ($state['turnTime'] ?? 15);
        $state['lastAction'] = null;
        $state['lastRaiserIdx'] = $bbIdx;
        $state['lastRaiseSize'] = $bb;
        $state['actedThisRound'] = [];
        $state['bbHasActed'] = false;
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

        if ($playerCount <= 4) $structure = [0.60, 0.40];
        elseif ($playerCount <= 6) $structure = [0.50, 0.30, 0.20];
        elseif ($playerCount <= 9) $structure = [0.40, 0.25, 0.18, 0.17];
        else $structure = [0.35, 0.22, 0.15, 0.10, 0.08, 0.05, 0.05];

        $prizes = [];
        foreach ($structure as $i => $pct) $prizes[$i + 1] = (int) round($prizePool * $pct);

        return ['prizePool' => $prizePool, 'bountyPool' => $bountyPool,
            'bountyPerKill' => $playerCount > 1 ? intdiv($bountyPool, $playerCount - 1) : 0, 'prizes' => $prizes];
    }

    // ── 유저에게 보낼 상태 ──
    public static function getPlayerView(array $state, int $userId): array
    {
        $view = $state;
        unset($view['deck'], $view['actedThisRound']);

        foreach ($view['seats'] as &$seat) {
            if ($seat['id'] !== $userId && $view['status'] !== 'showdown') {
                $seat['cards'] = ['??', '??'];
            }
        }
        return $view;
    }
}
