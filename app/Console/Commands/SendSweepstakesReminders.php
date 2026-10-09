<?php

namespace App\Console\Commands;

use App\Events\NewNotification;
use App\Models\Notification;
use App\Models\SweepstakesReminder;
use Illuminate\Console\Command;

class SendSweepstakesReminders extends Command
{
    protected $signature = 'sweepstakes:send-reminders';
    protected $description = '추첨 시작 5분 전 알림(인앱 + 푸시) 발송';

    public function handle(): int
    {
        $sent = 0;

        $reminders = SweepstakesReminder::with(['sweepstakes', 'user'])
            ->where('remind_at', '<=', now())
            ->whereNull('notified_at')
            ->whereNull('dismissed_at')
            ->limit(500)
            ->get();

        foreach ($reminders as $r) {
            $s = $r->sweepstakes;
            if (!$s || $s->status !== 'active' || !$s->end_at || !$s->end_at->isFuture()) {
                // 이미 마감/종료 — 더 이상 보낼 필요 없음
                $r->update(['notified_at' => now()]);
                continue;
            }

            try {
                Notification::create([
                    'user_id' => $r->user_id,
                    'type' => 'sweepstakes_reminder',
                    'title' => '⏰ 곧 추첨이 시작돼요',
                    'content' => ($s->prize_name ?: $s->title) . ' 추첨이 5분 뒤 시작됩니다',
                    'data' => ['sweepstakes_id' => $s->id, 'event_id' => $s->event_id, 'url' => '/events/' . $s->event_id],
                ]);
                $r->update(['notified_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                $this->error("알림 생성 실패 reminder#{$r->id}: " . $e->getMessage());
                continue;
            }

            try {
                $unread = Notification::where('user_id', $r->user_id)->whereNull('read_at')->count();
                broadcast(new NewNotification($r->user_id, $unread, '⏰ 곧 추첨이 시작돼요'));
            } catch (\Throwable $e) {
                // 실시간 전송 실패는 무시 (인앱 알림은 이미 저장됨)
            }

            try {
                if ($r->user?->fcm_token) {
                    app(\App\Services\PushNotificationService::class)->sendToToken(
                        $r->user->fcm_token,
                        '⏰ 곧 추첨이 시작돼요',
                        ($s->prize_name ?: $s->title) . ' 추첨이 5분 뒤 시작됩니다',
                        ['type' => 'sweepstakes_reminder', 'event_id' => (string) $s->event_id, 'url' => '/events/' . $s->event_id]
                    );
                }
            } catch (\Throwable $e) {
                // 푸시 실패는 무시
            }
        }

        $this->info("추첨 알림 {$sent}건 발송");
        return self::SUCCESS;
    }
}
