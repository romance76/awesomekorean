<?php

namespace App\Console\Commands;

use App\Models\ChatRoom;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireChatRooms extends Command
{
    protected $signature = 'chat:expire-rooms';
    protected $description = '비활성 개인/그룹 채팅방 자동 잠금 및 삭제 (매시간 실행)';

    public function handle(): void
    {
        $lockDays = \App\Support\ChatRules::get('inactive_lock_days', 7);
        $deleteDays = \App\Support\ChatRules::get('lock_delete_days', 3);

        // 1) 잠금 처리: 공개방 제외, 아직 안 잠긴 방 중 마지막 메시지가 기준일 이전인 방
        // (메시지가 한 번도 없던 방은 last_message_at 이 null 이므로 created_at 기준으로 판단)
        $lockCutoff = now()->subDays($lockDays);
        $lockedCount = ChatRoom::whereIn('type', ['dm', 'group'])
            ->whereNull('locked_at')
            ->where(function ($q) use ($lockCutoff) {
                $q->where('last_message_at', '<', $lockCutoff)
                  ->orWhere(function ($q2) use ($lockCutoff) {
                      $q2->whereNull('last_message_at')->where('created_at', '<', $lockCutoff);
                  });
            })
            ->update(['locked_at' => now()]);

        // 2) 삭제 처리: 잠긴 지 기준일이 지난 방은 완전히 삭제
        $deleteCount = 0;
        $rooms = ChatRoom::whereIn('type', ['dm', 'group'])
            ->whereNotNull('locked_at')
            ->where('locked_at', '<', now()->subDays($deleteDays))
            ->get();

        foreach ($rooms as $room) {
            DB::table('chat_room_bans')->where('chat_room_id', $room->id)->delete();
            // chat_room_users.chat_room_id FK 는 cascadeOnDelete 이므로 별도 삭제 불필요
            $room->delete(); // chat_messages 는 FK cascadeOnDelete 로 함께 삭제됨
            $deleteCount++;
        }

        $this->info("채팅방 잠금 {$lockedCount}개, 삭제 {$deleteCount}개 처리 완료.");
    }
}
