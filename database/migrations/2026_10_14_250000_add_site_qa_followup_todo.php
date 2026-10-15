<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 2026-10-14 사이트 점검(qa/SITE_QA_2026-10-14.md)에서 나온, 사람이 결정해야 하는 후속 일을 할 일 목록에 한 줄로 올린다.
// 같은 제목이 이미 있으면 건너뜀(여러 번 돌려도 안전), 데이터 삭제 없음.
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $title = '사이트 점검 후속(10/14 보고서): 메뉴에서 뺀 서비스 주소, 약관, 수집 콘텐츠, 공동구매 마감';
        if (DB::table('admin_todos')->where('title', $title)->exists()) return;
        $now = now();
        DB::table('admin_todos')->insert([
            'title' => $title, 'category' => '결정 필요', 'priority' => 'high', 'status' => 'todo',
            'sort_order' => (int) DB::table('admin_todos')->max('sort_order') + 10, 'done_at' => null, 'created_at' => $now, 'updated_at' => $now,
            'detail' => "qa/SITE_QA_2026-10-14.md 의 B(결정 필요) 항목 요약:\n"
                . "1) /games·/elder·/poker7 가 주소로 직접 열림 — 특히 /elder 는 실제로 안 가는 자동 전화를 약속함 → 홈으로 이동 처리할지 결정\n"
                . "2) 이용약관에 게임(제24조)·서비스 목록의 게임/숏츠/음악이 있음 → 약관 수정 여부\n"
                . "3) 수집·복사 콘텐츠: 장터 수집 상품, 번역 Q&A, 뉴스 본문 전문, 업소록의 비한인 업체와 영어 업종 → 처리 방침\n"
                . "4) 더미 글(예: 커뮤니티 #90) 공개 중\n"
                . "5) 공동구매 마감이 지나도 '모집중' 유지, 자동 마감·환불 작업 없음(돈 관련이라 승인 후)\n"
                . "6) 글·구인·장터·업소 등록에 전용 횟수 제한 없음(권장: 글 분당 5회·일 50회)\n"
                . "7) 검증 없는 쓰기 함수 목록(업소·이력서·쇼핑 후기·구인 지원 등) — 로그인 점검 때 함께 확인",
        ]);
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
