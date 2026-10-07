<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NEW 전단 광고 — 라디오 광고처럼 "하루 중 어느 시간대"를 골라 사는 지역별 전단 게시판.
 *  flyer_ads   : 광고주가 올린 전단(이미지) 1건 + 승인 상태
 *  flyer_slots : 시간 단위 예약. (region_key, slot_date, slot_hour) UNIQUE 로 이중 예약을 DB가 막음.
 *                slot_date/slot_hour 는 그 지역(주)의 현지 시각 기준.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('flyer_ads')) {
            Schema::create('flyer_ads', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('title', 80);
                $table->string('kind', 20)->default('open');       // open 신장개업 / closing 폐업정리 / sale 세일 / etc
                $table->string('description', 400)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('link_url', 300)->nullable();
                $table->string('image_url', 300);
                $table->string('scope', 10);                       // national | state
                $table->string('region_key', 8);                   // 'ALL' 또는 주 코드(GA)
                $table->string('status', 12)->default('pending')->index(); // pending | approved | rejected | cancelled
                $table->string('reject_reason', 200)->nullable();
                $table->unsignedInteger('total_price')->default(0);
                $table->unsignedInteger('hours_count')->default(0);
                $table->date('start_date');
                $table->date('end_date');
                $table->unsignedInteger('view_count')->default(0);
                $table->unsignedInteger('click_count')->default(0);
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('flyer_slots')) {
            Schema::create('flyer_slots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('flyer_ad_id')->index();
                $table->string('region_key', 8);
                $table->date('slot_date');
                $table->unsignedTinyInteger('slot_hour');          // 0~23 (현지 시각)
                $table->unsignedInteger('price');
                $table->timestamps();
                $table->unique(['region_key', 'slot_date', 'slot_hour'], 'flyer_slot_unique');
                $table->foreign('flyer_ad_id')->references('id')->on('flyer_ads')->onDelete('cascade');
            });
        }

        $settings = [
            ['flyer_price_national', '전단 광고 시간당 가격 (전국, 포인트)', '60', '전국 노출 1시간 기본 가격. 100P ≈ $1 기준 — 24시간 × 7일 ≈ 약 $100'],
            ['flyer_price_state',    '전단 광고 시간당 가격 (내 지역=주, 포인트)', '30', '해당 주 방문자에게만 노출되는 1시간 기본 가격'],
            ['flyer_peak_pct',       '전단 광고 피크 시간대 가격 (%)', '150', '오후 5시~11시(현지) 가격 = 기본 가격 × 이 값 ÷ 100'],
            ['flyer_night_pct',      '전단 광고 심야 시간대 가격 (%)', '50', '자정~오전 6시(현지) 가격 = 기본 가격 × 이 값 ÷ 100'],
            ['flyer_max_days',       '전단 광고 1회 신청 최대 일수', '14', '한 번에 최대 며칠까지 신청할 수 있는지'],
            ['flyer_window_days',    '전단 광고 예약 가능 기간(일)', '30', '오늘부터 며칠 앞까지 예약할 수 있는지'],
        ];
        foreach ($settings as [$key, $label, $value, $desc]) {
            if (!DB::table('point_settings')->where('key', $key)->exists()) {
                DB::table('point_settings')->insert([
                    'category' => 'spend', 'key' => $key, 'label' => $label, 'value' => $value,
                    'description' => $desc, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // no-op: 결제/예약 이력과 얽혀 있어 되돌리지 않음
    }
};
