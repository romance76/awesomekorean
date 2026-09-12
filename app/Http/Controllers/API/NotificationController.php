<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * 알림센터 통합검색 — 알림이 쌓일수록 예전 알림을 다시 찾을 방법이
     * 전혀 없던 문제 수정. 제목/내용 검색 + 종류 필터 + 안읽음만 보기 지원.
     */
    public function index(Request $request) {
        $query = Notification::where('user_id', auth()->id());

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")->orWhere('content', 'like', "%{$q}%");
            });
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $notifs = $query->orderByDesc('created_at')->paginate(20);
        $unread = Notification::where('user_id', auth()->id())->whereNull('read_at')->count();
        return response()->json(['success' => true, 'data' => $notifs, 'unread_count' => $unread]);
    }

    public function markRead() {
        Notification::where('user_id', auth()->id())->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['success' => true]);
    }

    public function markOneRead($id) {
        Notification::where('user_id', auth()->id())->where('id', $id)->update(['read_at' => now()]);
        return response()->json(['success' => true]);
    }
}
