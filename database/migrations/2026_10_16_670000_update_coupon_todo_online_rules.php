<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 쿠폰 사용 방식 할 일에 온라인 사용 확정 사항을 덧붙인다. 같은 메모가 이미 있으면 건너뜀, 데이터 삭제 없음.
return new class extends Migration
{
    private const MARK = '[10/16 온라인 쿠폰 확정]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $row = DB::table('admin_todos')->where('title', '공동구매 쿠폰 사용 방식 설계 (전국 쿠폰형)')->first();
        if (!$row || str_contains((string) $row->detail, self::MARK)) return;
        DB::table('admin_todos')->where('id', $row->id)->update([
            'detail' => (string) $row->detail . "\n\n" . self::MARK . "\n"
                . "- 온라인 사용 방식 확정: 개설자가 등록 때 사용 주소·사용 방법(필요하면 자기 할인 코드)을 입력 → 고객이 '온라인에서 사용'을 누르면 주소·방법과 긴 쿠폰 번호(+개설자 할인 코드)를 보여줌 → 고객이 개설자 사이트에서 주문하며 메모에 쿠폰 번호를 적음 → 개설자가 자기 주문 확인 후 내 공동구매에서 쿠폰 번호(+PIN) 입력 → 고객에게 '맞나요?' → 맞다고 하면 사용 완료.\n"
                . "- 온라인 확인 응답 시간은 24시간(앱 알림·이메일 발송). 시간 안에 응답이 없으면 사용 요청이 취소되고 쿠폰은 미사용으로 남음. (매장 사용은 3분 그대로)\n"
                . "- 개설자가 입력한 할인 코드는 쿠폰 화면에 같이 보여줌.\n"
                . "- 전국 쿠폰 공동구매는 우선 온라인 사용만 허용. 매장 사용은 추후(등록 화면·서버에서 전국은 온라인만 받도록 구현할 것).\n"
                . "- 개설자 쇼핑몰 연동(할인 코드 자동 발급·사용 확인)은 2단계.\n"
                . "- 정리: 쿠폰 번호는 긴 번호(8자리 안팎, 온라인 주문 메모·확인용)와 확인 코드 4자리(매장에서 고객 폰이 안 될 때)를 따로 둔다. 구현은 카드 결제 시험(T4) 이후.",
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
