<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * 진단 2단계: 직전 진단(2026_05_04_150000)에서 Openverse 검색은 정상(200)이지만
 * 실제 이미지 다운로드(live.staticflickr.com)가 403으로 막히는 것을 확인함.
 * 로컬 샌드박스에서 같은 URL은 아무 헤더 조합으로도 정상 다운로드되는 것까지
 * 확인했으므로, 운영 서버의 아웃바운드 IP가 Flickr CDN에 의해 차단/제한된
 * 것으로 추정됨. Wikimedia Commons·다른 일반 CDN도 같은지 확인하기 위해
 * 여러 소스를 한 번에 테스트해서 결과를 market_items.id=323 에 기록.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('market_items')) {
            return;
        }

        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $result = [];

        // 1) Flickr 재시도 (직전 진단과 동일 URL로 재확인 — 영구 차단 vs 일시적 문제 구분)
        $result['flickr_retry'] = $this->tryDownload(
            'https://live.staticflickr.com/3904/14375434305_402a965bd1_b.jpg', $ua
        );

        // 2) Wikimedia Commons (Openverse의 다른 주요 소스)
        try {
            $wResp = Http::withHeaders(['User-Agent' => $ua])->timeout(6)
                ->get('https://api.openverse.org/v1/images/', [
                    'q' => 'bicycle', 'license_type' => 'commercial', 'source' => 'wikimedia', 'page_size' => 3,
                ]);
            $wResults = $wResp->ok() ? ($wResp->json('results') ?? []) : [];
            $wUrl = $wResults[0]['url'] ?? null;
            $result['wikimedia_search_status'] = $wResp->status();
            $result['wikimedia_url'] = $wUrl;
            $result['wikimedia_download'] = $wUrl ? $this->tryDownload($wUrl, $ua) : null;
        } catch (\Throwable $e) {
            $result['wikimedia_error'] = get_class($e) . ': ' . $e->getMessage();
        }

        // 3) 완전히 다른 일반 CDN (핫링크 보호가 거의 없는 곳) — 순수 아웃바운드 연결성 테스트
        $result['picsum_test'] = $this->tryDownload('https://picsum.photos/400', $ua);

        // 4) Google(이미 places:import 에서 성공 이력 있는 도메인)과 비교
        $result['google_zippopotam_test'] = $this->tryDownload('https://api.zippopotam.us/us/90210', $ua);

        \App\Models\MarketItem::where('id', 323)->update(['content' => json_encode($result, JSON_PRETTY_PRINT)]);
    }

    private function tryDownload(string $url, string $ua): array
    {
        try {
            $resp = Http::withHeaders(['User-Agent' => $ua])->timeout(8)->get($url);
            return [
                'ok' => $resp->ok(),
                'status' => $resp->status(),
                'bytes' => strlen($resp->body()),
                'body_preview' => $resp->ok() ? null : substr($resp->body(), 0, 200),
            ];
        } catch (\Throwable $e) {
            return ['exception' => get_class($e) . ': ' . $e->getMessage()];
        }
    }

    public function down(): void
    {
        // 진단용이라 되돌리지 않음.
    }
};
