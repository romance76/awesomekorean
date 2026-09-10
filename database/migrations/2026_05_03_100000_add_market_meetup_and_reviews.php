<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 헤이코리안 중고장터가 최근 리뉴얼로 추가한 3가지(실시간 채팅, 거래 약속 시간
 * 예약, 거래 후기)를 어썸코리안 중고장터에도 도입.
 * - 실시간 채팅은 기존 ChatController::createRoom(type=dm) 재사용이라 스키마 변경 없음.
 * - 거래 약속 시간/장소는 기존 market_reservations(홀드) 테이블에 컬럼만 추가.
 * - 거래 후기는 business_reviews와 동일한 패턴의 신규 테이블.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('market_reservations') && !Schema::hasColumn('market_reservations', 'meetup_at')) {
            Schema::table('market_reservations', function (Blueprint $table) {
                $table->timestamp('meetup_at')->nullable()->after('hold_until');
                $table->string('meetup_place')->nullable()->after('meetup_at');
            });
        }

        if (!Schema::hasTable('market_reviews')) {
            Schema::create('market_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('market_item_id')->constrained()->cascadeOnDelete();
                $table->foreignId('market_reservation_id')->constrained('market_reservations')->cascadeOnDelete();
                $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('reviewee_id')->constrained('users')->cascadeOnDelete();
                $table->tinyInteger('rating');
                $table->text('comment')->nullable();
                $table->timestamps();
                $table->unique(['market_reservation_id', 'reviewer_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('market_reviews');
        if (Schema::hasTable('market_reservations') && Schema::hasColumn('market_reservations', 'meetup_at')) {
            Schema::table('market_reservations', function (Blueprint $table) {
                $table->dropColumn(['meetup_at', 'meetup_place']);
            });
        }
    }
};
