<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 관리자 메뉴 개편 1·2단계와 휴대폰 하단 탭 개편을 끝낸 뒤 할 일 목록에서 완료로 표시하고,
// 3~9단계 일정표에는 진행 상황만 덧붙인다(여러 번 돌려도 안전, 데이터 삭제 없음).
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;

        $done = [
            ['메뉴 개편 1단계%', 'adminMenu.js 하나로 모든 관리자 화면 정의, PC 사이드바 7그룹(대시보드/회원/콘텐츠/광고·매출/홍보·이벤트/신고·문의/시스템), 옛 주소 그대로.'],
            ['메뉴 개편 2단계%', 'ConfirmSheet·SaveBar·DetailPanel·AdmTable 신설, 신고 관리 화면에 먼저 적용(확인창 교체). DetailPanel·AdmTable 은 다음 화면 적용 때 실전 검증.'],
            ['휴대폰 홈%하단 탭%', '하단 5탭(홈/돈/신고·문의/콘텐츠/더보기) + 더보기 허브(회원·홍보이벤트·시스템). 옛 허브 주소는 새 주소로 이동.'],
        ];
        foreach ($done as [$like, $note]) {
            $rows = DB::table('admin_todos')->where('title', 'like', $like)->where('status', '!=', 'done')->get(['id', 'detail']);
            foreach ($rows as $r) {
                DB::table('admin_todos')->where('id', $r->id)->update([
                    'status' => 'done', 'done_at' => now(), 'updated_at' => now(),
                    'detail' => trim(($r->detail ?? '') . "\n[완료 10/11] " . $note),
                ]);
            }
        }

        // 3~9단계는 아직 진행 중 — 끝난 조각만 메모로 남긴다(상태는 그대로).
        $note = "[진행 10/11] 끝난 조각: 보류 메뉴(게임·안심·쇼핑) 숨김 / 매출·결제 통합(결제/오더 화면 탭: 주문·매출 현황·인보이스) / 사이트 설정 분리(2090줄 → 탭별 컴포넌트 9개, 약 655줄). 남은 조각: 회원 패널(DetailPanel 적용), 홍보·이벤트 그룹 화면 통합.";
        $rows = DB::table('admin_todos')->where('title', 'like', '메뉴 개편 3~9단계%')->where('status', '!=', 'done')->get(['id', 'detail']);
        foreach ($rows as $r) {
            if (str_contains((string) $r->detail, '[진행 10/11]')) continue;
            DB::table('admin_todos')->where('id', $r->id)->update([
                'updated_at' => now(),
                'detail' => trim(($r->detail ?? '') . "\n" . $note),
            ]);
        }
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
