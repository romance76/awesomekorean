<?php

namespace App\Console\Commands;

use App\Events\CallInitiated;
use App\Models\Call;
use App\Models\Notification;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ElderScheduledCallCommand extends Command
{
    protected $signature   = 'elder:call';
    protected $description = '안심서비스 스케줄 전화 발신 (매 분 실행)';

    public function handle(): void
    {
        $now = Carbon::now();
        $currentTime = $now->format('H:i');
        $currentDay = strtolower($now->format('D')); // mon, tue, wed...
        $dayMap = ['mon'=>'mon','tue'=>'tue','wed'=>'wed','thu'=>'thu','fri'=>'fri','sat'=>'sat','sun'=>'sun'];

        $this->info("[ElderCall] Checking at {$currentTime} ({$currentDay})");

        // 모든 활성 스케줄 조회
        $schedules = DB::table('elder_schedules')
            ->where('is_active', true)
            ->get();

        $called = 0;
        $retried = 0;

        foreach ($schedules as $schedule) {
            try {
                $guardian = DB::table('elder_guardians')->find($schedule->elder_guardian_id);
                if (!$guardian || $guardian->status !== 'active') continue;

                $wardUser = User::find($guardian->ward_user_id);
                $guardianUser = User::find($guardian->guardian_user_id);
                if (!$wardUser) continue;

                $days = json_decode($schedule->days, true) ?: [];
                $scheduledTimes = json_decode($schedule->scheduled_times, true) ?: [];

                // 오늘이 스케줄 요일인지 확인
                if (!in_array($currentDay, $days)) continue;

                if ($schedule->type === 'scheduled') {
                    // 스케줄 전화: 지정 시간에 전화
                    foreach ($scheduledTimes as $time) {
                        if ($currentTime !== $time) continue;

                        // 이미 이 시간에 전화했는지 확인
                        $alreadyCalled = Call::where('callee_id', $wardUser->id)
                            ->where('call_type', 'elder')
                            ->where('created_at', '>=', $now->copy()->startOfDay())
                            ->whereRaw("TIME(created_at) BETWEEN ? AND ?", [
                                $time . ':00',
                                Carbon::parse($time)->addMinutes(1)->format('H:i:s')
                            ])
                            ->exists();

                        if ($alreadyCalled) continue;

                        $this->makeElderCall($wardUser, $guardianUser, $guardian->id);
                        $called++;
                    }
                } elseif ($schedule->type === 'random') {
                    // 랜덤 전화: 시작~종료 사이 랜덤 시간
                    $timeStart = $schedule->time_start ?: '09:00';
                    $timeEnd = $schedule->time_end ?: '18:00';
                    $callsPerDay = $schedule->calls_per_day ?: 1;

                    // 오늘 이미 전화한 횟수
                    $todayCalls = Call::where('callee_id', $wardUser->id)
                        ->where('call_type', 'elder')
                        ->where('created_at', '>=', $now->copy()->startOfDay())
                        ->count();

                    if ($todayCalls >= $callsPerDay) continue;

                    // 시간 범위 내인지 확인
                    if ($currentTime < $timeStart || $currentTime > $timeEnd) continue;

                    // 랜덤 확률: 남은 시간 내에 남은 전화를 고르게 분배
                    $remainingMinutes = Carbon::parse($timeEnd)->diffInMinutes(Carbon::parse($currentTime));
                    $remainingCalls = $callsPerDay - $todayCalls;
                    $probability = $remainingCalls / max(1, $remainingMinutes);

                    if (mt_rand(1, 100) <= ($probability * 100)) {
                        $this->makeElderCall($wardUser, $guardianUser, $guardian->id);
                        $called++;
                    }
                }

                // ── 미응답 재시도: 15분 이내 최대 3회 ──
                $pendingCalls = Call::where('callee_id', $wardUser->id)
                    ->where('call_type', 'elder')
                    ->where('status', 'ringing')
                    ->where('created_at', '>=', $now->copy()->subMinutes(15))
                    ->get();

                foreach ($pendingCalls as $pendingCall) {
                    $attempts = Call::where('callee_id', $wardUser->id)
                        ->where('call_type', 'elder')
                        ->where('created_at', '>=', $pendingCall->created_at)
                        ->count();

                    $minutesSince = $pendingCall->created_at->diffInMinutes($now);

                    // 5분 간격, 최대 3회
                    if ($attempts < 3 && $minutesSince >= ($attempts * 5)) {
                        $this->makeElderCall($wardUser, $guardianUser, $guardian->id);
                        $retried++;
                    }

                    // 3회 미응답 → 보호자에게 알림
                    if ($attempts >= 3 && $minutesSince >= 15) {
                        $pendingCall->update(['status' => 'missed']);

                        if ($guardianUser) {
                            Notification::create([
                                'user_id' => $guardianUser->id,
                                'type' => 'elder_call_missed',
                                'title' => '🚨 안심서비스 미응답',
                                'content' => "{$wardUser->name}님이 안심 전화에 3회 응답하지 않았습니다. 확인이 필요합니다.",
                                'data' => json_encode(['ward_id' => $wardUser->id]),
                            ]);

                            // FCM 푸시
                            if ($guardianUser->fcm_token) {
                                try {
                                    app(PushNotificationService::class)->sendToToken(
                                        $guardianUser->fcm_token,
                                        '🚨 안심서비스 미응답',
                                        "{$wardUser->name}님이 전화에 응답하지 않습니다",
                                        ['type' => 'elder_missed', 'ward_id' => $wardUser->id]
                                    );
                                } catch (\Exception $e) {}
                            }
                        }
                    }
                }

            } catch (\Exception $e) {
                $this->error("Error: {$e->getMessage()}");
            }
        }

        $this->info("[ElderCall] Done. Called: {$called}, Retried: {$retried}");
    }

    private function makeElderCall(User $ward, ?User $guardian, int $guardianRelId): void
    {
        $roomId = 'elder-' . uniqid('', true);

        // Call 레코드 생성 (caller = 시스템/보호자, callee = 보호대상)
        $call = Call::create([
            'room_id'   => $roomId,
            'caller_id' => $guardian?->id ?? 0,
            'callee_id' => $ward->id,
            'call_type' => 'elder',
            'status'    => 'ringing',
        ]);

        $this->line("  📞 Elder call to {$ward->name} (ID:{$ward->id}), call_id={$call->id}");

        // Echo 브로드캐스트 → 사이트 접속 중이면 전화벨
        try {
            broadcast(new CallInitiated($call));
        } catch (\Exception $e) {
            $this->warn("  Broadcast failed: {$e->getMessage()}");
        }

        // FCM 푸시 → 백그라운드에서도 알림
        if ($ward->fcm_token) {
            try {
                app(PushNotificationService::class)->sendIncomingCall(
                    fcmToken:     $ward->fcm_token,
                    callId:       $call->id,
                    roomId:       $roomId,
                    callerId:     $guardian?->id ?? 0,
                    callerName:   '안심서비스',
                    callerAvatar: '',
                );
            } catch (\Exception $e) {
                $this->warn("  FCM failed: {$e->getMessage()}");
            }
        }
    }
}
