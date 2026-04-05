<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\{User, Post, JobPost, MarketItem, Business, Event, News, Report, Board, Banner, IpBan, Payment};
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function overview() {
        return response()->json(['success'=>true,'data'=>[
            'total_users' => User::count(),
            'total_posts' => Post::count(),
            'posts_today' => Post::whereDate('created_at', today())->count(),
            'new_users_week' => User::where('created_at', '>=', now()->subWeek())->count(),
            'pending_reports' => Report::where('status','pending')->count(),
        ]]);
    }

    public function users(Request $request) {
        $query = User::query()
            ->when($request->search, fn($q,$v) => $q->where('name','like',"%{$v}%")->orWhere('email','like',"%{$v}%"))
            ->when($request->role, fn($q,$v) => $q->where('role', $v))
            ->orderByDesc('created_at');
        return response()->json(['success'=>true,'data'=>$query->paginate(20)]);
    }

    public function banUser(Request $request, $id) {
        User::findOrFail($id)->update(['is_banned'=>true,'ban_reason'=>$request->reason]);
        return response()->json(['success'=>true]);
    }

    public function unbanUser($id) {
        User::findOrFail($id)->update(['is_banned'=>false,'ban_reason'=>null]);
        return response()->json(['success'=>true]);
    }

    public function posts(Request $request) {
        $posts = Post::with('user:id,name','board:id,name')
            ->when($request->board_id, fn($q,$v) => $q->where('board_id',$v))
            ->orderByDesc('created_at')->paginate(20);
        return response()->json(['success'=>true,'data'=>$posts]);
    }

    public function hidePost($id) { Post::findOrFail($id)->update(['is_hidden'=>!Post::find($id)->is_hidden]); return response()->json(['success'=>true]); }
    public function pinPost($id) { Post::findOrFail($id)->update(['is_pinned'=>!Post::find($id)->is_pinned]); return response()->json(['success'=>true]); }
    public function deletePost($id) { Post::findOrFail($id)->delete(); return response()->json(['success'=>true]); }

    public function boards() { return response()->json(['success'=>true,'data'=>Board::orderBy('sort_order')->get()]); }
    public function createBoard(Request $request) { return response()->json(['success'=>true,'data'=>Board::create($request->only('name','slug','description','sort_order'))]); }
    public function updateBoard(Request $request, $id) { Board::findOrFail($id)->update($request->only('name','slug','description','sort_order','is_active')); return response()->json(['success'=>true]); }
    public function deleteBoard($id) { Board::findOrFail($id)->delete(); return response()->json(['success'=>true]); }

    public function reports(Request $request) { return response()->json(['success'=>true,'data'=>Report::orderByDesc('created_at')->paginate(20)]); }
    public function updateReport(Request $request, $id) { Report::findOrFail($id)->update($request->only('status','admin_note')); return response()->json(['success'=>true]); }

    public function banners() { return response()->json(['success'=>true,'data'=>Banner::orderBy('sort_order')->get()]); }
    public function createBanner(Request $request) { return response()->json(['success'=>true,'data'=>Banner::create($request->all())]); }
    public function deleteBanner($id) { Banner::findOrFail($id)->delete(); return response()->json(['success'=>true]); }

    public function ipBans() { return response()->json(['success'=>true,'data'=>IpBan::orderByDesc('created_at')->get()]); }
    public function createIpBan(Request $request) { IpBan::create(['ip_address'=>$request->ip_address,'reason'=>$request->reason,'banned_by'=>auth()->id()]); return response()->json(['success'=>true]); }
    public function deleteIpBan($id) { IpBan::findOrFail($id)->delete(); return response()->json(['success'=>true]); }

    public function payments(Request $request) { return response()->json(['success'=>true,'data'=>Payment::with('user:id,name')->orderByDesc('created_at')->paginate(20)]); }
}
