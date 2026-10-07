<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InfoPost;
use App\Support\Analytics;
use Illuminate\Support\Facades\Http;

/** 관리자 "방문 분석": 구글 애널리틱스 켜짐 상태와, 사이트 전체 페이지에 추적 코드가 실제로 들어갔는지 검사 */
class AdminAnalyticsController extends Controller
{
    // 화면(Vue) 쪽 주요 주소 — 전부 같은 HTML 껍데기를 쓰지만, 서버가 라우트마다 정상 응답하는지도 함께 본다
    private const SPA_PATHS = ['/', '/market', '/realestate', '/jobs', '/directory', '/clubs', '/news', '/recipes', '/community', '/events', '/shopping', '/games', '/music', '/shorts', '/stocks', '/groupbuy', '/login', '/register'];

    public function status()
    {
        $id = Analytics::measurementId();
        return response()->json(['success' => true, 'data' => ['enabled' => (bool) $id, 'measurement_id' => $id]]);
    }

    public function check()
    {
        Analytics::forget();
        $id = Analytics::measurementId();
        if (!$id) {
            return response()->json(['success' => true, 'data' => [
                'enabled' => false, 'measurement_id' => null, 'total' => 0, 'with_tag' => 0, 'missing' => [],
                'message' => '측정 ID가 등록되어 있지 않아요. 관리자 › 설정 › API 키 관리에서 서비스 코드 google_analytics 로 G- 로 시작하는 측정 ID를 등록하세요.',
            ]]);
        }

        $base = rtrim(config('app.url'), '/');
        $urls = array_map(fn ($p) => $base . $p, self::SPA_PATHS);
        $urls[] = $base . '/info';
        foreach (InfoPost::published()->orderByDesc('published_at')->limit(40)->pluck('slug') as $slug) {
            $urls[] = $base . '/info/' . rawurlencode($slug);
        }

        $needle = 'googletagmanager.com/gtag/js?id=' . $id;
        $withTag = 0;
        $missing = [];
        foreach (array_chunk($urls, 10) as $chunk) {
            $responses = Http::pool(fn ($pool) => array_map(
                fn ($u) => $pool->as($u)->timeout(15)->withHeaders(['User-Agent' => 'AwesomeKoreanSiteCheck/1.0'])->get($u),
                $chunk
            ));
            foreach ($chunk as $u) {
                $r = $responses[$u] ?? null;
                $ok = $r instanceof \Illuminate\Http\Client\Response && $r->successful()
                    && str_contains($r->body(), $needle) && str_contains($r->body(), "gtag('config', '{$id}')");
                if ($ok) $withTag++;
                else $missing[] = ['url' => $u, 'status' => $r instanceof \Illuminate\Http\Client\Response ? $r->status() : 0];
            }
        }

        return response()->json(['success' => true, 'data' => [
            'enabled' => true, 'measurement_id' => $id, 'total' => count($urls), 'with_tag' => $withTag, 'missing' => $missing,
            'message' => $missing ? '일부 페이지에서 추적 코드를 찾지 못했어요.' : '검사한 모든 페이지에 추적 코드가 들어 있어요.',
        ]]);
    }
}
