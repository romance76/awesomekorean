<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 이벤트완료인증(관리자 확인 후 보상)과 레시피 인기보상(좋아요/댓글 5개
// 이상부터 관리자가 금액 지정 후 지급) — 사용자 결정에 따라 둘 다
// "자동 지급"이 아니라 관리자 수동 승인 방식으로 신규 구축.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_attendees', function (Blueprint $table) {
            if (!Schema::hasColumn('event_attendees', 'proof_file')) {
                $table->string('proof_file')->nullable()->after('status');
            }
            if (!Schema::hasColumn('event_attendees', 'proof_status')) {
                $table->string('proof_status', 20)->default('none')->after('proof_file'); // none|pending|approved|rejected
            }
            if (!Schema::hasColumn('event_attendees', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('proof_status');
            }
        });

        Schema::table('recipe_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('recipe_posts', 'reward_paid_at')) {
                $table->timestamp('reward_paid_at')->nullable()->after('favorite_count');
            }
            if (!Schema::hasColumn('recipe_posts', 'reward_amount')) {
                $table->integer('reward_amount')->nullable()->after('reward_paid_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_attendees', function (Blueprint $table) {
            foreach (['proof_file', 'proof_status', 'reviewed_at'] as $col) {
                if (Schema::hasColumn('event_attendees', $col)) $table->dropColumn($col);
            }
        });
        Schema::table('recipe_posts', function (Blueprint $table) {
            foreach (['reward_paid_at', 'reward_amount'] as $col) {
                if (Schema::hasColumn('recipe_posts', $col)) $table->dropColumn($col);
            }
        });
    }
};
