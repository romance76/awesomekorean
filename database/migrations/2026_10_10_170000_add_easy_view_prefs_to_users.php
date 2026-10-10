<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "큰 글씨로 보기" 개인 설정(글씨 크기·진하게·바꾼 시각)을 계정에 저장 — 다른 기기에서 로그인해도 같은 크기로 열리게.
// 형식: "크기|진하게|바꾼시각(ms)" 예) "lg|1|1760112000000". 기존 데이터는 건드리지 않는 새 칸 추가만 한다.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'easy_view_prefs')) {
                $table->string('easy_view_prefs', 40)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'easy_view_prefs')) {
                $table->dropColumn('easy_view_prefs');
            }
        });
    }
};
