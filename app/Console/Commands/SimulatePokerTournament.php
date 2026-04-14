<?php

namespace App\Console\Commands;

use App\Services\PokerGameEngine;
use Illuminate\Console\Command;

class SimulatePokerTournament extends Command
{
    protected $signature = 'poker:simulate {players=50} {--verbose} {--max-hands=500}';
    protected $description = 'AI 50명 토너먼트 시뮬레이션 (규칙 검증)';

    private int $violations = 0;
    private int $totalChips = 0;
    private bool $verbose = false;

    public function handle()
    {
        $playerCount = (int) $this->argument('players');
        $maxHands = (int) $this->option('max-hands');
        $this->verbose = $this->option('verbose');

        $this->info("=== 포커 토너먼트 시뮬레이션 ({$playerCount}명) ===");

        // 9명 테이블 + 대기열로 진행
        $tableSize = min($playerCount, 9);
        $waitingPool = [];
        $eliminated = [];
        $startChips = 10000;
        $this->totalChips = $tableSize * $startChips; // 테이블 위의 칩만 추적

        // 플레이어 생성
        $allPlayers = [];
        for ($i = 1; $i <= $playerCount; $i++) {
            $allPlayers[] = [
                'id' => -$i,
                'name' => "AI_{$i}",
                'chips' => $startChips,
            ];
        }

        // 첫 테이블 (9명)
        $tablePlayers = array_slice($allPlayers, 0, $tableSize);
        $waitingPool = array_slice($allPlayers, $tableSize);

        // 게임 생성
        $state = PokerGameEngine::createGame($tablePlayers, [
            'bb' => 20, 'sb' => 10, 'startChips' => $startChips, 'turnTime' => 10,
        ]);
        $gameId = $state['gameId'];

        $this->info("게임 ID: {$gameId}, 테이블: {$tableSize}명, 대기: " . count($waitingPool) . "명");

        // 칩 보존 검증
        $this->verifyChipConservation($state, 'initial');

        $handCount = 0;

        while ($handCount < $maxHands) {
            $state = PokerGameEngine::getGameState($gameId);
            if (!$state) { $this->error("게임 상태 없음!"); break; }

            // 핸드 진행
            $handCount++;
            $actionCount = 0;
            $maxActions = 100; // 무한루프 방지

            while ($state['status'] === 'playing' && $actionCount < $maxActions) {
                $actIdx = $state['actIdx'];
                $seat = $state['seats'][$actIdx] ?? null;

                if (!$seat) {
                    $this->violation("actIdx={$actIdx} 좌석 없음 (핸드 #{$handCount})");
                    break;
                }

                // AI 액션
                $result = PokerGameEngine::processAITurn($gameId);
                if (!$result) {
                    // 유저 턴이면 자동 폴드/체크
                    $toCall = max(0, $state['betLevel'] - $seat['bet']);
                    $action = $toCall === 0 ? 'check' : 'fold';
                    $result = PokerGameEngine::processAction($gameId, $seat['id'], $action);
                }

                if (isset($result['error'])) {
                    $this->violation("액션 에러: {$result['error']} (핸드 #{$handCount}, seat={$actIdx}, id={$seat['id']})");
                    break;
                }

                $state = $result;
                $actionCount++;
            }

            if ($actionCount >= $maxActions) {
                $this->violation("핸드 #{$handCount}: 무한루프 (액션 {$maxActions}번 초과)");
                // 강제 진행
                $state = PokerGameEngine::getGameState($gameId);
                if ($state && $state['status'] === 'playing') {
                    // 강제 쇼다운
                    $state['status'] = 'showdown';
                    $state['stage'] = 'result';
                    PokerGameEngine::saveGameState($gameId, $state);
                }
            }

            // 쇼다운 결과 확인
            $state = PokerGameEngine::getGameState($gameId);
            if ($state['status'] === 'showdown') {
                if ($this->verbose && $state['result']) {
                    $winners = collect($state['result']['winners'] ?? [])->pluck('name')->implode(', ');
                    $hand = $state['result']['winners'][0]['hand'] ?? '-';
                    $pot = $state['result']['winners'][0]['pot'] ?? 0;
                    $this->line("  핸드 #{$handCount}: {$winners} 승리 ({$hand}) +{$pot}");
                }
            }

            // 칩 보존 검증
            $this->verifyChipConservation($state, "핸드 #{$handCount} 후");

            // 다음 핸드
            $nextState = PokerGameEngine::nextHand($gameId);
            if (!$nextState) {
                $this->error("nextHand 실패 (핸드 #{$handCount})");
                break;
            }

            // 토너먼트 종료?
            if ($nextState['status'] === 'finished') {
                $this->info("=== 토너먼트 종료! (핸드 #{$handCount}) ===");
                $this->showFinalResults($nextState, $eliminated);
                break;
            }

            // 탈락자 처리
            $newEliminated = [];
            foreach ($nextState['seats'] as $s) {
                if ($s['isOut'] && !in_array($s['id'], array_column($eliminated, 'id'))) {
                    $eliminated[] = ['id' => $s['id'], 'name' => $s['name'], 'hand' => $handCount];
                    $newEliminated[] = $s['name'];
                }
            }

            if (!empty($newEliminated)) {
                $this->info("  [탈락] " . implode(', ', $newEliminated) . " (핸드 #{$handCount})");

                // 대기열에서 보충
                $aliveOnTable = count(array_filter($nextState['seats'], fn($s) => !$s['isOut']));
                while ($aliveOnTable < 9 && !empty($waitingPool)) {
                    // 빈 자리(isOut)에 새 플레이어 추가
                    $newPlayer = array_shift($waitingPool);
                    foreach ($nextState['seats'] as $i => &$seat) {
                        if ($seat['isOut']) {
                            $seat['id'] = $newPlayer['id'];
                            $seat['name'] = $newPlayer['name'];
                            $seat['chips'] = $startChips;
                            $seat['isOut'] = false;
                            $seat['folded'] = true; // 이번 핸드는 참여 안 함
                            $seat['cards'] = [];
                            $this->totalChips += $startChips;
                            if ($this->verbose) $this->line("  [보충] {$newPlayer['name']} → 좌석 {$i}");
                            $aliveOnTable++;
                            break;
                        }
                    }
                    unset($seat);
                }

                PokerGameEngine::saveGameState($gameId, $nextState);
            }

            // 블라인드 레벨업 보고
            if ($nextState['blindLevelUp'] ?? false) {
                $this->info("  [블라인드 UP] {$nextState['sb']}/{$nextState['bb']}");
            }

            // 딜러 위치 검증
            $this->verifyDealerPosition($nextState, $handCount);

            $state = $nextState;
        }

        if ($handCount >= $maxHands) {
            $this->warn("최대 핸드 수({$maxHands}) 도달. 시뮬레이션 종료.");
            $state = PokerGameEngine::getGameState($gameId);
            if ($state) $this->showCurrentState($state, $eliminated);
        }

        // 최종 결과
        $this->newLine();
        $this->info("=== 시뮬레이션 결과 ===");
        $this->info("총 핸드: {$handCount}");
        $this->info("탈락: " . count($eliminated) . "명");
        $this->info("대기열 남음: " . count($waitingPool) . "명");

        if ($this->violations > 0) {
            $this->error("규칙 위반: {$this->violations}건");
        } else {
            $this->info("규칙 위반: 0건 ✓");
        }

        PokerGameEngine::deleteGameState($gameId);
        return $this->violations > 0 ? 1 : 0;
    }

    private function verifyChipConservation(array $state, string $context): void
    {
        $totalOnTable = $state['pot'];
        foreach ($state['seats'] as $s) {
            if (!$s['isOut']) {
                $totalOnTable += $s['chips'] + ($s['bet'] ?? 0);
            }
        }

        if ($totalOnTable !== $this->totalChips) {
            $this->violation("칩 보존 실패 @ {$context}: 기대={$this->totalChips}, 실제={$totalOnTable} (차이=" . ($totalOnTable - $this->totalChips) . ")");
        }
    }

    private function verifyDealerPosition(array $state, int $handNum): void
    {
        $dealerIdx = $state['dealerIdx'];
        $sbIdx = $state['sbIdx'] ?? null;
        $bbIdx = $state['bbIdx'] ?? null;

        if ($sbIdx === null || $bbIdx === null) {
            $this->violation("핸드 #{$handNum}: sbIdx/bbIdx 없음");
            return;
        }

        // 딜러가 살아있는지
        if ($state['seats'][$dealerIdx]['isOut']) {
            $this->violation("핸드 #{$handNum}: 딜러(좌석 {$dealerIdx})가 탈락 상태");
        }

        // SB/BB가 살아있는지
        if ($state['seats'][$sbIdx]['isOut']) {
            $this->violation("핸드 #{$handNum}: SB(좌석 {$sbIdx})가 탈락 상태");
        }
        if ($state['seats'][$bbIdx]['isOut']) {
            $this->violation("핸드 #{$handNum}: BB(좌석 {$bbIdx})가 탈락 상태");
        }
    }

    private function violation(string $msg): void
    {
        $this->violations++;
        $this->error("[VIOLATION] {$msg}");
    }

    private function showFinalResults(array $state, array $eliminated): void
    {
        $ranking = $state['finalRanking'] ?? [];
        foreach ($ranking as $r) {
            $this->info("  🏆 {$r['place']}등: {$r['name']} ({$r['chips']}칩)");
        }

        // 탈락 순서 (역순 = 나중에 탈락 = 더 높은 순위)
        $elimReversed = array_reverse($eliminated);
        $place = count($ranking) + 1;
        foreach ($elimReversed as $e) {
            if ($this->verbose) {
                $this->line("  {$place}등: {$e['name']} (핸드 #{$e['hand']}에서 탈락)");
            }
            $place++;
        }
    }

    private function showCurrentState(array $state, array $eliminated): void
    {
        $alive = array_filter($state['seats'], fn($s) => !$s['isOut']);
        $this->info("남은 플레이어:");
        foreach ($alive as $s) {
            $this->line("  {$s['name']}: {$s['chips']}칩");
        }
    }
}
