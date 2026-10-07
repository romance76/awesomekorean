<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Entry 획득 경로 확장 — 출석체크 외에 사이트 활동 보상 추가.
 * 모두 entry_settings 에서 조정 가능하고 0 이면 해당 보상이 꺼진다.
 * (Entry 는 돈으로 살 수 없고 활동으로만 얻는다 — Point 와 교환 경로 없음)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'entry_activity_progress')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('entry_activity_progress')->default(0);
            });
        }

        $rows = [
            ['email_verify_bonus', '이메일 인증 완료 Entry 보너스', '1', '이메일 인증을 마치는 순간 1회 지급 (0 = 끔)'],
            ['activity_required_count', '활동 Entry: 필요한 작성 횟수', '10', '글/댓글/답변/리뷰 등 포인트가 지급된 작성이 이 횟수에 도달하면 Entry 1개 지급 후 0부터 다시 시작 (0 = 끔)'],
            ['activity_daily_max', '활동 Entry: 하루 최대 지급', '1', '활동 보상으로 하루에 받을 수 있는 Entry 최대 개수'],
            ['milestone_bonus', '완료 보상 Entry (건당)', '1', '장터 판매완료·부동산 거래완료·채용확정·공동구매 완료·업소 소유권 승인 시 지급 (0 = 끔)'],
            ['milestone_daily_max', '완료 보상 Entry: 하루 최대 지급', '1', '완료 보상으로 하루에 받을 수 있는 Entry 최대 개수'],
        ];
        foreach ($rows as [$key, $label, $value, $desc]) {
            if (!DB::table('entry_settings')->where('key', $key)->exists()) {
                DB::table('entry_settings')->insert([
                    'key' => $key, 'label' => $label, 'value' => $value, 'description' => $desc,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // no-op: 지급 이력/잔액과 얽혀 있어 되돌리지 않음
    }
};
