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
            ->reorder()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(
            $conversation->messages()
                ->reorder()
                ->orderByDesc('id') // 최신 40개부터 (같은 초에 쓴 글도 순서 보장). 화면에서 뒤집어 표시
                ->with('sender:id,name,nickname,avatar,lifetime_points')
                ->paginate(40)
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

        // 응답을 먼저 보내고 실시간 전송·푸시·알림은 응답 이후에 처리한다 (외부 푸시 HTTP 때문에 느려지던 문제).
        $user = $request->user();
        $senderName = (string) $user->name;
        $body = (string) $request->body;
        $convId = $conversation->id;
        defer(function () use ($message, $partnerId, $myId, $senderName, $body, $convId) {
            try {
                broadcast(new CommMessageSent($message))->toOthers();
            } catch (\Throwable $e) {
                \Log::warning('[대화] 실시간 전송 실패: ' . $e->getMessage());
            }

            try {
                $partner = User::find($partnerId);
                if ($partner?->fcm_token) {
                    app(PushNotificationService::class)->sendNewMessage(
                        fcmToken:       $partner->fcm_token,
                        senderName:     $senderName,
                        messageBody:    $body,
                        conversationId: $convId,
                    );
                }
            } catch (\Throwable $e) {
                \Log::warning('[대화] 푸시 실패: ' . $e->getMessage());
            }

            // 구 쪽지(MessageController)는 인앱 알림, 신규 대화는 푸시만 발송해
            // 알림 방식이 이원화돼 있던 문제 수정 — 푸시 유무와 무관하게 여기도
            // 인앱 알림을 남겨 알림센터에서 동일하게 확인 가능하도록 함.
            try {
                Notification::create([
                    'user_id' => $partnerId,
                    'type' => 'new_message',
                    'title' => '새 메시지가 도착했습니다',
                    'content' => $senderName . '님: ' . mb_substr($body, 0, 80),
                    'data' => ['conversation_id' => $convId, 'sender_id' => $myId],
                ]);
                $unread = Notification::where('user_id', $partnerId)->whereNull('read_at')->count();
                broadcast(new NewNotification($partnerId, $unread, '새 메시지가 도착했습니다'))->toOthers();
            } catch (\Throwable $e) {}
        });

        return response()->json([
            'id'              => $message->id,
            'conversation_id' => $conversation->id,
            'sender_id'       => $myId,
            'body'            => $message->body,
            'type'            => $message->type ?? 'text',
            'read_at'         => null,
            'created_at'      => $message->created_at->toISOString(),
        ], 201);
    }
}
