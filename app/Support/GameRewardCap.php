<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * 게임 포인트 하루 지급 한도.
 *
 * 게임 점수·결과는 클라이언트(브라우저)가 "이겼다/신기록이다"라고 알려 주는 값이라, 같은 요청을 반복해서 보내면
 * 포인트를 무한히 쌓을 수 있었다(POST /api/games/scores, /api/games/result). 서버 쪽에서 한 사람이 하루에 게임으로
 * 받을 수 있는 포인트의 총량을 제한한다(뉴욕 시간 자정 기준). 기록(리더보드)은 그대로 저장되고, 한도를 넘으면 포인트만 안 준다.
 *
 * 한도는 관리자 포인트 설정(point_settings)의 game_daily_cap 으로 바꿀 수 있고, 없으면 기본 50P. 0 이면 게임 포인트를 주지 않는다.
 */
class GameRewardCap
{
    public const DEFAULT_CAP = 50;

    /** 순수 계산: 오늘 이미 받은 $used, 받으려는 $want, 한도 $cap → 지금 줄 수 있는 포인트(0 이상, want 이하) */
    public static function allowed(int $used, int $want, int $cap): int
    {
        return max(0, min($want, $cap - $used));
    }

    /** $want 포인트를 주려 할 때 한도 안에서 실제로 줄 수 있는 만큼을 돌려주고, 그만큼 오늘 사용량에 기록한다 */
    public static function grant(int $userId, int $want): int
    {
        if ($want <= 0) return 0;
        $cap = max(0, (int) PointRules::raw('game_daily_cap', (string) self::DEFAULT_CAP));
        $key = 'game_pts:' . $userId . ':' . now('America/New_York')->format('Ymd');
        $used = (int) Cache::get($key, 0);
        $ok = self::allowed($used, $want, $cap);
        if ($ok > 0) Cache::put($key, $used + $ok, 172800);
        return $ok;
    }
}
