<?php

namespace App\Console\Commands;

use App\Events\NewNotification;
use App\Models\Notification;
use App\Models\Sweepstakes;
use App\Models\SweepstakesPrizeClaim;
use App\Models\User;
use App\Support\SweepstakesPrizeClaimService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class NotifyPrizeWinners extends Command
{
    protected $signature = 'sweepstakes:notify-winners';
    protected $description = '추첨이 끝난 당첨자에게 연락처 확인 안내 알림을 보내고 상품 수령 건을 동기화';

    public function handle(): int
    {
        if (!Schema::hasTable('sweepstakes_prize_claims')) {
            return self::SUCCESS;
        }

        $sent = 0;
        // 최근 60일 내 종료된 추첨만 대상 (이미 모두 알림 완료된 추첨도 syncFor 는 idempotent)
        Sweepstakes::where('status', 'winner_selected')
            ->where(function ($q) {
                $q->whereNull('winner_selected_at')->orWhere('winner_selected_at', '>=', now()->subDays(60));
            })
            ->orderBy('id')
            ->chunkById(100, function ($list) use (&$sent) {
                foreach ($list as $s) {
                    $claims = SweepstakesPrizeClaimService::syncFor($s);
                    foreach ($claims as $claim) {
                        if ($claim->notified_at) {
                            continue;
                        }
                        try {
                            if (!$this->alreadyNotified($claim->user_id, $s->id)) {
                                $this->notify($claim, $s);
                                $sent++;
                            }
                        } catch (\Throwable $e) {
                            report($e);
                            continue; // notified_at 을 채우지 않아 다음 분에 재시도
                        }
                        $claim->forceFill(['notified_at' => now()])->save();
                    }
                }
            });

        if ($sent) {
            $this->info("당첨 알림 {$sent}건 발송");
        }
        return self::SUCCESS;
    }

    /** 다른 로직(추첨 확정 시점)이 이미 만든 당첨 알림이 있으면 중복 생성하지 않는다 */
    private function alreadyNotified(int $userId, int $sweepstakesId): bool
    {
        $rows = Notification::where('user_id', $userId)
            ->whereIn('type', ['sweepstakes_win', 'sweepstakes_winner'])
            ->orderByDesc('id')->limit(200)->get(['id', 'data']);
        foreach ($rows as $n) {
            if ((int) ($n->data['sweepstakes_id'] ?? 0) === $sweepstakesId) {
                return true;
            }
        }
        return false;
    }

    private function notify(SweepstakesPrizeClaim $claim, Sweepstakes $s): void
    {
        $title = '🎉 당첨되었어요!';
        $prize = $claim->prize_label ?: (string) $s->prize_name;
        $content = "{$claim->rank}등 · {$prize}에 당첨되셨어요. 상품 발송을 위해 이메일·전화번호·주소를 정확히 확인해 주세요.";
        $eventId = $s->event_id;

        Notification::create([
            'user_id' => $claim->user_id,
            'type' => 'sweepstakes_win',
            'title' => $title,
            'content' => $content,
            'data' => [
                'sweepstakes_id' => $s->id,
                'event_id' => $eventId,
                'rank' => $claim->rank,
                'url' => '/events?open=' . $eventId,
            ],
        ]);

        $unread = Notification::where('user_id', $claim->user_id)->whereNull('read_at')->count();
        broadcast(new NewNotification($claim->user_id, $unread, $title));

        $user = User::find($claim->user_id);
        if ($user?->fcm_token) {
            try {
                app(\App\Services\PushNotificationService::class)->sendToToken(
                    $user->fcm_token,
                    $title,
                    $content,
                    ['type' => 'sweepstakes_win', 'sweepstakes_id' => (string) $s->id, 'url' => '/events?open=' . $eventId]
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
