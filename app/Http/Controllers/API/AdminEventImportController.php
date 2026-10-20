<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\EventImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 관리자 › 지역 행사 가져오기 — Ticketmaster 에서 가져오는 주(State) 관리, API 키, 가져온 행사 숨김/삭제.
 */
class AdminEventImportController extends Controller
{
    private const RUNNING = 'event_import_running';

    public function index()
    {
        $counts = Event::where('source', EventImporter::SOURCE)->selectRaw('state, count(*) c, sum(is_active = 0) hidden')->groupBy('state')->get()->keyBy('state');
        $states = DB::table('event_import_states')->orderBy('sort_order')->get()->map(function ($s) use ($counts) {
            $s->event_count = (int) ($counts[$s->code]->c ?? 0);
            $s->hidden_count = (int) ($counts[$s->code]->hidden ?? 0);
            return $s;
        });
        $byCat = Event::where('source', EventImporter::SOURCE)->where('is_active', true)->where('start_date', '>=', now())
            ->selectRaw('category, count(*) c')->groupBy('category')->pluck('c', 'category');
        return response()->json(['success' => true, 'data' => [
            'key_set' => (bool) EventImporter::apiKey(),
            'key_source' => EventImporter::keySource(),
            'key_masked' => EventImporter::maskedKey(),
            'per_state' => EventImporter::perState(),
            'running' => (bool) Cache::get(self::RUNNING),
            'progress' => Cache::get(self::RUNNING) ? Cache::get('event_import_progress') : null,
            'states' => $states,
            'total' => (int) $counts->sum('c'),
            'upcoming_by_category' => $byCat,
            'last_run_at' => DB::table('event_import_states')->max('last_run_at'),
        ]])->header('Cache-Control', 'no-store');
    }

    public function saveKey(Request $request)
    {
        $data = $request->validate(['key' => 'nullable|string|max:120']);
        EventImporter::saveKey($data['key'] ?? null);
        return response()->json(['success' => true, 'message' => ($data['key'] ?? '') === '' ? 'API 키를 지웠어요' : 'API 키를 저장했어요', 'key_set' => (bool) EventImporter::apiKey(), 'key_masked' => EventImporter::maskedKey()]);
    }

    /** 주마다 가져올 행사 수 (3~40) */
    public function savePerState(Request $request)
    {
        $data = $request->validate(['per_state' => 'required|integer|min:3|max:40']);
        EventImporter::savePerState((int) $data['per_state']);
        return response()->json(['success' => true, 'message' => "주마다 {$data['per_state']}개씩 가져오도록 저장했어요 (다음 가져오기부터 적용)", 'per_state' => EventImporter::perState()]);
    }

    /** 주 하나 켜기/끄기 */
    public function toggleState(string $code)
    {
        $row = DB::table('event_import_states')->where('code', strtoupper($code))->first();
        if (!$row) return response()->json(['success' => false, 'message' => '없는 주예요'], 404);
        DB::table('event_import_states')->where('id', $row->id)->update(['enabled' => !$row->enabled, 'updated_at' => now()]);
        return response()->json(['success' => true, 'enabled' => !$row->enabled]);
    }

    /** 모든 주 한꺼번에 켜기/끄기 */
    public function setAllStates(Request $request)
    {
        $enabled = $request->boolean('enabled');
        DB::table('event_import_states')->update(['enabled' => $enabled, 'updated_at' => now()]);
        return response()->json(['success' => true, 'enabled' => $enabled]);
    }

    /** 지금 가져오기: state 가 있으면 그 주만(바로 결과), 없으면 켜 둔 모든 주(백그라운드) */
    public function run(Request $request)
    {
        if (!EventImporter::apiKey()) return response()->json(['success' => false, 'message' => '먼저 Ticketmaster API 키를 저장하세요'], 422);
        if (Cache::get(self::RUNNING)) return response()->json(['success' => false, 'message' => '이미 가져오는 중이에요. 잠시 뒤 새로 고침하세요'], 409);

        $state = strtoupper((string) $request->input('state', ''));
        if ($state !== '') {
            if (!DB::table('event_import_states')->where('code', $state)->exists()) return response()->json(['success' => false, 'message' => '없는 주예요'], 404);
            Cache::put(self::RUNNING, $state, 600);
            set_time_limit(180);
            try { $r = EventImporter::importState($state); } finally { Cache::forget(self::RUNNING); }
            return response()->json(['success' => !$r['error'], 'message' => $r['error'] ?: "{$state}: 대표 행사 {$r['fetched']}개 (새로 {$r['created']} · 갱신 {$r['updated']} · 정리 {$r['removed']})", 'data' => $r]);
        }

        Cache::put(self::RUNNING, 'all', 1500); // 도중에 프로세스가 죽어도 25분 뒤엔 다시 실행 가능
        Cache::put('event_import_progress', ['done' => 0, 'total' => (int) DB::table('event_import_states')->where('enabled', true)->count(), 'current' => '', 'fetched' => 0, 'created' => 0], 1500);
        $respond = response()->json(['success' => true, 'message' => '켜 둔 모든 주를 백그라운드로 가져오는 중이에요. 몇 분 걸려요 — 새로 고침하면 진행이 보여요.']);
        if (function_exists('fastcgi_finish_request')) {
            $respond->send();
            fastcgi_finish_request();
        }
        ignore_user_abort(true);
        set_time_limit(0);
        try { \Artisan::call('events:import'); } finally { Cache::forget(self::RUNNING); }
        return $respond;
    }

    /** 가져온 행사 목록 (숨긴 것 포함) */
    public function events(Request $request)
    {
        $q = Event::where('source', EventImporter::SOURCE)
            ->when($request->state, fn ($q, $v) => $q->where('state', strtoupper($v)))
            ->when($request->category, fn ($q, $v) => $q->where('category', $v))
            ->when($request->has('is_active') && $request->is_active !== '', fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->q, fn ($q, $v) => $q->where(fn ($q2) => $q2->where('title', 'like', "%{$v}%")->orWhere('venue', 'like', "%{$v}%")->orWhere('city', 'like', "%{$v}%")))
            ->orderBy('start_date');
        return response()->json(['success' => true, 'data' => $q->select('id', 'title', 'category', 'venue', 'city', 'state', 'start_date', 'is_active', 'url', 'image_url')->paginate(min(100, (int) ($request->per_page ?: 30)))]);
    }

    public function toggleEvent(int $id)
    {
        $ev = Event::where('source', EventImporter::SOURCE)->findOrFail($id);
        $ev->update(['is_active' => !$ev->is_active]);
        return response()->json(['success' => true, 'is_active' => $ev->is_active]);
    }

    public function destroyEvent(int $id)
    {
        Event::where('source', EventImporter::SOURCE)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}
