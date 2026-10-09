<?php

namespace App\Support;

use App\Models\Sweepstakes;
use App\Models\SweepstakesEntry;

/**
 * 추첨 연출(휠/3D 로또 머신 등) 재생용 데이터 생성 헬퍼.
 *
 * [조사 결과 — SweepstakesWinnerService]
 *  - 당첨자는 서버에서 random_int(0, total-1) 로 뽑은 0-기반 "winning_index" 로 결정된다.
 *  - 참가자(sweepstakes_entries)를 id 오름차순으로 나열하고 각자 entries_count 만큼
 *    구간을 누적 배정(0..total-1)한 뒤, winning_index 가 속한 구간의 참가자가 당첨자.
 *  - winning_index / total_entries_at_draw 는 불변 감사 테이블(sweepstakes_winner_audits)에 저장됨.
 *
 * [티켓 번호 정의]
 *  - 티켓은 1..N (N = 추첨 시점 총 Entry 수). winning_ticket = winning_index + 1.
 *  - 감사 레코드가 없는 예외적인 경우(구버전 데이터)에는 당첨자 Entry 구간의 첫 번째 티켓을
 *    사용한다 — id 순 누적 구간 기준으로 항상 동일한 값이 나오는 결정적 값.
 *  - 프론트는 이미 확정된 이 값을 "재생"만 한다. 당첨자 결정은 절대 프론트에서 하지 않는다.
 *
 * 개인정보: 당첨자 표시이름 외 다른 참가자 정보는 내려주지 않는다.
 */
class SweepstakesDrawReplay
{
    public const DRAW_STYLES = ['wheel', 'lottery3d'];

    private const COLOR_KEYS = ['bg_top', 'bg_bottom', 'accent', 'floor', 'glass_tint'];
    private const URL_KEYS = ['logo_url', 'prize_image_url', 'background_image_url'];

    /** 공개 응답에 합쳐 내려줄 추가 필드 (draw_style, theme, draw) */
    public static function extra(Sweepstakes $sweepstakes): array
    {
        $style = $sweepstakes->getAttribute('draw_style');
        $style = in_array($style, self::DRAW_STYLES, true) ? $style : 'wheel';
        $theme = $sweepstakes->getAttribute('theme');

        return [
            'draw_style' => $style,
            'theme' => is_array($theme) ? $theme : null,
            'draw' => self::build($sweepstakes),
        ];
    }

    /** winner_selected 상태일 때만 draw 객체를 만든다. 아니면 null */
    public static function build(Sweepstakes $sweepstakes): ?array
    {
        if ($sweepstakes->status !== 'winner_selected') {
            return null;
        }

        $audit = $sweepstakes->winnerAudit;
        $total = $audit && $audit->total_entries_at_draw > 0
            ? (int) $audit->total_entries_at_draw
            : (int) $sweepstakes->total_entries;
        if ($total <= 0) {
            return null;
        }

        $ticket = null;
        if ($audit && $audit->winning_index !== null) {
            $ticket = (int) $audit->winning_index + 1;
        } elseif ($sweepstakes->winner_user_id) {
            $cumulative = 0;
            $rows = SweepstakesEntry::where('sweepstakes_id', $sweepstakes->id)
                ->orderBy('id')->get(['user_id', 'entries_count']);
            foreach ($rows as $row) {
                if ((int) $row->user_id === (int) $sweepstakes->winner_user_id) {
                    $ticket = $cumulative + 1;
                    break;
                }
                $cumulative += (int) $row->entries_count;
            }
        }
        if ($ticket === null) {
            return null;
        }
        $ticket = max(1, min($total, $ticket));

        $drawnAt = $audit?->selected_at ?? $sweepstakes->winner_selected_at;

        return [
            'winning_ticket' => $ticket,
            'total_tickets' => $total,
            'participants_count' => (int) SweepstakesEntry::where('sweepstakes_id', $sweepstakes->id)->count(),
            'winner_display_name' => (string) ($sweepstakes->winner?->display_name ?? ''),
            'drawn_at' => $drawnAt ? $drawnAt->toIso8601String() : null,
            'algorithm_version' => $audit?->selection_method ?? SweepstakesWinnerService::SELECTION_METHOD,
        ];
    }

    /** theme 입력을 허용된 키만 남기고 정리. 비어 있으면 null */
    public static function sanitizeTheme($theme): ?array
    {
        if (!is_array($theme)) {
            return null;
        }
        $out = [];

        foreach (self::COLOR_KEYS as $k) {
            if (isset($theme[$k]) && self::isHex($theme[$k])) {
                $out[$k] = $theme[$k];
            }
        }
        if (isset($theme['ball_colors']) && is_array($theme['ball_colors'])) {
            $colors = [];
            foreach (array_values($theme['ball_colors']) as $c) {
                if (self::isHex($c)) {
                    $colors[] = $c;
                }
                if (count($colors) >= 8) {
                    break;
                }
            }
            if ($colors) {
                $out['ball_colors'] = $colors;
            }
        }
        foreach (self::URL_KEYS as $k) {
            if (isset($theme[$k]) && self::isSafeUrl($theme[$k])) {
                $out[$k] = $theme[$k];
            }
        }
        if (array_key_exists('show_logo', $theme)) {
            $out['show_logo'] = filter_var($theme['show_logo'], FILTER_VALIDATE_BOOLEAN);
        }

        return $out ?: null;
    }

    private static function isHex($v): bool
    {
        return is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) === 1;
    }

    private static function isSafeUrl($v): bool
    {
        if (!is_string($v) || $v === '' || strlen($v) > 500) {
            return false;
        }
        // 사이트 상대 경로('/'로 시작, '//' 프로토콜 상대 URL 제외) 또는 https URL만 허용
        if ($v[0] === '/' && !str_starts_with($v, '//')) {
            return true;
        }
        return str_starts_with($v, 'https://');
    }
}
