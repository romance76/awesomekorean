<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 신용카드 카페 스카이패스 글 제목 정정: "호텔 포인트 전환" 은 사실과 달라 제목에서 뺌(본문은 이미 고침).
// money:sync-posts 는 제목으로 글을 찾으므로, 동기화 전에(migrate 가 먼저 돎) DB 제목을 JSON 과 같게 바꿔 중복 글이 생기지 않게 한다.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('money_posts')
            ->where('title', '대한항공 스카이패스, 미국에서 모으기: 가입, 델타편 적립, 호텔 포인트 전환, 가족 합산, 마일 유효기간 10년')
            ->update(['title' => '대한항공 스카이패스, 미국에서 모으기: 가입, 델타편 적립, 가족 합산, 마일 유효기간 10년']);
    }

    public function down(): void
    {
    }
};
