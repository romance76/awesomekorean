<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Short;
use App\Models\ShortLike;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShortController extends Controller
{

    // ★ 전체 숏츠 목록 (셔플용, 최대 500)
    public function index(Request $request)
    {
        $perPage = min((int) $request->query("per_page", 500), 500);
        $user    = Auth::user();

        $shorts = Short::with("user:id,username,avatar")
            ->where("is_active", true)
            ->when($request->input("sort") === "random", fn($q) => $q->inRandomOrder(), fn($q) => $q->latest())
            ->limit($perPage)
            ->when(request('search'), fn($q, $s) => $q->where('title', 'LIKE', '%'.$s.'%'))->get()
            ->map(function ($s) use ($user) {
                $s->liked = $user
                    ? ShortLike::where("user_id", $user->id)->where("short_id", $s->id)->exists()
                    : false;
                return $s;
            });

        return response()->json(["data" => $shorts, "total" => $shorts->count()]);
    }

    /**
     * Get a single short by ID.
     */
    public function show($id)
    {
        $short = Short::findOrFail($id);
        return response()->json($short);
    }


    // 피드 (로그인 시 개인화, 비로그인 시 인기순)
    public function feed(Request $request)
    {
        $page  = (int) $request->query('page', 1);
        $limit = 10;
        $user  = Auth::user();

        $q = Short::with('user:id,username,avatar')
            ->where('is_active', true);

        // 개인화: 내 관심 태그 기반 우선 노출
        if ($user) {
            $interests = DB::table('user_interests')
                ->where('user_id', $user->id)
                ->value('tags');
            $tags = $interests ? json_decode($interests, true) : [];

            if (!empty($tags)) {
                // 관심 태그 포함 쇼츠 우선, 나머지 뒤
                $q->orderByRaw(
                    'CASE WHEN ' . collect($tags)->map(fn($t) => "JSON_CONTAINS(tags, ?)")->implode(' OR ') . ' THEN 0 ELSE 1 END',
                    collect($tags)->map(fn($t) => '"'.$t.'"')->values()->toArray()
                );
            }
        }

        $shorts = $q->orderByDesc('like_count')
            ->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(function ($s) use ($user) {
                $s->liked = $user
                    ? ShortLike::where('user_id', $user->id)->where('short_id', $s->id)->exists()
                    : false;
                return $s;
            });

        return response()->json(['data' => $shorts, 'page' => $page]);
    }

    // 쇼츠 등록
    public function store(Request $request)
    {
        $request->validate([
            'url'         => 'required|url',
            'title'       => 'nullable|string|max:100',
            'description' => 'nullable|string|max:300',
            'tags'        => 'nullable|array|max:5',
        ]);
        // YouTube URLs must be Shorts (vertical) only
        $url = $request->url;
        if (preg_match('/youtube\.com|youtu\.be/i', $url)) {
            if (!preg_match('/youtube\.com\/shorts\//i', $url)) {
                return response()->json(['message' => '세로형 YouTube Shorts URL만 등록 가능합니다. (youtube.com/shorts/...)'], 422);
            }
        }


        $parsed = $this->parseEmbed($request->url);

        $short = Short::create([
            'user_id'     => Auth::id(),
            'url'         => $request->url,
            'embed_url'   => $parsed['embed_url'],
            'platform'    => $parsed['platform'],
            'thumbnail'   => $parsed['thumbnail'],
            'title'       => $request->title,
            'description' => $request->description,
            'tags'        => $request->tags ?? [],
        ]);

        return response()->json($short->load('user:id,username,avatar'), 201);
    }

    // 좋아요 토글
    public function like($id)
    {
        $short = Short::findOrFail($id);
        $userId = Auth::id();

        $existing = ShortLike::where('user_id', $userId)->where('short_id', $id)->first();
        if ($existing) {
            $existing->delete();
            $short->decrement('like_count');
            return response()->json(['liked' => false, 'like_count' => $short->fresh()->like_count]);
        }

        ShortLike::create(['user_id' => $userId, 'short_id' => $id]);
        $short->increment('like_count');
        return response()->json(['liked' => true, 'like_count' => $short->fresh()->like_count]);
    }

    // 조회수 +1
    public function view($id)
    {
        Short::where('id', $id)->increment('view_count');
        return response()->json(['ok' => true]);
    }

    // 내 쇼츠 목록
    public function myShorts(Request $request)
    {
        $shorts = Short::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->paginate(12);
        return response()->json($shorts);
    }

    // 삭제
    public function destroy($id)
    {
        $short = Short::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $short->delete();
        return response()->json(['message' => '삭제되었습니다.']);
    }

    // 관심 태그 조회
    public function getInterests()
    {
        $tags = DB::table('user_interests')->where('user_id', Auth::id())->value('tags');
        return response()->json(['tags' => $tags ? json_decode($tags, true) : []]);
    }

    // 관심 태그 저장
    public function saveInterests(Request $request)
    {
        $request->validate(['tags' => 'required|array|max:10']);
        DB::table('user_interests')->updateOrInsert(
            ['user_id' => Auth::id()],
            ['tags' => json_encode($request->tags), 'updated_at' => now(), 'created_at' => now()]
        );
        return response()->json(['message' => '관심 태그가 저장되었습니다.']);
    }

    // URL → embed 파싱
    private function parseEmbed(string $url): array
    {
        // YouTube Shorts / 일반 / 단축
        if (preg_match('/youtube\.com\/shorts\/([a-zA-Z0-9_-]+)/', $url, $m) ||
            preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $m) ||
            preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $url, $m)) {
            $vid = $m[1];
            return [
                'platform'  => 'youtube',
                'embed_url' => "https://www.youtube.com/embed/{$vid}?autoplay=1&mute=1&controls=1&loop=1&playlist={$vid}&rel=0",
                'thumbnail' => "https://img.youtube.com/vi/{$vid}/hqdefault.jpg",
            ];
        }
        // TikTok
        if (preg_match('/tiktok\.com\/@[^\/]+\/video\/(\d+)/', $url, $m)) {
            return [
                'platform'  => 'tiktok',
                'embed_url' => "https://www.tiktok.com/embed/v2/{$m[1]}",
                'thumbnail' => null,
            ];
        }
        // Instagram Reels
        if (preg_match('/instagram\.com\/(?:p|reel)\/([a-zA-Z0-9_-]+)/', $url, $m)) {
            return [
                'platform'  => 'instagram',
                'embed_url' => "https://www.instagram.com/p/{$m[1]}/embed/",
                'thumbnail' => null,
            ];
        }
        return ['platform' => 'other', 'embed_url' => $url, 'thumbnail' => null];
    }
}