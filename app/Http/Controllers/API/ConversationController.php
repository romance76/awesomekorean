<?php

namespace App\Http\Controllers\API;

use App\Events\CommMessageSent;
use App\Events\NewNotification;
use App\Http\Controllers\Controller;
use App\Models\CommMessage;
use App\Models\Conversation;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ConversationController extends Controller
{
    /**
     * List all conversations for the authenticated user.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $conversations = Conversation::with(['latestMessage', 'userA', 'userB'])
            ->where('user_a_id', $userId)
            ->orWhere('user_b_id', $userId)
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function ($conv) use ($userId) {
                $other = $conv->otherUser($userId);
                return [
                    'id'           => $conv->id,
                    'partner'      => [
                        'id'     => $other->id,
                        'name'   => $other->name,
                        'avatar' => $other->avatar,
                        'online' => Cache::has('user-online-' . $other->id),
                    ],
                    'last_message' => $conv->latestMessage?->body,
                    'last_at'      => $conv->last_message_at?->toISOString(),
                    'unread_count' => $conv->unreadCount($userId),
                ];
            });

        return response()->json($conversations);
    }

    /**
     * Get paginated messages for a conversation.
     * Also marks unread messages as read.
     */
    public function messages(Request $request, Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        // Mark other user's messages as read
        $conversation->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(
            $conversation->messages()->with('sender')->paginate(40)
        );
    }

    /**
     * Send a message to a partner (creates conversation if needed).
     */
    public function send(Request $request, int $partnerId)
    {
        $request->validate(['body' => 'required|string|max:2000']);
        $myId = $request->user()->id;

        // Block check: either direction
        if (UserBlock::isBlocked($partnerId, $myId) || UserBlock::isBlocked($myId, $partnerId)) {
            return response()->json(['error' => '메시지를 보낼 수 없는 사용자입니다.'], 403);
        }

        $conversation = Conversation::findOrCreateBetween($myId, $partnerId);
        $message = CommMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $myId,
            'body'            => $request->body,
            'type'            => 'text',
        ]);
        $conversation->update(['last_message_at' => now()]);

        // Broadcast to conversation channel
        broadcast(new CommMessageSent($message))->toOthers();

        // FCM push (stub — logs warning until Firebase is installed)
        $partner = User::find($partnerId);
        if ($partner?->fcm_token) {
            app(PushNotificationService::class)->sendNewMessage(
                fcmToken:       $partner->fcm_token,
                senderName:     $request->user()->name,
                messageBody:    $request->body,
                conversationId: $conversation->id,
            );
        }

        // 구 쪽지(MessageController)는 인앱 알림, 신규 대화는 푸시만 발송해
        // 알림 방식이 이원화돼 있던 문제 수정 — 푸시 유무와 무관하게 여기도
        // 인앱 알림을 남겨 알림센터에서 동일하게 확인 가능하도록 함.
        try {
            Notification::create([
                'user_id' => $partnerId,
                'type' => 'new_message',
                'title' => '새 메시지가 도착했습니다',
                'content' => $request->user()->name . '님: ' . mb_substr($request->body, 0, 80),
                'data' => ['conversation_id' => $conversation->id, 'sender_id' => $myId],
            ]);
            $unread = Notification::where('user_id', $partnerId)->whereNull('read_at')->count();
            broadcast(new NewNotification($partnerId, $unread, '새 메시지가 도착했습니다'))->toOthers();
        } catch (\Exception $e) {}

        return response()->json([
            'id'         => $message->id,
            'body'       => $message->body,
            'created_at' => $message->created_at->toISOString(),
        ], 201);
    }
}
