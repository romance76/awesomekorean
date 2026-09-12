<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Notification;
use App\Models\UserBlock;
use App\Events\NewNotification;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request) {
        $userId = auth()->id();
        $tab = $request->tab ?? 'received'; // received | sent

        $query = Message::query();
        if ($tab === 'sent') {
            $query->with('receiver:id,name,nickname,avatar')->where('sender_id', $userId);
        } else {
            $query->with('sender:id,name,nickname,avatar')->where('receiver_id', $userId);
        }

        $messages = $query->orderByDesc('created_at')->paginate(20);
        $unread = Message::where('receiver_id', $userId)->where('is_read', false)->count();

        return response()->json(['success' => true, 'data' => $messages, 'unread_count' => $unread]);
    }

    public function store(Request $request) {
        $request->validate(['receiver_id' => 'required|exists:users,id', 'content' => 'required|max:500']);

        // 쪽지 수신 거부 체크
        $receiver = \App\Models\User::find($request->receiver_id);
        if ($receiver && !$receiver->allow_messages) {
            return response()->json(['success' => false, 'message' => '상대방이 쪽지 수신을 거부했습니다.'], 403);
        }

        // 신규 대화(ConversationController)/통화는 이미 차단을 확인하는데 구 쪽지만
        // 빠져있어 차단해도 구 쪽지로는 계속 연락 가능했던 문제 수정.
        if (UserBlock::isBlocked($request->receiver_id, auth()->id()) || UserBlock::isBlocked(auth()->id(), $request->receiver_id)) {
            return response()->json(['success' => false, 'message' => '쪽지를 보낼 수 없는 사용자입니다.'], 403);
        }

        $msg = Message::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $request->receiver_id,
            'content' => $request->content,
        ]);

        // 알림 생성
        Notification::create([
            'user_id' => $request->receiver_id,
            'type' => 'message',
            'title' => '새 쪽지가 도착했습니다',
            'content' => auth()->user()->name . '님이 쪽지를 보냈습니다.',
            'data' => ['message_id' => $msg->id, 'sender_id' => auth()->id(), 'sender_name' => auth()->user()->name],
        ]);

        // WebSocket 실시간 알림
        $unread = Notification::where('user_id', $request->receiver_id)->whereNull('read_at')->count();
        broadcast(new NewNotification($request->receiver_id, $unread, '새 쪽지가 도착했습니다'))->toOthers();

        // 구 쪽지는 인앱 알림만 있고 푸시가 없어 신규 대화(ConversationController)와
        // 알림 방식이 다르던 문제 수정 — 동일하게 푸시도 발송.
        if ($receiver?->fcm_token) {
            app(\App\Services\PushNotificationService::class)->sendToToken(
                $receiver->fcm_token,
                '새 쪽지가 도착했습니다',
                auth()->user()->name . '님이 쪽지를 보냈습니다.',
                ['type' => 'message', 'message_id' => (string) $msg->id]
            );
        }

        return response()->json(['success' => true, 'data' => $msg], 201);
    }

    public function markRead($id) {
        Message::where('id', $id)->where('receiver_id', auth()->id())->update(['is_read' => true]);
        return response()->json(['success' => true]);
    }

    public function destroy($id) {
        // 보낸 사람 또는 받은 사람만 삭제 가능
        $msg = Message::where('id', $id)
            ->where(function ($q) { $q->where('sender_id', auth()->id())->orWhere('receiver_id', auth()->id()); })
            ->firstOrFail();
        $msg->delete();
        return response()->json(['success' => true, 'message' => '삭제되었습니다']);
    }
}
