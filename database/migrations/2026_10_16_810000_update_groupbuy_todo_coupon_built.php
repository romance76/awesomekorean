<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// T1·쿠폰 할 일에 구현된 내용(완료·수락, 조기 마감 vs 공구 취소, 쿠폰, 이름 가리기)을 덧붙인다. 같은 메모가 이미 있으면 건너뜀, 데이터 삭제 없음.
return new class extends Migration
{
    private const MARK = '[10/16 쿠폰·완료 구현]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;
        $note = "\n\n" . self::MARK . "\n"
            . "- 만든 것: 개설자의 완료·수락(총액 수수료 − 낸 디파짓 결제), 쿠폰 발급(쿠폰형), 마이페이지 '내 쿠폰'(QR·번호·확인 코드 4자리), 가게 확인 페이지(/groupbuy/coupon/번호, PIN 4자리·틀리면 잠금), 양쪽 동의 사용(매장 3분·온라인 24시간), 고객 확인 코드 대체 확인, 온라인 사용 요청, 유효기간 한 번 연장, 10분 안 되돌리기(양쪽 동의), 신고(관리자 보안/신고에 접수), 만료 정리·응답 시간 초과 취소·만료 3일 전 알림(30분마다).\n"
            . "- 마감 방식 두 가지: ① 조기 마감하고 완료 — 마감일 전이거나 사람이 덜 모였어도 참가자가 1명 이상이면 지금 모집을 닫고 현재 참가자까지 공동구매 가격 적용(수수료·쿠폰 발급·참가자 정보 열람은 완료와 같음). ② 공구 취소 — 모든 참여 취소, 참가자 포인트 환불, 디파짓 환불 안 함, 참가자 전원에게 '피치 못할 사정으로 취소' 안내(알림+이메일, 개설자 한마디 덧붙이기).\n"
            . "- 참가자 이름은 완료 + 수수료 결제 전에는 공개 목록('참여자')과 개설자 화면('참가자 1')에서 모두 가림. 완료 후 개설자에게만 이름·전화·이메일 제공.\n"
            . "- 참여할 때 정보 제공 동의(필수) 체크, 동의 시각 기록.\n"
            . "- 아직 안 한 것: 전국 쿠폰의 매장 사용(추후), 개설자 쇼핑몰 연동(할인 코드 자동 발급·사용 확인, 2단계), 사용 후 개설자 마케팅 이용 감시. 카드 결제가 필요한 수락(총액이 커서 수수료가 디파짓보다 큰 경우)의 실제 결제 시험.";
        foreach (['T1 공동구매 구조 변경 (참가 무료 · 오픈 수수료 · 참가자 명단)', '공동구매 쿠폰 사용 방식 설계 (전국 쿠폰형)'] as $title) {
            $row = DB::table('admin_todos')->where('title', $title)->first();
            if (!$row || str_contains((string) $row->detail, self::MARK)) continue;
            DB::table('admin_todos')->where('id', $row->id)->update(['detail' => (string) $row->detail . $note, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
