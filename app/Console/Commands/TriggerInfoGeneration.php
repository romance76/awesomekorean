<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use Illuminate\Console\Command;

/**
 * '정보' 게시판 자동 생성 요청을 등록만 함 — 실제 글 생성은 이 커맨드가 직접
 * 하지 않고, 별도 세션(Claude 시간당 체크인 루틴)이 이 상태(running)를 보고
 * 수행함. 다른 콘텐츠(뉴스/레시피/중고장터/부동산 등)는 매일 자동 스케줄이
 * 있었지만 '정보'만 빠져 있어서 관리자가 버튼을 직접 누르지 않으면 전혀 새
 * 글이 안 쌓이던 문제가 있었음 — 이 커맨드를 매일 스케줄에 추가해 해결.
 */
class TriggerInfoGeneration extends Command
{
    protected $signature   = 'info:trigger-generation';
    protected $description = "'정보' 게시판 자동 생성 요청 등록 (실제 생성은 시간당 체크인 루틴이 수행)";

    private const SETTING_KEY = 'info_generation_status';

    public function handle(): int
    {
        $raw = SiteSetting::where('key', self::SETTING_KEY)->value('value');
        $status = $raw ? json_decode($raw, true) : null;

        if (($status['status'] ?? null) === 'running') {
            $this->info('이미 생성 진행 중 — 요청 건너뜀');
            return self::SUCCESS;
        }

        SiteSetting::updateOrCreate(['key' => self::SETTING_KEY], [
            'value' => json_encode([
                'status' => 'running', 'completed' => 0, 'target' => 10,
                'requested_by' => '자동 스케줄', 'started_at' => now()->toIso8601String(),
            ]),
            'group' => 'info',
        ]);
        $this->info('생성 요청 등록됨 — 시간당 체크인 루틴이 실제 생성을 수행함(최대 1시간 내 시작)');
        return self::SUCCESS;
    }
}
