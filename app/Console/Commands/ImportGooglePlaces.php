<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\BusinessReview;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ImportGooglePlaces extends Command
{
    protected $signature = 'places:import {--fresh : 기존 데이터 삭제 후 시작} {--limit=0 : 최대 임포트 수 (0=무제한)} {--city= : 특정 도시만}';
    protected $description = 'Google Places API에서 미국 한인 업소 데이터 임포트 (한글 리뷰 + 사진 포함)';

    private $apiKey;
    private $imported = 0;
    private $updated = 0;
    private $errors = 0;

    private $cities = [
        ['name' => 'Los Angeles', 'state' => 'CA', 'lat' => 34.0522, 'lng' => -118.2437],
        ['name' => 'New York', 'state' => 'NY', 'lat' => 40.7128, 'lng' => -74.0060],
        ['name' => 'Bergen County', 'state' => 'NJ', 'lat' => 40.9176, 'lng' => -74.0712],
        ['name' => 'Atlanta', 'state' => 'GA', 'lat' => 33.7490, 'lng' => -84.3880],
        ['name' => 'Chicago', 'state' => 'IL', 'lat' => 41.8781, 'lng' => -87.6298],
        ['name' => 'Dallas', 'state' => 'TX', 'lat' => 32.7767, 'lng' => -96.7970],
        ['name' => 'Houston', 'state' => 'TX', 'lat' => 29.7604, 'lng' => -95.3698],
        ['name' => 'Seattle', 'state' => 'WA', 'lat' => 47.6062, 'lng' => -122.3321],
        ['name' => 'San Francisco', 'state' => 'CA', 'lat' => 37.7749, 'lng' => -122.4194],
        ['name' => 'Washington', 'state' => 'DC', 'lat' => 38.9072, 'lng' => -77.0369],
        ['name' => 'Philadelphia', 'state' => 'PA', 'lat' => 39.9526, 'lng' => -75.1652],
        ['name' => 'Irvine', 'state' => 'CA', 'lat' => 33.6846, 'lng' => -117.8265],
        ['name' => 'Fullerton', 'state' => 'CA', 'lat' => 33.8703, 'lng' => -117.9242],
        ['name' => 'Flushing', 'state' => 'NY', 'lat' => 40.7654, 'lng' => -73.8328],
        ['name' => 'Honolulu', 'state' => 'HI', 'lat' => 21.3069, 'lng' => -157.8583],
        ['name' => 'Las Vegas', 'state' => 'NV', 'lat' => 36.1699, 'lng' => -115.1398],
        ['name' => 'Denver', 'state' => 'CO', 'lat' => 39.7392, 'lng' => -104.9903],
    ];

    private $searches = [
        // 음식점
        'Korean restaurant' => 'restaurant',
        'Korean BBQ' => 'restaurant',
        'Korean bakery' => 'restaurant',
        'Korean cafe' => 'restaurant',
        'Korean fried chicken' => 'restaurant',
        'Korean noodle' => 'restaurant',
        'Korean tofu house' => 'restaurant',
        // 마트/식료품
        'Korean grocery' => 'mart',
        'Korean market' => 'mart',
        'Korean supermarket' => 'mart',
        'H Mart' => 'mart',
        'Assi Plaza' => 'mart',
        'Zion Market' => 'mart',
        'Lotte Plaza' => 'mart',
        'Hankook Supermarket' => 'mart',
        'Asian grocery' => 'mart',
        'Asian supermarket' => 'mart',
        'Asian market' => 'mart',
        '아시아 식료품점' => 'mart',
        'Korean food store' => 'mart',
        // 미용
        'Korean beauty salon' => 'beauty',
        'Korean nail salon' => 'beauty',
        'Korean hair salon' => 'beauty',
        'Korean spa' => 'beauty',
        // 의료
        'Korean dentist' => 'medical',
        'Korean doctor' => 'medical',
        'Korean pharmacy' => 'medical',
        'Korean optometrist' => 'medical',
        // 전문서비스
        'Korean lawyer' => 'professional',
        'Korean accountant' => 'professional',
        'Korean CPA' => 'professional',
        'Korean insurance agent' => 'professional',
        'Korean tax service' => 'professional',
        // 자동차
        'Korean auto repair' => 'auto',
        'Korean auto body shop' => 'auto',
        // 부동산
        'Korean real estate' => 'realestate',
        'Korean realtor' => 'realestate',
        // 교육
        'Korean school' => 'education',
        'Korean academy' => 'education',
        'Korean tutoring' => 'education',
        'Korean SAT prep' => 'education',
        // 기타
        'Korean church' => 'religion',
        'Korean temple' => 'religion',
        'Korean Buddhist temple' => 'religion',
        'Korean travel agency' => 'etc',
        'Korean dry cleaner' => 'etc',
        'Korean taekwondo' => 'etc',
    ];

    public function handle()
    {
        $this->apiKey = env('GOOGLE_PLACES_API_KEY');
        if (!$this->apiKey) {
            $this->error('GOOGLE_PLACES_API_KEY 없음');
            return 1;
        }

        $limit = (int) $this->option('limit');
        $onlyCity = $this->option('city');

        if ($this->option('fresh')) {
            $this->warn('기존 데이터 삭제 중...');
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table('business_reviews')->truncate();
            DB::table('business_claims')->truncate();
            DB::table('businesses')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $this->info('삭제 완료.');
        }

        $this->info("Google Places API 임포트 시작...");

        $cities = $this->cities;
        if ($onlyCity) {
            $cities = array_filter($cities, fn($c) => stripos($c['name'], $onlyCity) !== false);
        }

        foreach ($cities as $city) {
            foreach ($this->searches as $query => $category) {
                if ($limit > 0 && $this->imported >= $limit) {
                    $this->printSummary();
                    return 0;
                }

                $this->line("🔍 {$city['name']}: {$query}");

                try {
                    $this->searchAndImport($query, $city, $category, $limit);
                } catch (\Exception $e) {
                    if (str_contains($e->getMessage(), 'OVER_QUERY_LIMIT') || str_contains($e->getMessage(), 'quota')) {
                        $this->error("⚠️ API 할당량 초과! 내일 다시 실행하세요.");
                        $this->printSummary();
                        return 0;
                    }
                    $this->error("에러: " . substr($e->getMessage(), 0, 100));
                    $this->errors++;
                }

                usleep(200000);
            }
        }

        $this->printSummary();
        return 0;
    }

    private function searchAndImport(string $query, array $city, string $category, int $limit)
    {
        $response = Http::get('https://maps.googleapis.com/maps/api/place/textsearch/json', [
            'query' => "{$query} in {$city['name']}, {$city['state']}",
            'key' => $this->apiKey,
            'language' => 'ko',
        ]);

        $data = $response->json();
        if (($data['status'] ?? '') === 'OVER_QUERY_LIMIT') throw new \Exception('OVER_QUERY_LIMIT');

        $results = $data['results'] ?? [];
        $this->line("  → " . count($results) . "개 발견");

        $this->processResults($results, $category, $city, $limit);

        // 2, 3 페이지도 가져오기
        for ($page = 0; $page < 2; $page++) {
            if (empty($data['next_page_token'])) break;
            if ($limit > 0 && $this->imported >= $limit) break;
            sleep(2);
            $data = Http::get('https://maps.googleapis.com/maps/api/place/textsearch/json', [
                'pagetoken' => $data['next_page_token'],
                'key' => $this->apiKey,
            ])->json();
            $this->processResults($data['results'] ?? [], $category, $city, $limit);
        }
    }

    private function processResults(array $results, string $category, array $city, int $limit)
    {
        foreach ($results as $place) {
            if ($limit > 0 && $this->imported >= $limit) return;

            $placeId = $place['place_id'] ?? null;
            if (!$placeId) continue;

            $existing = Business::where('google_place_id', $placeId)->first();
            // 이름+전화번호 중복 체크
            if (!$existing) {
                $name = $place['name'] ?? '';
                if ($name && Business::where('name', $name)->where('city', $place['vicinity'] ?? '')->exists()) continue;
            }

            $details = $this->getPlaceDetails($placeId);
            if (!$details) continue;

            $bizData = $this->mapPlaceData($details, $category, $city);

            if ($existing) {
                $existing->update([
                    'rating' => $bizData['rating'],
                    'review_count' => $bizData['review_count'],
                    'google_reviews' => $bizData['google_reviews'],
                    'phone' => $bizData['phone'] ?: $existing->phone,
                    'website' => $bizData['website'] ?: $existing->website,
                    'hours' => $bizData['hours'] ?: $existing->hours,
                    'images' => $bizData['images'] ?: $existing->images,
                ]);
                $this->updated++;
            } else {
                $biz = Business::create($bizData);
                $this->imported++;
            }

            usleep(100000);
        }
    }

    private function getPlaceDetails(string $placeId): ?array
    {
        // 한글 리뷰 먼저 시도
        $response = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
            'place_id' => $placeId,
            'key' => $this->apiKey,
            'fields' => 'name,formatted_phone_number,formatted_address,geometry,website,opening_hours,rating,user_ratings_total,reviews,address_components,photos,price_level,types',
            'language' => 'ko',
            'reviews_sort' => 'newest',
        ]);

        $data = $response->json();
        if (($data['status'] ?? '') === 'OVER_QUERY_LIMIT') throw new \Exception('OVER_QUERY_LIMIT');

        $result = $data['result'] ?? null;
        if (!$result) return null;

        // 한글 리뷰가 부족하면 영어 리뷰도 가져오기
        $koReviews = $result['reviews'] ?? [];
        if (count($koReviews) < 5) {
            try {
                $enResponse = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
                    'place_id' => $placeId,
                    'key' => $this->apiKey,
                    'fields' => 'reviews',
                    'language' => 'en',
                    'reviews_sort' => 'newest',
                ]);
                $enReviews = $enResponse->json()['result']['reviews'] ?? [];
                // 중복 제거 후 합치기
                $existingAuthors = collect($koReviews)->pluck('author_name')->toArray();
                foreach ($enReviews as $r) {
                    if (!in_array($r['author_name'] ?? '', $existingAuthors)) {
                        $koReviews[] = $r;
                    }
                }
                usleep(100000);
            } catch (\Exception $e) {}
        }

        $result['reviews'] = $koReviews;
        return $result;
    }

    private function mapPlaceData(array $details, string $category, array $city): array
    {
        $components = $details['address_components'] ?? [];
        $cityName = $city['name'];
        $stateName = $city['state'];
        $zipcode = '';

        foreach ($components as $c) {
            if (in_array('locality', $c['types'])) $cityName = $c['long_name'];
            if (in_array('administrative_area_level_1', $c['types'])) $stateName = $c['short_name'];
            if (in_array('postal_code', $c['types'])) $zipcode = $c['long_name'];
        }

        // 영업시간
        $hours = null;
        if (!empty($details['opening_hours']['weekday_text'])) {
            $hours = [];
            foreach ($details['opening_hours']['weekday_text'] as $line) {
                $parts = explode(': ', $line, 2);
                if (count($parts) === 2) $hours[$parts[0]] = $parts[1];
            }
        }

        // 사진 다운로드 (최대 3장, 서버에 저장)
        $images = [];
        foreach (array_slice($details['photos'] ?? [], 0, 3) as $photo) {
            $ref = $photo['photo_reference'] ?? null;
            if ($ref) {
                try {
                    $imgUrl = "https://maps.googleapis.com/maps/api/place/photo?maxwidth=600&photoreference={$ref}&key={$this->apiKey}";
                    $imgContent = @file_get_contents($imgUrl);
                    if ($imgContent && strlen($imgContent) > 1000) {
                        $filename = 'businesses/' . md5($ref) . '.jpg';
                        \Storage::disk('public')->put($filename, $imgContent);
                        $images[] = '/storage/' . $filename;
                    }
                } catch (\Exception $e) {}
            }
        }

        // 리뷰 (한글 우선, 최대 10개)
        $googleReviews = [];
        foreach (array_slice($details['reviews'] ?? [], 0, 10) as $r) {
            $googleReviews[] = [
                'author' => $r['author_name'] ?? '익명',
                'rating' => $r['rating'] ?? 5,
                'text' => $r['text'] ?? '',
                'time' => $r['relative_time_description'] ?? '',
                'profile_photo' => $r['profile_photo_url'] ?? '',
                'language' => $r['language'] ?? 'en',
            ];
        }

        // 카테고리 세분화
        $subcategory = '';
        $types = $details['types'] ?? [];
        if (in_array('restaurant', $types)) $subcategory = '한식';
        elseif (in_array('bakery', $types)) $subcategory = '베이커리';
        elseif (in_array('cafe', $types)) $subcategory = '카페';
        elseif (in_array('grocery_or_supermarket', $types)) $subcategory = '식료품';
        elseif (in_array('beauty_salon', $types)) $subcategory = '뷰티';
        elseif (in_array('hair_care', $types)) $subcategory = '헤어';
        elseif (in_array('dentist', $types)) $subcategory = '치과';
        elseif (in_array('doctor', $types)) $subcategory = '병원';
        elseif (in_array('lawyer', $types)) $subcategory = '법률';
        elseif (in_array('accounting', $types)) $subcategory = '회계';
        elseif (in_array('church', $types)) $subcategory = '교회';
        elseif (in_array('school', $types)) $subcategory = '학원';
        elseif (in_array('car_repair', $types)) $subcategory = '정비';
        elseif (in_array('real_estate_agency', $types)) $subcategory = '부동산';

        return [
            'google_place_id' => $details['place_id'] ?? null,
            'name' => $details['name'] ?? 'Unknown',
            'description' => '',
            'category' => $category,
            'subcategory' => $subcategory,
            'phone' => $details['formatted_phone_number'] ?? null,
            'website' => $details['website'] ?? null,
            'address' => $details['formatted_address'] ?? null,
            'city' => $cityName,
            'state' => $stateName,
            'zipcode' => $zipcode,
            'lat' => $details['geometry']['location']['lat'] ?? $city['lat'],
            'lng' => $details['geometry']['location']['lng'] ?? $city['lng'],
            'images' => $images,
            'hours' => $hours,
            'rating' => $details['rating'] ?? 0,
            'review_count' => $details['user_ratings_total'] ?? 0,
            'google_reviews' => $googleReviews,
            'is_verified' => true,
        ];
    }

    private function printSummary()
    {
        $this->newLine();
        $this->info("═══ 임포트 완료 ═══");
        $this->info("✅ 새로 추가: {$this->imported}개");
        $this->info("🔄 업데이트: {$this->updated}개");
        $this->info("❌ 에러: {$this->errors}개");
        $this->info("📊 전체 업소: " . Business::count() . "개");
    }
}
