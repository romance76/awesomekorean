<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// realestate:scrape가 간혹 가로 120px짜리 초소형 썸네일만 수집해버린 매물(실측 확인,
// rdcpix.com CDN의 '...숫자+s.jpg' 패턴)을 1회성으로 정리. 코드 쪽은 이미
// ScrapeRealEstateListings::extractPhotos()에서 이 패턴을 걸러내도록 고쳤으므로
// 앞으로 새로 수집되는 매물은 문제없고, 이미 저장된 과거 데이터만 지운다
// (source=scraped만 대상 — 회원이 직접 올린 매물은 건드리지 않음).
return new class extends Migration {
    public function up(): void
    {
        $rows = DB::table('real_estate_listings')->where('source', 'scraped')->get(['id', 'images']);

        $deleted = 0;
        foreach ($rows as $row) {
            $images = json_decode($row->images ?? '[]', true);
            if (!is_array($images) || !$images) continue;

            $allLowRes = collect($images)->every(
                fn ($url) => is_string($url) && preg_match('/rdcpix\.com\/.*\d+s\.jpg(\?.*)?$/i', $url)
            );

            if ($allLowRes) {
                DB::table('real_estate_listings')->where('id', $row->id)->delete();
                $deleted++;
            }
        }
    }

    public function down(): void
    {
        // 저화질 매물 삭제 1회성 정리 — 되돌릴 필요 없음 (매일 재수집됨)
    }
};
