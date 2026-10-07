<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Chat channels - public (anyone logged in can listen)
// 채팅방 실시간 채널: 로그인만 하면 아무 방이나 엿들을 수 있었다 → 방 종류별로 API 와 같은 기준으로 막는다
//  · 차단된 사람 ✕ · 공개방은 입장권(방장/운영진/무료방/24시간 입장권) · 그 외(1:1/그룹/비공개)는 방 멤버만 · 운영진은 모든 방
Broadcast::channel('chat.{roomId}', function ($user, $roomId) {
    $room = \App\Models\ChatRoom::find((int) $roomId);
    if (!$room) return false;
    if (in_array($user->role, ['admin', 'super_admin', 'moderator'], true)) return true;
    if (\Illuminate\Support\Facades\DB::table('chat_room_bans')->where('chat_room_id', $room->id)->where('user_id', $user->id)->exists()) return false;
    if ($room->type === 'public') return \App\Support\ChatAccess::hasPublicAccess($room, $user);
    return \App\Models\ChatRoomUser::where('chat_room_id', $room->id)->where('user_id', $user->id)->whereNull('left_at')->exists();
});

// WebRTC private call channel
Broadcast::channel('call.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Poker channels (public - any logged in user)
Broadcast::channel('poker.lobby', function ($user) {
    return auth()->check();
});

Broadcast::channel('poker.tournament.{tournamentId}', function ($user, $tournamentId) {
    return auth()->check();
});

Broadcast::channel('poker.table.{tableId}', function ($user, $tableId) {
    return auth()->check();
});

// 멀티플레이어 포커 게임 (Presence — 참가자만)
Broadcast::channel('poker.{gameId}', function ($user, $gameId) {
    if (!auth()->check()) return false;
    return ['id' => $user->id, 'name' => $user->nickname ?? $user->name];
});

// 안심 커뮤니케이션 채널
Broadcast::channel('conversation.{conversationId}', function ($user, int $conversationId) {
    $conversation = \App\Models\Conversation::find($conversationId);
    if (!$conversation) return false;
    return in_array($user->id, [$conversation->user_a_id, $conversation->user_b_id]);
});

Broadcast::channel('user.{userId}', function ($user, int $userId) {
    return $user->id === $userId;
});
