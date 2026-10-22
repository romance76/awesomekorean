<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 한인 유튜버: 채널 주인에게 안내 메시지를 보냈는지 기록 (이메일 직접 발송 또는 DM 을 보낸 뒤 '보냄 표시')
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('youtube_channels', function (Blueprint $t) {
            if (!Schema::hasColumn('youtube_channels', 'outreach_sent_at')) $t->timestamp('outreach_sent_at')->nullable();
            if (!Schema::hasColumn('youtube_channels', 'outreach_via')) $t->string('outreach_via', 20)->nullable();   // email · dm
            if (!Schema::hasColumn('youtube_channels', 'outreach_to')) $t->string('outreach_to', 190)->nullable();   // 보낸 이메일 주소
        });
    }

    public function down(): void
    {
        Schema::table('youtube_channels', function (Blueprint $t) {
            foreach (['outreach_sent_at', 'outreach_via', 'outreach_to'] as $c) {
                if (Schema::hasColumn('youtube_channels', $c)) $t->dropColumn($c);
            }
        });
    }
};
