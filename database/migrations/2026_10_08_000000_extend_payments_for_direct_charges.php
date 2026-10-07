<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payments 테이블을 "포인트 구매" 외에 달러 직접 결제(경품 이벤트 의뢰, NEW 전면광고)도
 * 담도록 확장한다. 기존 행은 모두 kind='points' (포인트 구매)로 남는다.
 *
 * 상태(status):
 *  - 포인트 구매: pending → completed (→ refunded)         ← 기존 그대로
 *  - 직접 결제:   pending → authorized(카드 보류) → captured(청구됨) / released(보류 해제)
 *                 captured 이후 환불은 refunded_amount 에 누적, 전액이면 refunded
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('kind', 30)->default('points')->after('user_id');   // points | event_request | flyer
            $table->string('ref_type', 60)->nullable()->after('kind');
            $table->unsignedBigInteger('ref_id')->nullable()->after('ref_type');
            $table->string('description', 255)->nullable()->after('currency');
            $table->decimal('refunded_amount', 10, 2)->default(0)->after('amount');
            $table->timestamp('captured_at')->nullable()->after('status');
            $table->index(['kind', 'status', 'created_at'], 'payments_kind_status_created_idx');
            $table->index(['ref_type', 'ref_id'], 'payments_ref_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_kind_status_created_idx');
            $table->dropIndex('payments_ref_idx');
            $table->dropColumn(['kind', 'ref_type', 'ref_id', 'description', 'refunded_amount', 'captured_at']);
        });
    }
};
