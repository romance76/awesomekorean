<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 11라운드에서 고친 중간 2건을 할 일 목록에서 완료로 표시한다(제목 앞부분으로 찾음, 여러 번 돌려도 안전).
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $now = now();
        $done = [
            '약관·메뉴·사이트 설정 저장의 남은 검증%' => '약관은 terms/privacy 만(그 외 404), 빈 내용 거부, 60KB 넘으면 안내. 회사 정보·사이트 설정·SEO 는 칸별 형식 검사(이메일·주소·숫자 범위·확장자 목록·날짜, < > 금지)와 정해진 칸만 저장, 바뀐 칸만 검사.',
            '당첨자 %상품 받았어요% 수령 확인 버튼%' => '보낸 뒤 회원 화면에 "받았어요/아직이요" 카드(받았어요 → 수령 확인 + 이력). 경품 관리에 "안 보낸 상품" 탭(여러 추첨 모아 보기, 기다린 날수). 당첨 후 3일 넘게 안 보낸 건이 있으면 매일 오전 10시(애틀랜타) 최고관리자에게 알림.',
        ];
        foreach ($done as $like => $note) {
            DB::table('admin_todos')->where('title', 'like', $like)->where('status', '!=', 'done')
                ->update(['status' => 'done', 'done_at' => $now, 'updated_at' => $now,
                    'detail' => DB::raw("CONCAT(COALESCE(detail, ''), '\n[완료 10/11] " . addslashes($note) . "')")]);
        }
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
