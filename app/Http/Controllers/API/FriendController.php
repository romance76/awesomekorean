<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Friend;
use App\Models\Notification;
use App\Models\User;
use App\Events\NewNotification;
use Illuminate\Http\Request;

class FriendController extends Controller
{
    // 친구 요청/수락 알림이 전혀 없어 신청자·수신자 모두 페이지를 직접 열어봐야만
    // 알 수 있던 문제 수정 — MessageController::store()와 동일한 패턴.
    private function notify(int $userId, string $type, string $title, string $content, array $data = []): void
    {
        Notification::create(['user_id' => $userId, 'type' => $type, 'title' => $title, 'content' => $content, 'data' => $data]);
        $unread = Notification::where('user_id', $userId)->whereNull('read_at')->count();
        try { broadcast(new NewNotification($userId, $unread, $title))->toOthers(); } catch (\Exception $e) {}
    }

    // 전체 친구 목록 (모든 상태 + 온라인 정보)
    public function index(Request $request) {
        $userId = auth()->id();

        // 만료된 pending 요청 자동 삭제
        Friend::where('status', 'pending')
            ->whereNotNull('expires_at')->where('expires_at', '<', now())
            ->where(function($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('friend_id', $userId);
            })->delete();

        $query = Friend::query()
            ->where(function($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('friend_id', $userId);
            });

        // source 필터
        if ($request->source) $query->where('source', $request->source);
        // status 필터
        if ($request->status) $query->where('status', $request->status);

        $friends = $query->get();

        // 관련 유저 정보 + 온라인 상태 추가
        // 양방향 레코드 중복 제거 (같은 상대방이 2번 나오는 것 방지)
        $seenIds = [];
        $result = $friends->map(function($f) use ($userId, &$seenIds) {
            $otherId = $f->user_id == $userId ? $f->friend_id : $f->user_id;
            if (in_array($otherId, $seenIds)) return null; // 중복 스킵
            $seenIds[] = $otherId;
            $isSender = $f->user_id == $userId; // 내가 보낸 건지
            $other = User::select('id','name','nickname','avatar','city','state','bio','last_active_at')->find($otherId);
            if (!$other) return null;

            // 온라인 상태
            $status = 'offline';
            if ($other->last_active_at) {
                $mins = now()->diffInMinutes($other->last_active_at);
                if ($mins <= 5) $status = 'online';
                elseif ($mins <= 30) $status = 'away';
            }

            return [
                'id' => $f->id,
                'friend' => $other,
                'status' => $f->status,
                'source' => $f->source,
                'online_status' => $status,
                'is_sender' => $isSender,
                'can_cancel' => $isSender && $f->canCancel(),
                'expires_at' => $f->expires_at,
                'created_at' => $f->created_at,
            ];
        })->filter()->values();

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function sendRequest(Request $request, $userId) {
        if ($userId == auth()->id()) return response()->json(['success' => false, 'message' => '자신에게 요청할 수 없습니다'], 400);
        $target = User::find($userId);
        if (!$target) return response()->json(['success' => false, 'message' => '사용자를 찾을 수 없습니다'], 404);
        if (!$target->allow_friend_request) return response()->json(['success' => false, 'message' => '이 사용자는 친구 요청을 받지 않습니다'], 403);

        // 차단 관계에서는 친구 요청도 막아야 하는데 빠져있던 문제 수정.
        if (\App\Models\UserBlock::isBlocked($userId, auth()->id()) || \App\Models\UserBlock::isBlocked(auth()->id(), $userId)) {
            return response()->json(['success' => false, 'message' => '친구 요청을 보낼 수 없는 사용자입니다'], 403);
        }

        // 만료된 요청 자동 삭제 (양방향)
        Friend::where('status', 'pending')->whereNotNull('expires_at')->where('expires_at', '<', now())
            ->where(function($q) use ($userId) {
                $q->where(function($q2) use ($userId) {
                    $q2->where('user_id', auth()->id())->where('friend_id', $userId);
                })->orWhere(function($q2) use ($userId) {
                    $q2->where('user_id', $userId)->where('friend_id', auth()->id());
                });
            })->delete();

        // 내가 이미 보낸 요청 체크
        $existing = Friend::where('user_id', auth()->id())->where('friend_id', $userId)->first();
        if ($existing) {
            if ($existing->status === 'accepted') return response()->json(['success' => false, 'message' => '이미 친구입니다'], 400);
            return response()->json(['success' => false, 'message' => '이미 요청했습니다'], 400);
        }

        // 상대방이 나에게 보낸 pending 요청이 있으면 → 자동 수락
        $reverse = Friend::where('user_id', $userId)->where('friend_id', auth()->id())->where('status', 'pending')->first();
        if ($reverse) {
            $reverse->update(['status' => 'accepted', 'expires_at' => null]);
            Friend::firstOrCreate(
                ['user_id' => auth()->id(), 'friend_id' => $userId],
                ['status' => 'accepted', 'source' => $request->source ?? 'community']
            );
            $this->notify($userId, 'friend_accepted', '친구 요청이 수락되었습니다', auth()->user()->name . '님과 서로 친구가 되었습니다.');
            return response()->json(['success' => true, 'message' => '서로 친구가 되었습니다!', 'auto_accepted' => true]);
        }

        // 이미 친구인지 체크 (역방향 accepted)
        $reverseAccepted = Friend::where('user_id', $userId)->where('friend_id', auth()->id())->where('status', 'accepted')->first();
        if ($reverseAccepted) return response()->json(['success' => false, 'message' => '이미 친구입니다'], 400);

        $friend = Friend::create([
            'user_id' => auth()->id(),
            'friend_id' => $userId,
            'status' => 'pending',
            'source' => $request->source ?? 'community',
            'expires_at' => now()->addDays(7),
        ]);
        $this->notify($userId, 'friend_request', '새 친구 요청이 도착했습니다', auth()->user()->name . '님이 친구 요청을 보냈습니다.', ['friend_id' => $friend->id]);
        return response()->json(['success' => true, 'message' => '친구 요청을 보냈습니다', 'data' => $friend]);
    }

    public function cancelRequest($id) {
        $req = Friend::where('user_id', auth()->id())->where('id', $id)->where('status', 'pending')->firstOrFail();

        if ($req->created_at->diffInHours(now()) < 24) {
            return response()->json([
                'success' => false,
                'message' => '요청 후 24시간이 지나야 취소할 수 있습니다.',
                'can_cancel_at' => $req->created_at->addHours(24)->toISOString(),
            ], 403);
        }

        $req->delete();
        return response()->json(['success' => true, 'message' => '친구 요청을 취소했습니다']);
    }

    public function accept($id) {
        $req = Friend::where('friend_id', auth()->id())->where('id', $id)->where('status', 'pending')->firstOrFail();
        $req->update(['status' => 'accepted']);
        // 양방향 친구 관계 생성
        Friend::firstOrCreate(
            ['user_id' => auth()->id(), 'friend_id' => $req->user_id],
            ['status' => 'accepted', 'source' => $req->source]
        );
        $this->notify($req->user_id, 'friend_accepted', '친구 요청이 수락되었습니다', auth()->user()->name . '님과 서로 친구가 되었습니다.');
        return response()->json(['success' => true, 'message' => '친구 요청을 수락했습니다']);
    }

    public function block($userId) {
        Friend::updateOrCreate(['user_id' => auth()->id(), 'friend_id' => $userId], ['status' => 'blocked']);
        // Friend.status='blocked'는 어디서도 확인되지 않는 죽은 상태값이라
        // 저장만 되고 실제로는 아무것도 차단하지 못했음(3갈래로 흩어진 차단
        // 기능 중 하나, 실측 확인) — 실제로 통화·대화를 막는 UserBlock에도 반영.
        \App\Models\UserBlock::firstOrCreate(['blocker_id' => auth()->id(), 'blocked_id' => $userId]);
        return response()->json(['success' => true]);
    }

    public function remove($id) {
        // 소유권 검증 없이 ID만으로 조회하면 임의 사용자가 남의 친구관계를
        // 지울 수 있음(IDOR) — 실측으로 확인된 버그, 본인이 당사자인
        // 친구관계만 지울 수 있도록 제한
        $userId = auth()->id();
        $f = Friend::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhere('friend_id', $userId);
        })->findOrFail($id);
        // 양방향 삭제
        Friend::where('user_id', $f->user_id)->where('friend_id', $f->friend_id)->delete();
        Friend::where('user_id', $f->friend_id)->where('friend_id', $f->user_id)->delete();
        return response()->json(['success' => true]);
    }

    // 1:1/그룹 채팅방 생성은 ChatController::createRoom (실제 스키마: chat_room_users)이 담당.
    // 이 컨트롤러에 있던 createPrivateChat/createGroupChat/privateChatRooms는 존재하지 않는
    // chat_room_members/is_private 컬럼을 참조하는 죽은 코드(항상 SQL 오류)라 제거함.
}
