<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Kay 지시(2026-10-12): 일반론 위주로 올렸던 운영팀 글(작성자 "어썸코리안" 운영 계정)을 모두 지우고,
// 돈을 아끼고 모으고 불리는 실제 사이트·서비스 소개 글로 다시 올린다.
// - 운영 계정이 쓴 글만 대상. 회원이 쓴 글은 절대 건드리지 않는다.
// - 혹시 회원 댓글이 달린 글은 지우지 않고 숨긴다(댓글이 떠 있지 않게).
return new class extends Migration
{
    public function up(): void
    {
        $ops = DB::table('users')->where('email', 'money-ops@somekorean.local')->first();
        if (!$ops) return;
        DB::table('money_posts')->where('user_id', $ops->id)->where('comment_count', '>', 0)->update(['is_hidden' => true]);
        DB::table('money_posts')->where('user_id', $ops->id)->where('comment_count', 0)->delete();
    }

    public function down(): void
    {
        // 되돌리지 않는다.
    }
};
