<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 추첨 연출 스킨(draw_style)과 테마(theme) 컬럼 추가 — 기존 데이터는 건드리지 않음
    public function up(): void
    {
        if (!Schema::hasColumn('sweepstakes', 'draw_style')) {
            Schema::table('sweepstakes', function (Blueprint $table) {
                $table->string('draw_style', 20)->default('wheel');
            });
        }
        if (!Schema::hasColumn('sweepstakes', 'theme')) {
            Schema::table('sweepstakes', function (Blueprint $table) {
                $table->json('theme')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sweepstakes', 'theme')) {
            Schema::table('sweepstakes', function (Blueprint $table) {
                $table->dropColumn('theme');
            });
        }
        if (Schema::hasColumn('sweepstakes', 'draw_style')) {
            Schema::table('sweepstakes', function (Blueprint $table) {
                $table->dropColumn('draw_style');
            });
        }
    }
};
