<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\MoneyCafe;
use App\Models\MoneyPost;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Support\EmergencySwitches;
use App\Support\SocialCard;
use App\Support\SocialPublisher;
use App\Support\SocialQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// 관리자 › 홍보·이벤트 › SNS 게시 (최고관리자 전용 — AdminTier social-posts). 목록에서 지우기는 목록 기록만 지워요(SNS 에 올라간 글은 그대로).
class AdminSocialPostController extends Controller
{
    private function audit(Request $r, string $action, $id, array $after = []): void
    {
        try {
            DB::table('admin_audit_log')->insert(['admin_id' => $r->user()->id, 'action' => $action, 'target_type' => 'social_posts', 'target_id' => (int) $id,
                'after_value' => json_encode($after, JSON_UNESCAPED_UNICODE), 'note' => $r->user()->role, 'ip' => $r->ip(), 'created_at' => now()]);
        } catch (\Throwable $e) { report($e); }
    }

    public function index(Request $r)
    {
        $q = SocialPost::query();
        if (in_array($r->status, SocialPost::STATUSES, true)) $q->where('status', $r->status);
        if (in_array($r->platform, SocialPost::PLATFORMS, true)) $q->where('platform', $r->platform);
        // 올린 글은 최근 것부터, 나머지는 게시 순서(우선순위 높은 것 → 먼저 들어온 것)대로
        $list = ($r->status === 'posted' ? $q->orderByDesc('posted_at') : $q->orderByDesc('priority')->orderBy('id'))->paginate(30);

        $counts = SocialPost::select('status', 'platform', DB::raw('count(*) c'))->groupBy('status', 'platform')->get();
        $today = now('America/New_York')->startOfDay()->utc();
        $accounts = SocialAccount::whereIn('platform', SocialPost::PLATFORMS)->get()->mapWithKeys(fn ($a) => [$a->platform => [
            'handle' => $a->handle, 'connected' => (bool) ($a->access_token && $a->account_id), 'is_active' => $a->is_active,
            'autopost_enabled' => $a->autopost_enabled, 'token_expires_at' => optional($a->token_expires_at)->toDateString(),
            'posted_today' => SocialPost::where('platform', $a->platform)->where('status', 'posted')->where('posted_at', '>=', $today)->count(),
        ]]);
        return response()->json(['success' => true, 'data' => $list, 'counts' => $counts, 'accounts' => $accounts,
            'settings' => SocialQueue::settings(), 'paused' => EmergencySwitches::on('social_autopost')]);
    }

    public function saveSettings(Request $r)
    {
        $d = $r->validate([
            'enabled' => 'boolean', 'require_approval' => 'boolean', 'auto_events' => 'boolean', 'auto_money' => 'boolean',
            'instagram_daily' => 'integer|min:0|max:20', 'threads_daily' => 'integer|min:0|max:30',
            'times' => 'array|min:1|max:6', 'times.*' => ['string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'hashtags' => ['nullable', 'string', 'max:300', 'regex:/^[#\p{L}\p{N}_\s]*$/u'],
        ], ['times.*.regex' => '시간은 08:00 처럼 24시간 형식으로 적어 주세요.', 'hashtags.regex' => '해시태그는 #글자 형태로만 적어 주세요.']);
        if (isset($d['times'])) $d['times'] = array_values(array_unique($d['times']));
        $s = SocialQueue::saveSettings($d);
        $this->audit($r, 'UPDATE social-settings', 0, $d);
        return response()->json(['success' => true, 'message' => 'SNS 게시 설정을 저장했어요', 'settings' => $s]);
    }

    public function update(Request $r, int $id)
    {
        $p = SocialPost::findOrFail($id);
        if ($p->status === 'posted') return response()->json(['success' => false, 'message' => '이미 올린 글은 고칠 수 없어요'], 422);
        $d = $r->validate([
            'caption' => 'sometimes|string|max:' . ($p->platform === 'threads' ? 500 : 2200),
            'priority' => 'sometimes|integer|min:0|max:100',
            'not_before' => 'sometimes|nullable|date',
        ], ['caption.max' => $p->platform === 'threads' ? '스레드는 500자까지예요(링크 포함).' : '인스타 캡션은 2,200자까지예요.']);
        $p->fill($d)->save();
        $this->audit($r, 'UPDATE social-post', $p->id, array_keys($d));
        return response()->json(['success' => true, 'data' => $p]);
    }

    /** 승인 / 건너뛰기 / 다시 시도 — ids 여러 개 가능 */
    public function setStatus(Request $r)
    {
        $d = $r->validate(['ids' => 'required|array|max:200', 'ids.*' => 'integer', 'action' => 'required|in:approve,skip,retry,unapprove']);
        $from = ['approve' => ['pending', 'skipped'], 'skip' => ['pending', 'approved', 'failed'], 'retry' => ['failed'], 'unapprove' => ['approved']][$d['action']];
        $to = ['approve' => 'approved', 'skip' => 'skipped', 'retry' => 'approved', 'unapprove' => 'pending'][$d['action']];
        $upd = ['status' => $to, 'updated_at' => now()];
        if ($d['action'] === 'approve' || $d['action'] === 'retry') $upd['approved_by'] = $r->user()->id;
        if ($d['action'] === 'retry') { $upd['attempts'] = 0; $upd['error'] = null; }
        $n = SocialPost::whereIn('id', $d['ids'])->whereIn('status', $from)->update($upd);
        $this->audit($r, 'STATUS social-post ' . $d['action'], 0, ['ids' => $d['ids'], 'changed' => $n]);
        return response()->json(['success' => true, 'changed' => $n]);
    }

    /** 목록에서 지우기 — 이 화면의 기록만 지워요. 인스타·스레드에 올라간 글은 건드리지 않아요(SNS 앱에서 직접 지운 글을 목록에서도 치울 때 사용). */
    public function destroy(Request $r, int $id)
    {
        $p = SocialPost::findOrFail($id);
        $snap = $p->only(['id', 'platform', 'status', 'title', 'external_url']);
        $p->delete();
        $this->audit($r, 'DELETE social-post', $id, $snap);
        return response()->json(['success' => true, 'message' => '목록에서 지웠어요']);
    }

    /** 지금 바로 올리기(시험용) — 비밀번호 재확인 */
    public function publishNow(Request $r, int $id)
    {
        $p = SocialPost::findOrFail($id);
        if ($p->status === 'posted') return response()->json(['success' => false, 'message' => '이미 올렸어요: ' . $p->external_url], 422);
        if (in_array($p->status, ['pending', 'skipped', 'failed'], true)) { $p->status = 'approved'; $p->approved_by = $r->user()->id; $p->save(); }
        $ok = SocialPublisher::publish($p);
        $this->audit($r, 'PUBLISH social-post', $p->id, ['ok' => $ok, 'platform' => $p->platform]);
        $p->refresh();
        return response()->json(['success' => $ok, 'message' => $ok ? '올렸어요' : ('올리지 못했어요: ' . $p->error), 'data' => $p], $ok ? 200 : 422);
    }

    /** 카드뉴스 다시 만들기 */
    public function regenerateCard(Request $r, int $id)
    {
        $p = SocialPost::findOrFail($id);
        if ($p->status === 'posted') return response()->json(['success' => false, 'message' => '이미 올린 글이에요'], 422);
        $lines = []; $tag = '어썸코리안 소식'; $theme = 'orange'; $title = $p->title ?: '어썸코리안';
        if ($p->source_type === 'money_post' && ($m = MoneyPost::with('cafe')->find($p->source_id))) {
            [$intro, $b] = SocialQueue::summarize($m->content); $lines = $b ?: [$intro];
            $tag = 'MONEY · ' . ($m->cafe?->name ?? ''); $theme = $m->cafe?->theme ?: 'emerald'; $title = $m->title;
        } elseif ($p->source_type === 'event' && ($e = Event::find($p->source_id))) {
            $tag = $e->event_type === 'sweepstakes' ? '경품 이벤트' : '어썸코리안 이벤트'; $theme = $e->event_type === 'sweepstakes' ? 'pink' : 'orange'; $title = $e->title;
            $lines = ['참여 방법은 프로필 링크에서'];
        }
        $p->image_url = SocialCard::make(['tag' => $tag, 'title' => $title, 'lines' => $lines, 'theme' => $theme], $p->source_type . '-' . ($p->source_id ?: $p->id));
        $p->save();
        return response()->json(['success' => true, 'data' => $p]);
    }

    /** 예전 MONEY 운영팀 글을 골라 대기열에 (오래된 글부터, 이미 넣은 건 건너뜀) */
    public function enqueueMoney(Request $r)
    {
        $d = $r->validate(['cafe' => 'nullable|string|max:60', 'limit' => 'required|integer|min:1|max:30']);
        $q = MoneyPost::where('is_hidden', false)
            ->whereNotIn('id', SocialPost::where('source_type', 'money_post')->select('source_id'))
            ->whereIn('user_id', DB::table('users')->where('email', 'money-ops@awesomekorean.local')->select('id'))
            ->orderBy('id');
        if (!empty($d['cafe'])) $q->where('cafe_id', MoneyCafe::where('slug', $d['cafe'])->value('id') ?? 0);
        $n = 0;
        foreach ($q->limit($d['limit'])->get() as $p) $n += SocialQueue::enqueueMoneyPost($p, $r->user()->id);
        $this->audit($r, 'ENQUEUE social money', 0, $d + ['added' => $n]);
        return response()->json(['success' => true, 'added' => $n, 'message' => "{$n}개를 대기열에 넣었어요"]);
    }

    /** 이벤트 하나를 대기열에 (자동으로 안 들어간 예전 이벤트용) */
    public function enqueueEvent(Request $r, int $id)
    {
        $e = Event::findOrFail($id);
        $n = SocialQueue::enqueueEvent($e, $r->user()->id);
        return response()->json(['success' => true, 'added' => $n, 'message' => $n ? "{$n}개를 넣었어요" : '넣을 것이 없어요(이미 있거나, 어썸코리안 이벤트가 아니거나, 끝난 이벤트)']);
    }

    public function recentEvents()
    {
        $rows = Event::whereIn('event_type', SocialQueue::EVENT_TYPES)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>', now()))
            ->orderByDesc('id')->limit(30)->get(['id', 'title', 'event_type', 'start_date', 'end_date']);
        $queued = SocialPost::where('source_type', 'event')->whereIn('source_id', $rows->pluck('id'))->pluck('source_id')->unique()->all();
        return response()->json(['success' => true, 'data' => $rows->map(fn ($e) => $e->only(['id', 'title', 'event_type', 'start_date', 'end_date']) + ['queued' => in_array($e->id, $queued)])]);
    }

    /** 직접 쓰기 (사진 올리기 선택, 없으면 카드뉴스 자동) */
    public function storeManual(Request $r)
    {
        $d = $r->validate([
            'title' => 'required|string|max:120',
            'caption' => 'required|string|max:2000',
            'lines' => 'nullable|array|max:3', 'lines.*' => 'nullable|string|max:80',
            'link_url' => ['nullable', 'string', 'max:300', 'regex:#^https://#'],
            'platforms' => 'required|array|min:1', 'platforms.*' => 'in:instagram,threads',
            'theme' => 'nullable|in:' . implode(',', array_keys(SocialCard::THEMES)),
            'tag' => 'nullable|string|max:30',
            'not_before' => 'nullable|date',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:8192',
        ], ['link_url.regex' => '링크는 https:// 로 시작해야 해요.']);
        if ($r->hasFile('image')) {
            // 인스타는 JPEG 만 받으므로 jpg 로 바꿔 저장 (긴 쪽 1440px)
            $src = @imagecreatefromstring(file_get_contents($r->file('image')->getRealPath()));
            if (!$src) return response()->json(['success' => false, 'message' => '사진을 읽지 못했어요'], 422);
            $w = imagesx($src); $h = imagesy($src); $k = min(1, 1440 / max($w, $h));
            $dst = imagecreatetruecolor((int) ($w * $k), (int) ($h * $k));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
            ob_start(); imagejpeg($dst, null, 88); $bin = ob_get_clean();
            $rel = 'social/' . now('America/New_York')->format('Ym') . '/upload-' . now()->format('YmdHis') . '-' . $r->user()->id . '.jpg';
            Storage::disk('public')->put($rel, $bin);
            $d['image_url'] = rtrim(config('app.url'), '/') . '/storage/' . $rel;
        }
        $d['lines'] = array_values(array_filter($d['lines'] ?? []));
        $d['tag'] = $d['tag'] ?? '';
        $n = SocialQueue::enqueueManual($d, $r->user()->id);
        $this->audit($r, 'CREATE social-post manual', 0, ['title' => $d['title'], 'platforms' => $d['platforms']]);
        return response()->json(['success' => true, 'added' => $n, 'message' => "{$n}개를 대기열에 넣었어요"]);
    }
}
