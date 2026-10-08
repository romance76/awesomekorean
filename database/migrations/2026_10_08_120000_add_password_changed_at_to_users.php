<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 비밀번호를 바꾼 시각. 이 시각보다 먼저 발급된 로그인 토큰은 무효로 처리한다(다른 기기 로그인도 끊김).
// 기존 회원은 NULL(제한 없음)이라 배포 때 아무도 로그아웃되지 않는다.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'password_changed_at')) return;
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_changed_at')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'password_changed_at')) return;
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_changed_at');
        });
    }
};
