<?php

namespace App\Support;

/**
 * 회원 등급 15단계. users.lifetime_points(차감 없이 누적만 되는 평생 획득량)
 * 기준으로 산정한다. 별도 컬럼에 캐싱하지 않고 항상 즉석 계산하므로
 * 기준점(point_settings의 grade_N_min)을 나중에 바꿔도 전 회원에 즉시 반영된다.
 *
 * 단계마다 프로필 사진 테두리(링) 이미지가 있다: public/images/grades/level_NN.png (640px),
 * 목록/작은 아바타용 level_NN_s.png (256px).
 */
class MemberGrade
{
    public const MAX_LEVEL = 15;

    /** [이름, 아이콘, 기본 기준점(누적 P), 한 줄 설명] — 기준점은 관리자 설정(point_settings grade_N_min)이 우선 */
    private const DEFAULTS = [
        1  => ['새싹',         '🌱', 0,     '이제 막 시작한 새 이웃이에요.'],
        2  => ['브론즈',       '🥉', 30,    '프로필을 채우고 첫 발을 뗀 회원이에요.'],
        3  => ['실버',         '🥈', 100,   '꾸준히 얼굴을 비추는 회원이에요.'],
        4  => ['골드',         '🥇', 250,   '글과 댓글로 활동 흔적을 남기는 회원이에요.'],
        5  => ['로즈',         '🌹', 500,   '이웃들이 알아보기 시작한 믿음직한 회원이에요.'],
        6  => ['자수정',       '🔮', 900,   '보랏빛 왕관을 쓴 커뮤니티의 단골이에요.'],
        7  => ['사파이어',     '💠', 1500,  '푸른 왕관처럼 신뢰받는 활동 회원이에요.'],
        8  => ['아쿠아 월계',  '🌊', 2400,  '월계수를 두른 청록빛 베테랑이에요.'],
        9  => ['에메랄드 월계', '🍀', 3600,  '초록 월계관을 받은 오랜 활동 회원이에요.'],
        10 => ['스타 사파이어', '⭐', 5200,  '별빛을 두른 커뮤니티의 명예 회원이에요.'],
        11 => ['바이올렛 월계', '🌟', 7500,  '1년 가까이 함께한 깊은 베테랑이에요.'],
        12 => ['마젠타 월계',  '🌸', 10500, '이웃을 이끄는 화려한 마스터예요.'],
        13 => ['루비 윙',      '🔥', 15000, '날개를 단 최상위권 회원이에요.'],
        14 => ['블랙 골드',    '🏆', 21000, '손꼽히는 몇 안 되는 그랜드마스터예요.'],
        15 => ['레전드',       '💎', 30000, '사이트의 역사와 함께한 전설이에요.'],
    ];

    /** 한 요청 안에서는 한 번만 계산 (목록에 사용자가 많아도 설정 조회가 반복되지 않게) */
    private static ?array $memo = null;

    public static function flushMemo(): void
    {
        self::$memo = null;
    }

    public static function tiers(): array
    {
        if (self::$memo !== null) return self::$memo;

        $tiers = [];
        $prevMin = -1;
        foreach (self::DEFAULTS as $lvl => [$label, $icon, $defaultMin, $desc]) {
            $min = $lvl === 1 ? 0 : (int) PointRules::get('grade_' . $lvl . '_min', $defaultMin);
            // 관리자가 순서가 뒤집힌 값을 넣어도 등급 판정이 깨지지 않게 항상 이전 단계보다 크게 보정
            if ($lvl > 1 && $min <= $prevMin) $min = $prevMin + 1;
            $prevMin = $min;
            $nn = str_pad((string) $lvl, 2, '0', STR_PAD_LEFT);
            $tiers[$lvl] = [
                'level'      => $lvl,
                'label'      => $label,
                'icon'       => $icon,
                'min'        => $min,
                'desc'       => $desc,
                'ring'       => "/images/grades/level_{$nn}.png",
                'ring_small' => "/images/grades/level_{$nn}_s.png",
            ];
        }
        return self::$memo = $tiers;
    }

    /** 등급 번호만 필요할 때(목록 등) 쓰는 가벼운 계산 */
    public static function levelFor(int $lifetimePoints): int
    {
        $level = 1;
        foreach (self::tiers() as $lvl => $t) {
            if ($lifetimePoints >= $t['min']) $level = $lvl;
        }
        return $level;
    }

    public static function forPoints(int $lifetimePoints): array
    {
        $tiers = static::tiers();
        $level = static::levelFor($lifetimePoints);
        $current = $tiers[$level];
        $next = $tiers[$level + 1] ?? null;
        $progress = $next
            ? (int) round((($lifetimePoints - $current['min']) / max(1, $next['min'] - $current['min'])) * 100)
            : 100;

        return [
            'level' => $level,
            'label' => $current['label'],
            'icon' => $current['icon'],
            'ring' => $current['ring'],
            'ring_small' => $current['ring_small'],
            'min' => $current['min'],
            'next_min' => $next['min'] ?? null,
            'next_label' => $next['label'] ?? null,
            'progress' => max(0, min(100, $progress)),
            'lifetime_points' => $lifetimePoints,
        ];
    }

    /** 공개 안내 페이지용: 15단계 전체 목록 */
    public static function publicList(): array
    {
        return array_values(static::tiers());
    }
}
