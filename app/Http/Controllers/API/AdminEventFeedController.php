<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\EventFeedImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 관리자 › 공식 지역 행사 피드 — 출처 켜기/끄기, 몫(주·카운티·도시) 설정, 지금 한 번 실행.
 * 평소에는 매주 일요일에 자동으로 돌기 때문에 이 화면에서 매번 돌릴 필요는 없다.
 */
class AdminEventFeedController extends Controller
{
    private const RUNNING = 'event_feed_running';

    public function index()
    {
        $live = Event::where('source', EventFeedImporter::SOURCE)->where('is_active', true)->get(['source_id', 'state'])
            ->groupBy(fn ($e) => explode(':', (string) $e->source_id)[0])->map->count();
        $sources = DB::table('event_feed_sources')->orderBy('sort_order')->get()->map(function ($s) use ($live) {
            $s->live_count = (int) ($live[$s->key] ?? 0);
            unset($s->config);
            return $s;
        });
        $byState = Event::where('source', EventFeedImporter::SOURCE)->where('is_active', true)->selectRaw('state, count(*) c')->groupBy('state')->orderByDesc('c')->pluck('c', 'state');
        return response()->json(['success' => true, 'data' => [
            'sources' => $sources,
            'limits' => EventFeedImporter::limits(),
            'running' => (bool) Cache::get(self::RUNNING),
            'total' => (int) $byState->sum(),
            'by_state' => $byState,
            'last_run_at' => DB::table('event_feed_sources')->max('last_run_at'),
        ]])->header('Cache-Control', 'no-store');
    }

    public function saveLimits(Request $request)
    {
        $d = $request->validate(['per_state' => 'required|integer|min:1|max:40', 'per_county' => 'required|integer|min:1|max:20', 'per_city' => 'required|integer|min:1|max:10']);
        EventFeedImporter::saveLimits((int) $d['per_state'], (int) $d['per_county'], (int) $d['per_city']);
        return response()->json(['success' => true, 'message' => '저장했어요 (다음 가져오기부터 적용)', 'limits' => EventFeedImporter::limits()]);
    }

    public function toggleSource(string $key)
    {
        $row = DB::table('event_feed_sources')->where('key', $key)->first();
        if (!$row) return response()->json(['success' => false, 'message' => '없는 출처예요'], 404);
        DB::table('event_feed_sources')->where('id', $row->id)->update(['enabled' => !$row->enabled, 'updated_at' => now()]);
        return response()->json(['success' => true, 'enabled' => !$row->enabled]);
    }

    /** 지금 한 번 실행: key 가 있으면 그 출처만(바로 결과), 없으면 켜 둔 전체(백그라운드) */
    public function run(Request $request)
    {
        if (Cache::get(self::RUNNING)) return response()->json(['success' => false, 'message' => '이미 가져오는 중이에요. 잠시 뒤 새로 고침하세요'], 409);
        $key = (string) $request->input('key', '');
        if ($key !== '') {
            if (!DB::table('event_feed_sources')->where('key', $key)->exists()) return response()->json(['success' => false, 'message' => '없는 출처예요'], 404);
            Cache::put(self::RUNNING, $key, 900);
            set_time_limit(600);
            try { $r = EventFeedImporter::run($key); } finally { Cache::forget(self::RUNNING); }
            $s = $r['sources'][$key] ?? ['candidates' => 0, 'error' => null];
            return response()->json(['success' => !$s['error'], 'message' => $s['error'] ?: "{$key}: 후보 {$s['candidates']}개 중 {$r['selected']}개 선택 (새로 {$r['created']} · 갱신 {$r['updated']} · 정리 {$r['removed']})", 'data' => $r]);
        }

        Cache::put(self::RUNNING, 'all', 3600);
        $respond = response()->json(['success' => true, 'message' => '켜 둔 모든 출처를 백그라운드로 가져오는 중이에요. 몇 분 걸려요 — 새로 고침하면 결과가 보여요.']);
        if (function_exists('fastcgi_finish_request')) {
            $respond->send();
            fastcgi_finish_request();
        }
        ignore_user_abort(true);
        set_time_limit(0);
        try { \Artisan::call('events:import-feeds'); } finally { Cache::forget(self::RUNNING); }
        return $respond;
    }
}
