<?php

namespace App\Support;

/**
 * 관리자 설정(포인트·Entry·광고) 저장 전 값 검증.
 * 이전에는 어떤 값(음수·문자·거대수·깨진 JSON)도 "저장되었습니다"로 저장되어 회원 화면·결제 계산에 그대로 나갔다.
 * 규칙: 바뀐 값만 검사한다(옛 데이터에 이미 이상한 값이 있어도 화면에서 그대로 저장하다 막히지 않게),
 * 하나라도 틀리면 아무것도 저장하지 않고 항목별 오류 메시지를 돌려준다.
 */
class SettingsValidator
{
    private static function isInt($v): bool
    {
        return is_int($v) || (is_string($v) && preg_match('/^-?\d{1,12}$/', trim($v)));
    }

    private static function inRange($v, $min, $max): bool
    {
        return self::isInt($v) && (int) $v >= $min && (int) $v <= $max;
    }

    /** 포인트 설정 한 칸 검사. 오류 메시지 또는 null */
    public static function point(string $key, $raw): ?string
    {
        $v = is_scalar($raw) || $raw === null ? trim((string) $raw) : null;
        if ($v === null) return '값 형식이 올바르지 않아요';

        // JSON 칸
        if ($key === 'daily_spin_table') {
            $a = json_decode($v, true);
            if (!is_array($a) || !$a || count($a) > 30) return '룰렛 표는 JSON 목록(1~30칸)이어야 해요';
            $w = 0;
            foreach ($a as $r) {
                if (!is_array($r) || !isset($r['value'], $r['weight']) || !self::inRange($r['value'], 0, 1000000) || !self::inRange($r['weight'], 0, 100000)) return '룰렛 칸은 {"value":0이상 정수,"weight":0이상 정수} 형식이어야 해요';
                $w += (int) $r['weight'];
            }
            return $w > 0 ? null : '룰렛 확률(weight) 합이 0보다 커야 해요';
        }
        if ($key === 'purchase_bonus_brackets') {
            $a = json_decode($v, true);
            if (!is_array($a) || count($a) > 30) return '보너스 구간은 JSON 목록(30개 이하)이어야 해요';
            foreach ($a as $r) {
                if (!is_array($r) || !isset($r['min'], $r['max'], $r['bonus_pct']) || !is_numeric($r['min']) || !is_numeric($r['max']) || !is_numeric($r['bonus_pct'])) return '구간은 {"min","max","bonus_pct"} 형식이어야 해요';
                if ((float) $r['min'] < 0 || (float) $r['max'] < (float) $r['min'] || (float) $r['bonus_pct'] < 0 || (float) $r['bonus_pct'] > 100) return '구간의 금액은 0 이상, 끝이 시작보다 크고, 보너스는 0~100% 여야 해요';
            }
            return null;
        }
        if (str_starts_with($key, 'pkg_')) {
            if (!preg_match('/^(\d{1,4}(\.\d{1,2})?)\|(\d{1,7})\|(\d{1,7})$/', $v, $m)) return '패키지는 "가격|포인트|보너스" 형식이어야 해요 (예: 9.99|1000|50)';
            return ((float) $m[1] >= 0.5 && (float) $m[1] <= 5000) ? null : '패키지 가격은 $0.5~$5,000 사이여야 해요';
        }
        if ($key === 'flyer_block_edges') {
            if (!preg_match('/^\d{1,2}(,\d{1,2}){0,30}$/', $v)) return '쉼표로 구분한 시각(0~24)이어야 해요';
            $n = array_map('intval', explode(',', $v));
            foreach ($n as $i => $h) { if ($h < 0 || $h > 24 || ($i > 0 && $h <= $n[$i - 1])) return '시각은 0~24, 오름차순이어야 해요'; }
            return null;
        }
        if (str_ends_with($key, '.rss_source')) {
            return (mb_strlen($v) <= 100 && !preg_match('/[<>]/', $v)) ? null : '출처 이름은 100자 이하, < > 없이 입력해 주세요';
        }
        // 켜기/끄기 칸 (빈 값은 꺼짐)
        if (preg_match('/\.(enabled|auto_fetch|require_approval|allow_[a-z_]+)$/', $key) || in_array($key, ['points_to_game_enabled', 'shopping_review_first_approval'], true)) {
            return in_array($v, ['', '0', '1'], true) ? null : '켜짐(1)/꺼짐(0)만 입력할 수 있어요';
        }
        if ($key === 'elder_monthly_sub') {
            return (preg_match('/^\d{1,4}(\.\d{1,2})?$/', $v) && (float) $v <= 1000) ? null : '월 요금은 0~1000 달러(소수 2자리)여야 해요';
        }
        if (preg_match('/_hour$/', $key) || preg_match('/_(start|end)_hour$/', $key)) {
            return self::inRange($v, 0, 24) ? null : '시각은 0~24 사이 정수여야 해요';
        }
        if (preg_match('/_pct$/', $key)) {
            return self::inRange($v, 0, 1000) ? null : '퍼센트는 0~1000 사이 정수여야 해요';
        }
        // 포인트 증감 칸 (신고 감점·승격 비용처럼 음수가 정상인 칸 포함)
        if (preg_match('/(^|\.)point_/', $key) && !str_ends_with($key, '_daily_max')) {
            return self::inRange($v, -1000000, 1000000) ? null : '포인트는 -1,000,000 ~ 1,000,000 사이 정수여야 해요';
        }
        if (str_starts_with($key, 'game_money.')) {
            return self::inRange($v, 0, 1000000000) ? null : '0 이상 정수여야 해요';
        }
        // 그 밖의 숫자 칸 (한도·가격·횟수·기준 점수)
        return self::inRange($v, 0, 10000000) ? null : '0 이상 10,000,000 이하 정수여야 해요';
    }

    /** 등급 기준 점수(grade_N_min)는 반드시 오름차순 — 현재 값에 이번 변경을 합쳐 검사 */
    public static function gradeOrder(array $current, array $changes): ?string
    {
        $vals = [];
        for ($n = 2; $n <= 15; $n++) {
            $k = "grade_{$n}_min";
            $vals[$n] = (int) ($changes[$k] ?? $current[$k] ?? 0);
        }
        for ($n = 3; $n <= 15; $n++) {
            if ($vals[$n] <= $vals[$n - 1]) return "등급 기준은 점점 커져야 해요 (grade_{$n}_min 이 이전 등급 이하예요)";
        }
        return null;
    }

    /** Entry 설정 한 칸 */
    public static function entry(string $key, $raw): ?string
    {
        $v = is_scalar($raw) || $raw === null ? trim((string) $raw) : null;
        if ($v === null) return '값 형식이 올바르지 않아요';
        return match (true) {
            $key === 'expiry_enabled' => in_array($v, ['0', '1'], true) ? null : '켜짐(1)/꺼짐(0)만 입력할 수 있어요',
            $key === 'expiry_days' => self::inRange($v, 0, 3650) ? null : '유효 일수는 0~3650 사이 정수여야 해요',
            str_ends_with($key, '_required_count') => self::inRange($v, 1, 1000) ? null : '1~1000 사이 정수여야 해요',
            str_ends_with($key, '_daily_max') => self::inRange($v, 0, 100) ? null : '0~100 사이 정수여야 해요',
            default => self::inRange($v, 0, 1000) ? null : '0~1000 사이 정수여야 해요',
        };
    }

    /** 광고 페이지 설정(config): 페이지별 좌/우 슬롯 수와 이름 */
    public static function adConfig($config): ?string
    {
        if (!is_array($config) || !$config || count($config) > 40) return '광고 페이지 설정이 비어 있거나 형식이 올바르지 않아요';
        foreach ($config as $page => $c) {
            if (!is_string($page) || !preg_match('/^[a-z0-9_]{1,30}$/', $page) || !is_array($c)) return "페이지 이름({$page}) 형식이 올바르지 않아요";
            foreach (['left_slots', 'right_slots'] as $f) {
                if (!isset($c[$f]) || !self::inRange($c[$f], 0, 20)) return "{$page}: {$f} 는 0~20 사이 정수여야 해요";
            }
            if (isset($c['label']) && (!is_string($c['label']) || mb_strlen($c['label']) > 30 || preg_match('/[<>]/', $c['label']))) return "{$page}: 이름은 30자 이하, < > 없이 입력해 주세요";
        }
        return null;
    }

    /** 광고 슬롯 최소 가격과 지역 가산 */
    public static function adPrices($prices, $geo): ?string
    {
        if (!is_array($prices) || !$prices || count($prices) > 30) return '가격 표가 비어 있거나 형식이 올바르지 않아요';
        foreach ($prices as $k => $p) {
            if (!is_string($k) || !preg_match('/^[a-z0-9_]{1,30}$/', $k) || !is_numeric($p) || (float) $p < 0 || (float) $p > 100000) return "가격({$k})은 0~100,000 사이 숫자여야 해요";
        }
        if ($geo !== null) {
            if (!is_array($geo) || count($geo) > 30) return '지역 가산 형식이 올바르지 않아요';
            foreach ($geo as $k => $p) {
                if (!is_string($k) || !preg_match('/^[a-z0-9_]{1,30}$/', $k) || !is_numeric($p) || (float) $p < 0 || (float) $p > 1000) return "지역 가산({$k})은 0~1000 사이 숫자여야 해요";
            }
        }
        return null;
    }
}
