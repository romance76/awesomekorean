<?php

namespace App\Console\Commands;

use App\Support\SocialQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * SNS용으로 새로 만든 글(database/data/social_pieces.json)을 대기열에 넣는다. 같은 id 는 한 번만.
 *   php artisan social:seed --launch            승인 전 MONEY 글 대기열 삭제 + 새 MONEY 글 자동 등록 끄기 + 새 글 넣기
 *   php artisan social:seed --autopost=on|off   인스타·스레드 계정의 "자동 게시" 켜기/끄기
 *   php artisan social:seed --status=approved   새 글만 넣기(기본 approved)
 */
class SocialSeed extends Command
{
    protected $signature = 'social:seed {--file=database/data/social_pieces.json} {--status=approved : approved 또는 pending} {--only= : 이 id 들만(쉼표)} {--launch : 승인 전 MONEY 글 대기열 삭제 + 새 MONEY 글 자동 등록 끄기 + 새 글 넣기} {--autopost= : on 또는 off — 계정의 자동 게시 켜기/끄기}';
    protected $description = 'SNS 글(JSON)을 게시 대기열에 넣기';

    public function handle(): int
    {
        if ($this->option('autopost')) {
            $on = $this->option('autopost') === 'on';
            $n = DB::table('social_accounts')->whereIn('platform', ['instagram', 'threads'])->update(['autopost_enabled' => $on ? 1 : 0]);
            $this->info('자동 게시 ' . ($on ? '켬' : '끔') . ": {$n}개 계정");
            return self::SUCCESS;
        }
        if ($this->option('launch')) {
            $del = DB::table('social_posts')->where('source_type', 'money_post')->where('status', 'pending')->delete();
            SocialQueue::saveSettings(['auto_money' => false]);
            $this->info("승인 전 MONEY 글 대기열 {$del}건 삭제, 새 MONEY 글 자동 등록 끔");
        }
        $pieces = json_decode((string) file_get_contents(base_path($this->option('file'))), true);
        if (!is_array($pieces)) { $this->error('파일을 읽지 못했어요'); return self::FAILURE; }
        $only = array_filter(array_map('intval', explode(',', (string) $this->option('only'))));
        $n = 0;
        foreach ($pieces as $p) {
            if ($only && !in_array((int) $p['id'], $only, true)) continue;
            try { $c = SocialQueue::enqueuePiece($p, $this->option('status')); $n += $c; $this->line("#{$p['id']} {$p['title']} → {$c}건"); }
            catch (\Throwable $e) { $this->error("#{$p['id']} 실패: " . $e->getMessage()); }
        }
        $this->info("대기열에 {$n}건 넣었어요");
        return self::SUCCESS;
    }
}
