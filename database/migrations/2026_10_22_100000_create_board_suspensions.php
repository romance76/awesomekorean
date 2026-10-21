<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 신고로 인한 게시판별 이용 정지 — 한 건이 "정지 1회". 누적 3회면 계정 이용이 정지된다(App\Support\BoardSuspensions)
return new class extends Migration {
    public function up(): void
    {
        Schema::create('board_suspensions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->string('board_key', 40);                          // market · jobs · … · community:{게시판 번호}
            $t->string('board_label', 80);                        // 정지 당시 게시판 이름 (화면 표시용)
            $t->unsignedBigInteger('report_id')->nullable()->index();
            $t->string('reason', 255)->nullable();
            $t->timestamp('ends_at')->nullable();                 // 비어 있으면 영구
            $t->timestamp('lifted_at')->nullable();               // 관리자가 중간에 푼 시각
            $t->unsignedBigInteger('lifted_by')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'board_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_suspensions');
    }
};
