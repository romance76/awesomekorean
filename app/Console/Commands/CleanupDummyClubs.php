<?php

namespace App\Console\Commands;

use App\Models\Club;
use Illuminate\Console\Command;

// 동호회 더미/테스트 데이터를 정리한다 (소프트 운영 전환, 1회성 청소 명령).
// 카테고리별로 회원수가 가장 많은(=가장 그럴듯한) 동호회 1개만 남기고
// 나머지(대부분 "test"/"asdfa" 같은 테스트 등록, 회원 1명뿐)를 삭제한다.
// 기본은 미리보기만 하고, --force를 줘야 실제로 삭제한다.
class CleanupDummyClubs extends Command
{
    protected $signature = 'clubs:cleanup-dummy {--force : 실제로 삭제 실행 (기본은 미리보기만)}';
    protected $description = '카테고리별로 회원수가 가장 많은 동호회 1개만 남기고 나머지 삭제';

    public function handle(): int
    {
        $clubs = Club::orderByDesc('member_count')->orderBy('id')
            ->get(['id', 'name', 'category', 'member_count']);

        $keepIds = $clubs->groupBy('category')->map(fn ($group) => $group->first()->id)->values();
        $toDelete = $clubs->whereNotIn('id', $keepIds);

        if ($toDelete->isEmpty()) {
            $this->info('카테고리별로 이미 1개씩만 남아있습니다.');
            return self::SUCCESS;
        }

        $this->info("삭제 대상 {$toDelete->count()}건 (카테고리별 1개씩만 남김):");
        foreach ($toDelete as $c) {
            $this->line("  #{$c->id} {$c->name} ({$c->category}, 회원 {$c->member_count}명)");
        }

        if (!$this->option('force')) {
            $this->warn('미리보기만 했습니다. 실제로 삭제하려면 --force 옵션을 붙여 다시 실행하세요.');
            return self::SUCCESS;
        }

        $deleted = Club::whereIn('id', $toDelete->pluck('id'))->delete();
        $this->info("삭제 완료: {$deleted}건");

        return self::SUCCESS;
    }
}
