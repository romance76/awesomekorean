<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use App\Support\YoutubeSync;
use Illuminate\Http\Request;

/**
 * 한인 유튜버 소개 (/youtubers). 공개: 목록·채널·영상. 회원: 채널 신청(관리자 승인 후 공개). 관리자: 승인·거절·직접 등록·추천·삭제·새로 읽기.
 * 영상·쇼츠는 유튜브 공식 API 로 읽어 와 저장하고(App\Support\YoutubeSync), 화면에서는 유튜브 공식 임베드로 재생한다.
 */
class YoutubeChannelController extends Controller
{
    private function categories(): array
    {
        return YoutubeChannel::CATEGORIES;
    }

    private function card(YoutubeChannel $c): array
    {
        return [
            'id' => $c->id, 'title' => $c->title, 'handle' => $c->handle, 'category' => $c->category,
            'category_label' => $this->categories()[$c->category] ?? '기타',
            'thumbnail_url' => $c->thumbnail_url, 'subscriber_count' => (int) $c->subscriber_count,
            'intro' => $c->intro, 'is_featured' => (bool) $c->is_featured,
            'latest_video_at' => $c->latest_video_at, 'latest_thumb' => $c->latest_thumb ?? null,
        ];
    }

    /** 카드에 보여 줄 '최근 영상 썸네일' 한 장씩을 한 번에 붙인다 */
    private function withLatestThumbs($channels)
    {
        $ids = $channels->pluck('id')->all();
        if (!$ids) return $channels;
        $pick = fn ($short) => YoutubeVideo::whereIn('youtube_channel_id', $ids)->where('is_short', $short)->orderByDesc('published_at')->get(['youtube_channel_id', 'thumbnail_url'])
            ->unique('youtube_channel_id')->pluck('thumbnail_url', 'youtube_channel_id');
        $latest = $pick(false)->union($pick(true)->all());   // 일반 영상이 없는 채널은 최근 쇼츠 썸네일로 대신
        foreach ($channels as $c) $c->latest_thumb = $latest[$c->id] ?? null;
        return $channels;
    }

    // ───────── 공개 ─────────

    public function index(Request $request)
    {
        $q = YoutubeChannel::where('status', 'approved');
        $cat = (string) $request->query('category', '');
        if ($cat !== '' && isset($this->categories()[$cat])) $q->where('category', $cat);
        $s = trim((string) $request->query('q', ''));
        if ($s !== '') $q->where(function ($w) use ($s) { $w->where('title', 'like', '%' . $s . '%')->orWhere('handle', 'like', '%' . $s . '%'); });
        $sort = (string) $request->query('sort', 'latest');
        if ($sort === 'subs') $q->orderByDesc('subscriber_count');
        elseif ($sort === 'new') $q->orderByDesc('id');
        else $q->orderByRaw('latest_video_at is null')->orderByDesc('latest_video_at');
        $page = $q->paginate(min(48, max(1, (int) $request->query('per_page', 24))));
        $this->withLatestThumbs($page->getCollection());

        $featured = [];
        if ((int) $request->query('page', 1) === 1 && $s === '' && $cat === '') {
            $f = YoutubeChannel::where('status', 'approved')->where('is_featured', true)
                ->where(function ($w) { $w->whereNull('featured_until')->orWhere('featured_until', '>', now()); })
                ->orderByDesc('subscriber_count')->limit(6)->get();
            $featured = $this->withLatestThumbs($f)->map(fn ($c) => $this->card($c))->all();
        }

        return response()->json(['success' => true, 'data' => [
            'items' => $page->getCollection()->map(fn ($c) => $this->card($c))->all(),
            'featured' => $featured,
            'categories' => collect($this->categories())->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]]);
    }

    public function show($id)
    {
        $c = YoutubeChannel::where('status', 'approved')->find($id);
        if (!$c) return response()->json(['success' => false, 'message' => '채널을 찾을 수 없어요.'], 404);
        $c->increment('view_count');
        $d = $this->card($c);
        $d += [
            'channel_id' => $c->channel_id, 'description' => $c->description, 'banner_url' => $c->banner_url, 'video_count' => (int) $c->video_count,
            'url' => 'https://www.youtube.com/channel/' . $c->channel_id,
            'videos_total' => YoutubeVideo::where('youtube_channel_id', $c->id)->where('is_short', false)->count(),
            'shorts_total' => YoutubeVideo::where('youtube_channel_id', $c->id)->where('is_short', true)->count(),
        ];
        return response()->json(['success' => true, 'data' => $d]);
    }

    public function videos(Request $request, $id)
    {
        $c = YoutubeChannel::where('status', 'approved')->find($id);
        if (!$c) return response()->json(['success' => false, 'message' => '채널을 찾을 수 없어요.'], 404);
        $short = $request->query('type') === 'short';
        $page = YoutubeVideo::where('youtube_channel_id', $c->id)->where('is_short', $short)->orderByDesc('published_at')
            ->paginate(min(48, max(1, (int) $request->query('per_page', 24))));
        return response()->json(['success' => true, 'data' => [
            'items' => $page->getCollection()->map(fn ($v) => [
                'id' => $v->id, 'video_id' => $v->video_id, 'title' => $v->title, 'thumbnail_url' => $v->thumbnail_url,
                'duration_seconds' => (int) $v->duration_seconds, 'view_count' => (int) $v->view_count, 'published_at' => $v->published_at,
            ])->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]]);
    }

    // ───────── 회원: 채널 신청 ─────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'url' => 'required|string|max:200',
            'category' => 'required|string|max:30',
            'intro' => 'nullable|string|max:300',
        ], [
            'url.required' => '채널 주소를 입력해 주세요.',
            'category.required' => '분야를 골라 주세요.',
        ]);
        if (!isset($this->categories()[$data['category']])) return response()->json(['success' => false, 'message' => '분야를 다시 골라 주세요.'], 422);

        $user = $request->user();
        $mine = YoutubeChannel::where('user_id', $user->id)->whereIn('status', ['pending', 'approved'])->count();
        if ($mine >= 3) return response()->json(['success' => false, 'message' => '한 계정으로 채널은 3개까지 신청할 수 있어요.'], 422);

        $q = YoutubeSync::parseInput($data['url']);
        if (!$q) return response()->json(['success' => false, 'message' => '채널 주소를 알아볼 수 없어요. 예: https://www.youtube.com/@채널이름'], 422);
        $info = YoutubeSync::fetchChannel($q);
        if (!$info) return response()->json(['success' => false, 'message' => '유튜브에서 이 채널을 찾지 못했어요. 주소를 다시 확인해 주세요.'], 422);

        $exists = YoutubeChannel::where('channel_id', $info['channel_id'])->first();
        if ($exists) {
            $msg = $exists->status === 'approved' ? '이미 등록된 채널이에요.' : ($exists->status === 'pending' ? '이미 신청되어 검토를 기다리고 있어요.' : '이 채널은 등록할 수 없어요. 문의해 주세요.');
            return response()->json(['success' => false, 'message' => $msg], 422);
        }

        $ch = YoutubeChannel::create($info + [
            'user_id' => $user->id, 'category' => $data['category'], 'intro' => isset($data['intro']) ? trim($data['intro']) : null, 'status' => 'pending',
        ]);
        return response()->json(['success' => true, 'message' => '신청이 접수됐어요. 확인 후 공개됩니다.', 'data' => ['id' => $ch->id]], 201);
    }

    public function mine(Request $request)
    {
        $rows = YoutubeChannel::where('user_id', $request->user()->id)->orderByDesc('id')->get(['id', 'title', 'thumbnail_url', 'status', 'reject_reason', 'created_at']);
        return response()->json(['success' => true, 'data' => $rows]);
    }

    // ───────── 관리자 ─────────

    public function adminIndex(Request $request)
    {
        $q = YoutubeChannel::query();
        $st = (string) $request->query('status', '');
        if (in_array($st, ['pending', 'approved', 'rejected'], true)) $q->where('status', $st);
        $s = trim((string) $request->query('q', ''));
        if ($s !== '') $q->where(function ($w) use ($s) { $w->where('title', 'like', '%' . $s . '%')->orWhere('handle', 'like', '%' . $s . '%'); });
        $page = $q->orderByRaw("status = 'pending' desc")->orderByDesc('id')->paginate(30);
        return response()->json(['success' => true, 'data' => [
            'items' => $page->getCollection()->map(fn ($c) => $this->card($c) + [
                'status' => $c->status, 'channel_id' => $c->channel_id, 'user_id' => $c->user_id, 'reject_reason' => $c->reject_reason,
                'featured_until' => $c->featured_until, 'last_synced_at' => $c->last_synced_at, 'created_at' => $c->created_at,
                'videos' => YoutubeVideo::where('youtube_channel_id', $c->id)->count(),
            ])->all(),
            'counts' => [
                'pending' => YoutubeChannel::where('status', 'pending')->count(),
                'approved' => YoutubeChannel::where('status', 'approved')->count(),
                'rejected' => YoutubeChannel::where('status', 'rejected')->count(),
            ],
            'categories' => collect($this->categories())->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]]);
    }

    public function adminStore(Request $request)
    {
        $data = $request->validate(['url' => 'required|string|max:200', 'category' => 'required|string|max:30', 'intro' => 'nullable|string|max:300']);
        if (!isset($this->categories()[$data['category']])) return response()->json(['success' => false, 'message' => '분야를 다시 골라 주세요.'], 422);
        $q = YoutubeSync::parseInput($data['url']);
        if (!$q) return response()->json(['success' => false, 'message' => '채널 주소를 알아볼 수 없어요. 예: https://www.youtube.com/@채널이름'], 422);
        $info = YoutubeSync::fetchChannel($q);
        if (!$info) return response()->json(['success' => false, 'message' => '유튜브에서 이 채널을 찾지 못했어요.'], 422);
        if (YoutubeChannel::where('channel_id', $info['channel_id'])->exists()) return response()->json(['success' => false, 'message' => '이미 등록(또는 신청)된 채널이에요.'], 422);
        $ch = YoutubeChannel::create($info + ['category' => $data['category'], 'intro' => isset($data['intro']) ? trim($data['intro']) : null, 'status' => 'approved']);
        YoutubeSync::sync($ch);
        return response()->json(['success' => true, 'message' => '등록했어요.', 'data' => ['id' => $ch->id]], 201);
    }

    public function approve($id)
    {
        $c = YoutubeChannel::findOrFail($id);
        $c->update(['status' => 'approved', 'reject_reason' => null]);
        YoutubeSync::sync($c);
        return response()->json(['success' => true, 'message' => '승인했어요.']);
    }

    public function reject(Request $request, $id)
    {
        $c = YoutubeChannel::findOrFail($id);
        $c->update(['status' => 'rejected', 'reject_reason' => mb_substr(trim((string) $request->input('reason', '')), 0, 200) ?: null, 'is_featured' => false]);
        return response()->json(['success' => true, 'message' => '거절했어요.']);
    }

    public function update(Request $request, $id)
    {
        $c = YoutubeChannel::findOrFail($id);
        $data = $request->validate([
            'category' => 'sometimes|string|max:30', 'intro' => 'sometimes|nullable|string|max:300',
            'is_featured' => 'sometimes|boolean', 'featured_until' => 'sometimes|nullable|date',
        ]);
        if (isset($data['category']) && !isset($this->categories()[$data['category']])) return response()->json(['success' => false, 'message' => '분야가 올바르지 않아요.'], 422);
        $c->update($data);
        return response()->json(['success' => true, 'message' => '저장했어요.']);
    }

    public function resync($id)
    {
        $c = YoutubeChannel::findOrFail($id);
        $ok = YoutubeSync::sync($c);
        return response()->json(['success' => $ok, 'message' => $ok ? '유튜브에서 새로 읽어 왔어요.' : '읽어 오지 못했어요. (API 키·채널 상태 확인)']);
    }

    public function destroy($id)
    {
        $c = YoutubeChannel::findOrFail($id);
        YoutubeVideo::where('youtube_channel_id', $c->id)->delete();
        $c->delete();
        return response()->json(['success' => true, 'message' => '삭제했어요.']);
    }
}
