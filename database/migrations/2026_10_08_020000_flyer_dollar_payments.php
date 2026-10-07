<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NEW 전면광고를 포인트 선결제 → 달러 카드 결제(승인 시 청구)로 전환.
 *  - flyer_ads.payment_method : points(기존 행) | card(앞으로). card 인 행은 total_price/slot.price 가 "센트".
 *  - flyer_ads.payment_id     : payments.id (card)
 *  - status 에 awaiting_payment(카드 입력 중, 슬롯을 잠깐 잡아둠) 가 생겨 길이를 늘림
 *  - 시간당 가격 설정은 숫자가 그대로 센트가 됨 (기존 100P ≈ $1 이라 60P = 60¢) — 값은 바꾸지 않고 라벨만 정정
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('flyer_ads', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
            $table->string('payment_method', 10)->default('points')->after('total_price');
            $table->unsignedBigInteger('payment_id')->nullable()->after('payment_method');
        });

        $labels = [
            'flyer_price_national' => ['전면광고 시간당 가격 · 전국 (센트¢, 100 = $1)', '전국 노출 1시간 기본 가격. 60 = $0.60. 24시간 × 7일 ≈ $100'],
            'flyer_price_state'    => ['전면광고 시간당 가격 · 내 지역=주 (센트¢, 100 = $1)', '해당 주 방문자에게만 노출되는 1시간 기본 가격. 30 = $0.30'],
        ];
        foreach ($labels as $key => [$label, $desc]) {
            DB::table('point_settings')->where('key', $key)->update(['label' => $label, 'description' => $desc, 'updated_at' => now()]);
        }

        if (!DB::table('point_settings')->where('key', 'flyer_min_order_cents')->exists()) {
            DB::table('point_settings')->insert([
                'category' => 'spend', 'key' => 'flyer_min_order_cents', 'label' => '전면광고 최소 결제 금액 (센트¢, 100 = $1)',
                'value' => '500', 'description' => '신청 합계가 이 금액보다 작으면 신청할 수 없어요(카드 수수료 때문). 500 = $5',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('flyer_ads', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_id']);
        });
    }
};
