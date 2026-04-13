<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 프로필 완성 보너스 1회 지급 여부
        if (!Schema::hasColumn('users', 'profile_bonus_given')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('profile_bonus_given')->default(false)->after('points');
            });
        }

        // point_logs.type을 string으로 변경 (enum 제약 해제 — banner, photo, hold, boost 등 지원)
        if (Schema::hasColumn('point_logs', 'type')) {
            Schema::table('point_logs', function (Blueprint $table) {
                $table->string('type', 30)->default('earn')->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_bonus_given');
        });
    }
};
