<?php

namespace App\Http\Controllers;

use App\Models\InfoPost;
use Illuminate\Http\Response;

// 어썸코리안 본 사이트엔 sitemap.xml이 아예 없었음(SPA 캐치올이 모든 경로를
// HTML로 응답) — 우선 '정보' 탭 글들부터 제공. 나머지 섹션(커뮤니티/중고장터 등)은
// 로그인 필요/휘발성 콘텐츠라 검색엔진 색인 대상이 아니라서 범위에서 제외.
class SitemapController extends Controller
{
    public function index(): Response
    {
        $posts = InfoPost::published()->orderByDesc('published_at')->get(['slug', 'updated_at']);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= '  <url><loc>' . e(url('/')) . '</loc></url>' . "\n";
        $xml .= '  <url><loc>' . e(route('info.index')) . '</loc></url>' . "\n";

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
