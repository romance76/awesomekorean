<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 오늘(미국 동부 날짜) 사이트를 쓴 사람 수: 같은 사람이 여러 번 오가도 하루 한 번만 센다
        if (!Schema::hasTable('site_visits')) {
            Schema::create('site_visits', function (Blueprint $t) {
                $t->id();
                $t->date('visit_date');
                $t->char('visitor_key', 40);           // 브라우저 임의 ID 의 해시 (개인 식별 정보 아님)
                $t->unsignedBigInteger('user_id')->nullable();
                $t->timestamp('first_seen_at')->nullable();
                $t->timestamp('last_seen_at')->nullable();
                $t->unique(['visit_date', 'visitor_key']);
                $t->index('last_seen_at');
            });
        }

        // 지금 오픈 채팅방을 열어 둔 사람 (사람당 한 줄: 마지막으로 연 방과 시각)
        if (!Schema::hasTable('chat_presence')) {
            Schema::create('chat_presence', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->unique();
                $t->unsignedBigInteger('room_id')->nullable();
                $t->timestamp('last_seen_at')->nullable()->index();
            });
        }

        // 회원별 관심종목 티커
        if (!Schema::hasTable('user_watchlists')) {
            Schema::create('user_watchlists', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id');
                $t->string('symbol', 20);
                $t->string('name')->nullable();
                $t->unsignedSmallInteger('sort_order')->default(0);
                $t->timestamps();
                $t->unique(['user_id', 'symbol']);
            });
        }

        // 실적 발표(어닝) 일정 — Nasdaq 공개 캘린더에서 수집
        if (!Schema::hasTable('earnings_events')) {
            Schema::create('earnings_events', function (Blueprint $t) {
                $t->id();
                $t->date('report_date');
                $t->string('symbol', 20);
                $t->string('name')->nullable();
                $t->string('time_slot', 4)->default('tns');   // bmo(장 시작 전) | amc(장 마감 후) | tns(미정)
                $t->unsignedBigInteger('market_cap')->nullable();
                $t->string('eps_forecast', 20)->nullable();
                $t->string('last_year_eps', 20)->nullable();
                $t->string('fiscal_quarter', 20)->nullable();
                $t->unsignedSmallInteger('est_count')->nullable();
                $t->timestamps();
                $t->unique(['report_date', 'symbol']);
                $t->index('report_date');
            });
        }

        if (Schema::hasTable('market_quotes') && !Schema::hasColumn('market_quotes', 'quoted_at')) {
            Schema::table('market_quotes', function (Blueprint $t) { $t->timestamp('quoted_at')->nullable(); });
        }

        // 미국 사이트이므로 한국 지수/종목 시세는 정리 (새 수집 목록으로 다시 채워진다)
        if (Schema::hasTable('market_quotes')) {
            DB::table('market_quotes')->where('symbol', 'like', '%.KS')->orWhere('symbol', '^KS11')->delete();
        }
    }

    public function down(): void
    {
        foreach (['earnings_events', 'user_watchlists', 'chat_presence', 'site_visits'] as $t) Schema::dropIfExists($t);
        if (Schema::hasColumn('market_quotes', 'quoted_at')) Schema::table('market_quotes', fn(Blueprint $t) => $t->dropColumn('quoted_at'));
    }
};
