<?php

namespace App\Support;

/**
 * 회원 등급 10단계. users.lifetime_points(차감 없이 누적만 되는 평생 획득량)
 * 기준으로 산정한다. 별도 컬럼에 캐싱하지 않고 항상 즉석 계산하므로
 * 기준점(point_settings의 grade_N_min)을 나중에 바꿔도 전 회원에 즉시 반영된다.
 */
class MemberGrade
{
    public static function tiers(): array
    {
        return [
            1  => ['label' => '새싹',       'icon' => '🌱', 'min' => 0],
            2  => ['label' => '초보',       'icon' => '🌿', 'min' => PointRules::get('grade_2_min', 100)],
            3  => ['label' => '일반회원',    'icon' => '🍀', 'min' => PointRules::get('grade_3_min', 300)],
            4  => ['label' => '활동회원',    'icon' => '🌟', 'min' => PointRules::get('grade_4_min', 800)],
            5  => ['label' => '우수회원',    'icon' => '⭐', 'min' => PointRules::get('grade_5_min', 2000)],
            6  => ['label' => '인기회원',    'icon' => '💫', 'min' => PointRules::get('grade_6_min', 5000)],
            7  => ['label' => '베테랑',      'icon' => '🏅', 'min' => PointRules::get('grade_7_min', 10000)],
            8  => ['label' => '마스터',      'icon' => '🏆', 'min' => PointRules::get('grade_8_min', 25000)],
            9  => ['label' => '레전드',      'icon' => '👑', 'min' => PointRules::get('grade_9_min', 50000)],
            10 => ['label' => '명예의 전당', 'icon' => '💎', 'min' => PointRules::get('grade_10_min', 100000)],
        ];
    }

    public static function forPoints(int $lifetimePoints): array
    {
        $tiers = static::tiers();
        $level = 1;
        $current = $tiers[1];
        foreach ($tiers as $lvl => $t) {
            if ($lifetimePoints >= $t['min']) {
                $level = $lvl;
                $current = $t;
            }
        }
        $next = $tiers[$level + 1] ?? null;
        $progress = $next
            ? (int) round((($lifetimePoints - $current['min']) / max(1, $next['min'] - $current['min'])) * 100)
            : 100;

        return [
            'level' => $level,
            'label' => $current['label'],
            'icon' => $current['icon'],
            'min' => $current['min'],
            'next_min' => $next['min'] ?? null,
            'next_label' => $next['label'] ?? null,
            'progress' => max(0, min(100, $progress)),
            'lifetime_points' => $lifetimePoints,
        ];
    }
}
