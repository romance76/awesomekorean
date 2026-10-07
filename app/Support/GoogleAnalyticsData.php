<?php

namespace App\Support;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * 구글 애널리틱스(GA4) Data API 읽기 전용 클라이언트.
 * 서비스 계정 JSON 은 api_keys(service=ga_service_account)에 암호화되어 저장되고, 속성 ID 는 service=ga_property_id.
 * 외부 라이브러리 없이 서비스 계정 JWT(RS256)로 토큰을 받아 쓴다. 토큰은 50분 캐시.
 */
class GoogleAnalyticsData
{
    public const SA_SERVICE = 'ga_service_account';
    public const PROP_SERVICE = 'ga_property_id';
    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';
    private const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    public static function credentials(): ?array
    {
        $json = ApiKey::keyFor(self::SA_SERVICE);
        $creds = $json ? json_decode($json, true) : null;
        return is_array($creds) && !empty($creds['client_email']) && !empty($creds['private_key']) ? $creds : null;
    }

    public static function propertyId(): ?string
    {
        $id = trim((string) ApiKey::keyFor(self::PROP_SERVICE));
        return preg_match('/^\d{5,15}$/', $id) ? $id : null;
    }

    /** 업로드된 JSON 문자열 검증 → 서비스 계정 배열(없으면 오류 메시지 문자열) */
    public static function parseServiceAccount(string $json): array|string
    {
        $c = json_decode($json, true);
        if (!is_array($c) || ($c['type'] ?? '') !== 'service_account') return '서비스 계정 JSON 파일이 아니에요.';
        if (empty($c['client_email']) || !preg_match('/^[A-Za-z0-9._-]+@[A-Za-z0-9.-]+\.iam\.gserviceaccount\.com$/', $c['client_email'])) return '서비스 계정 이메일이 올바르지 않아요.';
        if (empty($c['private_key']) || !str_contains($c['private_key'], 'BEGIN PRIVATE KEY')) return '개인 키가 들어 있지 않아요.';
        return ['type' => 'service_account', 'client_email' => $c['client_email'], 'private_key' => $c['private_key'], 'project_id' => $c['project_id'] ?? null, 'token_uri' => self::TOKEN_URI];
    }

    private static function b64(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

    /** @return array{0:?string,1:?string} [토큰, 오류] */
    public static function accessToken(array $creds): array
    {
        $key = 'ga-token:' . sha1($creds['client_email']);
        if ($t = Cache::get($key)) return [$t, null];

        $now = time();
        $head = self::b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = self::b64(json_encode(['iss' => $creds['client_email'], 'scope' => self::SCOPE, 'aud' => self::TOKEN_URI, 'iat' => $now, 'exp' => $now + 3600]));
        $sig = '';
        $pk = openssl_pkey_get_private($creds['private_key']);
        if (!$pk || !openssl_sign("$head.$claim", $sig, $pk, OPENSSL_ALGO_SHA256)) return [null, '개인 키를 읽지 못했어요.'];

        $res = Http::asForm()->timeout(15)->post(self::TOKEN_URI, ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => "$head.$claim." . self::b64($sig)]);
        if (!$res->successful() || !$res->json('access_token')) return [null, '구글 인증에 실패했어요: ' . ($res->json('error_description') ?: $res->json('error') ?: $res->status())];
        Cache::put($key, $res->json('access_token'), 3000);
        return [$res->json('access_token'), null];
    }

    /** Data API 호출. @return array{0:?array,1:?string} [응답, 오류] */
    public static function call(array $creds, string $propertyId, string $method, array $body): array
    {
        [$token, $err] = self::accessToken($creds);
        if (!$token) return [null, $err];
        $res = Http::withToken($token)->timeout(25)->post("https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:{$method}", $body);
        if (!$res->successful()) {
            $msg = (string) $res->json('error.message');
            if ($res->status() === 403) $msg = '권한이 없어요. 애널리틱스 속성 액세스 관리에서 서비스 계정 이메일을 뷰어로 추가했는지, Analytics Data API가 켜져 있는지 확인하세요.';
            elseif ($res->status() === 404 || $res->status() === 400) $msg = '속성 ID를 확인하세요. (' . mb_substr($msg, 0, 120) . ')';
            return [null, $msg ?: ('HTTP ' . $res->status())];
        }
        return [$res->json(), null];
    }

    private static function rows(?array $report, array $dims, array $mets): array
    {
        $out = [];
        foreach (($report['rows'] ?? []) as $r) {
            $row = [];
            foreach ($dims as $i => $d) $row[$d] = $r['dimensionValues'][$i]['value'] ?? '';
            foreach ($mets as $i => $m) $row[$m] = (float) ($r['metricValues'][$i]['value'] ?? 0);
            $out[] = $row;
        }
        return $out;
    }

    /** 화면용 요약. @return array{0:?array,1:?string} */
    public static function dashboard(int $days): array
    {
        $creds = self::credentials(); $pid = self::propertyId();
        if (!$creds || !$pid) return [null, '구글 애널리틱스가 아직 연결되지 않았어요.'];

        $range = [['startDate' => "{$days}daysAgo", 'endDate' => 'today']];
        [$batch, $err] = self::call($creds, $pid, 'batchRunReports', ['requests' => [
            ['dateRanges' => $range, 'metrics' => [['name' => 'activeUsers'], ['name' => 'newUsers'], ['name' => 'sessions'], ['name' => 'screenPageViews'], ['name' => 'averageSessionDuration'], ['name' => 'engagementRate']]],
            ['dateRanges' => $range, 'dimensions' => [['name' => 'date']], 'metrics' => [['name' => 'activeUsers'], ['name' => 'screenPageViews']], 'orderBys' => [['dimension' => ['dimensionName' => 'date']]], 'limit' => 100],
            ['dateRanges' => $range, 'dimensions' => [['name' => 'pagePath'], ['name' => 'pageTitle']], 'metrics' => [['name' => 'screenPageViews'], ['name' => 'activeUsers']], 'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]], 'limit' => 10],
            ['dateRanges' => $range, 'dimensions' => [['name' => 'sessionDefaultChannelGroup']], 'metrics' => [['name' => 'sessions']], 'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]], 'limit' => 8],
            ['dateRanges' => $range, 'dimensions' => [['name' => 'country']], 'metrics' => [['name' => 'activeUsers']], 'orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]], 'limit' => 8],
        ]]);
        if (!$batch) return [null, $err];

        $r = $batch['reports'] ?? [];
        $tot = self::rows($r[0] ?? null, [], ['activeUsers', 'newUsers', 'sessions', 'pageViews', 'avgSessionSec', 'engagementRate'])[0] ?? array_fill_keys(['activeUsers', 'newUsers', 'sessions', 'pageViews', 'avgSessionSec', 'engagementRate'], 0);

        [$rt] = self::call($creds, $pid, 'runRealtimeReport', ['metrics' => [['name' => 'activeUsers']]]);

        return [[
            'days' => $days,
            'totals' => $tot,
            'realtime_users' => (int) ($rt['rows'][0]['metricValues'][0]['value'] ?? 0),
            'daily' => self::rows($r[1] ?? null, ['date'], ['users', 'views']),
            'pages' => self::rows($r[2] ?? null, ['path', 'title'], ['views', 'users']),
            'channels' => self::rows($r[3] ?? null, ['channel'], ['sessions']),
            'countries' => self::rows($r[4] ?? null, ['country'], ['users']),
        ], null];
    }
}
