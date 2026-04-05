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

    public function index() {
        $bookmarks = Bookmark::where('user_id',auth()->id())->orderByDesc('created_at')->paginate(20);
        return response()->json(['success'=>true,'data'=>$bookmarks]);
    }
}
