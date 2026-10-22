<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 한인 유튜버: 채널 주인 인증(채널 설명에 인증 코드를 넣어 확인) + 목록 끌어올리기
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('youtube_channels', function (Blueprint $t) {
            if (!Schema::hasColumn('youtube_channels', 'owner_user_id')) $t->unsignedBigInteger('owner_user_id')->nullable()->index();   // 인증된 주인(회원)
            if (!Schema::hasColumn('youtube_channels', 'verified_at')) $t->timestamp('verified_at')->nullable();
            if (!Schema::hasColumn('youtube_channels', 'claim_user_id')) $t->unsignedBigInteger('claim_user_id')->nullable();           // 인증 진행 중인 회원
            if (!Schema::hasColumn('youtube_channels', 'claim_code')) $t->string('claim_code', 40)->nullable();
            if (!Schema::hasColumn('youtube_channels', 'claim_requested_at')) $t->timestamp('claim_requested_at')->nullable();
            if (!Schema::hasColumn('youtube_channels', 'bumped_at')) $t->timestamp('bumped_at')->nullable();                           // 마지막 끌어올린 시각
        });
    }

    public function down(): void
    {
        Schema::table('youtube_channels', function (Blueprint $t) {
            foreach (['owner_user_id', 'verified_at', 'claim_user_id', 'claim_code', 'claim_requested_at', 'bumped_at'] as $c) {
                if (Schema::hasColumn('youtube_channels', $c)) $t->dropColumn($c);
            }
        });
    }
};
