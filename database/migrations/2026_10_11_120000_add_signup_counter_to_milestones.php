<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 가입 보너스: "켠 뒤로 이메일 인증을 마친 회원 수"를 세는 전용 카운터 (전체 회원 수와 무관). 칸 추가만 한다.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sweepstakes_milestones') && !Schema::hasColumn('sweepstakes_milestones', 'signup_counter')) {
            Schema::table('sweepstakes_milestones', function (Blueprint $table) {
                $table->unsignedInteger('signup_counter')->default(0);
            });
        }
    }

    public function down(): void
    {
        // 데이터 보호: 칸을 지우지 않는다.
    }
};
