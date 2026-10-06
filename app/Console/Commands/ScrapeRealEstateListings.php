<?php

namespace App\Console\Commands;

use App\Models\RealEstateListing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * RealtyAPI(realtor.realtyapi.io)에서 전국 한인 밀집 지역 매물을 가져와
 * real_estate_listings에 source=scraped로 저장한다. 회원이 올린 매물(source=user)은
 * 절대 건드리지 않고, 30일 지난 scraped 매물 삭제는 realestate:expire-scraped가 담당.
 *
 * 한 번 실행할 때마다 전국 한인 밀집 지역 ZIP 코드 풀에서 무료 API 크레딧 예산에 맞게
 * 일부(PICK_PER_RUN개)만 랜덤으로 뽑아 요청한다 — 매번 애틀랜타만 도는 게 아니라
 * LA/뉴욕/댈러스 등 전국이 고르게 섞이도록.
 *
 * RealtyAPI 응답 스키마가 공식 문서에 명시돼 있지 않아(OpenAPI 스펙에 필드 목록이 없음),
 * 아래 extractField()가 흔히 쓰이는 snake_case/camelCase 후보 키를 순서대로 시도한다.
 * 실제 키가 다르면 storage/logs/realestate-scrape-sample.json 에 원본 응답을 남기니
 * 그걸 보고 FIELD_CANDIDATES를 조정하면 된다.
 */
class ScrapeRealEstateListings extends Command
{
    protected $signature = 'realestate:scrape {--type=sale : sale 또는 rent} {--dry-run : DB에 저장하지 않고 결과만 출력}';
    protected $description = 'RealtyAPI에서 전국 한인 밀집 지역 매물을 랜덤으로 가져와 real_estate_listings에 저장 (source=scraped)';

    // 전국 한인 밀집 지역 ZIP 코드 풀 — 매 실행마다 이 중 일부를 랜덤으로 뽑아 사용
    private array $zipPool = [
        // 애틀랜타 / Gwinnett County, GA
        '30024' => ['city' => 'Suwanee', 'state' => 'GA'],
        '30096' => ['city' => 'Duluth', 'state' => 'GA'],
        '30097' => ['city' => 'Johns Creek', 'state' => 'GA'],
        '30071' => ['city' => 'Norcross', 'state' => 'GA'],
        '30340' => ['city' => 'Doraville', 'state' => 'GA'],
        '30092' => ['city' => 'Peachtree Corners', 'state' => 'GA'],
        // LA 한인타운 / 오렌지카운티, CA
        '90006' => ['city' => 'Koreatown', 'state' => 'CA'],
        '90005' => ['city' => 'Koreatown', 'state' => 'CA'],
        '90020' => ['city' => 'Koreatown', 'state' => 'CA'],
        '92618' => ['city' => 'Irvine', 'state' => 'CA'],
        '92620' => ['city' => 'Irvine', 'state' => 'CA'],
        // 뉴욕/뉴저지
        '11354' => ['city' => 'Flushing', 'state' => 'NY'],
        '11355' => ['city' => 'Flushing', 'state' => 'NY'],
        '07024' => ['city' => 'Fort Lee', 'state' => 'NJ'],
        '07650' => ['city' => 'Palisades Park', 'state' => 'NJ'],
        // 댈러스, TX
        '75007' => ['city' => 'Carrollton', 'state' => 'TX'],
        '75010' => ['city' => 'Carrollton', 'state' => 'TX'],
        // 시애틀, WA
        '98003' => ['city' => 'Federal Way', 'state' => 'WA'],
        '98036' => ['city' => 'Lynnwood', 'state' => 'WA'],
        // 시카고, IL
        '60659' => ['city' => 'Chicago', 'state' => 'IL'],
        '60714' => ['city' => 'Niles', 'state' => 'IL'],
        // 워싱턴 DC 인근 (버지니아)
        '22003' => ['city' => 'Annandale', 'state' => 'VA'],
        // 휴스턴, TX
        '77079' => ['city' => 'Houston', 'state' => 'TX'],
    ];

    private const RESULT_COUNT = 8; // ZIP당 가져올 건수 — 무료 크레딧(월 250) 안에서 매일 돌리기 위해 적게 유지
    private const PICK_PER_RUN = 4; // 한 번 실행할 때 전국 풀에서 랜덤으로 뽑을 ZIP 개수 (매매+렌트 둘 다 매일 돌리므로 무료 크레딧 예산에 맞춰 줄임)

    public function handle(): int
    {
        $apiKey = $this->resolveApiKey();
        if (!$apiKey) {
            $this->warn('RealtyAPI 키가 설정되어 있지 않아 건너뜁니다. .env의 REALTYAPI_KEY 또는 관리자 페이지 API 키 관리(서비스 코드: realtyapi)에 키를 추가하세요.');
            return self::SUCCESS;
        }

        $dealType = $this->option('type') === 'rent' ? 'rent' : 'sale';
        $searchType = $dealType === 'rent' ? 'For_Rent' : 'For_Sale';
        $dryRun = (bool) $this->option('dry-run');

        $totalCreated = 0;
        $totalUpdated = 0;
        $firstResponseLogged = false;

        // Collection::shuffle()은 연관배열 키(ZIP코드)를 보존하지 않고 0,1,2...로
        // 재색인하므로(실측 확인), 키만 따로 섞은 뒤 풀에서 값을 다시 찾는다.
        $zipKeys = collect(array_keys($this->zipPool))->shuffle()->take(self::PICK_PER_RUN);

        foreach ($zipKeys as $zip) {
            $loc = $this->zipPool[$zip];
            try {
                $response = Http::withHeaders(['x-realtyapi-key' => $apiKey])
                    ->timeout(20)
                    ->get('https://realtor.realtyapi.io/search/byzip', [
                        'zipCode' => $zip,
                        'searchType' => $searchType,
                        'resultCount' => self::RESULT_COUNT,
                        'sortOrder' => 'Newest',
                        'hasPhotos' => true,
                    ]);
            } catch (\Throwable $e) {
                $this->warn("[{$zip}] 요청 실패: {$e->getMessage()}");
                continue;
            }

            if (!$response->successful()) {
                $this->warn("[{$zip}] HTTP {$response->status()}: " . substr($response->body(), 0, 300));
                continue;
            }

            $data = $response->json();

            if (!$firstResponseLogged) {
                file_put_contents(storage_path('logs/realestate-scrape-sample.json'), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $firstResponseLogged = true;
            }

            $items = $this->extractItems($data);
            if (!$items) {
                $this->warn("[{$zip}] 매물 없음 또는 응답 형식을 인식하지 못함");
                continue;
            }

            foreach ($items as $item) {
                $parsed = $this->parseItem($item, $zip, $loc, $dealType);
                if (!$parsed) continue;

                if ($dryRun) {
                    $this->line("DRY-RUN: {$parsed['title']} / \${$parsed['price']} / 사진 " . count($parsed['images']) . "장");
                    continue;
                }

                $existing = RealEstateListing::where('external_source', 'realtyapi')
                    ->where('external_id', $parsed['external_id'])
                    ->first();

                if ($existing) {
                    $existing->update($parsed);
                    $totalUpdated++;
                } else {
                    RealEstateListing::create($parsed + ['source' => 'scraped', 'external_source' => 'realtyapi', 'is_active' => true]);
                    $totalCreated++;
                }
            }

            $this->info("[{$zip}] 처리 완료");
        }

        $this->info("완료: 신규={$totalCreated}, 갱신={$totalUpdated}");
        Log::info("realestate:scrape 완료 (type={$dealType}, 신규={$totalCreated}, 갱신={$totalUpdated})");

        return self::SUCCESS;
    }

    // PlacesController::getApiKey()와 동일한 패턴: .env 우선, 없으면 관리자 페이지
    // "API 키 관리"에서 등록한 api_keys 테이블(서비스 코드: realtyapi)을 fallback으로 사용
    private function resolveApiKey(): ?string
    {
        $key = config('services.realtyapi.key');
        if ($key) return $key;

        try {
            $row = DB::table('api_keys')->where('service', 'realtyapi')->where('is_active', true)->first();
            if ($row && $row->api_key) return $row->api_key;
        } catch (\Exception $e) {}

        return null;
    }

    private function extractItems(?array $data): array
    {
        if (!$data) return [];
        foreach (['searchResults', 'properties', 'results', 'listings', 'data', 'items'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) return $data[$key];
        }
        // 최상위가 바로 배열인 경우
        if (array_is_list($data)) return $data;
        return [];
    }

    private function parseItem(array $item, string $zip, array $loc, string $dealType): ?array
    {
        $externalId = $this->field($item, ['listing_id', 'listingId', 'property_id', 'propertyId', 'id']);
        $price = $this->field($item, ['list_price', 'price', 'listPrice']);
        $address = $this->field($item, ['address.line', 'address.full', 'formattedAddress', 'full_address']);
        if (!$externalId || !$price || !$address) return null; // 핵심 필드 없으면 신뢰할 수 없는 항목이라 스킵

        $beds = (int) ($this->field($item, ['beds', 'bedrooms', 'description.beds']) ?? 0);
        $baths = (int) ($this->field($item, ['baths', 'bathrooms', 'description.baths']) ?? 0);
        $sqft = (int) ($this->field($item, ['sqft', 'square_feet', 'squareFootage', 'description.sqft']) ?? 0);
        $propertyTypeRaw = (string) ($this->field($item, ['propertyType', 'property_type', 'description.type']) ?? '');
        $city = $this->field($item, ['address.city', 'city']) ?? $loc['city'];
        $lat = $this->field($item, ['address.latitude', 'location.lat', 'lat', 'latitude']);
        $lng = $this->field($item, ['address.longitude', 'location.lng', 'lng', 'longitude']);

        $images = $this->extractPhotos($item);
        if (!$images) return null; // 사진 필수 (hasPhotos=true로 요청했지만 한번 더 확인)

        $propertyType = $this->mapPropertyType($propertyTypeRaw, $dealType, $beds);
        $typeLabel = $dealType === 'rent' ? '렌트' : '매매';

        return [
            'title' => "{$city} {$address} ({$typeLabel})",
            'content' => "자동 수집된 매물입니다 (실거래 정보, RealtyAPI 제공). 상세 조건은 매물 사진과 함께 확인해 주세요.\n주소: {$address}",
            'type' => $dealType,
            'property_type' => $propertyType,
            'price' => $price,
            'images' => $images,
            'address' => (string) $address,
            'city' => (string) $city,
            'state' => $loc['state'],
            'zipcode' => $zip,
            'lat' => $lat,
            'lng' => $lng,
            'bedrooms' => $beds,
            'bathrooms' => $baths,
            'sqft' => $sqft,
            'external_id' => (string) $externalId,
            'scraped_at' => now(),
        ];
    }

    private function extractPhotos(array $item): array
    {
        foreach (['photos', 'photoUrls', 'images', 'media.photos'] as $key) {
            $val = data_get($item, $key);
            if (!is_array($val) || !$val) continue;

            $urls = [];
            foreach ($val as $p) {
                $u = null;
                if (is_string($p)) $u = $p;
                elseif (is_array($p)) $u = $p['href'] ?? $p['url'] ?? $p['highRes'] ?? $p['midRes'] ?? $p['src'] ?? null;
                // 초소형 썸네일(확인: 실측 120x80px)은 제외하고 다음 후보 필드로 넘어감 —
                // 같은 매물이 두 갈래 피드로 중복 수집돼 한쪽은 1024px, 한쪽은 이 썸네일만 주는
                // 경우가 있어서(실측 확인), 이걸 그대로 쓰면 목록/상세 페이지에서 사진이 심하게
                // 흐릿하게 보임.
                if ($u && !$this->isLowResPhoto($u)) $urls[] = $u;
            }
            if ($urls) return array_values(array_unique($urls));
        }

        $primary = $this->field($item, ['primary_photo.href', 'primaryPhoto.href', 'primaryListingImageUrl', 'thumbnail']);
        if ($primary && !$this->isLowResPhoto($primary)) return [$primary];

        return [];
    }

    // Realtor.com의 rdcpix.com CDN URL은 끝에 사이즈 코드가 붙는데(예: ...397s.jpg, ...283od.jpg),
    // 숫자 바로 뒤에 's'로 끝나는 건 가로 120px 수준의 초소형 썸네일(실측 확인), 'od'로 끝나는 건
    // 이 API가 주는 가장 큰 사이즈(실측 1024px 안팎)라 정상 사용 가능.
    private function isLowResPhoto(string $url): bool
    {
        return (bool) preg_match('/rdcpix\.com\/.*\d+s\.jpg(\?.*)?$/i', $url);
    }

    // 점(.) 구분 경로 여러 개를 순서대로 시도해 첫 번째로 "있는 스칼라 값"을 반환.
    // 후보 중 일부 매물에만 없는 필드가 있어 다음 후보(예: 'address' 통째)로 넘어갔다가
    // 배열이 그대로 반환되어 문자열 보간 시 깨지는 사고가 있었음(실측 확인) — 배열/객체는
    // 건너뛴다. 배열 자체가 필요한 곳(사진 목록 등)은 data_get()을 직접 쓸 것.
    private function field(array $item, array $candidates)
    {
        foreach ($candidates as $path) {
            $value = data_get($item, $path);
            if ($value !== null && $value !== '' && !is_array($value)) return $value;
        }
        return null;
    }

    private function mapPropertyType(string $raw, string $dealType, int $beds): string
    {
        if ($dealType === 'rent') {
            if ($beds <= 0) return 'studio';
            if ($beds === 1) return '1br';
            if ($beds === 2) return '2br';
            return '3br_plus';
        }

        $t = strtolower($raw);
        if (str_contains($t, 'condo')) return 'condo';
        if (str_contains($t, 'town')) return 'townhouse';
        if (str_contains($t, 'duplex')) return 'duplex';
        if (str_contains($t, 'villa')) return 'villa';
        if (str_contains($t, 'multi') || str_contains($t, 'commercial') || str_contains($t, 'office')) return 'office_sale';
        return 'house';
    }
}
