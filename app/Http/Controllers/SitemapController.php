<?php

namespace App\Http\Controllers;

use App\Models\InfoPost;
use Illuminate\Http\Response;

// 어썸코리안 본 사이트엔 sitemap.xml이 아예 없었음(SPA 캐치올이 모든 경로를
// HTML로 응답) — 우선 '정보' 탭 글들부터 제공했다. 주요 메뉴의 목록 화면(로그인 없이 볼 수 있는 공개 화면)은
// 10/14 에 추가: SECTION_PAGES. 지식인에서 가져온 Q&A·수집 매물(중고장터/부동산)·번역 뉴스는 처리 방침이 정해질 때까지 뺀다.
class SitemapController extends Controller
{
    /** 사이트맵에 넣는 공개 메뉴 화면 (목록 화면만, 글·매물 하나하나는 넣지 않는다) */
    public const SECTION_PAGES = ['/community', '/jobs', '/directory', '/events', '/clubs', '/groupbuy', '/about', '/contact', '/terms', '/privacy', '/email-policy', '/legal'];

    public function index(): Response
    {
        $posts = InfoPost::published()->orderByDesc('published_at')->get(['slug', 'updated_at']);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= '  <url><loc>' . e(url('/')) . '</loc></url>' . "\n";
        $xml .= '  <url><loc>' . e(route('info.index')) . '</loc></url>' . "\n";
        foreach (self::SECTION_PAGES as $path) {
            $xml .= '  <url><loc>' . e(url($path)) . '</loc></url>' . "\n";
        }

        foreach ($posts as $post) {
            $xml .= '  <url>';
            $xml .= '<loc>' . e(route('info.show', $post->slug)) . '</loc>';
            $xml .= '<lastmod>' . $post->updated_at->toAtomString() . '</lastmod>';
            $xml .= '</url>' . "\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
