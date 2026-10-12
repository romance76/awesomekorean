<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// SNS 자동 게시 대기열 (인스타그램·스레드). 계획서 qa/sns_autopost_plan.md — Kay "제안대로" 2026-10-12.
// 한 줄 = 한 플랫폼에 올릴 글 하나. 원본: MONEY 운영팀 글(money_post) · 어썸코리안 이벤트(event) · 관리자 직접 쓰기(manual).
// 상태: pending(승인 대기) → approved(예약 게시 대기) → posted / failed / skipped
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('social_posts')) {
            Schema::create('social_posts', function (Blueprint $t) {
                $t->id();
                $t->string('source_type', 20);                 // money_post | event | manual
                $t->unsignedBigInteger('source_id')->nullable();
                $t->string('platform', 20);                    // instagram | threads
                $t->string('title', 200)->nullable();          // 관리자 목록용 제목
                $t->text('caption');
                $t->string('link_url', 500)->nullable();       // 스레드에 붙는 링크(인스타는 "프로필 링크")
                $t->string('image_url', 500)->nullable();      // 인스타 카드뉴스·스레드 사진(공개 https 주소)
                $t->string('status', 20)->default('pending');
                $t->integer('priority')->default(0);           // 클수록 먼저 (이벤트 10)
                $t->timestamp('not_before')->nullable();       // 이 시각 전에는 올리지 않음
                $t->timestamp('posted_at')->nullable();
                $t->string('external_id', 100)->nullable();
                $t->string('external_url', 500)->nullable();
                $t->text('error')->nullable();
                $t->unsignedInteger('attempts')->default(0);
                $t->unsignedBigInteger('approved_by')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['status', 'platform', 'priority']);
                $t->index(['source_type', 'source_id']);
            });
        }

        // 설정 기본값 (Kay 확정 제안값) — 이미 있으면 그대로
        if (!DB::table('site_settings')->where('key', 'social_autopost')->exists()) {
            DB::table('site_settings')->insert(['key' => 'social_autopost', 'value' => json_encode([
                'enabled' => true,              // 전체 스위치(끄면 아무것도 안 올림)
                'require_approval' => true,     // 승인한 것만 게시
                'instagram_daily' => 2,
                'threads_daily' => 3,
                'times' => ['08:00', '12:00', '19:00'],   // 애틀랜타 시각
                'hashtags' => '#미국생활 #애틀랜타한인 #미국한인 #어썸코리안',
                'auto_events' => true,          // 어썸코리안 이벤트 자동으로 대기열에
                'auto_money' => true,           // 새 MONEY 운영팀 글 자동으로 대기열에
            ]), 'created_at' => now(), 'updated_at' => now()]);
        }
        // 기존 MONEY 글(250여 편)은 자동으로 넣지 않음 — 지금 가장 큰 글 번호를 기준점으로 저장, 이후 새 글만 자동 등록
        if (Schema::hasTable('money_posts') && !DB::table('site_settings')->where('key', 'social_money_last_id')->exists()) {
            DB::table('site_settings')->insert(['key' => 'social_money_last_id', 'value' => (string) (int) DB::table('money_posts')->max('id'), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // 표를 지우지 않음(운영 규칙: DROP 금지)
    }
};
