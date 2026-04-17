<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Issue #14: user_daily_spins 에 (user_id, spun_date) UNIQUE 제약 추가 — race 방지.
     * 기존 레코드에 계산 컬럼 spun_date 를 채운 뒤 UNIQUE 인덱스 부여.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('user_daily_spins', 'spun_date')) {
            Schema::table('user_daily_spins', function (Blueprint $table) {
                $table->date('spun_date')->nullable()->after('spun_at');
            });
        }

        // 기존 레코드 백필
        DB::statement("UPDATE user_daily_spins SET spun_date = DATE(spun_at) WHERE spun_date IS NULL");

        // 중복 제거 (가장 오래된 것만 보존) — 혹시 race 로 과거 중복 있으면 차단
        DB::statement("
            DELETE s1 FROM user_daily_spins s1
            INNER JOIN user_daily_spins s2
                ON s1.user_id = s2.user_id
                AND s1.spun_date = s2.spun_date
                AND s1.id > s2.id
        ");

        Schema::table('user_daily_spins', function (Blueprint $table) {
            $table->date('spun_date')->nullable(false)->change();
            $table->unique(['user_id', 'spun_date'], 'user_daily_spin_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_daily_spins', function (Blueprint $table) {
            $table->dropUnique('user_daily_spin_unique');
            $table->dropColumn('spun_date');
        });
    }
};
