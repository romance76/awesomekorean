<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 12라운드에서 고친 반복 일정 항목을 할 일 목록에서 완료로 표시한다(여러 번 돌려도 안전).
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $note = '반복 규칙(단위·요일·날짜·시각)을 바꾸면 다음 실행 시각 재계산, 첫 시작 시각 형식 오류·과거 시각 422, 이미 만든 횟수보다 작은 반복 횟수 거절, 중지·완료 일정 수정 차단. 소유권 거절 사유 1000자 제한. 경품 날짜: 마감이 시작보다 앞서거나 지난 마감으로 진행중 생성 차단. 회원 이벤트 보상 포인트는 설정값(user_event_reward_max, 기본 100P)까지. 응모 기간 중 추첨은 서버가 한 번 막고 화면에서 다시 확인.';
        DB::table('admin_todos')->where('title', 'like', '반복 일정 수정 시 다음 실행 시각 재계산%')->where('status', '!=', 'done')
            ->update(['status' => 'done', 'done_at' => now(), 'updated_at' => now(),
                'detail' => DB::raw("CONCAT(COALESCE(detail, ''), '\n[완료 10/11] " . addslashes($note) . "')")]);
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
