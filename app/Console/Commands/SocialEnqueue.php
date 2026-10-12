<?php

namespace App\Console\Commands;

use App\Support\SocialPublisher;
use App\Support\SocialQueue;
use Illuminate\Console\Command;

// 새 MONEY 운영팀 글·어썸코리안 이벤트를 SNS 대기열에 넣음(매시간). --refresh 면 토큰 연장도(매주).
class SocialEnqueue extends Command
{
    protected $signature = 'social:enqueue {--refresh : 인스타·스레드 토큰 연장} {--force-refresh : 기간 상관없이 연장}';
    protected $description = 'SNS 대기열 채우기(새 MONEY 글·이벤트) / 토큰 연장';

    public function handle(): int
    {
        if ($this->option('refresh') || $this->option('force-refresh')) {
            foreach (SocialPublisher::refreshTokens((bool) $this->option('force-refresh')) as $pf => $msg) $this->line("{$pf}: {$msg}");
            return self::SUCCESS;
        }
        $r = SocialQueue::enqueueNew();
        $this->line("MONEY {$r['money']}개, 이벤트 {$r['events']}개 대기열에 추가");
        return self::SUCCESS;
    }
}
