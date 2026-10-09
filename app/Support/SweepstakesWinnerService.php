<?php

namespace App\Support;

use App\Models\Notification;
use App\Models\Sweepstakes;
use App\Models\SweepstakesWinner;
use App\Models\SweepstakesWinnerAudit;
use Illuminate\Support\Facades\DB;

/**
 * 당첨자 선정 — 반드시 서버에서, random_int()(CSPRNG)로만 수행한다.
 * 룰렛 애니메이션은 이미 결정된 이 결과를 보여주는 용도일 뿐, 당첨자
 * 선정 로직 자체와는 완전히 분리되어 있다(프론트는 이 메서드의 결과를
 * 받아서 재생만 함).
 */
class SweepstakesWinnerService
{
    public const SELECTION_METHOD = 'random_int_cumulative_v1';

    public const SELECTION_METHOD_RANKED = 'random_int_cumulative_ranked_v1';

    /**
     * 등수별 다중 당첨자 선정 (winner_count > 1).
     * - 한 트랜잭션 안에서 N등 → 1등 순서로 모두 확정(프론트 공개 순서와 동일).
     * - 한 사람은 한 등수만 당첨(이미 뽑힌 사용자는 이후 추첨 풀에서 제외).
     * - 티켓 번호는 "원래 전체 Entry 구간(0..total-1)" 기준으로 기록해, 당첨자 구간 안의
     *   실제 티켓 하나를 가리키도록 한다.
     * - 참가자 수 < winner_count 이면 참가자 수만큼만(1..M등) 선정.
     * - sweepstakes.winner_user_id / winner_selected_at / status 는 1등 기준으로 기록하고,
     *   기존 감사 테이블에도 1등 레코드를 남긴다(기존 경로 호환).
     *
     * @return array{audit: SweepstakesWinnerAudit, winners: \Illuminate\Support\Collection}
     */
    public static function selectWinners(Sweepstakes $sweepstakes): array
    {
        $result = DB::transaction(function () use ($sweepstakes) {
            $locked = Sweepstakes::whereKey($sweepstakes->id)->lockForUpdate()->first();

            if ($locked->status === 'winner_selected' || $locked->winner_user_id) {
                throw new \RuntimeException('이미 당첨자가 선정된 Sweepstakes입니다. 결과는 변경할 수 없습니다.');
            }

            $total = (int) $locked->total_entries;
            if ($total <= 0) {
                throw new \RuntimeException('참가 Entry가 없어 당첨자를 선정할 수 없습니다.');
            }

            $entries = $locked->entries()->orderBy('id')->get(['user_id', 'entries_count']);

            $wanted = max(1, min(10, (int) $locked->winner_count));
            $ranksToDraw = min($wanted, $entries->where('entries_count', '>', 0)->count());
            if ($ranksToDraw < 1) {
                throw new \RuntimeException('참가 Entry가 없어 당첨자를 선정할 수 없습니다.');
            }

            $selectedAt = now();
            $excluded = [];   // user_id => true
            $picked = [];     // rank => [user_id, winning_index]

            // N등(마지막 등수)부터 1등 순서로 뽑는다
            for ($rank = $ranksToDraw; $rank >= 1; $rank--) {
                $remaining = 0;
                foreach ($entries as $row) {
                    if (!isset($excluded[$row->user_id])) {
                        $remaining += (int) $row->entries_count;
                    }
                }
                if ($remaining <= 0) {
                    throw new \RuntimeException('당첨자 계산에 실패했습니다 (남은 Entry 없음).');
                }

                $r = random_int(0, $remaining - 1);

                $reducedCum = 0;   // 제외자를 뺀 누적
                $originalCum = 0;  // 원래 전체 구간 누적
                $winnerUserId = null;
                $globalIndex = null;
                foreach ($entries as $row) {
                    $count = (int) $row->entries_count;
                    if (isset($excluded[$row->user_id])) {
                        $originalCum += $count;
                        continue;
                    }
                    if ($r < $reducedCum + $count) {
                        $winnerUserId = $row->user_id;
                        $globalIndex = $originalCum + ($r - $reducedCum);
                        break;
                    }
                    $reducedCum += $count;
                    $originalCum += $count;
                }

                if ($winnerUserId === null) {
                    throw new \RuntimeException('당첨자 계산에 실패했습니다 (entries_count 합계 불일치).');
                }

                $excluded[$winnerUserId] = true;
                $picked[$rank] = ['user_id' => $winnerUserId, 'winning_index' => $globalIndex];
            }

            $rows = collect();
            foreach ($picked as $rank => $p) {
                $rows->push(SweepstakesWinner::create([
                    'sweepstakes_id' => $locked->id,
                    'rank' => $rank,
                    'user_id' => $p['user_id'],
                    'winning_index' => $p['winning_index'],
                    'total_entries_at_draw' => $total,
                    'prize_label' => $locked->prizeLabelForRank($rank),
                    'selection_method' => self::SELECTION_METHOD_RANKED,
                    'selected_at' => $selectedAt,
                ]));
            }
            $rows = $rows->sortBy('rank')->values();

            $first = $picked[1];
            $locked->forceFill([
                'status' => 'winner_selected',
                'winner_user_id' => $first['user_id'],
                'winner_selected_at' => $selectedAt,
            ])->save();

            $audit = SweepstakesWinnerAudit::create([
                'sweepstakes_id' => $locked->id,
                'winner_user_id' => $first['user_id'],
                'winning_index' => $first['winning_index'],
                'total_entries_at_draw' => $total,
                'selection_method' => self::SELECTION_METHOD_RANKED,
                'selected_at' => $selectedAt,
            ]);

            return ['audit' => $audit, 'winners' => $rows, 'sweepstakes' => $locked];
        });

        // 알림은 트랜잭션 확정 후 — 실패해도 추첨 결과에는 영향 없음
        try {
            $s = $result['sweepstakes'];
            foreach ($result['winners'] as $w) {
                $label = $w->prize_label ?: $s->prize_name;
                Notification::create([
                    'user_id' => $w->user_id,
                    'type' => 'sweepstakes_winner',
                    'title' => "🎉 {$w->rank}등에 당첨되었습니다",
                    'content' => "🎉 {$w->rank}등에 당첨되었습니다 — {$label}",
                    'data' => ['sweepstakes_id' => $s->id, 'event_id' => $s->event_id, 'rank' => $w->rank, 'url' => $s->event_id ? '/events/' . $s->event_id : null],
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('경품 추첨 당첨 알림 실패: ' . $e->getMessage());
        }

        return ['audit' => $result['audit'], 'winners' => $result['winners']];
    }

    public static function selectWinner(Sweepstakes $sweepstakes): SweepstakesWinnerAudit
    {
        return DB::transaction(function () use ($sweepstakes) {
            $locked = Sweepstakes::whereKey($sweepstakes->id)->lockForUpdate()->first();

            if ($locked->status === 'winner_selected' || $locked->winner_user_id) {
                throw new \RuntimeException('이미 당첨자가 선정된 Sweepstakes입니다. 결과는 변경할 수 없습니다.');
            }

            $total = (int) $locked->total_entries;
            if ($total <= 0) {
                throw new \RuntimeException('참가 Entry가 없어 당첨자를 선정할 수 없습니다.');
            }

            // user_id 두 번째 기준은 동률(동시 생성) 상황에서 순서를 결정적으로
            // 만들기 위함 — id 하나만으로도 충분하지만 안전하게 추가.
            $entries = $locked->entries()->orderBy('id')->get(['user_id', 'entries_count']);

            // 각 Entry가 동일한 당첨 확률을 갖도록: 0..total-1 구간을 참가자별로
            // entries_count만큼 순서대로 나눠 배정하고, 그 구간 중 하나를
            // CSPRNG로 균등하게 뽑는다.
            $winningIndex = random_int(0, $total - 1);

            $cumulative = 0;
            $winnerUserId = null;
            foreach ($entries as $row) {
                $cumulative += (int) $row->entries_count;
                if ($winningIndex < $cumulative) {
                    $winnerUserId = $row->user_id;
                    break;
                }
            }

            if ($winnerUserId === null) {
                // total_entries 비정규화 카운터와 실제 합이 어긋난 경우를 대비한
                // 방어 코드 — 정상 흐름에서는 도달하지 않아야 함.
                throw new \RuntimeException('당첨자 계산에 실패했습니다 (entries_count 합계 불일치).');
            }

            $selectedAt = now();
            // winner_user_id/winner_selected_at은 당첨자 선정 로직 외 경로로
            // 설정되면 안 되므로 의도적으로 $fillable에서 뺐음 — forceFill로 설정.
            $locked->forceFill([
                'status' => 'winner_selected',
                'winner_user_id' => $winnerUserId,
                'winner_selected_at' => $selectedAt,
            ])->save();

            return SweepstakesWinnerAudit::create([
                'sweepstakes_id' => $locked->id,
                'winner_user_id' => $winnerUserId,
                'winning_index' => $winningIndex,
                'total_entries_at_draw' => $total,
                'selection_method' => self::SELECTION_METHOD,
                'selected_at' => $selectedAt,
            ]);
        });
    }
}
