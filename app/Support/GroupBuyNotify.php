<?php

namespace App\Support;

use App\Events\NewNotification;
use App\Models\Notification;

/** 공동구매 알림 한 줄 보내기 — 종 모양 알림 + 실시간 방송. 실패해도 본 작업은 계속된다. */
class GroupBuyNotify
{
    public static function send(int $userId, string $type, string $title, string $content, array $data = []): void
    {
        try {
            Notification::create(['user_id' => $userId, 'type' => $type, 'title' => $title, 'content' => mb_substr($content, 0, 500), 'data' => $data]);
            $unread = Notification::where('user_id', $userId)->whereNull('read_at')->count();
            try { broadcast(new NewNotification($userId, $unread, $title))->toOthers(); } catch (\Throwable $e) {}
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** 이메일도 같이 보낸다(온라인 쿠폰 확인 요청 등). 메일 설정이 없거나 실패해도 조용히 넘어간다. */
    public static function mail(?string $to, string $subject, string $body): void
    {
        if (!$to) return;
        try {
            \Illuminate\Support\Facades\Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));
        } catch (\Throwable $e) {
            \Log::warning('공동구매 메일 발송 실패: ' . $e->getMessage());
        }
    }
}
