<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * 진단 3단계: 멀티소스(Flickr+Wikimedia 혼합)로 바꿔서 실제로 4분 넘게
 * 실행됐는데도 여전히 0건 채움. 이번엔 실제 타겟 아이템(id=1)의 진짜
 * 검색 쿼리로 Openverse를 호출해서 나오는 URL들을 전부 실제로
 * 다운로드까지 시도해보고, 각 URL의 호스트/상태코드를 전부 기록해서
 * 어느 호스트가 왜 막히는지 정확히 확인한다.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('market_items')) {
            return;
        }

        $ua = 'AwesomeKoreanBot/1.0 (https://awesomekorean.com; demo listing images)';
        $log = [];

        try {
            $resp = Http::withHeaders(['User-Agent' => $ua])->timeout(6)
                ->get('https://api.openverse.org/v1/images/', [
                    'q' => 'road bicycle',
                    'license_type' => 'commercial',
                    'page_size' => 12,
                ]);
            $log['search_status'] = $resp->status();
            $results = $resp->ok() ? ($resp->json('results') ?? []) : [];
            $log['result_count'] = count($results);

            $attempts = [];
            foreach (array_slice($results, 0, 8) as $r) {
                $url = $r['url'] ?? null;
                if (!$url) continue;
                $host = parse_url($url, PHP_URL_HOST);
                try {
                    $dResp = Http::withHeaders(['User-Agent' => $ua])->timeout(8)->get($url);
                    $attempts[] = [
                        'host' => $host,
                        'source' => $r['source'] ?? null,
                        'status' => $dResp->status(),
                        'bytes' => strlen($dResp->body()),
                    ];
                } catch (\Throwable $e) {
                    $attempts[] = [
                        'host' => $host,
                        'source' => $r['source'] ?? null,
                        'exception' => get_class($e) . ': ' . substr($e->getMessage(), 0, 150),
                    ];
                }
                usleep(300000);
            }
            $log['attempts'] = $attempts;
        } catch (\Throwable $e) {
            $log['top_level_exception'] = get_class($e) . ': ' . $e->getMessage();
        }

        \App\Models\MarketItem::where('id', 323)->update(['content' => json_encode($log, JSON_PRETTY_PRINT)]);
    }

    public function down(): void
    {
        // 진단용이라 되돌리지 않음.
    }
};
