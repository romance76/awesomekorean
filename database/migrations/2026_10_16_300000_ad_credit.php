<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 광고 잔액 — 최소 결제 금액($5)보다 광고 금액이 작아 최소 금액으로 결제하거나, 일부러 더 많이 결제했을 때 남는 금액을
 * 환불 없이 "광고 잔액"으로 쌓아 두었다가 다음 NEW 전면광고 신청 때 쓸 수 있게 한다.
 *  - users.ad_credit_cents          : 현재 잔액(센트)
 *  - ad_credit_logs                 : 적립/사용/되돌림 기록
 *  - flyer_ads.topup_cents          : 이 신청으로 광고 금액보다 더 낸 금액 (승인되어 청구될 때 잔액으로 적립)
 *  - flyer_ads.credit_used_cents    : 이 신청에 쓴 광고 잔액 (취소/반려/지난 시간이면 되돌림)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'ad_credit_cents')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('ad_credit_cents')->default(0);
            });
        }
        if (!Schema::hasTable('ad_credit_logs')) {
            Schema::create('ad_credit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->integer('delta_cents');                    // +적립/되돌림, -사용
                $table->unsignedInteger('balance_after');
                $table->string('type', 12);                        // topup | spend | restore | admin
                $table->string('reason', 200);
                $table->string('related_type', 100)->nullable();
                $table->unsignedBigInteger('related_id')->nullable();
                $table->timestamps();
            });
        }
        Schema::table('flyer_ads', function (Blueprint $table) {
            if (!Schema::hasColumn('flyer_ads', 'topup_cents')) $table->unsignedInteger('topup_cents')->default(0);
            if (!Schema::hasColumn('flyer_ads', 'credit_used_cents')) $table->unsignedInteger('credit_used_cents')->default(0);
        });
    }

    public function down(): void {}
};
