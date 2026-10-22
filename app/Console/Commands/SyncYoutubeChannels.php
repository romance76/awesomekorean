<?php

namespace App\Console\Commands;

use App\Models\YoutubeChannel;
use App\Support\YoutubeSync;
use Illuminate\Console\Command;

class SyncYoutubeChannels extends Command
{
    protected $signature = 'youtubers:sync {--limit=100 : 한 번에 갱신할 채널 수(오래 안 갱신한 순)}';
    protected $description = '한인 유튜버(승인된 채널)의 정보와 최근 영상·쇼츠를 유튜브에서 새로 읽어 온다';

    public function handle(): int
    {
        $ok = 0;
        $fail = 0;
        YoutubeChannel::where('status', 'approved')->orderByRaw('last_synced_at is not null')->orderBy('last_synced_at')->limit((int) $this->option('limit'))->get()
            ->each(function (YoutubeChannel $ch) use (&$ok, &$fail) {
                try { YoutubeSync::sync($ch) ? $ok++ : $fail++; } catch (\Throwable $e) { $fail++; $this->warn($ch->title . ': ' . $e->getMessage()); }
            });
        $this->info("갱신 {$ok}곳, 실패 {$fail}곳");
        return self::SUCCESS;
    }
}
