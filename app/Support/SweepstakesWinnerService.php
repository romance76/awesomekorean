<?php

namespace App\Support;

use App\Models\Sweepstakes;
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
