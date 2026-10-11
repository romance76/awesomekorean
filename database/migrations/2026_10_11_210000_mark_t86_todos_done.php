<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 관리자 알림 딥링크 · 비상 스위치 · 시스템 상태 카드를 만든 뒤 할 일 목록에서 완료로 표시한다(여러 번 돌려도 안전, 데이터 삭제 없음).
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;

        $items = [
            ['관리자 시스템 화면에 상태 카드%', '시스템 화면 맨 위와 휴대폰 홈에 상태 카드(최고관리자 전용): 백그라운드 작업 대기·실패, 자동 작업 실행기(스케줄러) 마지막 실행, 저장공간, 메모리, 최근 배포. 실패 작업이 있거나 저장공간 85% 초과, 실행기가 3분 넘게 멈추면 빨간 표시와 쉬운 설명. GET /api/admin/system-status (30초 캐시, 값마다 따로 보호).'],
            ['관리자 알림에서 처리 화면으로%', '신고 접수·광고 신청·NEW 전단 신청·업소 소유권 신청·이벤트 완료 인증·쇼핑 리뷰 승인/신고·안 보낸 경품 알림을 누르면 해당 관리자 처리 화면(필터 적용)이 바로 열림(알림 data.url). 휴대폰 홈 "오늘 할 일" 카드도 필터 쿼리 포함 링크. 비상 스위치 3개(가입 보너스 지급 멈추기 · 경품 반복 일정 전체 멈추기 · 음악·뉴스·숏츠 자동 수집 멈추기)를 시스템 화면과 휴대폰 홈에 추가(확인창 + 감사 로그, 최고관리자 전용). 캐시 초기화는 시스템 화면의 기존 버튼 그대로. 건별 시트가 자동으로 열리지는 않고 목록까지만 열림.'],
        ];

        foreach ($items as [$like, $note]) {
            $rows = DB::table('admin_todos')->where('title', 'like', $like)->where('status', '!=', 'done')->get(['id', 'detail']);
            foreach ($rows as $r) {
                DB::table('admin_todos')->where('id', $r->id)->update([
                    'status' => 'done', 'done_at' => now(), 'updated_at' => now(),
                    'detail' => trim(($r->detail ?? '') . "\n[완료 10/11] " . $note),
                ]);
            }
        }
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
