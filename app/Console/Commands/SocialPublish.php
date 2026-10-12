<?php

namespace App\Console\Commands;

use App\Models\SocialPost;
use App\Support\EmergencySwitches;
use App\Support\SocialPublisher;
use App\Support\SocialQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * SNS 예약 게시 — 5분마다 돌고, 설정한 시간대(애틀랜타, 기본 08:00·12:00·19:00)가 되면 한 번만 게시한다.
 * 하루 개수(기본 인스타 2·스레드 3)를 남은 시간대에 나눠서 올림. 승인된 글만(설정에서 승인 끄면 대기 글도).
 * 멈추기: 설정 "자동 게시" 끄기, 계정별 "자동 게시" 체크 해제, 비상 스위치 social_autopost.
 */
class SocialPublish extends Command
{
    protected $signature = 'social:publish {--now : 시간대와 상관없이 지금 한 번 실행}';
    protected $description = 'SNS(인스타그램·스레드) 대기열에서 예약 시간에 맞춰 게시';

    public function handle(): int
    {
        $s = SocialQueue::settings();
        if (!$s['enabled'] || EmergencySwitches::on('social_autopost')) { $this->line('자동 게시 꺼짐'); return self::SUCCESS; }

        $now = now('America/New_York');
        $times = collect($s['times'])->filter(fn ($t) => preg_match('/^\d{2}:\d{2}$/', $t))->sort()->values();
        $slot = null;
        foreach ($times as $t) {
            $at = $now->copy()->setTimeFromTimeString($t);
            if ($now->gte($at) && $now->lt($at->copy()->addMinutes(10))) { $slot = $t; break; }
        }
        if (!$this->option('now')) {
            if (!$slot) return self::SUCCESS;
            $key = 'social_slot_done:' . $now->toDateString() . ':' . $slot;
            if (!Cache::add($key, 1, 86400)) return self::SUCCESS;   // 이 시간대는 이미 실행함
        }
        $slotsLeft = max(1, $times->filter(fn ($t) => $t >= ($slot ?? $now->format('H:i')))->count());

        foreach (SocialPost::PLATFORMS as $pf) {
            $acc = SocialPublisher::account($pf);
            if (!$acc || !$acc->autopost_enabled) { $this->line("{$pf}: 계정 꺼짐/자동 게시 꺼짐"); continue; }
            $daily = (int) ($s[$pf . '_daily'] ?? 0);
            $done = SocialPost::where('platform', $pf)->where('status', 'posted')
                ->where('posted_at', '>=', $now->copy()->startOfDay()->utc())->count();
            $remain = $daily - $done;
            if ($remain <= 0) { $this->line("{$pf}: 오늘 개수 다 채움"); continue; }
            $quota = (int) ceil($remain / $slotsLeft);

            $statuses = $s['require_approval'] ? ['approved'] : ['approved', 'pending'];
            $items = SocialPost::where('platform', $pf)->whereIn('status', $statuses)
                ->where(fn ($q) => $q->whereNull('not_before')->orWhere('not_before', '<=', now()))
                ->orderByDesc('priority')->orderBy('id')->limit($quota)->get();
            foreach ($items as $p) {
                $ok = SocialPublisher::publish($p);
                $this->line("{$pf} #{$p->id}: " . ($ok ? '게시됨 ' . $p->external_url : '실패 ' . $p->error));
            }
        }
        return self::SUCCESS;
    }
}
