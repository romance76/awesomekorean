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
    /** 내가 삭제하지 않은 쪽지만 (보낸 쪽지는 sender_deleted, 받은 쪽지는 receiver_deleted 로 각자 따로 삭제) */
    private function visibleTo($q, int $me) {
        return $q->where(function ($w) use ($me) {
            $w->where(fn ($a) => $a->where('sender_id', $me)->where('sender_deleted', false))
              ->orWhere(fn ($b) => $b->where('receiver_id', $me)->where('receiver_deleted', false));
        });
    }

    private function unreadCount(int $me): int {
        return Message::where('receiver_id', $me)->where('receiver_deleted', false)->where('is_read', false)->count();
    }

    public function index(Request $request) {
        $userId = auth()->id();
        $tab = $request->tab ?? 'received'; // received | sent

        $query = Message::query();
        if ($tab === 'sent') {
            $query->with('receiver:id,name,nickname,avatar')->where('sender_id', $userId)->where('sender_deleted', false);
        } else {
            $query->with('sender:id,name,nickname,avatar')->where('receiver_id', $userId)->where('receiver_deleted', false);
        }

        $messages = $query->orderByDesc('created_at')->paginate(20);

        return response()->json(['success' => true, 'data' => $messages, 'unread_count' => $this->unreadCount((int) $userId)]);
    }

    /**
     * 상대방별 대화 목록 (채팅처럼 한 사람과 주고받은 쪽지를 묶어서 보여주기 위함).
     * 받은/보낸 쪽지를 합쳐서 상대별 마지막 쪽지·안 읽은 수를 돌려준다.
     */
    public function threads() {
        $me = (int) auth()->id();
        $rows = \DB::table('messages')
            ->selectRaw('CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS partner_id, MAX(id) AS last_id, SUM(CASE WHEN receiver_id = ? AND is_read = 0 THEN 1 ELSE 0 END) AS unread, COUNT(*) AS total', [$me, $me])
            ->where(function ($q) use ($me) {
                $q->where(fn ($a) => $a->where('sender_id', $me)->where('sender_deleted', 0))
                  ->orWhere(fn ($b) => $b->where('receiver_id', $me)->where('receiver_deleted', 0));
            })
            ->groupBy('partner_id')
            ->orderByDesc('last_id')
            ->limit(200)
            ->get();

        $lasts = Message::whereIn('id', $rows->pluck('last_id'))->get()->keyBy('id');
        $users = \App\Models\User::whereIn('id', $rows->pluck('partner_id'))->get(['id', 'name', 'nickname', 'avatar', 'city', 'state', 'last_active_at', 'lifetime_points'])->keyBy('id');

        // 친구 관계(어디서 만났는지 source 포함) — 친구가 아닌 사람의 쪽지는 '모르는 사람'으로 따로 묶기 위함
        $rel = []; // partnerId => ['status' => accepted|pending|blocked, 'source' => ..., 'mine' => 내가 먼저 요청했는지]
        $friendRows = \App\Models\Friend::where(function ($q) use ($me) { $q->where('user_id', $me)->orWhere('friend_id', $me); })->get();
        foreach ($friendRows as $f) {
            $pid = $f->user_id == $me ? $f->friend_id : $f->user_id;
            if (!isset($rel[$pid]) || $f->status === 'accepted') $rel[$pid] = ['status' => $f->status, 'source' => $f->source ?: '', 'mine' => $f->user_id == $me];
        }
        $blockedIds = \App\Models\UserBlock::where('blocker_id', $me)->pluck('blocked_id')->all();

        $threads = $rows->map(function ($r) use ($lasts, $users, $me, $rel, $blockedIds) {
            $last = $lasts[$r->last_id] ?? null;
            $u = $users[$r->partner_id] ?? null;
            $pid = (int) $r->partner_id;
            $mins = ($u && $u->last_active_at) ? abs(now()->diffInMinutes($u->last_active_at)) : null;
            $st = $rel[$pid]['status'] ?? 'none';
            return [
                'partner' => $u ? $u->only(['id', 'name', 'nickname', 'avatar', 'city', 'state', 'grade_level']) : ['id' => $pid, 'name' => '(탈퇴한 회원)'],
                'is_friend' => $st === 'accepted',
                'friend_status' => $st, // none | pending | accepted | blocked
                'request_mine' => (bool) ($rel[$pid]['mine'] ?? false),
                'blocked' => in_array($pid, $blockedIds),
                'source' => $rel[$pid]['source'] ?? '',
                'online_status' => $mins === null ? 'offline' : ($mins <= 5 ? 'online' : ($mins <= 30 ? 'away' : 'offline')),
                'last_content' => $last?->content,
                'last_at' => $last?->created_at,
                'last_mine' => $last ? ((int) $last->sender_id === $me) : false,
                'unread' => (int) $r->unread,
                'total' => (int) $r->total,
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $threads, 'unread_count' => $this->unreadCount($me)]);
    }

    /** 한 사람과 주고받은 쪽지 전체(오래된 것 → 최신). 상대가 보낸 안 읽은 쪽지는 읽음 처리. */
    public function thread($partnerId, Request $request) {
        $me = (int) auth()->id();
        $partnerId = (int) $partnerId;
        $limit = max(1, min(300, (int) $request->input('limit', 200)));

        $q = Message::where(function ($w) use ($me, $partnerId) {
            $w->where(fn ($a) => $a->where('sender_id', $me)->where('receiver_id', $partnerId)->where('sender_deleted', false))
              ->orWhere(fn ($b) => $b->where('sender_id', $partnerId)->where('receiver_id', $me)->where('receiver_deleted', false));
        });
        if ($request->before_id) $q->where('id', '<', (int) $request->before_id);
        $messages = $q->orderByDesc('id')->limit($limit)->get()->reverse()->values();

        Message::where('sender_id', $partnerId)->where('receiver_id', $me)->where('receiver_deleted', false)->where('is_read', false)->update(['is_read' => true]);

        $partner = \App\Models\User::select('id', 'name', 'nickname', 'avatar', 'lifetime_points')->find($partnerId);
        return response()->json([
            'success' => true,
            'data' => $messages,
            'partner' => $partner ?? ['id' => $partnerId, 'name' => '(탈퇴한 회원)'],
            'has_more' => $messages->count() >= $limit,
            'unread_count' => $this->unreadCount($me),
        ]);
    }

    /** 이 사람과의 대화 전체를 내 쪽지함에서 삭제 (상대 쪽지함에는 그대로 남음) */
    public function destroyThread($partnerId) {
        $me = (int) auth()->id();
        $partnerId = (int) $partnerId;
        Message::where('sender_id', $me)->where('receiver_id', $partnerId)->update(['sender_deleted' => true]);
        Message::where('sender_id', $partnerId)->where('receiver_id', $me)->update(['receiver_deleted' => true]);
        // 양쪽 모두 지운 쪽지는 더 보여줄 곳이 없으므로 정리
        Message::where('sender_deleted', true)->where('receiver_deleted', true)
            ->where(function ($q) use ($me, $partnerId) {
                $q->where(fn ($a) => $a->where('sender_id', $me)->where('receiver_id', $partnerId))
                  ->orWhere(fn ($b) => $b->where('sender_id', $partnerId)->where('receiver_id', $me));
            })->delete();
        return response()->json(['success' => true, 'message' => '대화가 삭제되었습니다', 'unread_count' => $this->unreadCount($me)]);
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

        $me = auth()->user();
        $msg = Message::create([
            'sender_id' => $me->id,
            'receiver_id' => $request->receiver_id,
            'content' => $request->content,
        ]);

        // 응답을 먼저 보내고(쪽지 저장 직후) 알림·실시간·푸시는 응답 이후에 처리한다.
        // (푸시는 외부 HTTP 호출이라 수백 ms 걸려 전송 반응이 느려지던 문제)
        $receiverId = (int) $request->receiver_id;
        $senderId = (int) $me->id;
        $senderName = (string) $me->name;
        $msgId = $msg->id;
        $fcmToken = $receiver?->fcm_token;
        defer(function () use ($receiverId, $senderId, $senderName, $msgId, $fcmToken) {
            try {
                // 알림 생성
                Notification::create([
                    'user_id' => $receiverId,
                    'type' => 'message',
                    'title' => '새 쪽지가 도착했습니다',
                    'content' => $senderName . '님이 쪽지를 보냈습니다.',
                    'data' => ['message_id' => $msgId, 'sender_id' => $senderId, 'sender_name' => $senderName],
                ]);

                // WebSocket 실시간 알림
                $unread = Notification::where('user_id', $receiverId)->whereNull('read_at')->count();
                broadcast(new NewNotification($receiverId, $unread, '새 쪽지가 도착했습니다'))->toOthers();
            } catch (\Throwable $e) {
                \Log::warning('[쪽지] 알림/실시간 처리 실패: ' . $e->getMessage());
            }

            // 구 쪽지는 인앱 알림만 있고 푸시가 없어 신규 대화(ConversationController)와
            // 알림 방식이 다르던 문제 수정 — 동일하게 푸시도 발송.
            if ($fcmToken) {
                try {
                    app(\App\Services\PushNotificationService::class)->sendToToken(
                        $fcmToken,
                        '새 쪽지가 도착했습니다',
                        $senderName . '님이 쪽지를 보냈습니다.',
                        ['type' => 'message', 'message_id' => (string) $msgId, 'url' => '/dashboard?tab=messages']
                    );
                } catch (\Throwable $e) {
                    \Log::warning('[쪽지] 푸시 실패: ' . $e->getMessage());
                }
            }
        });

        return response()->json(['success' => true, 'data' => $msg], 201);
    }

    public function markRead($id) {
        Message::where('id', $id)->where('receiver_id', auth()->id())->update(['is_read' => true]);
        return response()->json(['success' => true]);
    }

    public function destroy($id) {
        // 보낸 사람 또는 받은 사람만 삭제 가능 — 각자 자기 쪽지함에서만 지워지고, 양쪽 모두 지우면 완전 삭제
        $me = (int) auth()->id();
        $msg = Message::where('id', $id)
            ->where(function ($q) use ($me) { $q->where('sender_id', $me)->orWhere('receiver_id', $me); })
            ->firstOrFail();
        if ((int) $msg->sender_id === $me) $msg->sender_deleted = true;
        if ((int) $msg->receiver_id === $me) $msg->receiver_deleted = true;
        if ($msg->sender_deleted && $msg->receiver_deleted) $msg->delete(); else $msg->save();
        return response()->json(['success' => true, 'message' => '삭제되었습니다']);
    }
}
