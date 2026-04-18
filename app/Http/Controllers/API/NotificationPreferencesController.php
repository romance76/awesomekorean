<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * MyNotificationSettings 영속화 (Phase 2-C Post).
 * users.notification_preferences JSON 컬럼 사용.
 */
class NotificationPreferencesController extends Controller
{
    protected array $allowedKeys = [
        'notify_like', 'notify_comment', 'notify_friend_request', 'notify_message',
        'notify_event', 'notify_sos',
        'email_digest', 'email_marketing',
        'push_enabled', 'push_quiet_night',
    ];

    public function show()
    {
        $user = auth()->user();
        $prefs = $user->notification_preferences ?? $this->defaults();
        return response()->json(['success' => true, 'data' => $prefs]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        $current = $user->notification_preferences ?? $this->defaults();

        foreach ($this->allowedKeys as $k) {
            if ($request->has($k)) {
                $current[$k] = (bool) $request->input($k);
            }
        }

        $user->notification_preferences = $current;
        $user->save();

        return response()->json(['success' => true, 'data' => $current]);
    }

    protected function defaults(): array
    {
        return [
            'notify_like' => true, 'notify_comment' => true, 'notify_friend_request' => true, 'notify_message' => true,
            'notify_event' => true, 'notify_sos' => true,
            'email_digest' => false, 'email_marketing' => false,
            'push_enabled' => true, 'push_quiet_night' => false,
        ];
    }
}
