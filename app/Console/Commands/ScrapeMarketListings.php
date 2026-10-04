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
 * realestate:scrape와 동일하게, 전국 한인 밀집 지역 ZIP 코드 풀에서 실행마다 일부를
 * 랜덤으로 뽑아 eBay의 "로컬 픽업(local pickup)" 반경 필터(pickupPostalCode/pickupRadius)로
 * 검색한다 — 매번 똑같은 전국 검색이 아니라 그 지역 근처에서 실제로 픽업 가능한 매물만.
 * 단, 로컬 픽업을 지원하는 eBay 매물 자체가 적어서 ZIP+카테고리 조합에 따라 0건일 수 있음.
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

    // 우리 사이트 카테고리 -> eBay 검색 키워드 (한국 관련성 높은 키워드 위주 —
    // 검색 자체를 한국 관련 용어로 좁히고, parseItem()에서 한글/한국어 로마자
    // 표기 재확인까지 한 번 더 거른다)
    private array $categoryKeywords = [
        'electronics' => ['Samsung Korea version', 'LG Korea electronics'],
        'furniture'   => ['Korean celadon', 'Korean antique furniture'],
        'clothing'    => ['hanbok', 'Korean streetwear'],
        'auto'        => ['Hyundai Kia parts', 'Korean car accessories'],
        'baby'        => ['Korean baby carrier', 'Korean baby products'],
        'sports'      => ['taekwondo', 'Korean golf'],
        'books'       => ['manhwa', 'Korean textbook'],
        'etc'         => ['kpop photocard', 'Korean kitchenware'],
    ];

    // 한글 유니코드가 없어도 "hanbok"처럼 한국어를 영어로 표기한 경우를 잡기 위한 키워드
    private const KOREAN_KEYWORDS = [
        'hanbok', 'kimchi', 'kpop', 'k-pop', 'korean', 'korea', 'hyundai', 'kia',
        'manhwa', 'taekwondo', 'celadon', 'hanji', 'soju', 'bibimbap', 'dongchimi',
        'kdrama', 'k-drama', 'hangul', 'joseon', 'bulgogi', 'tteok', 'gochujang',
        'doenjang', 'hanok', 'seoul',
    ];

    // realestate:scrape와 동일한 전국 한인 밀집 지역 ZIP 코드 풀 — 매 실행마다
    // 이 중 일부를 랜덤으로 뽑아 그 지역 로컬 픽업 반경 내 매물만 검색한다
    private array $zipPool = [
        '30024' => ['city' => 'Suwanee', 'state' => 'GA'],
        '30096' => ['city' => 'Duluth', 'state' => 'GA'],
        '30097' => ['city' => 'Johns Creek', 'state' => 'GA'],
        '30071' => ['city' => 'Norcross', 'state' => 'GA'],
        '90006' => ['city' => 'Koreatown', 'state' => 'CA'],
        '90005' => ['city' => 'Koreatown', 'state' => 'CA'],
        '92618' => ['city' => 'Irvine', 'state' => 'CA'],
        '11354' => ['city' => 'Flushing', 'state' => 'NY'],
        '07024' => ['city' => 'Fort Lee', 'state' => 'NJ'],
        '75007' => ['city' => 'Carrollton', 'state' => 'TX'],
        '98003' => ['city' => 'Federal Way', 'state' => 'WA'],
        '60659' => ['city' => 'Chicago', 'state' => 'IL'],
        '22003' => ['city' => 'Annandale', 'state' => 'VA'],
        '77079' => ['city' => 'Houston', 'state' => 'TX'],
    ];

    private const RESULT_COUNT = 30; // 검색어당 가져올 건수 (한국 관련성 필터로 많이 걸러지므로 넉넉히)
    private const PICK_PER_RUN = 4; // 한 번 실행할 때 전국 풀에서 랜덤으로 뽑을 ZIP 개수 (로컬 픽업 필터로 요청 수가 ZIP x 키워드만큼 늘어나므로 제한)
    private const PICKUP_RADIUS_MI = 50;
    private const TOKEN_CACHE_KEY = 'ebay_app_token';

    private ?string $lastTokenError = null;

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
            $this->warn('eBay OAuth 토큰 발급 실패: ' . ($this->lastTokenError ?? '알 수 없는 오류'));
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $totalCreated = 0;
        $totalUpdated = 0;
        $firstResponseLogged = false;

        // Collection::shuffle()은 연관배열 키(ZIP코드)를 보존하지 않고 재색인하므로
        // (realestate:scrape와 동일하게 확인됨), 키만 따로 섞은 뒤 풀에서 값을 다시 찾는다.
        $zipKeys = collect(array_keys($this->zipPool))->shuffle()->take(self::PICK_PER_RUN);

        foreach ($zipKeys as $zip) {
          $loc = $this->zipPool[$zip];
          foreach ($this->categoryKeywords as $category => $keywords) {
          foreach ($keywords as $keyword) {
            try {
                $response = Http::withToken($token)
                    ->withHeaders(['X-EBAY-C-MARKETPLACE-ID' => 'EBAY_US'])
                    ->timeout(20)
                    ->get('https://api.ebay.com/buy/browse/v1/item_summary/search', [
                        'q' => $keyword,
                        'limit' => self::RESULT_COUNT,
                        // 중고/리퍼 위주 + 해당 ZIP 반경 내 로컬 픽업 가능한 매물만
                        'filter' => 'conditionIds:{3000|4000|5000|6000|7000},deliveryOptions:{SELLER_ARRANGED_LOCAL_PICKUP},'
                            . "pickupCountry:US,pickupPostalCode:{$zip},pickupRadius:" . self::PICKUP_RADIUS_MI . ',pickupRadiusUnit:mi',
                    ]);
            } catch (\Throwable $e) {
                $this->warn("[{$zip}/{$category}:{$keyword}] 요청 실패: {$e->getMessage()}");
                continue;
            }

            if (!$response->successful()) {
                $this->warn("[{$zip}/{$category}:{$keyword}] HTTP {$response->status()}: " . substr($response->body(), 0, 300));
                continue;
            }

            $data = $response->json();

            if (!$firstResponseLogged) {
                file_put_contents(storage_path('logs/market-scrape-sample.json'), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $firstResponseLogged = true;
            }

            $items = $this->extractItems($data);
            if (!$items) {
                $this->warn("[{$zip}/{$category}:{$keyword}] 매물 없음 또는 응답 형식을 인식하지 못함");
                continue;
            }

            $matched = 0;
            foreach ($items as $item) {
                $parsed = $this->parseItem($item, $category, $zip, $loc);
                if (!$parsed) continue;
                $matched++;

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

            $this->info("[{$zip}/{$category}:{$keyword}] 처리 완료 (한국 관련 {$matched}건)");
          }
          }
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
                $this->lastTokenError = $e->getMessage();
                return null;
            }

            if (!$response->successful()) {
                $this->lastTokenError = "HTTP {$response->status()}: " . substr($response->body(), 0, 300);
                return null;
            }

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

    private function parseItem(array $item, string $category, string $zip, array $loc): ?array
    {
        $externalId = $this->field($item, ['itemId', 'legacyItemId', 'id']);
        $title = $this->field($item, ['title']);
        $price = $this->field($item, ['price.value', 'price.amount', 'currentPrice.value']);
        if (!$externalId || !$title || !$price) return null;

        $shortDesc = (string) ($this->field($item, ['shortDescription']) ?? '');
        if (!$this->isKoreanRelevant((string) $title, $shortDesc)) return null; // 한글/한국어 표기 없으면 스킵

        $images = $this->extractImages($item);
        if (!$images) return null; // 사진 필수

        $condition = (string) ($this->field($item, ['condition']) ?? '');
        // 로컬 픽업 검색 특성상 매물은 항상 검색에 사용한 ZIP 반경 내에 있으므로,
        // eBay가 itemLocation을 비워서 줄 때는 검색에 쓴 지역 정보로 채운다
        $city = $this->field($item, ['itemLocation.city']) ?? $loc['city'];
        $state = $this->field($item, ['itemLocation.stateOrProvince']) ?? $loc['state'];
        $zipcode = $this->field($item, ['itemLocation.postalCode']) ?? $zip;

        return [
            'title' => (string) $title,
            'content' => "자동 수집된 중고 매물입니다 (eBay 제공, 실거래 정보). 상세 조건은 사진과 함께 확인해 주세요.\n상태: {$condition}",
            'price' => $price,
            'images' => $images,
            'category' => $category,
            'condition' => $this->mapCondition($condition),
            'city' => (string) $city,
            'state' => (string) $state,
            'zipcode' => (string) $zipcode,
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

    // 제목/짧은 설명에 한글이 있거나, hanbok/kimchi처럼 한국어를 로마자로 표기한
    // 단어가 있으면 한국 관련 상품으로 본다 (대소문자 무시)
    private function isKoreanRelevant(string $title, string $desc = ''): bool
    {
        $text = $title . ' ' . $desc;
        if (preg_match('/\p{Hangul}/u', $text)) return true;

        $lower = mb_strtolower($text);
        foreach (self::KOREAN_KEYWORDS as $word) {
            if (str_contains($lower, $word)) return true;
        }
        return false;
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
