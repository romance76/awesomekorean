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
        return response()->json(['success' => true, 'data' => $bookmarks]);
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
