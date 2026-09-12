<?php

namespace App\Console\Commands;

use App\Models\ElderCheckinLog;
use App\Models\ElderSetting;
use App\Models\Notification;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ElderCheckCommand extends Command
{
    protected $signature   = 'elder:check';
    protected $description = '노인 안심 알림 체크 (매 분 실행)';

    public function handle(): void
    {
        $now = Carbon::now();
        $this->info("Elder check started at {$now}");

        // Get global settings
        $alertDelay  = $this->getGlobalSetting('alert_delay_minutes', 30);
        $secondAlert = $this->getGlobalSetting('second_alert_minutes', 60);

        // Get all active elder users
        $elders = ElderSetting::whereNotNull('guardian_id')
            ->where('checkin_interval', '>', 0)
            ->get();

        $created  = 0;
        $alerted  = 0;
        $alerted2 = 0;

        foreach ($elders as $setting) {
            try {
                $tz = $setting->timezone ?: 'America/New_York';
                $userNow = Carbon::now($tz);
                $userToday = $userNow->toDateString();

                // Parse checkin_time (HH:MM format)
                $checkinTime = $setting->checkin_time ?: '09:00';
                list($hour, $minute) = explode(':', $checkinTime);
                $scheduledTime = Carbon::parse($userToday . ' ' . $checkinTime, $tz);

                // ── Step 1: Create pending record at checkin time ──
                if ($userNow->format('H:i') === $checkinTime) {
                    $log = ElderCheckinLog::firstOrCreate(
                        ['user_id' => $setting->user_id, 'checkin_date' => $userToday],
                        ['status' => 'pending']
                    );
                    if ($log->wasRecentlyCreated) {
                        $created++;
                        $this->line("  Created pending for user_id={$setting->user_id}");
                    }
                }

                // Get today's log
                $todayLog = ElderCheckinLog::where('user_id', $setting->user_id)
                    ->where('checkin_date', $userToday)
                    ->first();

                if (!$todayLog || $todayLog->status !== 'pending') {
                    continue; // Already checked in or no pending log
                }

                $minutesSinceScheduled = $scheduledTime->diffInMinutes($userNow, false);

                // ── Step 2: First alert after alertDelay minutes ──
                if ($minutesSinceScheduled >= $alertDelay && !$todayLog->guardian_notified) {
                    $this->sendGuardianAlert($setting, '1차 미응답 알림', $todayLog);
                    $todayLog->update([
                        'guardian_notified' => true,
                        'alert_sent_at'    => now(),
                    ]);
                    $setting->update(['alert_sent' => true]);
                    $setting->increment('missed_count');
                    $alerted++;
                    $this->line("  1st alert for user_id={$setting->user_id} (missed_count={$setting->missed_count})");
                }

                // ── Step 3: Second alert after secondAlert minutes ──
                if ($minutesSinceScheduled >= $secondAlert && $todayLog->guardian_notified && $todayLog->status === 'pending') {
                    // Check if second alert already sent (by checking alert_sent_at time)
                    if ($todayLog->alert_sent_at && $todayLog->alert_sent_at->diffInMinutes(now()) >= ($secondAlert - $alertDelay - 5)) {
                        $this->sendGuardianAlert($setting, '2차 미응답 알림 (긴급)', $todayLog);
                        $todayLog->update(['status' => 'missed']);
                        $alerted2++;
                        $this->line("  2nd alert for user_id={$setting->user_id}");
                    }
                }
            } catch (\Exception $e) {
                $this->error("Error for user_id={$setting->user_id}: {$e->getMessage()}");
            }
        }

        $this->info("Elder check complete. Created: {$created}, 1st alerts: {$alerted}, 2nd alerts: {$alerted2}");
    }

    private function sendGuardianAlert(ElderSetting $setting, string $alertType, ElderCheckinLog $log): void
    {
        $user = $setting->user;
        $userName = $user ? ($user->nickname ?? $user->name) : "User#{$setting->user_id}";

        // Notify guardian site member
        if ($setting->guardian_user_id) {
            Notification::create([
                'user_id' => $setting->guardian_user_id,
                'type'    => 'elder_checkin_missed',
                'title'   => "노인안심 {$alertType}",
                // Notification::$fillable엔 'content'만 있고 'body'는 없어(실제
                // 컬럼명도 content) 이 필드가 조용히 저장 안 되고 알림 본문이
                // 항상 비어 있던 문제 수정 — 프론트(Notifications.vue)도 n.content를 읽음.
                'content' => "{$userName}님이 체크인에 응답하지 않았습니다. 확인이 필요합니다.",
                'data'    => json_encode([
                    'elder_user_id' => $setting->user_id,
                    'alert_type'    => $alertType,
                    'missed_count'  => $setting->missed_count,
                ]),
                'url'     => '/elder/guardian/' . $setting->user_id,
            ]);

            // 인앱(DB) 알림만 생성되고 푸시는 발송되지 않던 문제 수정 —
            // ElderScheduledCallCommand의 부재중 처리와 동일한 패턴으로 연결.
            $guardian = User::find($setting->guardian_user_id);
            if ($guardian?->fcm_token) {
                app(PushNotificationService::class)->sendToToken(
                    $guardian->fcm_token,
                    "노인안심 {$alertType}",
                    "{$userName}님이 체크인에 응답하지 않았습니다. 확인이 필요합니다.",
                    ['type' => 'elder_checkin_missed', 'elder_user_id' => (string) $setting->user_id]
                );
            }
        }

        // Notify 2nd guardian if exists
        if ($setting->guardian2_name && $setting->guardian_user_id) {
            // 2nd guardian is phone-only, SMS would go here
            // TODO: Twilio SMS to guardian2_phone
        }


        // 소켓 서버에 실시간 알림 전송
        try {
            if ($setting->guardian_user_id) {
                Http::timeout(3)->post('http://127.0.0.1:3001/send-notification', [
                    'userId' => (string) $setting->guardian_user_id,
                    'event'  => 'elder:checkin-missed',
                    'data'   => [
                        'elderId'   => $setting->user_id,
                        'elderName' => $userName,
                        'message'   => "{$userName}님이 체크인에 응답하지 않았습니다",
                        'alertType' => $alertType,
                        'time'      => now()->toISOString(),
                    ],
                ]);
            }
        } catch (\Exception $e) {
            // 소켓 서버 다운 시 무시 (DB 알림은 이미 전송됨)
        }

        // Notify the elder user
        Notification::create([
            'user_id' => $setting->user_id,
            'type'    => 'elder_checkin_missed',
            'title'   => '체크인 알림',
            'content' => '오늘 체크인을 아직 하지 않으셨습니다. 체크인을 해주세요.',
            'data'    => json_encode([]),
            'url'     => '/elder',
        ]);
    }

    private function getGlobalSetting(string $key, $default = null)
    {
        try {
            $value = DB::table('site_settings')
                ->where('group', 'elder')
                ->where('key', $key)
                ->value('value');
            return $value !== null ? (int) $value : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }
}
