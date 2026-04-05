<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BoardPost;
use App\Models\BoardComment;
use App\Models\BoardPostLike;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CommunityController extends Controller
{
    private $categories = [
        ['slug'=>'free','name'=>'자유게시판','icon'=>'💬','description'=>'일상·수다·정보공유'],
        ['slug'=>'anonymous','name'=>'익명게시판','icon'=>'🎭','description'=>'실명 없이 자유롭게'],
        ['slug'=>'humor','name'=>'유머·짤','icon'=>'😂','description'=>'웃긴 글·짤방'],
        ['slug'=>'vent','name'=>'속풀이','icon'=>'😤','description'=>'고민상담·감정 토로'],
        ['slug'=>'cooking','name'=>'요리·레시피','icon'=>'🍳','description'=>'한식·미국 요리'],
        ['slug'=>'shopping','name'=>'쇼핑·핫딜','icon'=>'🛍️','description'=>'미국 핫딜·할인 정보'],
        ['slug'=>'parenting','name'=>'육아·교육','icon'=>'👶','description'=>'자녀 양육·학교'],
        ['slug'=>'beauty','name'=>'뷰티·패션','icon'=>'💄','description'=>'화장품·스킨케어·패션'],
        ['slug'=>'travel','name'=>'여행·맛집','icon'=>'✈️','description'=>'미국 여행·한인 맛집'],
        ['slug'=>'news_talk','name'=>'뉴스·시사','icon'=>'📰','description'=>'미국·한국 뉴스'],
        ['slug'=>'local_atlanta','name'=>'애틀란타','icon'=>'🍑','description'=>'조지아·수와니·둘루스'],
        ['slug'=>'local_la','name'=>'로스앤젤레스','icon'=>'🌴','description'=>'LA·OC·샌디에고'],
        ['slug'=>'local_ny','name'=>'뉴욕·뉴저지','icon'=>'🗽','description'=>'NY·NJ·보스턴'],
        ['slug'=>'local_dallas','name'=>'달라스·휴스턴','icon'=>'🤠','description'=>'텍사스 한인'],
        ['slug'=>'local_seattle','name'=>'시애틀·시카고','icon'=>'🌧️','description'=>'북서부·중부'],
    ];

    public function categories()
    {
        $cats = collect($this->categories)->map(function ($cat) {
            $count = \App\Models\BoardPost::where('category_slug', $cat['slug'])->whereNull('deleted_at')->count();
            $latest = \App\Models\BoardPost::where('category_slug', $cat['slug'])->whereNull('deleted_at')->max('created_at');
            $cat['count'] = $count;
            $cat['latest_at'] = $latest;
            return $cat;
        });
        return response()->json($cats);
    }

    public function posts($slug)
    {
        $sort = request('sort', 'latest');

        $query = BoardPost::with(['user:id,name,username,avatar'])
            ->where('category_slug', $slug)
            ->whereNull('deleted_at');

        if ($sort === 'popular') {
            $query->orderByDesc('like_count')->orderByDesc('view_count');
        } else {
            $query->orderByDesc('created_at');
        }

        $posts = $query->paginate(20);

        $posts->getCollection()->transform(function ($post) {
            if ($post->is_anonymous) {
                $post->user = (object)['id' => 0, 'name' => '익명', 'username' => 'anonymous', 'avatar' => null];
            }
            $post->author_name = $post->user->name ?? '알수없음';
            return $post;
        });

        return response()->json($posts);
    }

    public function show($slugOrId, $id = null)
    {
        if ($id === null) { $id = $slugOrId; }

        $post = BoardPost::with(['user:id,name,username,avatar'])->findOrFail($id);
        $post->increment('view_count');

        if ($post->is_anonymous) {
            $post->user = (object)['id' => 0, 'name' => '익명', 'username' => 'anonymous', 'avatar' => null];
        }

        $post->author_name = $post->is_anonymous ? '익명' : ($post->user->name ?? '알수없음');
        $post->is_liked = false;
        $post->likes_count = $post->like_count;

        if (Auth::check()) {
            $post->is_liked = BoardPostLike::where('post_id', $id)->where('user_id', Auth::id())->exists();
        }

        // Load comments separately
        $comments = BoardComment::where('post_id', $id)
            ->whereNull('deleted_at')
            ->with(['user:id,name,username,avatar'])
            ->orderBy('created_at')
            ->get()
            ->transform(function ($c) {
                if ($c->is_anonymous) {
                    $c->user = (object)['id' => 0, 'name' => '익명', 'username' => 'anonymous', 'avatar' => null];
                }
                $c->author_name = $c->is_anonymous ? '익명' : ($c->user->name ?? '알수없음');
                return $c;
            });

        $post->comments = $comments;

        return response()->json($post);
    }

    public function store(Request $request, $slug = null)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_slug' => 'required|string',
            'is_anonymous' => 'boolean',
        ]);

        $post = BoardPost::create([
            'user_id' => Auth::id(),
            'category_slug' => $request->category_slug,
            'title' => $request->title,
            'content' => $request->content,
            'is_anonymous' => $request->is_anonymous ?? false,
        ]);

        return response()->json($post->load('user:id,name,username,avatar'), 201);
    }

    public function update(Request $request, $slugOrId, $id = null)
    {
        if ($id === null) { $id = $slugOrId; }
        $post = BoardPost::findOrFail($id);

        if ($post->user_id !== Auth::id()) {
            return response()->json(['message' => '권한이 없습니다.'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
        ]);

        $post->update($validated);
        return response()->json($post);
    }

    public function destroy($slugOrId, $id = null)
    {
        if ($id === null) { $id = $slugOrId; }
        $post = BoardPost::findOrFail($id);

        if ($post->user_id !== Auth::id() && !(Auth::user()->is_admin ?? false)) {
            return response()->json(['message' => '권한이 없습니다.'], 403);
        }

        $post->delete();
        return response()->json(['message' => '삭제되었습니다.']);
    }

    public function like($slug, $id)
    {
        $post = BoardPost::findOrFail($id);
        $existing = BoardPostLike::where('post_id', $id)->where('user_id', Auth::id())->first();

        if ($existing) {
            $existing->delete();
            $post->decrement('like_count');
            return response()->json(['liked' => false, 'like_count' => $post->fresh()->like_count]);
        } else {
            BoardPostLike::create(['post_id' => $id, 'user_id' => Auth::id()]);
            $post->increment('like_count');
            return response()->json(['liked' => true, 'like_count' => $post->fresh()->like_count]);
        }
    }

    public function comments($slug, $postId)
    {
        $comments = BoardComment::where('post_id', $postId)
            ->whereNull('deleted_at')
            ->with(['user:id,name,username,avatar'])
            ->orderBy('created_at')
            ->get()
            ->transform(function ($c) {
                if ($c->is_anonymous) {
                    $c->user = (object)['id' => 0, 'name' => '익명', 'username' => 'anonymous', 'avatar' => null];
                }
                $c->author_name = $c->is_anonymous ? '익명' : ($c->user->name ?? '알수없음');
                return $c;
            });

        return response()->json($comments);
    }

    public function storeComment(Request $request, $slug, $postId)
    {
        $request->validate([
            'content' => 'required|string|max:2000',
            'parent_id' => 'nullable|integer',
            'is_anonymous' => 'boolean',
        ]);

        $post = BoardPost::findOrFail($postId);

        $comment = BoardComment::create([
            'post_id' => $postId,
            'user_id' => Auth::id(),
            'content' => $request->content,
            'parent_id' => $request->parent_id,
            'is_anonymous' => $request->is_anonymous ?? false,
        ]);

        $post->increment('comment_count');

        return response()->json($comment->load('user:id,name,username,avatar'), 201);
    }

    public function destroyComment($id)
    {
        $comment = BoardComment::findOrFail($id);

        if ($comment->user_id !== Auth::id() && !(Auth::user()->is_admin ?? false)) {
            return response()->json(['message' => '권한이 없습니다.'], 403);
        }

        $post = BoardPost::find($comment->post_id);
        $comment->delete();
        if ($post) {
            $post->decrement('comment_count');
        }

        return response()->json(['message' => '댓글이 삭제되었습니다.']);
    }
}
