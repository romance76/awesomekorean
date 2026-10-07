<?php

namespace App\Support;

use App\Models\ChatRoom;
use App\Models\ChatRoomUser;
use App\Models\User;

/**
 * 공개 채팅방 열람 권한 — 방장 / 운영진 / 무료 채팅방 지정 / 유효한 24시간 입장권.
 * 화면의 블러·입장 확인창은 화면일 뿐이라(휴대폰 앱·차량 브라우저·직접 API 호출은 그 화면을 안 거침),
 * 메시지 조회·검색·실시간 채널도 서버에서 같은 기준으로 막는다. 공개방이 아니면 이 규칙의 대상이 아님.
 */
class ChatAccess
{
    public static function hasPublicAccess(ChatRoom $room, ?User $user): bool
    {
        if ($room->type !== 'public') return true;
        if (!$user) return false;
        if (in_array($user->role, ['admin', 'super_admin', 'moderator'])) return true;
        if ($room->created_by === $user->id || $room->id === $user->free_public_room_id) return true;

        $pass = ChatRoomUser::where('chat_room_id', $room->id)->where('user_id', $user->id)->value('access_expires_at');
        return $pass && now()->lessThan($pass);
    }

    public static function denied()
    {
        return response()->json(['success' => false, 'message' => '채팅방에 입장한 뒤 볼 수 있습니다.', 'needs_entry' => true], 403);
    }
}
