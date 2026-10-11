<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 광고 카드 결제(Kay 결정 2026-10-11) + 정식 인보이스용 칸 추가. 칸만 추가하고 기존 데이터는 그대로 둔다.
 *  banner_ads.payment_method  'points'(예전 신청, 기본값) | 'card'
 *  banner_ads.payment_id      카드 결제(payments) 번호
 *  payments.card_brand/last4  청구된 카드 종류·끝 4자리 (인보이스 "결제 수단"에 표시, 전체 번호는 저장하지 않음)
 *  invoices.billing_details   발행 시점의 받는 사람 정보(전화·주소·도시·주·우편번호) 스냅샷
 * 여러 번 돌려도 안전. down() 은 데이터 보호로 아무것도 하지 않음.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('banner_ads')) {
            Schema::table('banner_ads', function (Blueprint $table) {
                if (!Schema::hasColumn('banner_ads', 'payment_method')) $table->string('payment_method', 10)->default('points');
                if (!Schema::hasColumn('banner_ads', 'payment_id')) $table->unsignedBigInteger('payment_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (!Schema::hasColumn('payments', 'card_brand')) $table->string('card_brand', 20)->nullable();
                if (!Schema::hasColumn('payments', 'card_last4')) $table->string('card_last4', 4)->nullable();
            });
        }
        if (Schema::hasTable('invoices') && !Schema::hasColumn('invoices', 'billing_details')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->text('billing_details')->nullable();
            });
        }
    }

    public function down(): void
    {
        // 데이터 보호: 칸을 지우지 않는다.
    }
};
