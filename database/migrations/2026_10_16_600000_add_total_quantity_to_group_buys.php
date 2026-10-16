<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 공동구매 "총 수량(개)" — 소액 공동구매는 50개 이하(2026-10-16). 이미 있는 공동구매는 null(제한 없음)로 둔다. 여러 번 돌려도 안전.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('group_buys') || Schema::hasColumn('group_buys', 'total_quantity')) return;
        Schema::table('group_buys', function (Blueprint $table) {
            $table->unsignedInteger('total_quantity')->nullable()->after('max_participants');
        });
    }

    public function down(): void
    {
        // 데이터 보호: 컬럼을 지우지 않는다.
    }
};
