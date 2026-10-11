<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 13라운드에서 고친 결제 통계 항목을 할 일 목록에서 완료로 표시한다(여러 번 돌려도 안전).
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $note = '매출 기준 한 곳(App\\\\Support\\\\Revenue)으로 통일: 대시보드·결제/오더·매출/결제 현황 모두 포인트 구매 완료 + 직접 결제 청구액-환불액, 이달·오늘은 애틀랜타 기준. 환불은 보너스 포함 지급 포인트(points_purchased)를 그대로 회수함을 시험으로 확인. 직접 결제(전단)는 환불 버튼 대신 "전단 관리에서 중지·반려 → 남은 시간 자동 환불" 안내.';
        DB::table('admin_todos')->where('title', 'like', '결제/오더 통계와 매출/결제 현황 기준 통일%')->where('status', '!=', 'done')
            ->update(['status' => 'done', 'done_at' => now(), 'updated_at' => now(),
                'detail' => DB::raw("CONCAT(COALESCE(detail, ''), '\n[완료 10/11] " . addslashes($note) . "')")]);
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
