<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sweepstakes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('prize_name');
            $table->decimal('prize_value', 10, 2)->nullable();
            $table->string('prize_image')->nullable();
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->string('status', 20)->default('draft'); // draft, active, ended, winner_selected, cancelled
            $table->unsignedBigInteger('total_entries')->default(0); // 비정규화 카운터 — 조회 성능용, sweepstakes_entries 합과 항상 일치해야 함
            $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('winner_selected_at')->nullable();
            // 향후 법률 검토 반영용 확장 필드 — 실제 운영 전 별도 법률 검토 필요
            $table->unsignedTinyInteger('minimum_age')->default(18);
            $table->json('eligible_regions')->nullable(); // 예: ["US"] 또는 특정 state 코드 배열, null = 제한 없음
            $table->string('official_rules_url')->nullable();
            $table->text('no_purchase_required_text')->nullable();
            $table->string('terms_version')->nullable();
            $table->timestamps();
            $table->index(['status', 'end_at']);
        });

        // 사용자별 특정 Sweepstakes 참가 누적 — Entry 트랜잭션(entry_transactions)은
        // 매 참가 이벤트를 기록하고, 이 테이블은 당첨자 추첨에 바로 쓸 수 있는
        // user별 누적 합계를 유지(매 추첨마다 트랜잭션 로그를 합산하지 않도록).
        Schema::create('sweepstakes_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sweepstakes_id')->constrained('sweepstakes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('entries_count')->default(0);
            $table->timestamps();
            $table->unique(['sweepstakes_id', 'user_id']);
        });

        // 당첨자 선정 감사 로그 — 불변 기록. 관리자가 결과를 되돌리는 기능은
        // 만들지 않으므로, 이 테이블의 행은 생성 후 절대 수정하지 않는다.
        Schema::create('sweepstakes_winner_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sweepstakes_id')->constrained('sweepstakes')->cascadeOnDelete();
            $table->foreignId('winner_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('winning_index'); // 추첨 시점에 뽑힌 누적 인덱스(0-based, 검증 가능하도록 기록)
            $table->unsignedBigInteger('total_entries_at_draw');
            $table->string('selection_method', 50); // 예: random_int_cumulative_v1
            $table->timestamp('selected_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sweepstakes_winner_audits');
        Schema::dropIfExists('sweepstakes_entries');
        Schema::dropIfExists('sweepstakes');
    }
};
