<?php

namespace App\Console\Commands;

use App\Events\NewNotification;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 당첨 후 N일(기본 3일)이 지나도록 상품을 안 보낸 건이 있으면 최고관리자에게 하루 한 번 알린다.
 * 같은 날 이미 알린 관리자에게는 다시 보내지 않는다(여러 번 돌려도 안전).
 */
class RemindOverduePrizeDeliveries extends Command
{
    protected $signature = 'sweepstakes:remind-overdue-deliveries {--days=3}';
    protected $description = '오래 안 보낸 경품을 최고관리자에게 하루 한 번 알림';

    public function handle(): int
    {
        if (!Schema::hasTable('sweepstakes_prize_claims')) return self::SUCCESS;
        $days = max(1, (int) $this->option('days'));

        $overdue = DB::table('sweepstakes_prize_claims as c')
            ->join('sweepstakes as s', 's.id', '=', 'c.sweepstakes_id')
            ->where('s.status', 'winner_selected')
            ->where('s.winner_selected_at', '<=', now()->subDays($days))
            ->where(fn ($q) => $q->whereNull('c.delivery_status')->orWhere('c.delivery_status', 'pending'))
            ->count();
        if ($overdue === 0) {
            $this->info('오래 안 보낸 경품 없음');
            return self::SUCCESS;
        }

        $title = "🎁 {$days}일 넘게 안 보낸 경품이 {$overdue}건 있어요";
        $todayStart = now('America/New_York')->startOfDay()->utc();
        $n = 0;
        foreach (User::where('role', 'super_admin')->pluck('id') as $adminId) {
            $already = Notification::where('user_id', $adminId)->where('type', 'prize_delivery_overdue')->where('created_at', '>=', $todayStart)->exists();
            if ($already) continue;
            Notification::create([
                'user_id' => $adminId, 'type' => 'prize_delivery_overdue', 'title' => $title,
                'content' => '관리자 › 경품 추첨 › "안 보낸 상품" 탭에서 모아 보고 보낼 수 있어요.',
                'data' => ['url' => '/admin/sweepstakes?tab=pending', 'count' => $overdue],
            ]);
            try {
                $unread = Notification::where('user_id', $adminId)->whereNull('read_at')->count();
                broadcast(new NewNotification($adminId, $unread, $title));
            } catch (\Throwable $e) {
                report($e);
            }
            $n++;
        }
        $this->info("알림 {$n}명 (대상 {$overdue}건)");
        return self::SUCCESS;
    }
}
