<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MarketQuote;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** 메인 화면의 실시간 숫자(오늘 방문자/지금 접속/채팅 사용자)와 상단 마퀴 */
class SiteLiveController extends Controller
{
    private const ET = 'America/New_York';

    /** 방문 신호: 브라우저가 2분마다 보낸다. 오늘(미국 동부 날짜) 한 사람은 한 번만 센다. */
    public function ping(Request $request)
    {
        $vid = (string) $request->input('vid');
        if (strlen($vid) < 8 || strlen($vid) > 64) return response()->json(['ok' => false], 422);

        $key = sha1($vid);
        $today = Carbon::now(self::ET)->toDateString();
        $userId = null;
        try { $userId = auth('api')->id(); } catch (\Throwable $e) {}

        $updated = DB::table('site_visits')->where('visit_date', $today)->where('visitor_key', $key)
            ->update(['last_seen_at' => now(), 'user_id' => DB::raw('COALESCE(user_id, ' . ($userId ? (int) $userId : 'NULL') . ')')]);
        if (!$updated) {
            try {
                DB::table('site_visits')->insert(['visit_date' => $today, 'visitor_key' => $key, 'user_id' => $userId, 'first_seen_at' => now(), 'last_seen_at' => now()]);
                Cache::forget('site-live-stats'); Cache::forget('site-ticker');   // 새 방문자가 생기면 숫자를 바로 새로 센다
            } catch (\Throwable $e) { /* 동시에 들어온 같은 방문자: 무시 */ }
        }
        return response()->json(['ok' => true]);
    }

    /** 오픈 채팅방을 열어 둔 회원 표시 (방을 열고 있는 동안 1분마다) */
    public function chatPing(Request $request)
    {
        $uid = $request->user()->id;
        $vals = ['room_id' => (int) $request->input('room_id') ?: null, 'last_seen_at' => now()];
        try {
            DB::table('chat_presence')->updateOrInsert(['user_id' => $uid], $vals);
        } catch (\Illuminate\Database\QueryException $e) {
            // 같은 사용자의 요청이 동시에 두 번 들어와 insert 가 겹친 경우(중복 키) → 업데이트로 마무리
            DB::table('chat_presence')->where('user_id', $uid)->update($vals);
        }
        return response()->json(['ok' => true]);
    }

    public function stats()
    {
        return response()->json(['success' => true, 'data' => $this->statsData()]);
    }

    private function statsData(): array
    {
        return Cache::remember('site-live-stats', 20, function () {
            $today = Carbon::now(self::ET)->toDateString();
            return [
                'today_visitors' => (int) DB::table('site_visits')->where('visit_date', $today)->count(),
                'online_now'     => (int) DB::table('site_visits')->where('last_seen_at', '>=', now()->subMinutes(5))->count(),
                'chat_users'     => (int) DB::table('chat_presence')->where('last_seen_at', '>=', now()->subMinutes(3))->count(),
                'as_of'          => now()->toISOString(),
            ];
        });
    }

    /** 상단 마퀴: 실제 최신 활동 + 실시간 숫자 + 시세. 시간은 ISO 로 내려주고 화면에서 "N분 전"으로 계산 */
    public function ticker()
    {
        $data = Cache::remember('site-ticker', 30, function () {
            $s = $this->statsData();
            $items = [];

            $items[] = ['label' => '오늘 방문자', 'text' => "오늘 {$s['today_visitors']}명이 어썸코리안을 찾았어요", 'live' => true, 'link' => '/'];
            if ($s['chat_users'] > 0) $items[] = ['label' => '오픈 채팅방', 'text' => "지금 {$s['chat_users']}명이 채팅방에서 대화 중", 'live' => true, 'link' => '/chat'];

            foreach (MarketQuote::where('category', 'index')->whereIn('symbol', ['^GSPC', '^IXIC', '^DJI'])->orderBy('sort_order')->get() as $q) {
                if ($q->change_pct === null) continue;
                $items[] = ['label' => '미국 증시', 'text' => $q->name . ' ' . number_format((float) $q->price, 2), 'quote' => (float) $q->change_pct, 'link' => '/stocks', 'at' => $q->quoted_at?->toISOString()];
            }

            $since = now()->subDays(14);
            $q = fn(string $table, string $titleCol = 'title') => DB::table($table)->where("$table.created_at", '>=', $since)->orderByDesc("$table.created_at")->limit(2);
            // [분류 이름, 쿼리, 링크, 문구]
            $feeds = [
                ['커뮤니티', fn() => $q('posts')->join('boards', 'boards.id', '=', 'posts.board_id')->where('posts.is_hidden', false)->select('posts.id', 'posts.title', 'posts.created_at', 'boards.slug'),
                    fn($r) => "/community/{$r->slug}/{$r->id}", '"%s" 글이 올라왔어요'],
                ['중고장터', fn() => $q('market_items')->whereIn('status', ['active', 'reserved'])->select('id', 'title', 'created_at'),
                    fn($r) => "/market/{$r->id}", '"%s" 새 매물이 올라왔어요'],
                ['구인구직', fn() => $q('job_posts')->where('is_active', true)->select('id', 'title', 'created_at'),
                    fn($r) => "/jobs/{$r->id}", '"%s" 공고'],
                ['부동산', fn() => $q('real_estate_listings')->where('is_active', true)->select('id', 'title', 'created_at'),
                    fn($r) => "/realestate/{$r->id}", '"%s" 새 매물'],
                ['Q&A', fn() => $q('qa_posts')->where('is_hidden', false)->select('id', 'title', 'created_at'),
                    fn($r) => "/qa/{$r->id}", '"%s" 질문이 올라왔어요'],
                ['동호회', fn() => $q('clubs')->where('is_active', true)->select('id', 'name as title', 'created_at'),
                    fn($r) => "/clubs/{$r->id}", '"%s" 동호회가 새로 열렸어요'],
                ['공동구매', fn() => $q('group_buys')->where('is_approved', true)->select('id', 'title', 'created_at'),
                    fn($r) => "/groupbuy/{$r->id}", '"%s" 공동구매'],
            ];
            $recent = [];
            foreach ($feeds as [$label, $query, $link, $fmt]) {
                try { $rows = $query()->get(); } catch (\Throwable $e) { continue; }   // 테이블/컬럼이 없으면 그 분류만 건너뜀
                foreach ($rows as $r) {
                    $title = mb_strimwidth((string) ($r->title ?? ''), 0, 40, '…');
                    if ($title === '') continue;
                    $recent[] = ['label' => $label, 'text' => sprintf($fmt, $title), 'link' => $link($r), 'at' => Carbon::parse($r->created_at)->toISOString()];
                }
            }
            usort($recent, fn($a, $b) => strcmp($b['at'], $a['at']));
            $items = array_merge($items, array_slice($recent, 0, 12));

            // 곧 시작하는 이벤트 (가장 가까운 1건)
            $ev = DB::table('events')->where('is_active', true)->where('start_date', '>=', now())->orderBy('start_date')->first(['id', 'title', 'start_date']);
            if ($ev) {
                $days = Carbon::now(self::ET)->startOfDay()->diffInDays(Carbon::parse($ev->start_date, 'UTC')->setTimezone(self::ET)->startOfDay(), false);
                $items[] = ['label' => '이벤트', 'text' => '"' . mb_strimwidth($ev->title, 0, 36, '…') . '"', 'tag' => $days <= 0 ? '오늘' : "D-{$days}", 'link' => "/events/{$ev->id}"];
            }
            return ['items' => $items, 'generated_at' => now()->toISOString()];
        });
        return response()->json(['success' => true, 'data' => $data]);
    }
}
