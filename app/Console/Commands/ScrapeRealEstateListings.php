<?php

namespace App\Console\Commands;

use App\Models\RealEstateListing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * RealtyAPI(realtor.realtyapi.io)에서 애틀랜타 한인 밀집 지역 매물을 가져와
 * real_estate_listings에 source=scraped로 저장한다. 회원이 올린 매물(source=user)은
 * 절대 건드리지 않고, 30일 지난 scraped 매물 삭제는 realestate:expire-scraped가 담당.
 *
 * RealtyAPI 응답 스키마가 공식 문서에 명시돼 있지 않아(OpenAPI 스펙에 필드 목록이 없음),
 * 아래 extractField()가 흔히 쓰이는 snake_case/camelCase 후보 키를 순서대로 시도한다.
 * 실제 키가 다르면 storage/logs/realestate-scrape-sample.json 에 원본 응답을 남기니
 * 그걸 보고 FIELD_CANDIDATES를 조정하면 된다.
 */
class ScrapeRealEstateListings extends Command
{
    protected $signature = 'realestate:scrape {--type=sale : sale 또는 rent} {--dry-run : DB에 저장하지 않고 결과만 출력}';
    protected $description = 'RealtyAPI에서 애틀랜타 한인 밀집 지역 매물을 가져와 real_estate_listings에 저장 (source=scraped)';

    // 애틀랜타 한인 밀집 지역(Gwinnett County 중심) ZIP 코드
    private array $zipCodes = [
        '30024' => ['city' => 'Suwanee', 'state' => 'GA'],
        '30096' => ['city' => 'Duluth', 'state' => 'GA'],
        '30097' => ['city' => 'Johns Creek', 'state' => 'GA'],
        '30071' => ['city' => 'Norcross', 'state' => 'GA'],
        '30340' => ['city' => 'Doraville', 'state' => 'GA'],
        '30092' => ['city' => 'Peachtree Corners', 'state' => 'GA'],
    ];

    private const RESULT_COUNT = 8; // ZIP당 가져올 건수 — 무료 크레딧(월 250) 안에서 매일 돌리기 위해 적게 유지

    public function handle(): int
    {
        $apiKey = config('services.realtyapi.key');
        if (!$apiKey) {
            $this->warn('REALTYAPI_KEY가 설정되어 있지 않아 건너뜁니다. .env에 키를 추가하세요.');
            return self::SUCCESS;
        }

        $dealType = $this->option('type') === 'rent' ? 'rent' : 'sale';
        $searchType = $dealType === 'rent' ? 'For_Rent' : 'For_Sale';
        $dryRun = (bool) $this->option('dry-run');

        $totalCreated = 0;
        $totalUpdated = 0;
        $firstResponseLogged = false;

        foreach ($this->zipCodes as $zip => $loc) {
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

    private function extractItems(?array $data): array
    {
        if (!$data) return [];
        foreach (['properties', 'results', 'listings', 'data', 'items'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) return $data[$key];
        }
        // 최상위가 바로 배열인 경우
        if (array_is_list($data)) return $data;
        return [];
    }

    private function parseItem(array $item, string $zip, array $loc, string $dealType): ?array
    {
        $externalId = $this->field($item, ['listing_id', 'listingId', 'property_id', 'propertyId', 'id']);
        $price = $this->field($item, ['price', 'listPrice', 'list_price']);
        $address = $this->field($item, ['address.line', 'address.full', 'address', 'formattedAddress', 'full_address']);
        if (!$externalId || !$price || !$address) return null; // 핵심 필드 없으면 신뢰할 수 없는 항목이라 스킵

        $beds = (int) ($this->field($item, ['beds', 'bedrooms', 'description.beds']) ?? 0);
        $baths = (int) ($this->field($item, ['baths', 'bathrooms', 'description.baths']) ?? 0);
        $sqft = (int) ($this->field($item, ['sqft', 'square_feet', 'squareFootage', 'description.sqft']) ?? 0);
        $propertyTypeRaw = (string) ($this->field($item, ['propertyType', 'property_type', 'description.type']) ?? '');
        $city = $this->field($item, ['address.city', 'city']) ?? $loc['city'];
        $lat = $this->field($item, ['location.lat', 'lat', 'latitude']);
        $lng = $this->field($item, ['location.lng', 'lng', 'longitude']);

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
            $val = $this->field($item, [$key]);
            if (is_array($val) && $val) {
                $urls = [];
                foreach ($val as $p) {
                    if (is_string($p)) $urls[] = $p;
                    elseif (is_array($p)) {
                        $u = $p['href'] ?? $p['url'] ?? $p['highRes'] ?? $p['midRes'] ?? $p['src'] ?? null;
                        if ($u) $urls[] = $u;
                    }
                }
                if ($urls) return array_values(array_unique($urls));
            }
        }
        $primary = $this->field($item, ['primary_photo.href', 'primaryPhoto.href', 'primaryListingImageUrl', 'thumbnail']);
        return $primary ? [$primary] : [];
    }

    // 점(.) 구분 경로 여러 개를 순서대로 시도해 첫 번째로 값이 있는 걸 반환
    private function field(array $item, array $candidates)
    {
        foreach ($candidates as $path) {
            $value = data_get($item, $path);
            if ($value !== null && $value !== '') return $value;
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
