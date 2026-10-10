<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 경품 자동화: (1) 반복 일정 — 같은 상품·같은 기간 이벤트를 매일/매주/매월 자동 생성,
// (2) 가입 보너스 — "매 N번째 가입 회원에게 상품" 같은 조건형 이벤트.
// 기존 표·데이터는 그대로 두고 표/칸만 추가한다.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sweepstakes_schedules')) {
            Schema::create('sweepstakes_schedules', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('status', 12)->default('paused');          // active | paused | stopped | completed
                $table->string('title_template', 200);                    // {n}=회차, {date}=시작 날짜
                $table->text('description')->nullable();
                $table->string('prize_name', 255);
                $table->decimal('prize_value', 10, 2)->nullable();
                $table->string('prize_image', 500)->nullable();
                $table->unsignedTinyInteger('winner_count')->default(1);
                $table->boolean('auto_draw')->default(false);             // 끝나면 자동으로 당첨자 추첨
                $table->string('repeat_unit', 10)->default('weekly');      // daily | weekly | monthly
                $table->unsignedTinyInteger('weekday')->nullable();        // 0(일)~6(토) — weekly
                $table->unsignedTinyInteger('month_day')->nullable();      // 1~28 — monthly
                $table->string('start_time', 5)->default('09:00');         // 애틀랜타 시각 HH:MM
                $table->unsignedSmallInteger('duration_hours')->default(168); // 이벤트 진행 시간(시간)
                $table->unsignedSmallInteger('total_runs')->nullable();    // 총 회차(null = 계속)
                $table->unsignedSmallInteger('runs_done')->default(0);
                $table->timestamp('next_run_at')->nullable();              // UTC
                $table->timestamp('last_run_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['status', 'next_run_at']);
            });
        }

        if (!Schema::hasTable('sweepstakes_milestones')) {
            Schema::create('sweepstakes_milestones', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('status', 12)->default('paused');          // active | paused | stopped
                $table->string('trigger_type', 20)->default('nth_signup'); // 지금은 nth_signup 만
                $table->unsignedInteger('every_n')->default(100);          // 매 N번째 가입
                $table->string('prize_name', 255);
                $table->decimal('prize_value', 10, 2)->nullable();
                $table->string('prize_type', 10)->default('digital');
                $table->unsignedInteger('max_awards')->nullable();         // 최대 지급 횟수(null = 계속)
                $table->unsignedInteger('awards_done')->default(0);
                $table->unsignedInteger('base_count')->default(0);         // 켠 시점의 회원 수 — 이 뒤로 가입한 사람부터 센다(기존 회원·더미 계정은 제외)
                $table->unsignedBigInteger('container_id')->nullable();    // 당첨자를 모아 두는 sweepstakes 행(지급 관리 화면 재사용)
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sweepstakes_milestone_awards')) {
            Schema::create('sweepstakes_milestone_awards', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('milestone_id');
                $table->unsignedInteger('milestone_no');                   // 몇 번째 구간인지 (회원 수 ÷ N)
                $table->unsignedBigInteger('user_id');
                $table->unsignedInteger('member_count');                   // 그때의 회원 수
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['milestone_id', 'milestone_no']);          // 같은 구간 중복 지급 방지
            });
        }

        if (Schema::hasTable('sweepstakes')) {
            Schema::table('sweepstakes', function (Blueprint $table) {
                if (!Schema::hasColumn('sweepstakes', 'kind')) {
                    $table->string('kind', 12)->default('event');          // event | milestone(가입 보너스 모음)
                }
                if (!Schema::hasColumn('sweepstakes', 'schedule_id')) {
                    $table->unsignedBigInteger('schedule_id')->nullable()->index();
                }
                if (!Schema::hasColumn('sweepstakes', 'run_no')) {
                    $table->unsignedSmallInteger('run_no')->nullable();
                }
            });
            try {
                Schema::table('sweepstakes', function (Blueprint $table) {
                    $table->unique(['schedule_id', 'run_no'], 'sweepstakes_schedule_run_unique'); // 같은 회차 이중 생성 방지 (NULL 은 여러 개 허용)
                });
            } catch (\Throwable $e) {
                // 이미 있으면 무시
            }
        }
    }

    public function down(): void
    {
        // 데이터 보호: 표를 지우지 않는다.
    }
};
