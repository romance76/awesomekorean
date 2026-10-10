<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 경품 지급 관리: 당첨자별 상품 종류(디지털/실물), 지급 상태, 상품 비용(장부), 처리 이력(로그).
// 기존 컬럼·데이터는 그대로 두고 칸/표만 추가한다.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sweepstakes_prize_claims')) {
            Schema::table('sweepstakes_prize_claims', function (Blueprint $table) {
                if (!Schema::hasColumn('sweepstakes_prize_claims', 'prize_type')) {
                    $table->string('prize_type', 10)->default('digital');            // digital | physical
                }
                if (!Schema::hasColumn('sweepstakes_prize_claims', 'delivery_status')) {
                    $table->string('delivery_status', 12)->default('pending');       // pending | sent | confirmed
                }
                if (!Schema::hasColumn('sweepstakes_prize_claims', 'delivery_link')) {
                    $table->text('delivery_link')->nullable();                       // 디지털 상품 링크/코드 (관리자만 볼 수 있음)
                }
                if (!Schema::hasColumn('sweepstakes_prize_claims', 'cost_usd')) {
                    $table->decimal('cost_usd', 10, 2)->nullable();                  // 내가 실제로 쓴 금액
                }
                if (!Schema::hasColumn('sweepstakes_prize_claims', 'sent_at')) {
                    $table->timestamp('sent_at')->nullable();
                }
                if (!Schema::hasColumn('sweepstakes_prize_claims', 'sent_by')) {
                    $table->unsignedBigInteger('sent_by')->nullable();
                }
                if (!Schema::hasColumn('sweepstakes_prize_claims', 'delivery_confirmed_at')) {
                    $table->timestamp('delivery_confirmed_at')->nullable();
                }
            });
        }

        if (!Schema::hasTable('sweepstakes_delivery_logs')) {
            Schema::create('sweepstakes_delivery_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('claim_id')->index();
                $table->unsignedBigInteger('sweepstakes_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();   // 당첨자
                $table->unsignedBigInteger('actor_id')->nullable();  // 처리한 관리자
                $table->string('action', 30);                        // digital_sent, physical_notice_sent, status_changed, cost_set, note ...
                $table->text('note')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sweepstakes_delivery_logs');
    }
};
