<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 공동구매 개설 디파짓 — 금액(센트)과 결제 행(payments.id). 2026-10-16.
// status 'awaiting_deposit' = 디파짓 카드 확인을 기다리는 임시 글(목록·관리자 승인 대기에 나오지 않는다). 이미 있는 공동구매는 0 / null.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('group_buys')) return;
        Schema::table('group_buys', function (Blueprint $table) {
            if (!Schema::hasColumn('group_buys', 'deposit_cents')) $table->unsignedInteger('deposit_cents')->default(0)->after('target_states');
            if (!Schema::hasColumn('group_buys', 'deposit_payment_id')) $table->unsignedBigInteger('deposit_payment_id')->nullable()->after('deposit_cents');
        });
    }

    public function down(): void
    {
        // 데이터 보호: 컬럼을 지우지 않는다.
    }
};
