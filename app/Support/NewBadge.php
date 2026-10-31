<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * '새로 등록' 표시(N)를 며칠 동안 붙일지 — 사이트 초기엔 글이 드무니 길게, 회원이 늘수록 짧게.
 * 회원 1000명 이하 7일 · 3000명 이하 5일 · 5000명 이하 4일 · 8000명 이하 3일 · 10000명 이하 2일 · 그 이상 1일
 */
class NewBadge
{
    public static function days(): int
    {
        return Cache::remember('new_badge_days', 600, function () {
            $n = User::count();
            if ($n <= 1000) return 7;
            if ($n <= 3000) return 5;
            if ($n <= 5000) return 4;
            if ($n <= 8000) return 3;
            if ($n <= 10000) return 2;
            return 1;
        });
    }
}
