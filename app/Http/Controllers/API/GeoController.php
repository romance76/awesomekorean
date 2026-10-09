<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * 접속자 IP 기반 대략적 위치 (도시 단위).
 * - 프로필에 주소가 없는 회원/비회원의 "내 지역" 기본값으로만 사용.
 * - IP 는 저장하지 않고, 캐시 키에는 해시만 사용한다.
 */
class GeoController extends Controller
{
    public function ip(Request $request)
    {
        $ip = $this->clientIp($request);
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return response()->json(['success' => true, 'data' => null]);
        }

        $key = 'geo:ip:' . sha1($ip);
        $cached = Cache::get($key);
        if ($cached !== null) {
            return response()->json(['success' => true, 'data' => $cached === false ? null : $cached]);
        }

        $result = $this->lookup($ip);
        if ($result === 'error') {
            // 일시 장애: 잠깐만 음성 캐시 (공급자 과호출 방지)
            Cache::put($key, false, now()->addMinutes(5));
            return response()->json(['success' => true, 'data' => null]);
        }
        // 성공/미국 외 모두 24시간 (미국 외는 false)
        Cache::put($key, $result ?: false, now()->addHours(24));

        return response()->json(['success' => true, 'data' => $result ?: null]);
    }

    /** @return array|false|string  array=위치, false=미국 외/알 수 없음, 'error'=조회 실패 */
    private function lookup(string $ip)
    {
        try {
            $res = Http::timeout(3)->connectTimeout(2)->acceptJson()
                ->get('https://ipwho.is/' . rawurlencode($ip), [
                    'fields' => 'success,country_code,region_code,city,latitude,longitude',
                ]);
            if (!$res->ok()) return 'error';
            $j = $res->json();
            if (!is_array($j) || empty($j['success'])) return false;
            if (($j['country_code'] ?? '') !== 'US') return false;
            $city = trim((string) ($j['city'] ?? ''));
            $state = strtoupper(trim((string) ($j['region_code'] ?? '')));
            $lat = $j['latitude'] ?? null;
            $lng = $j['longitude'] ?? null;
            if ($city === '' || strlen($state) !== 2 || !is_numeric($lat) || !is_numeric($lng)) return false;
            return [
                'city'   => mb_substr($city, 0, 80),
                'state'  => $state,
                'lat'    => round((float) $lat, 4),
                'lng'    => round((float) $lng, 4),
                'source' => 'ip',
            ];
        } catch (\Throwable $e) {
            return 'error';
        }
    }

    /**
     * 접속자 IP. $request->ip() 우선(php-fpm 의 REMOTE_ADDR).
     * 그 값이 사설/루프백(= 내부 프록시 뒤)일 때만 X-Forwarded-For 의 가장 오른쪽 공인 IP 를 사용한다
     * (오른쪽 항목은 우리 프록시가 붙인 값. 임의 클라이언트 헤더를 바로 신뢰하지 않음).
     */
    private function clientIp(Request $request): ?string
    {
        $ip = $request->ip();
        $public = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP, $public)) return $ip;

        $xff = (string) $request->headers->get('X-Forwarded-For', '');
        if ($xff !== '') {
            $parts = array_reverse(array_map('trim', explode(',', $xff)));
            foreach ($parts as $p) {
                if (filter_var($p, FILTER_VALIDATE_IP, $public)) return $p;
            }
        }
        return $ip ?: null;
    }
}
