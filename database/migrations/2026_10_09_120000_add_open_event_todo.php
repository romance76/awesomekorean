<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 소프트 오픈(10/15) 전에 오픈 이벤트 화면에서 "적용"을 눌러야 한다는 할 일을 남긴다. 이미 있으면 건드리지 않는다.
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;

        $title = '오픈 이벤트 적용 (소프트 오픈 10/15 ~ 11/30)';
        if (DB::table('admin_todos')->where('title', $title)->exists()) return;

        $now = now();
        DB::table('admin_todos')->insert([
            'title' => $title,
            'detail' => "관리자 › 광고/가격 › 오픈 이벤트 에서 '오픈 이벤트 사용'을 체크하고 '적용하기'를 누르면 기간(미국 동부 날짜) 동안 자동 적용되고 끝나면 원래대로 돌아옴.\n"
                . "구성: 포인트 적립·가입 보너스 2배 / 채팅방 개설 무료 / 장터·부동산 사진 5장까지 무료 / 끌어올리기·상위노출·광고 90% 할인 / 달러 결제(NEW 전단) 90% 할인 / 포인트 구매 +10% 보너스.\n"
                . "확인: 저장 후 아래 '적용 미리보기' 표와 사이트 상단 안내 띠가 보임. 10/14까지 켜 두면 10/15 00:00(미국 동부)에 시작.",
            'category' => '운영',
            'priority' => 'high',
            'status' => 'todo',
            'sort_order' => (int) DB::table('admin_todos')->max('sort_order') + 10,
            'done_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // 진행 상황 기록이라 되돌리지 않음
    }
};
