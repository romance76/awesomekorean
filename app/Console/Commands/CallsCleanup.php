<?php

namespace App\Console\Commands;

use App\Models\Call;
use Illuminate\Console\Command;

/**
 * 끝나지 않고 남은 통화를 정리한다 (브라우저가 닫히거나 신호가 끊겨 "끝났다"는 연락이 못 온 경우).
 *  - 벨이 2분 넘게 울리고 있는 친구 통화 → 부재중(no_answer)
 *  - 응답 후 4시간 넘게 끝나지 않은 통화 → 종료(stale)
 * 안심서비스(elder) 전화는 자체 재시도 로직(elder:call)이 상태를 쓰므로 건드리지 않는다.
 */
class CallsCleanup extends Command
{
    protected $signature = 'calls:cleanup';
    protected $description = '끝나지 않고 남은 통화 기록 정리';

    public function handle(): int
    {
        $missed = 0; $stale = 0;
        Call::where('status', 'ringing')->where('call_type', '!=', 'elder')->where('created_at', '<', now()->subMinutes(2))
            ->each(function (Call $c) use (&$missed) { $c->end('no_answer', null, '자동 정리: 응답 없이 벨이 계속 울림'); $missed++; });
        Call::where('status', 'answered')->where('answered_at', '<', now()->subHours(4))
            ->each(function (Call $c) use (&$stale) { $c->end('stale', null, '자동 정리: 종료 신호 없음'); $stale++; });
        $this->info("calls:cleanup missed={$missed} stale={$stale}");
        return self::SUCCESS;
    }
}
