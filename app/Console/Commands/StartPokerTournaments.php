<?php

namespace App\Console\Commands;

use App\Events\PokerTournamentStart;
use App\Events\PokerTournamentUpdate;
use App\Models\PokerTournament;
use App\Models\PokerTournamentEntry;
use App\Models\PokerWallet;
use App\Services\PokerGameEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StartPokerTournaments extends Command
{
    protected $signature = 'poker:start-tournaments';
    protected $description = '시간이 된 토너먼트를 자동 시작하고 테이블에 배정';

    public function handle()
    {
        // 시작 시간이 지난 scheduled/registering 토너먼트 찾기
        $tournaments = PokerTournament::where('is_template', false)
            ->whereIn('status', ['scheduled', 'registering'])
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($tournaments as $tournament) {
            $this->processTournament($tournament);
        }

        if ($tournaments->count()) {
            $this->info("처리 완료: {$tournaments->count()}개 토너먼트");
        }
    }

    private function processTournament(PokerTournament $tournament)
    {
        $entries = PokerTournamentEntry::where('tournament_id', $tournament->id)
            ->where('status', 'registered')
            ->get();

        $playerCount = $entries->count();
        $minPlayers = $tournament->min_players ?? 2;

        // 최소 인원 미달 → 5분 유예 후 취소
        if ($playerCount < $minPlayers) {
            $gracePeriod = $tournament->scheduled_at->addMinutes(5);

            if (now()->gt($gracePeriod)) {
                // 유예 기간 지남 → 취소 + 환불
                $this->cancelAndRefund($tournament, $entries);
                $this->warn("토너먼트 #{$tournament->id} '{$tournament->title}' 취소 (인원 부족: {$playerCount}/{$minPlayers})");
            } else {
                // 아직 유예 기간 → 1명이라도 있으면 AI로 채워서 시작
                if ($playerCount >= 1) {
                    $this->startTournament($tournament, $entries);
                }
            }
            return;
        }

        // 충분한 인원 → 즉시 시작
        $this->startTournament($tournament, $entries);
    }

    private function startTournament(PokerTournament $tournament, $entries)
    {
        $this->info("토너먼트 시작: #{$tournament->id} '{$tournament->title}' ({$entries->count()}명)");

        // 상태 변경
        $tournament->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        // 플레이어 목록 준비
        $players = [];
        foreach ($entries as $entry) {
            $players[] = [
                'id' => $entry->user_id,
                'name' => $entry->user->nickname ?? $entry->user->name ?? '플레이어',
                'chips' => $tournament->starting_chips,
                'entryId' => $entry->id,
            ];
            $entry->update(['status' => 'playing']);
        }

        // AI로 빈 자리 채우기 (최소 4명 테이블)
        $aiNames = [
            ['name' => '대니얼', 'style' => 'TAG'], ['name' => '소피아', 'style' => 'LAG'],
            ['name' => '재민', 'style' => 'TP'], ['name' => '린다', 'style' => 'LP'],
            ['name' => '마이크', 'style' => 'maniac'], ['name' => '유나', 'style' => 'balanced'],
            ['name' => '빅터', 'style' => 'nit'], ['name' => '하나', 'style' => 'tricky'],
        ];
        shuffle($aiNames);
        $aiIdx = 0;
        $targetCount = max(count($players) + 1, 4); // 최소 4명
        $targetCount = min($targetCount, 9); // 최대 9명

        while (count($players) < $targetCount && $aiIdx < count($aiNames)) {
            $ai = $aiNames[$aiIdx++];
            $players[] = [
                'id' => -$aiIdx,
                'name' => $ai['name'] . ' (AI)',
                'chips' => $tournament->starting_chips,
                'isAI' => true,
                'style' => $ai['style'],
            ];
        }

        // 블라인드 스케줄
        $blindSchedule = [
            ['sb' => 10, 'bb' => 20, 'ante' => 0, 'duration' => 10],
            ['sb' => 20, 'bb' => 40, 'ante' => 0, 'duration' => 10],
            ['sb' => 30, 'bb' => 60, 'ante' => 0, 'duration' => 10],
            ['sb' => 50, 'bb' => 100, 'ante' => 10, 'duration' => 10],
            ['sb' => 75, 'bb' => 150, 'ante' => 15, 'duration' => 10],
            ['sb' => 100, 'bb' => 200, 'ante' => 20, 'duration' => 8],
            ['sb' => 150, 'bb' => 300, 'ante' => 30, 'duration' => 8],
            ['sb' => 200, 'bb' => 400, 'ante' => 40, 'duration' => 6],
            ['sb' => 300, 'bb' => 600, 'ante' => 60, 'duration' => 6],
            ['sb' => 500, 'bb' => 1000, 'ante' => 100, 'duration' => 5],
        ];

        // 게임 생성
        $config = [
            'bb' => $blindSchedule[0]['bb'],
            'sb' => $blindSchedule[0]['sb'],
            'startChips' => $tournament->starting_chips,
            'turnTime' => 15,
            'type' => 'tournament',
            'tournamentId' => $tournament->id,
            'blindSchedule' => $blindSchedule,
            'blindLevel' => 0,
            'blindStartedAt' => time(),
            'bountyPct' => $tournament->bounty_pct ?? 10,
        ];

        $state = PokerGameEngine::createGame($players, $config);

        // 토너먼트와 게임 ID 연결 (캐시에 저장)
        Cache::put("poker_tournament_game_{$tournament->id}", $state['gameId'], 86400);
        Cache::put("poker_game_tournament_{$state['gameId']}", $tournament->id, 86400);

        // WebSocket으로 모든 참가자에게 알림
        broadcast(new PokerTournamentStart(
            $tournament,
            $state['gameId'],
            $entries->pluck('user_id')->toArray()
        ));

        // 로비도 업데이트
        broadcast(new PokerTournamentUpdate($tournament->fresh()->loadCount([
            'entries as registered_count',
            'entries as online_count' => fn($q) => $q->where('is_online', true),
        ])));

        $this->info("  → 게임 생성: {$state['gameId']}, 플레이어 {$state['seats'][0]['name']} 외 " . (count($state['seats']) - 1) . "명");
    }

    private function cancelAndRefund(PokerTournament $tournament, $entries)
    {
        // 환불
        foreach ($entries as $entry) {
            if ($tournament->buy_in > 0) {
                $wallet = PokerWallet::firstOrCreate(
                    ['user_id' => $entry->user_id],
                    ['chips_balance' => 0, 'total_deposited' => 0, 'total_withdrawn' => 0]
                );
                $wallet->deposit($tournament->buy_in, "토너먼트 자동취소 환불: {$tournament->title}");
            }
            $entry->delete();
        }

        $tournament->update(['status' => 'cancelled']);

        broadcast(new PokerTournamentUpdate($tournament->fresh()));
    }
}
