<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kay 결정(2026-10-11)
 *  - 이벤트 완료 인증 보상은 Entry: events.reward_entries (비어 있으면 1개, 0 = 보상 없음) 칸 추가
 *  - 내돈내산 리뷰: 작성 규칙을 통과해 공개되면 Entry 자동 지급 — entry_settings 에 review_bonus(1)·review_daily_max(1) 추가
 *  - 일반 회원이 이벤트에 걸 수 있는 완료 보상 Entry 최대 개수: event_reward_entries_user_max(3)
 * 칸·설정만 추가하고 기존 데이터는 건드리지 않는다. 여러 번 돌려도 안전. down() 은 데이터 보호로 아무것도 안 함.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('events') && !Schema::hasColumn('events', 'reward_entries')) {
            Schema::table('events', function (Blueprint $table) {
                $table->unsignedSmallInteger('reward_entries')->nullable()->after('reward_points');
            });
        }

        if (Schema::hasTable('entry_settings')) {
            $rows = [
                ['review_bonus', '내돈내산 리뷰 Entry (건당)', '1', '작성 규칙(글자 수·직접 찍은 사진·구매 확인)을 통과해 공개된 리뷰에 자동 지급 (0 = 끔)'],
                ['review_daily_max', '내돈내산 리뷰 Entry: 하루 최대 지급', '1', '리뷰 보상으로 하루에 받을 수 있는 Entry 최대 개수'],
                ['event_reward_entries_user_max', '이벤트 완료 보상 Entry: 일반 회원 최대 개수', '3', '일반 회원이 만든 이벤트에 걸 수 있는 완료 인증 보상 Entry 최대 개수 (운영진은 100)'],
            ];
            foreach ($rows as [$key, $label, $value, $desc]) {
                if (!DB::table('entry_settings')->where('key', $key)->exists()) {
                    DB::table('entry_settings')->insert([
                        'key' => $key, 'label' => $label, 'value' => $value, 'description' => $desc,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
            Cache::forget('entry_settings_kv');
        }
    }

    public function down(): void
    {
        // 데이터 보호: 칸·설정을 지우지 않는다.
    }
};
