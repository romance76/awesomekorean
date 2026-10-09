<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sweepstakes extends Model
{
    protected $table = 'sweepstakes';

    protected $fillable = [
        'event_id', 'title', 'description', 'prize_name', 'prize_value', 'prize_image',
        'start_at', 'end_at', 'status',
        'minimum_age', 'eligible_regions', 'official_rules_url',
        'no_purchase_required_text', 'terms_version',
        'draw_style', 'theme',
        'winner_count', 'prize_tiers',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'winner_selected_at' => 'datetime',
        'prize_value' => 'decimal:2',
        'total_entries' => 'integer',
        'minimum_age' => 'integer',
        'eligible_regions' => 'array',
        'theme' => 'array',
        'winner_count' => 'integer',
        'prize_tiers' => 'array',
    ];

    public function entries()
    {
        return $this->hasMany(SweepstakesEntry::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    public function winnerAudit()
    {
        return $this->hasOne(SweepstakesWinnerAudit::class);
    }

    /** 다중 당첨자 (등수 오름차순). 단일 당첨자 추첨은 이 테이블에 행이 없다 */
    public function winners()
    {
        return $this->hasMany(SweepstakesWinner::class)->orderBy('rank');
    }

    /** 등수별 상품명 (prize_tiers 에 없으면 기본 prize_name) */
    public function prizeLabelForRank(int $rank): string
    {
        $tiers = $this->getAttribute('prize_tiers');
        if (is_array($tiers)) {
            foreach ($tiers as $t) {
                if (is_array($t) && (int) ($t['rank'] ?? 0) === $rank && !empty($t['prize_name'])) {
                    return (string) $t['prize_name'];
                }
            }
        }
        return (string) $this->prize_name;
    }

    /** 입력된 winner_count / prize_tiers 정리: [winner_count(1..10), prize_tiers|null] */
    public static function sanitizeWinnerConfig($count, $tiers): array
    {
        $count = (int) $count;
        $count = max(1, min(10, $count > 0 ? $count : 1));

        if (is_string($tiers)) {
            $decoded = json_decode($tiers, true);
            $tiers = is_array($decoded) ? $decoded : null;
        }
        $out = [];
        if (is_array($tiers)) {
            foreach ($tiers as $t) {
                if (!is_array($t)) {
                    continue;
                }
                $rank = (int) ($t['rank'] ?? 0);
                $name = isset($t['prize_name']) && is_string($t['prize_name']) ? trim($t['prize_name']) : '';
                if ($rank < 1 || $rank > $count || $name === '' || isset($out[$rank])) {
                    continue;
                }
                $out[$rank] = ['rank' => $rank, 'prize_name' => mb_substr($name, 0, 255)];
            }
            ksort($out);
        }

        return [$count, $out ? array_values($out) : null];
    }

    public function isOpenForEntries(): bool
    {
        return $this->status === 'active'
            && now()->between($this->start_at, $this->end_at);
    }
}
