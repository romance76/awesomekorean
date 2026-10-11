<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 인보이스(결제 영수증) 1단계 — 새 표 두 개만 추가한다. 기존 표(payments 등)는 건드리지 않는다.
 *
 *  invoices            결제 한 건당 인보이스 한 장(payment_id 가 unique 라 중복 발행 불가).
 *                      금액·이름·이메일은 "발행 시점" 값을 그대로 저장(스냅샷) — 나중에 회원 정보가 바뀌어도 영수증은 그대로.
 *                      금액은 센트(정수). 결제 업체(Stripe/CardPointe)와 무관하게 payments 행만 보고 만든다.
 *  invoice_deliveries  인보이스 메일 발송 이력(자동 발송·관리자 재발송 모두). triggered_by = 'auto' 또는 관리자 회원 번호.
 *
 * 외래키는 일부러 걸지 않는다: 회원이 탈퇴해 payments 행이 지워져도 회계 기록(인보이스)은 남아야 하기 때문.
 * 여러 번 돌려도 안전(이미 있으면 건너뜀). down() 은 데이터를 지우지 않는다(표 삭제 금지 규칙).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_no', 30)->unique();                        // 예: AK-2026-000123
                $table->unsignedBigInteger('payment_id')->nullable()->unique();    // 결제 한 건당 한 장
                $table->unsignedBigInteger('user_id')->index();
                $table->string('status', 10)->default('issued');                   // issued | void
                $table->string('kind', 30)->nullable();                            // points | flyer | event_request (참고용)
                $table->string('currency', 5)->default('USD');
                $table->unsignedBigInteger('subtotal_cents')->default(0);
                $table->unsignedBigInteger('discount_cents')->default(0);
                $table->unsignedBigInteger('total_cents')->default(0);
                $table->json('line_items')->nullable();                            // [{name,name_en,qty,unit_cents,amount_cents}]
                $table->string('billing_name', 100)->nullable();
                $table->string('billing_email', 190)->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->string('void_reason', 255)->nullable();
                $table->timestamps();

                $table->index(['status', 'issued_at'], 'invoices_status_issued_idx');
                $table->index('issued_at', 'invoices_issued_idx');
            });
        }

        if (!Schema::hasTable('invoice_deliveries')) {
            Schema::create('invoice_deliveries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('invoice_id');
                $table->string('to_email', 190)->nullable();
                $table->string('status', 10)->default('queued');                   // queued | sent | failed
                $table->string('error', 255)->nullable();                          // 실패 이유 한 줄
                $table->timestamp('sent_at')->nullable();
                $table->string('triggered_by', 20)->default('auto');               // 'auto' 또는 관리자 회원 번호
                $table->timestamps();

                $table->index(['invoice_id', 'created_at'], 'invoice_deliveries_invoice_idx');
                $table->index('status', 'invoice_deliveries_status_idx');
            });
        }
    }

    public function down(): void
    {
        // 의도적으로 비워 둔다 — 회계 기록이라 롤백으로도 표/데이터를 지우지 않는다.
    }
};
