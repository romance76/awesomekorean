<?php

namespace App\Console\Commands;

use App\Support\SocialQueue;
use Illuminate\Console\Command;

/**
 * SNS용으로 새로 만든 글(database/data/social_pieces.json)을 대기열에 넣는다. 같은 id 는 한 번만.
 * 예: php artisan social:seed --status=approved
 */
class SocialSeed extends Command
{
    protected $signature = 'social:seed {--file=database/data/social_pieces.json} {--status=approved : approved 또는 pending} {--only= : 이 id 들만(쉼표)}';
    protected $description = 'SNS 글(JSON)을 게시 대기열에 넣기';

    public function handle(): int
    {
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
