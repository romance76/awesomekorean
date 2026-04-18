<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ApiKeyManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * /admin/v2/integrations/api-keys 관리 API (Phase 2-C 묶음 6).
 */
class AdminApiKeysController extends Controller
{
    public function index()
    {
        $rows = DB::table('api_keys')->orderBy('service')->get()
            ->map(function ($r) {
                $plain = $r->api_key;
                try { $plain = Crypt::decryptString($r->api_key); } catch (\Throwable $e) {}
                $r->api_key_masked = ApiKeyManager::mask($plain);
                unset($r->api_key);
                return $r;
            });

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function usage(Request $request)
    {
        $days = min((int) $request->query('days', 7), 90);
        $since = now()->subDays($days)->toDateString();

        $rows = DB::table('api_usage')
            ->where('date', '>=', $since)
            ->orderBy('service')->orderBy('date')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function logs(Request $request)
    {
        $service = $request->query('service');
        $level   = $request->query('level');
        $q = DB::table('api_logs');
        if ($service) $q->where('service', $service);
        if ($level)   $q->where('level', $level);
        return response()->json([
            'success' => true,
            'data' => $q->orderByDesc('created_at')->limit(100)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'service'      => 'required|string|max:100',
            'name'         => 'required|string|max:255',
            'api_key'      => 'required|string',
            'description'  => 'nullable|string',
            'docs_url'     => 'nullable|url',
            'feature_list' => 'nullable|string',
            'quota_limit'  => 'nullable|integer',
            'quota_period' => 'nullable|in:daily,monthly',
            'test_mode'    => 'boolean',
            'is_active'    => 'boolean',
        ]);
        $encrypted = Crypt::encryptString($data['api_key']);
        $id = DB::table('api_keys')->insertGetId([
            'service'      => $data['service'],
            'name'         => $data['name'],
            'api_key'      => $encrypted,
            'description'  => $data['description'] ?? null,
            'docs_url'     => $data['docs_url'] ?? null,
            'feature_list' => $data['feature_list'] ?? null,
            'quota_limit'  => $data['quota_limit'] ?? 0,
            'quota_period' => $data['quota_period'] ?? 'daily',
            'test_mode'    => $data['test_mode'] ?? false,
            'is_active'    => $data['is_active'] ?? true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $this->auditLog($data['service'], 'created', null, ApiKeyManager::mask($data['api_key']));
        Cache::forget("apikey.{$data['service']}");
        return response()->json(['success' => true, 'id' => $id]);
    }

    public function update(Request $request, $id)
    {
        $row = DB::table('api_keys')->where('id', $id)->first();
        if (!$row) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        $data = $request->only(['name','description','docs_url','feature_list','quota_limit','quota_period','test_mode','is_active']);
        $data['updated_at'] = now();

        if ($request->filled('api_key')) {
            $oldPlain = $row->api_key;
            try { $oldPlain = Crypt::decryptString($row->api_key); } catch (\Throwable $e) {}
            $data['api_key'] = Crypt::encryptString($request->input('api_key'));
            $this->auditLog($row->service, 'rotated', ApiKeyManager::mask($oldPlain), ApiKeyManager::mask($request->input('api_key')));
        } else {
            $this->auditLog($row->service, 'updated');
        }

        DB::table('api_keys')->where('id', $id)->update($data);
        Cache::forget("apikey.{$row->service}");
        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        $row = DB::table('api_keys')->where('id', $id)->first();
        if (!$row) return response()->json(['success' => false], 404);
        $this->auditLog($row->service, 'deleted');
        DB::table('api_keys')->where('id', $id)->delete();
        Cache::forget("apikey.{$row->service}");
        return response()->json(['success' => true]);
    }

    public function reveal($id)
    {
        // super_admin 만 허용 — 라우트에서 permission 체크
        $row = DB::table('api_keys')->where('id', $id)->first();
        if (!$row) return response()->json(['success' => false], 404);
        $plain = $row->api_key;
        try { $plain = Crypt::decryptString($row->api_key); } catch (\Throwable $e) {}
        $this->auditLog($row->service, 'revealed');
        return response()->json(['success' => true, 'api_key' => $plain]);
    }

    /**
     * 실제 서비스별 헬스 체크. Phase 2-C Post.
     * 각 API 에 가벼운 읽기 호출 → 응답시간·에러 측정.
     */
    public function test($id)
    {
        $row = DB::table('api_keys')->where('id', $id)->first();
        if (!$row) return response()->json(['success' => false], 404);

        $plain = $row->api_key;
        try { $plain = Crypt::decryptString($row->api_key); } catch (\Throwable $e) {}

        $start = microtime(true);
        $result = $this->healthCheckByService($row->service, $plain);
        $elapsedMs = (int) ((microtime(true) - $start) * 1000);

        // api_usage 기록
        \App\Services\ApiKeyManager::recordCall(
            $row->service,
            $result['ok'],
            $elapsedMs,
            $result['http_status'] ?? null,
            $result['ok'] ? null : ($result['error'] ?? 'test failed')
        );

        $this->auditLog($row->service, 'tested');

        return response()->json([
            'success'          => true,
            'service'          => $row->service,
            'health_ok'        => $result['ok'],
            'response_time_ms' => $elapsedMs,
            'http_status'      => $result['http_status'] ?? null,
            'detail'           => $result['detail'] ?? null,
            'error'            => $result['error'] ?? null,
            'is_active'        => (bool) $row->is_active,
            'test_mode'        => (bool) ($row->test_mode ?? false),
            'key_length'       => strlen($plain),
            'last_verified_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * 서비스별 실제 헬스 체크. 각각 가장 가벼운 엔드포인트 호출.
     */
    protected function healthCheckByService(string $service, string $key): array
    {
        try {
            switch ($service) {
                case 'youtube':
                case 'youtube_data_api':
                case 'google_youtube':
                    // 매우 가벼운 search: maxResults=1, quota 1 unit
                    $res = \Http::timeout(8)->get('https://www.googleapis.com/youtube/v3/search', [
                        'part' => 'id', 'q' => 'test', 'maxResults' => 1, 'key' => $key,
                    ]);
                    return [
                        'ok'          => $res->ok(),
                        'http_status' => $res->status(),
                        'detail'      => $res->ok() ? 'YouTube API 응답 OK' : ($res->json('error.message') ?? 'YouTube API 오류'),
                        'error'       => $res->ok() ? null : $res->json('error.message'),
                    ];

                case 'google_places':
                case 'google_maps':
                case 'places':
                    // 가장 싼 findplacefromtext
                    $res = \Http::timeout(8)->get('https://maps.googleapis.com/maps/api/place/findplacefromtext/json', [
                        'input' => 'Seoul', 'inputtype' => 'textquery', 'fields' => 'name', 'key' => $key,
                    ]);
                    $status = $res->json('status');
                    return [
                        'ok'          => $status === 'OK' || $status === 'ZERO_RESULTS',
                        'http_status' => $res->status(),
                        'detail'      => "Places status={$status}",
                        'error'       => in_array($status, ['REQUEST_DENIED','INVALID_REQUEST','OVER_QUERY_LIMIT'], true) ? $status : null,
                    ];

                case 'stripe':
                case 'stripe_secret':
                    // 계정 정보 조회 (캐시 우회 싸구려 호출)
                    $res = \Http::timeout(8)->withToken($key)->get('https://api.stripe.com/v1/balance');
                    return [
                        'ok'          => $res->ok(),
                        'http_status' => $res->status(),
                        'detail'      => $res->ok() ? 'Stripe Balance API OK' : ($res->json('error.message') ?? 'Stripe 오류'),
                        'error'       => $res->ok() ? null : $res->json('error.message'),
                    ];

                case 'foodsafety':
                case 'foodsafety_kr':
                    $baseUrl = config('services.foodsafety.url', 'http://openapi.foodsafetykorea.go.kr/api');
                    $svc     = config('services.foodsafety.service', 'COOKRCP01');
                    $url     = rtrim($baseUrl, '/') . "/{$key}/{$svc}/json/1/1";
                    $res = \Http::timeout(8)->get($url);
                    $code = $res->json('COOKRCP01.RESULT.CODE');
                    return [
                        'ok'          => $code === 'INFO-000',
                        'http_status' => $res->status(),
                        'detail'      => "Foodsafety code={$code}",
                        'error'       => $code !== 'INFO-000' ? ($res->json('COOKRCP01.RESULT.MSG') ?? 'Foodsafety 오류') : null,
                    ];

                case 'arirang_news':
                    $res = \Http::timeout(8)->get('https://apis.data.go.kr/1130000/AriranglstService/getAriranglst01', [
                        'serviceKey' => $key, 'pageNo' => 1, 'numOfRows' => 1, 'type' => 'json',
                    ]);
                    return [
                        'ok'          => $res->ok(),
                        'http_status' => $res->status(),
                        'detail'      => $res->ok() ? 'Arirang OK' : '응답 실패',
                        'error'       => $res->ok() ? null : $res->body(),
                    ];

                case 'reverb':
                    // WebSocket 포트 연결 체크 (로컬 8080)
                    $fp = @fsockopen('127.0.0.1', 8080, $errno, $errstr, 3);
                    if ($fp) { fclose($fp); return ['ok' => true, 'http_status' => null, 'detail' => 'Reverb 포트 8080 LISTEN']; }
                    return ['ok' => false, 'http_status' => null, 'error' => "fsockopen failed: {$errstr} ({$errno})"];

                case 'jwt':
                    // JWT secret 존재 + 테스트 토큰 생성 가능 여부
                    return [
                        'ok' => !empty($key) && strlen($key) >= 32,
                        'http_status' => null,
                        'detail' => !empty($key) ? 'JWT secret ' . strlen($key) . ' chars' : 'secret 없음',
                    ];

                default:
                    // 알 수 없는 서비스: 키 존재 여부만 확인
                    return [
                        'ok'          => !empty($key),
                        'http_status' => null,
                        'detail'      => '[' . $service . '] 서비스별 헬스체크 미구현 — 키 존재 여부만 확인',
                        'error'       => empty($key) ? '키가 비어있습니다' : null,
                    ];
            }
        } catch (\Throwable $e) {
            return [
                'ok'    => false,
                'error' => $e->getMessage(),
                'detail'=> 'Exception: ' . get_class($e),
            ];
        }
    }

    public function history(Request $request)
    {
        $service = $request->query('service');
        $q = DB::table('api_key_history')
            ->leftJoin('users', 'users.id', '=', 'api_key_history.changed_by')
            ->select('api_key_history.*', 'users.nickname as changed_by_name');
        if ($service) $q->where('service', $service);
        return response()->json([
            'success' => true,
            'data' => $q->orderByDesc('api_key_history.created_at')->limit(100)->get(),
        ]);
    }

    protected function auditLog(string $service, string $action, ?string $oldMasked = null, ?string $newMasked = null): void
    {
        DB::table('api_key_history')->insert([
            'service'          => $service,
            'changed_by'       => auth()->id(),
            'action'           => $action,
            'old_value_masked' => $oldMasked,
            'new_value_masked' => $newMasked,
            'ip'               => request()->ip(),
            'created_at'       => now(),
        ]);
    }
}
