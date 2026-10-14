<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

/**
 * 오래된 알림 정리 — 읽은 알림은 60일 뒤, 안 읽은 알림도 180일 뒤에 지운다.
 * (종 모양 알림창에는 최근 몇 개만 보이고, 전체 목록은 /notifications 에서. 회원이 직접 지울 수도 있다.)
 */
class NotificationsPrune extends Command
{
    protected $signature = 'notifications:prune {--read-days=60} {--all-days=180}';
    protected $description = '오래된 알림 지우기(읽은 것 60일, 전체 180일)';

    public function handle(): int
    {
        $read = Notification::whereNotNull('read_at')->where('created_at', '<', now()->subDays((int) $this->option('read-days')))->delete();
        $all = Notification::where('created_at', '<', now()->subDays((int) $this->option('all-days')))->delete();
        $this->info("읽은 알림 {$read}건, 오래된 알림 {$all}건 지웠어요");
        return self::SUCCESS;
    }
}
