<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 지급·보상 센터(Kay 요청 2026-10-11): 관리자가 "실제로 받은 구매 금액"을 직접 기록할 수 있게 payments 에 칸 두 개만 추가한다.
 *  pay_method  'manual'(관리자가 직접 기록한 결제 — Zelle·현금·수표 등) | NULL(카드 결제)
 *  pay_note    결제 방법·메모(예: "Zelle 2026-10-11") — 인보이스 "결제 수단"에 표시
 * 칸만 추가하고 기존 데이터는 그대로. 여러 번 돌려도 안전, down() 은 아무것도 안 함.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payments')) return;
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'pay_method')) $table->string('pay_method', 20)->nullable();
            if (!Schema::hasColumn('payments', 'pay_note')) $table->string('pay_note', 200)->nullable();
        });
    }

    public function down(): void
    {
        // 데이터 보호: 칸을 지우지 않는다.
    }
};
