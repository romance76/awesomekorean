<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function toggle(Request $request) {
        $request->validate(['bookmarkable_type'=>'required','bookmarkable_id'=>'required']);
        $existing = Bookmark::where('user_id',auth()->id())->where('bookmarkable_type',$request->bookmarkable_type)->where('bookmarkable_id',$request->bookmarkable_id)->first();
        if ($existing) { $existing->delete(); return response()->json(['success'=>true,'bookmarked'=>false]); }
        Bookmark::create(['user_id'=>auth()->id(),'bookmarkable_type'=>$request->bookmarkable_type,'bookmarkable_id'=>$request->bookmarkable_id]);
        return response()->json(['success'=>true,'bookmarked'=>true]);
    }

    public function index(Request $request) {
        $query = Bookmark::with('bookmarkable')->where('user_id', auth()->id())
            ->when($request->type, fn($q, $t) => $q->where('bookmarkable_type', $t));
        $bookmarks = $query->orderByDesc('created_at')->paginate($request->per_page ?? 20);
        // 마이페이지 목록이 모델 이름("App\Models\ChatRoom #33") 대신 사람이 읽는 이름과 이동 주소를 쓰도록 서버에서 계산해 준다
        $bookmarks->getCollection()->transform(function ($b) {
            [$kind, $path] = self::TYPES[$b->bookmarkable_type] ?? ['저장한 항목', null];
            $t = $b->bookmarkable;
            $b->kind_label = $kind;
            $b->missing = $t === null;   // 원본이 지워졌으면 true
            $b->display_title = $t ? (self::titleOf($t) ?: ($kind . ' #' . $b->bookmarkable_id)) : ('삭제되었거나 볼 수 없는 ' . $kind);
            $b->url = ($t && $path) ? $path . $b->bookmarkable_id : null;
            return $b;
        });
        return response()->json(['success' => true, 'data' => $bookmarks]);
    }

    // 북마크 대상 종류 → [화면에 보이는 종류 이름, 이동 주소 앞부분]
    private const TYPES = [
        'App\Models\Post' => ['커뮤니티 글', '/community/free/'],
        'App\Models\MarketItem' => ['중고장터', '/market/'],
        'App\Models\JobPost' => ['구인구직', '/jobs/'],
        'App\Models\Business' => ['업소', '/directory/'],
        'App\Models\RecipePost' => ['레시피', '/recipes/'],
        'App\Models\QaPost' => ['Q&A', '/qa/'],
        'App\Models\News' => ['뉴스', '/news/'],
        'App\Models\Club' => ['동호회', '/clubs/'],
        'App\Models\Event' => ['이벤트', '/events/'],
        'App\Models\GroupBuy' => ['공동구매', '/groupbuy/'],
        'App\Models\RealEstateListing' => ['부동산', '/realestate/'],
        'App\Models\ChatRoom' => ['채팅방', '/chat/'],
    ];

    private static function titleOf($m): ?string
    {
        foreach (['title', 'name', 'subject'] as $k) {
            $v = $m->{$k} ?? null;
            if (is_string($v) && trim($v) !== '') return $v;
        }
        return null;
    }

    // 특정 type + id 들이 현재 유저에 의해 북마크되어있는지 일괄 확인 (리스트 렌더링용)
    public function check(Request $request) {
        $type = $request->type;
        $ids = is_array($request->ids) ? $request->ids : explode(',', (string) $request->ids);
        $ids = array_filter(array_map('intval', $ids));
        if (!$type || !$ids) return response()->json(['success' => true, 'data' => []]);
        $saved = Bookmark::where('user_id', auth()->id())
            ->where('bookmarkable_type', $type)
            ->whereIn('bookmarkable_id', $ids)
            ->pluck('bookmarkable_id')
            ->toArray();
        return response()->json(['success' => true, 'data' => $saved]);
    }
}
