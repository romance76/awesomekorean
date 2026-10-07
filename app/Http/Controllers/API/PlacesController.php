<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PlacesController extends Controller
{
    private function getApiKey()
    {
        // 1) env 우선
        $key = env('GOOGLE_PLACES_API_KEY') ?: env('VITE_GOOGLE_MAPS_KEY');
        if ($key) return $key;

        // 2) DB api_keys 테이블 fallback
        try {
            $row = DB::table('api_keys')->where('service', 'google_maps')->first();
            if ($row && $row->api_key) return \App\Casts\Secret::reveal($row->api_key);
        } catch (\Exception $e) {}

        return null;
    }

    public function nearbySchools(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $lat = round($request->lat, 4);  // 캐시 키 정규화
        $lng = round($request->lng, 4);
        $radius = min((int) $request->input('radius', 16000), 50000); // 기본 10마일, 최대 50km

        $cacheKey = "nearby_schools_{$lat}_{$lng}_{$radius}";

        $schools = Cache::remember($cacheKey, 86400, function () use ($lat, $lng, $radius) {
            $apiKey = $this->getApiKey();
            if (!$apiKey) return ['error' => 'API key not configured'];

            $results = [];
            $pageToken = null;

            // 최대 2페이지 (40개)
            for ($page = 0; $page < 2; $page++) {
                $params = $pageToken
                    ? ['pagetoken' => $pageToken, 'key' => $apiKey]
                    : [
                        'location' => "{$lat},{$lng}",
                        'radius' => $radius,
                        'type' => 'school',
                        'key' => $apiKey,
                        'language' => 'en',
                    ];

                if ($pageToken) sleep(2); // Google requires delay

                $response = Http::get('https://maps.googleapis.com/maps/api/place/nearbysearch/json', $params);
                $data = $response->json();

                if (!empty($data['error_message'])) {
                    return ['error' => $data['error_message']];
                }

                foreach (($data['results'] ?? []) as $place) {
                    $results[] = [
                        'name' => $place['name'] ?? '',
                        'address' => $place['vicinity'] ?? '',
                        'rating' => $place['rating'] ?? null,
                        'user_ratings_total' => $place['user_ratings_total'] ?? 0,
                        'types' => $place['types'] ?? [],
                        'lat' => $place['geometry']['location']['lat'] ?? null,
                        'lng' => $place['geometry']['location']['lng'] ?? null,
                        'place_id' => $place['place_id'] ?? '',
                    ];
                }

                $pageToken = $data['next_page_token'] ?? null;
                if (!$pageToken) break;
            }

            // Sort by distance from listing
            usort($results, function ($a, $b) use ($lat, $lng) {
                $distA = pow($a['lat'] - $lat, 2) + pow($a['lng'] - $lng, 2);
                $distB = pow($b['lat'] - $lat, 2) + pow($b['lng'] - $lng, 2);
                return $distA <=> $distB;
            });

            return $results;
        });

        // 에러 체크
        if (is_array($schools) && isset($schools['error'])) {
            return response()->json(['success' => false, 'message' => $schools['error'], 'data' => []], 200);
        }

        return response()->json(['success' => true, 'data' => $schools]);
    }
}
