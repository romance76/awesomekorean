<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 부동산 매물엔 "임대/매매 완료"를 표시할 방법이 전혀 없었음(비활성화=종료와
// 구분 불가) — 완료 처리 전용 필드를 신설해 단순 비활성화/삭제와 구분하고,
// 완료 시에만 포인트를 지급할 수 있게 함.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('real_estate_listings', function (Blueprint $table) {
            if (!Schema::hasColumn('real_estate_listings', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('real_estate_listings', function (Blueprint $table) {
            if (Schema::hasColumn('real_estate_listings', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });
    }
};
