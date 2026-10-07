<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 로그인 실패 기록 — 관리자 보안 화면에서 "누가 어디서 계속 틀리는지 / 지금 잠긴 계정"을 보고 풀어 주기 위한 것 (비밀번호는 저장하지 않음)
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('login_failures')) return;
        Schema::create('login_failures', function (Blueprint $t) {
            $t->id();
            $t->string('email', 190);
            $t->string('ip', 45);
            $t->timestamp('created_at')->useCurrent();
            $t->index(['created_at']);
            $t->index(['email', 'ip']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_failures');
    }
};
