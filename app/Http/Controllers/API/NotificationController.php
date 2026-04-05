<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Notification;

class NotificationController extends Controller
{
    public function index() {
        $notifs = Notification::where('user_id', auth()->id())->orderByDesc('created_at')->paginate(20);
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
