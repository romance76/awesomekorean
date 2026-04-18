<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2-C 묶음 3: 로그인 기록 (보안 감사).
 * 유저 /mypage/security + 관리자 /admin/v2/security/login-logs 에서 조회.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('login_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email', 191)->nullable();   // 실패 시 user_id 없을 수 있음
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('device', 100)->nullable();
            $table->boolean('successful')->default(true);
            $table->string('failure_reason', 100)->nullable();
            $table->string('jwt_jti', 100)->nullable();  // JWT token ID (세션 종료용)
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('logged_out_at')->nullable();
            $table->index(['user_id', 'created_at']);
            $table->index('ip');
            $table->index(['successful', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_histories');
    }
};
