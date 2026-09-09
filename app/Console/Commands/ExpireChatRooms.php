<?php

namespace App\Console\Commands;

use App\Models\ChatRoom;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 잠금/삭제 대상 판정 자체는 ChatController::sweepExpiredRooms() 에서 실제 트래픽 발생 시
 * (5분에 한 번) 이미 수행되므로, 이 명령어는 서버 cron(schedule:run)이 정상 동작할 때
 * 추가 안전망으로만 동작한다 — 둘 중 하나만 돌아도 결과는 동일하다.
 */
class ExpireChatRooms extends Command
{
    protected $signature = 'chat:expire-rooms';
    protected $description = '비활성 개인/그룹 채팅방 자동 삭제 (매시간 실행, 트래픽 기반 스윕의 보조 안전망)';

    public function handle(): void
    {
        $lockDays = \App\Support\ChatRules::get('inactive_lock_days', 7);
        $deleteDays = \App\Support\ChatRules::get('lock_delete_days', 3);
        $cutoff = now()->subDays($lockDays + $deleteDays);

        // 공개방 제외, 마지막 활동(없으면 생성일) 기준으로 (잠금일+삭제일)이 지난 방을 완전히 삭제
        $rooms = ChatRoom::whereIn('type', ['dm', 'group'])
            ->where(function ($q) use ($cutoff) {
                $q->where('last_message_at', '<', $cutoff)
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->whereNull('last_message_at')->where('created_at', '<', $cutoff);
                  });
            })
            ->get();

        foreach ($rooms as $room) {
            DB::table('chat_room_bans')->where('chat_room_id', $room->id)->delete();
            $room->delete(); // chat_room_users/chat_messages 는 FK cascadeOnDelete 로 함께 삭제됨
        }

        $this->info("채팅방 삭제 {$rooms->count()}개 처리 완료.");
    }
}
