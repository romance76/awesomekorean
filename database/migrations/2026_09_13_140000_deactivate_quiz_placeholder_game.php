<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 게임 완성도 감사에서 발견: "일일 퀴즈"(slug=quiz)는 실제 게임 없이
// "준비중" 안내와 다른 게임으로의 링크만 있는 죽은 자리였음. 사용자 결정으로
// 실기능을 새로 만드는 대신 게임 목록에서 비활성화(soft) — 과거 이 슬러그를
// 참조했을 수 있는 기록(game_scores 등)은 그대로 보존.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('games')->where('slug', 'quiz')->update(['is_active' => false]);
    }

    public function down(): void
    {
        DB::table('games')->where('slug', 'quiz')->update(['is_active' => true]);
    }
};
