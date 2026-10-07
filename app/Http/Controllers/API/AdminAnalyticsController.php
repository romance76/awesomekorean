<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InfoPost;
use App\Models\ApiKey;
use App\Support\Analytics;
use App\Support\GoogleAnalyticsData as GA;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** 관리자 "방문 분석": 구글 애널리틱스 켜짐 상태와, 사이트 전체 페이지에 추적 코드가 실제로 들어갔는지 검사 */
class AdminAnalyticsController extends Controller
{
    // 화면(Vue) 쪽 주요 주소 — 전부 같은 HTML 껍데기를 쓰지만, 서버가 라우트마다 정상 응답하는지도 함께 본다
    private const SPA_PATHS = ['/', '/market', '/realestate', '/jobs', '/directory', '/clubs', '/news', '/recipes', '/community', '/events', '/shopping', '/games', '/music', '/shorts', '/stocks', '/groupbuy', '/login', '/register'];

    public function status()
    {
        $id = Analytics::measurementId();
        $creds = GA::credentials();
        return response()->json(['success' => true, 'data' => [
            'enabled' => (bool) $id, 'measurement_id' => $id,
            'connected' => (bool) ($creds && GA::propertyId()),
            'service_account_email' => $creds['client_email'] ?? null,
            'property_id' => GA::propertyId(),
        ]]);
    }

    /** 서비스 계정 JSON + 속성 ID 연결 — 실제로 구글에서 읽어 보는 데 성공해야 저장한다 */
    public function saveCredentials(Request $request)
    {
        $d = $request->validate(['service_account_json' => 'required|string|max:20000', 'property_id' => ['required', 'regex:/^\d{5,15}$/']]);
        $creds = GA::parseServiceAccount($d['service_account_json']);
        if (is_string($creds)) return response()->json(['success' => false, 'message' => $creds], 422);

        [$res, $err] = GA::call($creds, $d['property_id'], 'runRealtimeReport', ['metrics' => [['name' => 'activeUsers']]]);
        if (!$res) return response()->json(['success' => false, 'message' => $err ?: '구글에서 읽지 못했어요.'], 422);

        $this->putKey(GA::SA_SERVICE, '구글 애널리틱스 서비스 계정 키', json_encode($creds), '방문 분석 화면용 읽기 전용 서비스 계정(암호화 저장)');
        $this->putKey(GA::PROP_SERVICE, '구글 애널리틱스 속성 ID', $d['property_id'], '방문 분석 화면용 GA4 속성 ID');
        Cache::forget('ga-token:' . sha1($creds['client_email']));
        foreach ([7, 28, 90] as $n) Cache::forget("ga-dash:$n");
        \App\Support\KeyReport::send('구글 애널리틱스 서비스 계정 연결 (' . $creds['client_email'] . ')', auth()->user()->email ?? '관리자');

        return response()->json(['success' => true, 'message' => '연결되었어요.', 'data' => ['service_account_email' => $creds['client_email'], 'property_id' => $d['property_id']]]);
    }

    public function deleteCredentials()
    {
        ApiKey::whereIn('service', [GA::SA_SERVICE, GA::PROP_SERVICE])->delete();
        foreach ([7, 28, 90] as $n) Cache::forget("ga-dash:$n");
        \App\Support\KeyReport::send('구글 애널리틱스 연결 해제', auth()->user()->email ?? '관리자');
        return response()->json(['success' => true]);
    }

    private function putKey(string $service, string $name, string $value, string $desc): void
    {
        $row = ApiKey::where('service', $service)->first();
        if ($row) $row->update(['name' => $name, 'api_key' => $value, 'description' => $desc, 'is_active' => true]);
        else ApiKey::create(['name' => $name, 'service' => $service, 'api_key' => $value, 'description' => $desc, 'is_active' => true]);
    }

    public function dashboard(Request $request)
    {
        $days = in_array((int) $request->query('days'), [7, 28, 90], true) ? (int) $request->query('days') : 7;
        if ($request->boolean('fresh')) Cache::forget("ga-dash:$days");

        $cached = Cache::get("ga-dash:$days");
        if ($cached) return response()->json(['success' => true, 'data' => $cached]);

        [$data, $err] = GA::dashboard($days);
        if (!$data) return response()->json(['success' => false, 'message' => $err ?: '불러오지 못했어요.'], 422);
        $data['fetched_at'] = now()->toIso8601String();
        Cache::put("ga-dash:$days", $data, 600);
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function check()
    {
        Analytics::forget();
        $id = Analytics::measurementId();
        if (!$id) {
            return response()->json(['success' => true, 'data' => [
                'enabled' => false, 'measurement_id' => null, 'total' => 0, 'with_tag' => 0, 'missing' => [],
                'message' => '측정 ID가 등록되어 있지 않아요. 관리자 › 설정 › API 키 관리에서 서비스 코드 google_analytics 로 G- 로 시작하는 측정 ID를 등록하세요.',
            ]]);
        }

        $base = rtrim(config('app.url'), '/');
        $urls = array_map(fn ($p) => $base . $p, self::SPA_PATHS);
        $urls[] = $base . '/info';
        foreach (InfoPost::published()->orderByDesc('published_at')->limit(40)->pluck('slug') as $slug) {
            $urls[] = $base . '/info/' . rawurlencode($slug);
        }

        $needle = 'googletagmanager.com/gtag/js?id=' . $id;
        $withTag = 0;
        $missing = [];
        foreach (array_chunk($urls, 10) as $chunk) {
            $responses = Http::pool(fn ($pool) => array_map(
                fn ($u) => $pool->as($u)->timeout(15)->withHeaders(['User-Agent' => 'AwesomeKoreanSiteCheck/1.0'])->get($u),
                $chunk
            ));
            foreach ($chunk as $u) {
                $r = $responses[$u] ?? null;
                $ok = $r instanceof \Illuminate\Http\Client\Response && $r->successful()
                    && str_contains($r->body(), $needle) && str_contains($r->body(), "gtag('config', '{$id}')");
                if ($ok) $withTag++;
                else $missing[] = ['url' => $u, 'status' => $r instanceof \Illuminate\Http\Client\Response ? $r->status() : 0];
            }
        }

        return response()->json(['success' => true, 'data' => [
            'enabled' => true, 'measurement_id' => $id, 'total' => count($urls), 'with_tag' => $withTag, 'missing' => $missing,
            'message' => $missing ? '일부 페이지에서 추적 코드를 찾지 못했어요.' : '검사한 모든 페이지에 추적 코드가 들어 있어요.',
        ]]);
    }
}
