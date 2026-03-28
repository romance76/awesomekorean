<?php

namespace App\Console\Commands;

use App\Models\ElderSetting;
use Illuminate\Console\Command;

class ElderCheckCommand extends Command
{
    protected $signature   = 'elder:check';
    protected $description = '노인 안심 알림 체크 (매 시간 실행)';

    public function handle(): void
    {
        $overdue = ElderSetting::where('elder_mode', true)
            ->where('alert_sent', false)
            ->whereNotNull('guardian_phone')
            ->get()
            ->filter(function ($setting) {
                if (!$setting->last_checkin_at) {
                    return true;
                }
                return $setting->last_checkin_at->diffInHours(now()) >= $setting->checkin_interval;
            });

        foreach ($overdue as $setting) {
            // TODO: Twilio SMS
            // $this->sendSms($setting->guardian_phone, "안심 알림: {$setting->user->name}님이 {$setting->checkin_interval}시간 이상 체크인하지 않았습니다.");

            $setting->update(['alert_sent' => true]);
            $this->info("Alert flagged for user_id={$setting->user_id}");
        }

        $this->info("Elder check complete. Flagged: {$overdue->count()}");
    }
}
