<?php

use App\Models\Post;
use App\Support\AutoSeedPosts;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 커뮤니티 게시판에 처음 채울 글 30개 (2026-11-11 사용자 요청): 미국 사는 한국 사람이 관심 가질 만한 주제를
// 가상 회원 이름으로, 2026-11-01 ~ 지금 사이 무작위 시각에 올린다. 게시판은 이름으로 찾고, 같은 제목이 있으면 건너뛰어 여러 번 돌려도 안전하다.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('posts') || !Schema::hasTable('boards') || !Schema::hasTable('users')) return;
        $items = json_decode((string) file_get_contents(database_path('data/seed_community_nov2026.json')), true);
        if (!is_array($items)) return;

        $boards = DB::table('boards')->pluck('id', 'name');
        $pool = AutoSeedPosts::authorPool();
        if (!$pool) return;

        $from = Carbon::create(2026, 11, 1, 8, 0, 0, AutoSeedPosts::TZ);
        $to = Carbon::now(AutoSeedPosts::TZ);
        if ($to->lte($from)) $to = $from->copy()->addDay();
        $span = max(1, $to->timestamp - $from->timestamp);

        $recent = [];
        foreach ($items as $it) {
            $boardId = $boards[$it['board']] ?? null;
            if (!$boardId) continue;
            if (DB::table('posts')->where('title', $it['title'])->exists()) continue;

            $uid = AutoSeedPosts::pickAuthor($recent, $pool);
            $recent[] = $uid;
            $recent = array_slice($recent, -8);
            $at = Carbon::createFromTimestampUTC($from->timestamp + random_int(0, $span - 1))->setTimezone(config('app.timezone'));

            $post = new Post();
            $post->forceFill([
                'board_id' => $boardId, 'user_id' => $uid, 'title' => $it['title'], 'content' => $it['content'],
                'view_count' => random_int(15, 140), 'created_at' => $at, 'updated_at' => $at,
            ])->save();
        }
    }

    public function down(): void
    {
        // 되돌리지 않는다.
    }
};
