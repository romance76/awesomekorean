<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 월요일 정기 소식 메일: 회원별 발송 기록(같은 주 중복 발송 방지) + 맨 아래 광고 띠의 광고별 노출·클릭 집계
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_digest_sends')) {
            Schema::create('email_digest_sends', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('week_key', 10);              // 예: 2026-W43 (해당 주 월요일 기준)
                $table->string('status', 10)->default('sent'); // sent | failed
                $table->string('error', 200)->nullable();
                $table->timestamp('sent_at')->useCurrent();
                $table->unique(['user_id', 'week_key']);
            });
        }
        if (!Schema::hasTable('email_ad_stats')) {
            Schema::create('email_ad_stats', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('banner_ad_id');
                $table->date('stat_date');
                $table->unsignedInteger('sent')->default(0);    // 이 광고가 실린 메일 수
                $table->unsignedInteger('clicks')->default(0);  // 메일 속 광고 클릭
                $table->unique(['banner_ad_id', 'stat_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_ad_stats');
        Schema::dropIfExists('email_digest_sends');
    }
};
