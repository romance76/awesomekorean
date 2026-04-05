<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     * Paginated notifications for authenticated user.
     */
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(20);

        $unread = Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'notifications' => $notifications,
                'unread_count'  => $unread,
            ],
        ]);
    }

    /**
     * POST /api/notifications/read
     * Mark all notifications as read.
     */
    public function markRead(Request $request)
    {
        Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => '모든 알림을 읽음 처리했습니다.',
        ]);
    }

    /**
     * POST /api/notifications/{id}/read
     * Mark a single notification as read.
     */
    public function markOneRead($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $notification->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => '알림을 읽음 처리했습니다.',
        ]);
    }

    /**
     * Static helper: create a notification from other controllers.
     */
    public static function send(int $userId, string $type, string $title, string $body, ?string $url = null, ?array $data = null): void
    {
        $notifData = $data ?? [];
        if ($url) {
            $notifData['url'] = $url;
        }

        Notification::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'content' => $body,
            'data'    => !empty($notifData) ? json_encode($notifData) : null,
        ]);
    }
}
