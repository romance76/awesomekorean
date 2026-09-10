<?php

namespace App\Support;

use App\Models\ChatRoom;
use Carbon\Carbon;

/**
 * 채팅방 잠금/삭제 여부를 "마지막 메시지 시각 + 설정값" 으로 그때그때 계산한다.
 * 크론(chat:expire-rooms)이 도는지 여부와 무관하게 항상 정확한 결과를 보장하기 위해,
 * 저장된 chat_rooms.locked_at 컬럼에 의존하지 않는다 — 실제 삭제만 크론/스윕이 처리.
 */
class ChatLockHelper
{
    /** 이 시각 이후로 마지막 활동이 없으면 잠긴 것으로 간주 */
    public static function lockAt(ChatRoom $room): Carbon
    {
        $lockDays = ChatRules::get('inactive_lock_days', 7);
        $base = $room->last_message_at ?: $room->created_at;
        return Carbon::parse($base)->addDays($lockDays);
    }

    /** 이 시각이 지나면 완전히 삭제 대상 */
    public static function deleteAt(ChatRoom $room): Carbon
    {
        $deleteDays = ChatRules::get('lock_delete_days', 3);
        return static::lockAt($room)->addDays($deleteDays);
    }

    public static function isLocked(ChatRoom $room): bool
    {
        // 공개방/동호회방은 특정 회원끼리의 사적 대화가 아니라 커뮤니티 공간이므로 비활성 잠금 대상에서 제외
        if (in_array($room->type, ['public', 'club'])) return false;
        return now()->greaterThan(static::lockAt($room));
    }

    public static function shouldDelete(ChatRoom $room): bool
    {
        // 공개방/동호회방은 특정 회원끼리의 사적 대화가 아니라 커뮤니티 공간이므로 비활성 잠금 대상에서 제외
        if (in_array($room->type, ['public', 'club'])) return false;
        return now()->greaterThan(static::deleteAt($room));
    }

    /** 잠긴 방이 삭제까지 남은 일수 (안 잠겼으면 null) */
    public static function daysUntilDelete(ChatRoom $room): ?int
    {
        if (!static::isLocked($room)) return null;
        $seconds = now()->diffInSeconds(static::deleteAt($room), false);
        return max(0, (int) ceil($seconds / 86400));
    }
}
