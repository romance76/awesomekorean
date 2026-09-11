<?php

namespace App\Console\Commands;

use App\Events\NewNotification;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Notification;
use Illuminate\Console\Command;

class SendEventReminders extends Command
{
    protected $signature = 'events:remind';
    protected $description = '내일 시작하는 이벤트의 참석자에게 리마인더 발송 (30분마다 실행) — 이전엔 RSVP 리마인더 자체가 없었음';

    public function handle(): void
    {
        $eventIds = Event::where('is_active', true)
            ->whereBetween('start_date', [now(), now()->addDay()])
            ->pluck('id');

        if ($eventIds->isEmpty()) return;

        $attendees = EventAttendee::with('event:id,title,start_date,venue')
            ->whereIn('event_id', $eventIds)
            ->where('status', 'going')
            ->whereNull('reminder_sent_at')
            ->get();

        $count = 0;
        foreach ($attendees as $a) {
            if (!$a->event) continue;
            try {
                Notification::create([
                    'user_id' => $a->user_id,
                    'type' => 'event_reminder',
                    'title' => '내일 참석 예정인 이벤트가 있습니다',
                    'content' => "'{$a->event->title}'" . ($a->event->venue ? " ({$a->event->venue})" : '') . "이(가) 곧 시작됩니다.",
                    'data' => ['event_id' => $a->event_id],
                ]);
                $unread = Notification::where('user_id', $a->user_id)->whereNull('read_at')->count();
                broadcast(new NewNotification($a->user_id, $unread, '내일 참석 예정인 이벤트가 있습니다'));
                $a->update(['reminder_sent_at' => now()]);
                $count++;
            } catch (\Exception $e) {
                $this->error("Attendee #{$a->id} 리마인더 발송 실패: {$e->getMessage()}");
            }
        }

        if ($count > 0) {
            $this->info("이벤트 리마인더 {$count}건 발송 완료.");
        }
    }
}
