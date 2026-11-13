<?php

namespace App\Console\Commands;

use App\Support\AutoSeedPosts;
use Illuminate\Console\Command;

/**
 * 커뮤니티·Q&A 자동 게시: 오늘 올릴 차례가 된 만큼, 미리 써 둔 글 모음(database/data/auto_seed_pool.json)에서
 * 무작위로 꺼내 가상 회원 이름으로 올린다. 켜짐 여부·회원 수 상한·하루 개수·시각은 App\Support\AutoSeedPosts 가 지킨다.
 * 서버 스케줄러가 10분마다 호출 — 하루 목표(5~7개)와 올릴 시각은 그날 처음 호출될 때 무작위로 정해진다.
 */
class AutoSeedPost extends Command
{
    protected $signature   = 'autoseed:post';
    protected $description = '커뮤니티·Q&A 자동 게시 (미리 써 둔 글을 무작위 시각·가상 회원 이름으로)';

    public function handle(): int
    {
        if ($why = AutoSeedPosts::blockReason()) {
            $this->info("자동 게시 멈춤: {$why}");
            return self::SUCCESS;
        }
        $state = AutoSeedPosts::state();
        foreach (AutoSeedPosts::KINDS as $kind) {
            $n = AutoSeedPosts::due($state, $kind);
            for ($i = 0; $i < $n; $i++) {
                $id = AutoSeedPosts::postNext($kind);
                if ($id === null) {
                    $this->warn("{$kind}: 올릴 글이 모자라요 (글 모음을 더 채워 주세요)");
                    break;
                }
                $this->info("{$kind} 글 #{$id} 올림");
            }
        }
        return self::SUCCESS;
    }
}
