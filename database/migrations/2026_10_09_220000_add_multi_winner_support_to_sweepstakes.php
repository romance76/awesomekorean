<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 다중(등수별) 당첨자 지원 — add-only. 기존 데이터는 건드리지 않음
    public function up(): void
    {
        if (!Schema::hasColumn('sweepstakes', 'winner_count')) {
            Schema::table('sweepstakes', function (Blueprint $table) {
                $table->unsignedTinyInteger('winner_count')->default(1);
            });
        }
        if (!Schema::hasColumn('sweepstakes', 'prize_tiers')) {
            Schema::table('sweepstakes', function (Blueprint $table) {
                $table->json('prize_tiers')->nullable(); // [{rank:int, prize_name:string}]
            });
        }

        if (!Schema::hasTable('sweepstakes_winners')) {
            Schema::create('sweepstakes_winners', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sweepstakes_id')->constrained('sweepstakes')->cascadeOnDelete();
                $table->unsignedTinyInteger('rank');
                // 감사 테이블(sweepstakes_winner_audits)의 winner_user_id 정의와 동일하게 미러링
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedInteger('winning_index'); // 추첨 시점 누적 Entry 구간 인덱스(0-based)
                $table->unsignedInteger('total_entries_at_draw');
                $table->string('prize_label')->nullable();
                $table->string('selection_method', 50);
                $table->timestamp('selected_at');
                $table->timestamps();
                $table->unique(['sweepstakes_id', 'rank']);
                $table->unique(['sweepstakes_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sweepstakes_winners');

        if (Schema::hasColumn('sweepstakes', 'prize_tiers')) {
            Schema::table('sweepstakes', function (Blueprint $table) {
                $table->dropColumn('prize_tiers');
            });
        }
        if (Schema::hasColumn('sweepstakes', 'winner_count')) {
            Schema::table('sweepstakes', function (Blueprint $table) {
                $table->dropColumn('winner_count');
            });
        }
    }
};
