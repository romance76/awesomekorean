<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NewsController extends Controller
{
    /**
     * GET /api/news
     * List news with category_id filter, search, sort, pagination
     */
    public function index(Request $request)
    {
        $query = News::query();

        // Main category filter
        if ($request->filled('main_category_id')) {
            $query->where('main_category_id', $request->main_category_id);
        }

        // Sub category filter
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Legacy string category filter
        if ($request->filled('category') && $request->category !== '전체') {
            $query->where('category', $request->category);
        }

        // Source filter
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        // Sort
        $sort = $request->input('sort', 'newest');
        switch ($sort) {
            case 'popular':
                $query->orderByDesc('view_count');
                break;
            default:
                $query->orderByDesc('published_at');
                break;
        }

        $perPage = min((int) ($request->per_page ?? 20), 50);
        $news = $query->paginate($perPage);

        return response()->json([
            'success'      => true,
            'data'         => $news->items(),
            'current_page' => $news->currentPage(),
            'last_page'    => $news->lastPage(),
            'total'        => $news->total(),
        ]);
    }

    /**
     * GET /api/news/{id}
     * Single news with view_count increment
     */
    public function show($id)
    {
        $news = News::findOrFail($id);
        $news->increment('view_count');

        $data = $news->toArray();

        // Category info
        if ($news->category_id) {
            $data['sub_category'] = $news->subCategory;
        }
        if ($news->main_category_id) {
            $data['main_category_obj'] = $news->mainCategory;
        }

        // Like status
        $data['is_liked'] = false;
        if (Auth::check()) {
            $data['is_liked'] = DB::table('content_likes')
                ->where('user_id', Auth::id())
                ->where('likeable_type', 'news')
                ->where('likeable_id', $id)
                ->exists();
        }

        // Like count
        $data['like_count'] = DB::table('content_likes')
            ->where('likeable_type', 'news')
            ->where('likeable_id', $id)
            ->count();

        // Comments
        $data['comments'] = Comment::where('commentable_type', 'news')
            ->where('commentable_id', $id)
            ->with('user:id,name,nickname,avatar')
            ->latest()
            ->get();

        // Related news (same sub-category)
        $data['related'] = [];
        if ($news->category_id) {
            $data['related'] = News::where('category_id', $news->category_id)
                ->where('id', '!=', $id)
                ->orderByDesc('published_at')
                ->limit(5)
                ->get(['id', 'title', 'image_url', 'published_at', 'source', 'category']);
        }

        // Previous / next
        $data['prev'] = News::where('id', '<', $id)->orderByDesc('id')->first(['id', 'title']);
        $data['next'] = News::where('id', '>', $id)->orderBy('id')->first(['id', 'title']);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * GET /api/news/categories
     * Return all categories with parent/children hierarchy (9 main + 57 sub)
     */
    public function categories()
    {
        $mainCategories = NewsCategory::active()
            ->mainCategories()
            ->with(['children' => function ($q) {
                $q->active()->orderBy('priority');
            }])
            ->orderBy('priority')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $mainCategories,
        ]);
    }

    /**
     * GET /api/news/category/{slug}
     * News by category slug
     */
    public function byCategory(Request $request, $slug)
    {
        $category = NewsCategory::where('slug', $slug)->firstOrFail();

        $query = News::orderByDesc('published_at');

        if (is_null($category->parent_id)) {
            $query->where('main_category_id', $category->id);
        } else {
            $query->where('category_id', $category->id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) ($request->per_page ?? 20), 50);
        $news = $query->paginate($perPage);

        return response()->json([
            'success'      => true,
            'category'     => $category,
            'data'         => $news->items(),
            'current_page' => $news->currentPage(),
            'last_page'    => $news->lastPage(),
            'total'        => $news->total(),
        ]);
    }

    /**
     * GET /api/news/digest
     * Daily digest: top 5 today, yesterday summary
     */
    public function digest()
    {
        $items = News::where('is_digest', true)
            ->whereDate('published_at', today())
            ->orderByDesc('view_count')
            ->limit(5)
            ->get(['id', 'title', 'summary', 'image_url', 'published_at', 'category', 'category_id', 'main_category_id', 'source']);

        if ($items->isEmpty()) {
            $items = News::where('published_at', '>=', now()->subHours(24))
                ->orderByDesc('view_count')
                ->limit(5)
                ->get(['id', 'title', 'summary', 'image_url', 'published_at', 'category', 'category_id', 'main_category_id', 'source']);
        }

        $yesterday = now()->subDay()->toDateString();
        $yesterdayNews = News::whereDate('published_at', $yesterday)
            ->orderByDesc('view_count')
            ->limit(20)
            ->get(['id', 'title', 'summary', 'image_url', 'published_at', 'category', 'source']);

        return response()->json([
            'success'   => true,
            'data'      => [
                'today'     => $items,
                'yesterday' => $yesterdayNews->take(8)->values(),
                'date'      => today()->toDateString(),
            ],
        ]);
    }

    /**
     * GET /api/news/featured
     * Featured / deep articles
     */
    public function featured(Request $request)
    {
        $query = News::where('is_featured', true)->orderByDesc('published_at');

        if ($request->filled('main_category_id')) {
            $query->where('main_category_id', $request->main_category_id);
        }

        $perPage = min((int) ($request->per_page ?? 10), 50);

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($perPage),
        ]);
    }

    /**
     * POST /api/news/{id}/like
     * Toggle like
     */
    public function like($id)
    {
        News::findOrFail($id);
        $userId = Auth::id();

        $existing = DB::table('content_likes')
            ->where('user_id', $userId)
            ->where('likeable_type', 'news')
            ->where('likeable_id', $id)
            ->first();

        if ($existing) {
            DB::table('content_likes')->where('id', $existing->id)->delete();
        } else {
            DB::table('content_likes')->insert([
                'user_id'       => $userId,
                'likeable_type' => 'news',
                'likeable_id'   => $id,
                'created_at'    => now(),
            ]);
        }

        $likeCount = DB::table('content_likes')
            ->where('likeable_type', 'news')
            ->where('likeable_id', $id)
            ->count();

        return response()->json([
            'success' => true,
            'data'    => ['liked' => !$existing, 'like_count' => $likeCount],
        ]);
    }

    /**
     * POST /api/news/{id}/comment
     */
    public function comment(Request $request, $id)
    {
        News::findOrFail($id);
        $request->validate(['content' => 'required|string|max:2000']);

        $comment = Comment::create([
            'commentable_type' => 'news',
            'commentable_id'   => $id,
            'user_id'          => Auth::id(),
            'content'          => $request->content,
        ]);

        // Points for comments (max 10 per day)
        $todayCount = Comment::where('user_id', Auth::id())->whereDate('created_at', today())->count();
        if ($todayCount <= 10) {
            Auth::user()->addPoints(5, 'comment_write', 'earn', $comment->id, '댓글 작성');
        }

        return response()->json([
            'success' => true,
            'message' => '댓글이 등록되었습니다.',
            'data'    => $comment->load('user:id,name,nickname,avatar'),
        ], 201);
    }
}
