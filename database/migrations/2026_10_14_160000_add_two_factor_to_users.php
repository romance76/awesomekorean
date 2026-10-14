<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 관리자 2단계 인증(TOTP): users 표에 칸 4개만 추가한다(없을 때만, 기존 데이터는 건드리지 않음).
// 칸이 모두 비어 있으면 2단계 인증이 꺼진 상태라 기존 회원·관리자의 로그인은 그대로다.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) return;
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'two_factor_secret')) $t->text('two_factor_secret')->nullable();
            if (!Schema::hasColumn('users', 'two_factor_recovery_codes')) $t->text('two_factor_recovery_codes')->nullable();
            if (!Schema::hasColumn('users', 'two_factor_confirmed_at')) $t->timestamp('two_factor_confirmed_at')->nullable();
            if (!Schema::hasColumn('users', 'two_factor_last_step')) $t->unsignedBigInteger('two_factor_last_step')->nullable();
        });
    }

    public function down(): void
    {
        // 보안 설정이라 되돌릴 때 지우지 않는다
    }
};
