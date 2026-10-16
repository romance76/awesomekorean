<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 공동구매 범위(scope: local=내 주+인접 주 / national=전국), 수령 방식(fulfillment: delivery=택배 / coupon=쿠폰), 보일 주 목록(target_states). 2026-10-16.
// 이미 있는 공동구매는 local·target_states=null(어느 주에서나 보임)로 둔다. 여러 번 돌려도 안전.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('group_buys')) return;
        Schema::table('group_buys', function (Blueprint $table) {
            if (!Schema::hasColumn('group_buys', 'scope')) $table->string('scope', 10)->default('local')->after('state');
            if (!Schema::hasColumn('group_buys', 'fulfillment')) $table->string('fulfillment', 10)->nullable()->after('scope');
            if (!Schema::hasColumn('group_buys', 'target_states')) $table->json('target_states')->nullable()->after('fulfillment');
        });
    }

    public function down(): void
    {
        // 데이터 보호: 컬럼을 지우지 않는다.
    }
};
