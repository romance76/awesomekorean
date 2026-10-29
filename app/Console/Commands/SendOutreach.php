<?php

namespace App\Console\Commands;

use App\Support\Outreach;
use Illuminate\Console\Command;

/**
 * 손님 메일(소개 메일) 발송기 — 스케줄러가 매분 호출한다.
 * "실제 발송"이 열려 있을 때만 일하고, 하루 한도 안에서 한 번에 조금씩만 보낸다(기본 10통).
 */
class SendOutreach extends Command
{
    protected $signature = 'mail:outreach {--limit=10 : 한 번 실행에 보낼 최대 통수}';
    protected $description = '손님 메일(소개 메일) 대기열 발송 — 우편 주소와 실제 발송 스위치가 없으면 아무것도 보내지 않음';

    public function handle(): int
    {
        if ($why = Outreach::lockReason()) { $this->info('잠김 — ' . $why); return self::SUCCESS; }
        [$sent, $failed, $skipped] = Outreach::runBatch(max(1, (int) $this->option('limit')));
        if ($sent || $failed || $skipped) $this->info("보냄 {$sent}통, 실패 {$failed}통, 건너뜀(수신 거부) {$skipped}통");
        return self::SUCCESS;
    }
}
