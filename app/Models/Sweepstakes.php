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
        'winner_count', 'prize_tiers', 'prize_mode',
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

    /** 등수 tier 한 건 (없으면 null) — 'tiered' 모드에서만 의미 있음 */
    public function tierForRank(int $rank): ?array
    {
        if (($this->getAttribute('prize_mode') ?? 'same') !== 'tiered') {
            return null;
        }
        $tiers = $this->getAttribute('prize_tiers');
        if (is_array($tiers)) {
            foreach ($tiers as $t) {
                if (is_array($t) && (int) ($t['rank'] ?? 0) === $rank && !empty($t['prize_name'])) {
                    return $t;
                }
            }
        }
        return null;
    }

    /** 등수별 상품명 (prize_tiers 에 없으면 기본 prize_name) */
    public function prizeLabelForRank(int $rank): string
    {
        $t = $this->tierForRank($rank);
        if ($t) {
            return (string) $t['prize_name'];
        }
        // 구버전 데이터(prize_mode 컬럼 없음/미설정)도 tiers 가 있으면 그대로 사용
        if ($this->getAttribute('prize_mode') === null) {
            $tiers = $this->getAttribute('prize_tiers');
            if (is_array($tiers)) {
                foreach ($tiers as $x) {
                    if (is_array($x) && (int) ($x['rank'] ?? 0) === $rank && !empty($x['prize_name'])) {
                        return (string) $x['prize_name'];
                    }
                }
            }
        }
        return (string) $this->prize_name;
    }

    /** 등수별 상품 이미지 (tier 이미지 → 대표 상품 이미지) */
    public function prizeImageForRank(int $rank): ?string
    {
        $t = $this->tierForRank($rank);
        if ($t && !empty($t['prize_image'])) {
            return (string) $t['prize_image'];
        }
        return $this->prize_image ?: null;
    }

    /** 등수별 상품 가치 (tier 값 → 대표 상품 가치) */
    public function prizeValueForRank(int $rank): ?float
    {
        $t = $this->tierForRank($rank);
        if ($t && isset($t['prize_value']) && $t['prize_value'] !== null) {
            return (float) $t['prize_value'];
        }
        return $this->prize_value !== null ? (float) $this->prize_value : null;
    }

    private static function safeUrl($v): ?string
    {
        if (!is_string($v)) {
            return null;
        }
        $v = trim($v);
        if ($v === '' || strlen($v) > 500) {
            return null;
        }
        if ($v[0] === '/' && !str_starts_with($v, '//')) {
            return $v;
        }
        return str_starts_with($v, 'https://') ? $v : null;
    }

    /**
     * 입력된 winner_count / prize_tiers 정리: [winner_count(1..10), prize_tiers|null]
     * 'same' 모드이거나 인원이 1명이면 tiers 는 저장하지 않는다(null).
     */
    public static function sanitizeWinnerConfig($count, $tiers, string $mode = 'tiered'): array
    {
        $count = (int) $count;
        $count = max(1, min(10, $count > 0 ? $count : 1));

        if ($mode !== 'tiered' || $count < 2) {
            return [$count, null];
        }

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
                $value = null;
                if (isset($t['prize_value']) && is_numeric($t['prize_value']) && (float) $t['prize_value'] >= 0) {
                    $value = round((float) $t['prize_value'], 2);
                }
                $out[$rank] = [
                    'rank' => $rank,
                    'prize_name' => mb_substr($name, 0, 255),
                    'prize_value' => $value,
                    'prize_image' => self::safeUrl($t['prize_image'] ?? null),
                ];
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
