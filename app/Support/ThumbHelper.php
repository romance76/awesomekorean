<?php

namespace App\Support;

/**
 * 썸네일 정적 경로를 계산해주는 헬퍼.
 *
 * API 응답에 이걸 포함시키면, 프론트는 /storage/thumbs/... 로 직접 요청해서
 * PHP 거치지 않고 nginx 가 즉시 static 파일로 서빙. 캐시 HIT 시 PHP 부트스트랩 비용 0.
 *
 * 아직 생성되지 않은 썸네일은 /api/thumb?url=... 로 떨어져서 첫 요청 시 생성.
 */
class ThumbHelper
{
    /**
     * 주어진 원본 URL 의 썸네일 정적 경로를 반환.
     * 파일이 존재하면 /storage/thumbs/.. 로, 없으면 /api/thumb?url=.. 로.
     */
    public static function url(?string $sourceUrl, int $width = 240): ?string
    {
        if (!$sourceUrl) return null;
        if (str_starts_with($sourceUrl, 'data:') || str_starts_with($sourceUrl, 'blob:')) {
            return $sourceUrl;
        }
        // HTML 엔티티 디코딩 (chosun 뉴스 URL 에 &amp; 섞여 있는 경우)
        $sourceUrl = html_entity_decode($sourceUrl, ENT_QUOTES | ENT_HTML5);

        $hash = md5($sourceUrl);
        $relPath = 'thumbs/' . substr($hash, 0, 2) . '/' . substr($hash, 2) . '_' . $width . '.jpg';
        $absPath = storage_path('app/public/' . $relPath);

        if (file_exists($absPath)) {
            return '/storage/' . $relPath;
        }

        // Fallback: 아직 생성 안 됐으면 API 가 생성 후 서빙
        return '/api/thumb?url=' . urlencode($sourceUrl) . '&w=' . $width;
    }
}
