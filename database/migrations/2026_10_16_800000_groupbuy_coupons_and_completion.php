<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 공동구매 완료·수락(총액 수수료 결제)과 쿠폰(발급·사용·양쪽 동의·감사 기록). 2026-10-16.
//  - group_buys: 쿠폰 사용 방식(매장/온라인), 사용 장소·주소·안내, 개설자 할인 코드, 매장 PIN(해시), 쿠폰 유효 일수, 연장 기록,
//                완료 때의 총액·수수료·수수료 결제 행, 완료 시각, 종료 사유
//  - group_buy_participants: 참가자 정보 제공 동의 시각
//  - group_buy_coupons: 쿠폰 한 장 = 한 줄 (참가자가 산 수량만큼)
//  - group_buy_coupon_events: 쿠폰에 일어난 일의 기록(분쟁·감사용)
// 이미 있는 칸·표는 건너뛴다(여러 번 돌려도 안전). 이미 있는 공동구매는 새 칸이 모두 null.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_buys')) {
            Schema::table('group_buys', function (Blueprint $table) {
                if (!Schema::hasColumn('group_buys', 'use_mode')) $table->string('use_mode', 10)->nullable()->after('deposit_payment_id');     // store | online | both
                if (!Schema::hasColumn('group_buys', 'use_place')) $table->string('use_place', 200)->nullable()->after('use_mode');          // 가게 이름·주소
                if (!Schema::hasColumn('group_buys', 'use_url')) $table->string('use_url', 500)->nullable()->after('use_place');              // 온라인 사용 주소
                if (!Schema::hasColumn('group_buys', 'use_instructions')) $table->text('use_instructions')->nullable()->after('use_url');    // 사용 방법
                if (!Schema::hasColumn('group_buys', 'promo_code')) $table->string('promo_code', 100)->nullable()->after('use_instructions'); // 개설자 할인 코드(쿠폰 화면에 같이 보임)
                if (!Schema::hasColumn('group_buys', 'store_pin_hash')) $table->string('store_pin_hash')->nullable()->after('promo_code');   // 매장 직원 확인용 PIN(해시)
                if (!Schema::hasColumn('group_buys', 'coupon_valid_days')) $table->unsignedSmallInteger('coupon_valid_days')->default(30)->after('store_pin_hash');
                if (!Schema::hasColumn('group_buys', 'coupon_extended_at')) $table->timestamp('coupon_extended_at')->nullable()->after('coupon_valid_days'); // 연장은 한 번만
                if (!Schema::hasColumn('group_buys', 'total_cents')) $table->unsignedBigInteger('total_cents')->nullable()->after('coupon_extended_at');
                if (!Schema::hasColumn('group_buys', 'fee_cents')) $table->unsignedBigInteger('fee_cents')->nullable()->after('total_cents');
                if (!Schema::hasColumn('group_buys', 'fee_payment_id')) $table->unsignedBigInteger('fee_payment_id')->nullable()->after('fee_cents');
                if (!Schema::hasColumn('group_buys', 'accepted_at')) $table->timestamp('accepted_at')->nullable()->after('fee_payment_id');
                if (!Schema::hasColumn('group_buys', 'closed_reason')) $table->string('closed_reason', 30)->nullable()->after('accepted_at'); // declined(수락하지 않고 종료) 등
            });
        }

        if (Schema::hasTable('group_buy_participants') && !Schema::hasColumn('group_buy_participants', 'consent_at')) {
            Schema::table('group_buy_participants', function (Blueprint $table) {
                $table->timestamp('consent_at')->nullable();
            });
        }

        if (!Schema::hasTable('group_buy_coupons')) {
            Schema::create('group_buy_coupons', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('group_buy_id')->index();
                $table->unsignedBigInteger('participant_id')->index();
                $table->unsignedBigInteger('user_id')->index();          // 쿠폰을 가진 사람(참가자)
                $table->unsignedSmallInteger('seq')->default(1);          // 같은 참가자의 몇 번째 쿠폰
                $table->string('code', 12)->unique();                     // 긴 쿠폰 번호(QR·온라인 주문 메모)
                $table->string('confirm_code', 4);                        // 고객 확인 코드 4자리(매장에서 고객 폰이 안 될 때)
                $table->string('status', 12)->default('unused');         // unused | pending | used | expired | cancelled
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('reminder_sent_at')->nullable();
                $table->string('pending_mode', 10)->nullable();           // store | online
                $table->string('pending_via', 10)->nullable();            // login | pin
                $table->timestamp('pending_at')->nullable();
                $table->timestamp('pending_expires_at')->nullable();      // 매장 3분 / 온라인 24시간
                $table->timestamp('used_at')->nullable();
                $table->string('used_mode', 10)->nullable();
                $table->string('used_via', 10)->nullable();               // holder(고객이 맞다고 함) | code(확인 코드 4자리)
                $table->timestamp('undo_requested_at')->nullable();
                $table->string('undo_requested_by', 10)->nullable();      // holder | opener
                $table->unsignedSmallInteger('denied_count')->default(0);
                $table->timestamps();
                $table->index(['status', 'expires_at']);
            });
        }

        if (!Schema::hasTable('group_buy_coupon_events')) {
            Schema::create('group_buy_coupon_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coupon_id')->index();
                $table->string('type', 30);                               // issued | use_requested | confirmed | denied | expired | extended | undo_requested | undone | reported ...
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_kind', 10)->nullable();             // holder | opener | staff | system | admin
                $table->string('note', 300)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        // 데이터 보호: 칸·표를 지우지 않는다.
    }
};
