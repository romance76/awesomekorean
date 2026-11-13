<?php

use App\Support\AutoSeedPosts;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 자동 게시 첫 글들이 애틀랜타 시각 그대로(서버는 UTC) 저장돼 5시간 이르게 기록된 것을 바로잡는다 (2026-11-13).
// 11월 13일 이후에 올라간, 글 모음(auto_seed_pool.json)에 있는 제목의 글과 그 답변만 대상. 한 번만 돈다.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('posts') || !Schema::hasTable('qa_posts')) return;
        $pool = AutoSeedPosts::pool();
        $since = '2026-11-13 00:00:00';
        $shift = function (?string $ts): ?string {
            if (!$ts) return $ts;
            return Carbon::parse($ts, AutoSeedPosts::TZ)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        };

        $titles = array_column($pool['community'] ?? [], 'title');
        if ($titles) {
            foreach (DB::table('posts')->whereIn('title', $titles)->where('created_at', '>=', $since)->get(['id', 'created_at', 'updated_at']) as $r) {
                DB::table('posts')->where('id', $r->id)->update(['created_at' => $shift($r->created_at), 'updated_at' => $shift($r->updated_at)]);
            }
        }
        $titles = array_column($pool['qa'] ?? [], 'title');
        if ($titles) {
            foreach (DB::table('qa_posts')->whereIn('title', $titles)->where('created_at', '>=', $since)->get(['id', 'created_at', 'updated_at']) as $r) {
                DB::table('qa_posts')->where('id', $r->id)->update(['created_at' => $shift($r->created_at), 'updated_at' => $shift($r->updated_at)]);
                foreach (DB::table('qa_answers')->where('qa_post_id', $r->id)->get(['id', 'created_at', 'updated_at']) as $a) {
                    DB::table('qa_answers')->where('id', $a->id)->update(['created_at' => $shift($a->created_at), 'updated_at' => $shift($a->updated_at)]);
                }
            }
        }
    }

    public function down(): void
    {
    }
};
