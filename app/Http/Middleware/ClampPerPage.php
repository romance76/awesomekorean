<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * 공개 API 목록 요청의 per_page(한 번에 받을 개수)를 안전한 범위로 맞춘다.
 *
 * 문제였던 것: per_page 에 상한이 없어서 로그인 없이 한 번에 500~1000건(중고장터·Q&A·뉴스는 1~2MB)을 통째로 받아 갈 수 있었고
 * (전체 복제·서버 부하), 음수(-5)를 넣으면 서버 오류(500)가 났다.
 * 규칙: GET 요청만, /api/admin/* 와 개인 북마크(/api/bookmarks, 화면이 한꺼번에 불러옴)는 건드리지 않는다.
 *       숫자가 아니거나 0 이하이면 기본 20, MAX(100)를 넘으면 MAX 로 줄인다.
 */
class ClampPerPage
{
    public const MAX = 100;
    public const DEFAULT = 20;
    /** 이 주소들은 화면이 큰 값을 직접 요청하므로 건드리지 않는다 (앞부분 일치) */
    public const SKIP = ['api/admin', 'api/bookmarks', 'api/music/tracks'];

    /** 순수 계산: 들어온 값 → 쓸 값 (null 이면 그대로 둔다) */
    public static function clamp($raw): ?int
    {
        if ($raw === null) return null;
        if (is_array($raw) || !is_numeric($raw)) return self::DEFAULT;
        $n = (int) $raw;
        if ($n <= 0) return self::DEFAULT;
        return min($n, self::MAX);
    }

    public static function skips(string $path): bool
    {
        $path = ltrim($path, '/');
        foreach (self::SKIP as $p) if (str_starts_with($path, $p)) return true;
        return false;
    }

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET') && $request->query->has('per_page') && !self::skips($request->path())) {
            $v = self::clamp($request->query->all()['per_page'] ?? null);
            if ($v !== null) $request->query->set('per_page', (string) $v);
        }
        return $next($request);
    }
}
