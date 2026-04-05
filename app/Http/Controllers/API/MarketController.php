<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MarketItem;
use App\Models\Bookmark;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\HasDistanceFilter;
use Illuminate\Support\Facades\DB;

class MarketController extends Controller
{
    use HasDistanceFilter;
    public function index(Request $request)
    {
        $query = MarketItem::with('user:id,name,username,avatar')
            ->where('status', 'active')
            ->where('item_type', $request->type ?? 'used');

        if ($request->category) $query->where('category', $request->category);
        if ($request->region)   $query->where('region', 'like', '%'.$request->region.'%');
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%'.$request->search.'%')
                  ->orWhere('description', 'like', '%'.$request->search.'%');
            });
        }

        $this->applyDistanceFilter($query, $request, "latitude", "longitude");
        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:200',
            'description' => 'required|string',
            'price'       => 'required|numeric|min:0',
            'item_type'   => 'in:used,real_estate,car',
        ]);
        $item = MarketItem::create(array_merge($request->only(['title','description','price','price_negotiable','category','item_type','region','condition']), ['user_id' => Auth::id()]));
        return response()->json(['message' => '등록되었습니다.', 'item' => $item], 201);
    }

    public function show(MarketItem $item)
    {
        $item->increment('view_count');
        $data = $item->load('user:id,name,username,avatar')->toArray();

        // 로그인 사용자 좋아요/북마크 여부
        if (Auth::check()) {
            $data['is_liked'] = DB::table('content_likes')
                ->where('user_id', Auth::id())
                ->where('likeable_type', 'market_item')
                ->where('likeable_id', $item->id)
                ->exists();
            $data['is_bookmarked'] = Bookmark::where('user_id', Auth::id())
                ->where('bookmarkable_type', MarketItem::class)
                ->where('bookmarkable_id', $item->id)
                ->exists();
        } else {
            $data['is_liked']      = false;
            $data['is_bookmarked'] = false;
        }

        // 좋아요 수 (content_likes 테이블에서 집계)
        $data['like_count'] = DB::table('content_likes')
            ->where('likeable_type', 'market_item')
            ->where('likeable_id', $item->id)
            ->count();

        // 댓글 불러오기
        $data['comments'] = Comment::where('commentable_type', 'market_item')
            ->where('commentable_id', $item->id)
            ->with('user:id,name,username,avatar')
            ->latest()
            ->get();

        return response()->json($data);
    }

    public function update(Request $request, MarketItem $item)
    {
        if ($item->user_id !== Auth::id() && !Auth::user()->is_admin) abort(403);
        $item->update($request->only(['title','description','price','price_negotiable','status','region','condition']));
        return response()->json(['message' => '수정되었습니다.', 'item' => $item]);
    }

    public function sold(Request $request, $id)
    {
        $item = MarketItem::findOrFail($id);
        if ($item->user_id !== Auth::id() && !Auth::user()->is_admin) abort(403);
        $item->update(['status' => 'sold']);
        return response()->json(['message' => '거래완료 처리되었습니다.', 'item' => $item]);
    }

    public function destroy(MarketItem $item)
    {
        if ($item->user_id !== Auth::id() && !Auth::user()->is_admin) abort(403);
        $item->delete();
        return response()->json(['message' => '삭제되었습니다.']);
    }

    /** 좋아요 토글 */
    public function like($id)
    {
        MarketItem::findOrFail($id);
        $userId = Auth::id();

        $existing = DB::table('content_likes')
            ->where('user_id', $userId)
            ->where('likeable_type', 'market_item')
            ->where('likeable_id', $id)
            ->first();

        if ($existing) {
            DB::table('content_likes')->where('id', $existing->id)->delete();
        } else {
            DB::table('content_likes')->insert([
                'user_id'       => $userId,
                'likeable_type' => 'market_item',
                'likeable_id'   => $id,
                'created_at'    => now(),
            ]);
        }

        $likeCount = DB::table('content_likes')
            ->where('likeable_type', 'market_item')
            ->where('likeable_id', $id)
            ->count();

        return response()->json(['liked' => !$existing, 'like_count' => $likeCount]);
    }

    /** 북마크 토글 */
    public function bookmark($id)
    {
        MarketItem::findOrFail($id);
        $userId = Auth::id();

        $existing = Bookmark::where('user_id', $userId)
            ->where('bookmarkable_type', MarketItem::class)
            ->where('bookmarkable_id', $id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['bookmarked' => false]);
        }

        Bookmark::create([
            'user_id'           => $userId,
            'bookmarkable_type' => MarketItem::class,
            'bookmarkable_id'   => $id,
        ]);
        return response()->json(['bookmarked' => true]);
    }

    /** 댓글 작성 */
    public function comment(Request $request, $id)
    {
        MarketItem::findOrFail($id);
        $request->validate(['content' => 'required|string|max:2000']);

        $comment = Comment::create([
            'post_id'          => null,
            'commentable_type' => 'market_item',
            'commentable_id'   => $id,
            'user_id'          => Auth::id(),
            'content'          => $request->content,
        ]);

        // 포인트 지급
        $todayCount = Comment::where('user_id', Auth::id())->whereDate('created_at', today())->count();
        if ($todayCount <= 10) {
            Auth::user()->addPoints(5, 'comment_write', 'earn', $comment->id, '댓글 작성');
        }

        return response()->json([
            'message' => '댓글이 등록되었습니다.',
            'comment' => $comment->load('user:id,name,username,avatar'),
        ], 201);
    }
}
