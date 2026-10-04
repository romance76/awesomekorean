<?php

namespace App\Console\Commands;

use App\Models\MarketItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * eBay Browse API(공식, 무료, 일 5,000회)에서 중고 매물을 가져와 market_items에
 * source=scraped로 저장한다. 회원이 올린 매물(source=user)은 절대 건드리지 않고,
 * 30일 지난 scraped 매물 삭제는 market:expire-scraped가 담당.
 *
 * 인증은 OAuth2 client credentials grant (App 토큰) — 토큰은 Cache에 저장해 재사용.
 * 응답 필드는 eBay 공식 문서 기준(itemSummaries[].title/price/image/condition/
 * itemLocation 등)으로 작성했지만, 혹시 실제 응답이 다를 경우를 대비해 부동산
 * 스크래퍼와 동일하게 방어적으로 파싱하고 최초 응답을 로그에 남긴다.
 */
class ScrapeMarketListings extends Command
{
    protected $signature = 'market:scrape {--dry-run : DB에 저장하지 않고 결과만 출력}';
    protected $description = 'eBay Browse API에서 중고 매물을 가져와 market_items에 저장 (source=scraped)';

    // 우리 사이트 카테고리 -> eBay 검색 키워드
    private array $categoryKeywords = [
        'electronics' => 'laptop electronics',
        'furniture'   => 'furniture',
        'clothing'    => 'clothing',
        'auto'        => 'car parts accessories',
        'baby'        => 'baby gear',
        'sports'      => 'sports equipment',
        'books'       => 'books',
        'etc'         => 'home goods',
    ];

    private const RESULT_COUNT = 12; // 카테고리당 가져올 건수
    private const TOKEN_CACHE_KEY = 'ebay_app_token';

    public function handle(): int
    {
        $clientId = $this->resolveCredential('EBAY_CLIENT_ID', 'client_id', 'ebay');
        $clientSecret = $this->resolveCredential('EBAY_CLIENT_SECRET', 'client_secret', 'ebay');

        if (!$clientId || !$clientSecret) {
            $this->warn('eBay Client ID/Secret이 설정되어 있지 않아 건너뜁니다. .env의 EBAY_CLIENT_ID/EBAY_CLIENT_SECRET을 추가하세요.');
            return self::SUCCESS;
        }

        $token = $this->getAccessToken($clientId, $clientSecret);
        if (!$token) {
            $this->warn('eBay OAuth 토큰 발급 실패. Client ID/Secret을 확인하세요.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $totalCreated = 0;
        $totalUpdated = 0;
        $firstResponseLogged = false;

        foreach ($this->categoryKeywords as $category => $keyword) {
            try {
                $response = Http::withToken($token)
                    ->withHeaders(['X-EBAY-C-MARKETPLACE-ID' => 'EBAY_US'])
                    ->timeout(20)
                    ->get('https://api.ebay.com/buy/browse/v1/item_summary/search', [
                        'q' => $keyword,
                        'limit' => self::RESULT_COUNT,
                        'filter' => 'conditionIds:{3000|4000|5000|6000|7000}', // 중고/리퍼 위주
                    ]);
            } catch (\Throwable $e) {
                $this->warn("[{$category}] 요청 실패: {$e->getMessage()}");
                continue;
            }

            if (!$response->successful()) {
                $this->warn("[{$category}] HTTP {$response->status()}: " . substr($response->body(), 0, 300));
                continue;
            }

            $data = $response->json();

            if (!$firstResponseLogged) {
                file_put_contents(storage_path('logs/market-scrape-sample.json'), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $firstResponseLogged = true;
            }

            $items = $this->extractItems($data);
            if (!$items) {
                $this->warn("[{$category}] 매물 없음 또는 응답 형식을 인식하지 못함");
                continue;
            }

            foreach ($items as $item) {
                $parsed = $this->parseItem($item, $category);
                if (!$parsed) continue;

                if ($dryRun) {
                    $this->line("DRY-RUN: {$parsed['title']} / \${$parsed['price']} / 사진 " . count($parsed['images']) . "장");
                    continue;
                }

                $existing = MarketItem::where('external_source', 'ebay')
                    ->where('external_id', $parsed['external_id'])
                    ->first();

                if ($existing) {
                    $existing->update($parsed);
                    $totalUpdated++;
                } else {
                    MarketItem::create($parsed + ['source' => 'scraped', 'external_source' => 'ebay', 'status' => 'active']);
                    $totalCreated++;
                }
            }

            $this->info("[{$category}] 처리 완료");
        }

        $this->info("완료: 신규={$totalCreated}, 갱신={$totalUpdated}");
        Log::info("market:scrape 완료 (신규={$totalCreated}, 갱신={$totalUpdated})");

        return self::SUCCESS;
    }

    // .env 우선, 없으면 관리자 페이지 "API 키 관리"의 api_keys 테이블 fallback
    // (서비스 코드: ebay_client_id / ebay_client_secret)
    private function resolveCredential(string $envKey, string $field, string $service): ?string
    {
        $val = config("services.{$service}.{$field}");
        if ($val) return $val;

        try {
            $row = DB::table('api_keys')->where('service', "{$service}_{$field}")->where('is_active', true)->first();
            if ($row && $row->api_key) return $row->api_key;
        } catch (\Exception $e) {}

        return null;
    }

    private function getAccessToken(string $clientId, string $clientSecret): ?string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, 6900, function () use ($clientId, $clientSecret) {
            try {
                $response = Http::asForm()
                    ->withBasicAuth($clientId, $clientSecret)
                    ->timeout(15)
                    ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                        'grant_type' => 'client_credentials',
                        'scope' => 'https://api.ebay.com/oauth/api_scope',
                    ]);
            } catch (\Throwable $e) {
                return null;
            }

            if (!$response->successful()) return null;

            return $response->json('access_token');
        }) ?: null;
    }

    private function extractItems(?array $data): array
    {
        if (!$data) return [];
        foreach (['itemSummaries', 'items', 'results', 'data'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) return $data[$key];
        }
        if (array_is_list($data)) return $data;
        return [];
    }

    private function parseItem(array $item, string $category): ?array
    {
        $externalId = $this->field($item, ['itemId', 'legacyItemId', 'id']);
        $title = $this->field($item, ['title']);
        $price = $this->field($item, ['price.value', 'price.amount', 'currentPrice.value']);
        if (!$externalId || !$title || !$price) return null;

        $images = $this->extractImages($item);
        if (!$images) return null; // 사진 필수

        $condition = (string) ($this->field($item, ['condition']) ?? '');
        $city = $this->field($item, ['itemLocation.city', 'itemLocation.stateOrProvince']) ?? 'Online';
        $state = $this->field($item, ['itemLocation.stateOrProvince']) ?? '';

        return [
            'title' => (string) $title,
            'content' => "자동 수집된 중고 매물입니다 (eBay 제공, 실거래 정보). 상세 조건은 사진과 함께 확인해 주세요.\n상태: {$condition}",
            'price' => $price,
            'images' => $images,
            'category' => $category,
            'condition' => $this->mapCondition($condition),
            'city' => (string) $city,
            'state' => (string) $state,
            'external_id' => (string) $externalId,
            'scraped_at' => now(),
        ];
    }

    private function extractImages(array $item): array
    {
        $urls = [];
        $primary = $this->field($item, ['image.imageUrl', 'thumbnailImages.0.imageUrl']);
        if ($primary) $urls[] = $primary;

        $additional = data_get($item, 'additionalImages');
        if (is_array($additional)) {
            foreach ($additional as $img) {
                $u = is_array($img) ? ($img['imageUrl'] ?? null) : (is_string($img) ? $img : null);
                if ($u) $urls[] = $u;
            }
        }

        return array_values(array_unique($urls));
    }

    private function mapCondition(string $raw): string
    {
        $t = strtolower($raw);
        if (str_contains($t, 'part') || str_contains($t, 'not working')) return 'fair';
        if (str_contains($t, 'refurbished') || str_contains($t, 'very good') || str_contains($t, 'like new')) return 'like_new';
        if (str_contains($t, 'new')) return 'new';
        if (str_contains($t, 'good')) return 'good';
        if (str_contains($t, 'used')) return 'good';
        return 'fair';
    }

    private function field(array $item, array $candidates)
    {
        foreach ($candidates as $path) {
            $value = data_get($item, $path);
            if ($value !== null && $value !== '' && !is_array($value)) return $value;
        }
        return null;
    }
}
