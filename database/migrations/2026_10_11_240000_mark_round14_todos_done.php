<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 14라운드(설정 토글·알림 딥링크·비상 스위치·시스템 상태·수집 큐화·인보이스 1단계) 배포 완료 반영.
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $now = now();
        DB::table('admin_todos')->whereIn('id', [85, 86, 87, 88])->where('status', '!=', 'done')
            ->update(['status' => 'done', 'done_at' => $now, 'updated_at' => $now]);

        // 테스트 운영자·더미 계정 정리 항목에 music/shorts 자동 삭제 결정 메모 추가 대신,
        // 별도 항목으로 Kay 결정을 기록(삭제하지 않고 상태만 남김).
        if (!DB::table('admin_todos')->where('title', 'like', '%music/shorts 자동 삭제%')->exists()) {
            $sort = (int) DB::table('admin_todos')->max('sort_order');
            DB::table('admin_todos')->insert([
                'title' => '참고: music/shorts 자동 삭제(365일/30일) 동작 — Kay 결정으로 유지',
                'detail' => "수집 큐화(할 일 #87) 중 발견: music:fetch/shorts:fetch 가 오래된 시스템 콘텐츠를 삭제하는 기존 동작이 수집 데이터 보호 규칙과 상충 가능성이 있어 Kay 에게 확인 → 2026-10-11 '그대로 유지'로 결정. 코드 변경 없음.",
                'category' => '참고', 'priority' => 'low', 'status' => 'done',
                'sort_order' => $sort + 10, 'done_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 지우지 않는다.
    }
};
