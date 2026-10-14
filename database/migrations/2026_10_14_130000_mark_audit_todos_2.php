<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 2026-10-14 코드 점검 2차: 이미 코드에 들어 있는 항목은 완료로, 확인했지만 남은 일이 있는 항목은 메모만 남긴다.
// 제목이 같은(LIKE) 항목만 대상, 메모에 표시가 있으면 건너뜀(여러 번 돌려도 안전), 데이터 삭제 없음.
return new class extends Migration
{
    private const MARK = '[10/14 점검2]';

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('admin_todos')) return;

        // [제목 LIKE, 완료 여부, 메모]
        $items = [
            ['관리자 알림을 누르면 관리자 처리 화면으로 이동%', true, 'utils/notifRoute.js 가 알림의 data.url(/admin/...)로 바로 이동시키고, 예전 알림은 종류별 표(신고·배너·전단·클레임·이벤트 증빙·경품 지연)로 보냄. NavBar 알림 패널과 알림 페이지가 같은 규칙을 씀.'],
            ['반복 일정·가입 보너스 실제 동작 시험%', false, '코드 확인: 반복 규칙(단위·요일·날짜·시각)을 고치면 다음 실행 시각을 새 규칙으로 다시 계산함(AdminSweepstakesAutomationController::updateSchedule). 남은 것: 운영 화면에서 실제 한 번 돌려 보는 시험(사람 확인 필요).'],
            ['오래된 pending 결제 자동 정리%', false, '코드 확인: 서버에서 할인 금액을 계산하는 곳은 패키지 결제(PaymentController, currentDiscount(package))뿐이고, 광고 할인(applies_to_ads)은 화면 표시용 공개 API(PricingPromotionController::publicActive)에서만 나옴 — 광고 결제·포인트 차감 계산에서 쓰는 곳은 찾지 못함. 광고 할인이 실제로 적용되는지 운영 화면에서 확인하고, 안 되면 적용하거나 표시를 숨길지 결정 필요. pending 결제 자동 정리 작업은 아직 없음.'],
        ];

        foreach ($items as [$like, $done, $note]) {
            $rows = DB::table('admin_todos')->where('title', 'like', $like)->get(['id', 'detail', 'status']);
            foreach ($rows as $r) {
                if (str_contains((string) $r->detail, self::MARK)) continue;
                $update = ['updated_at' => now(), 'detail' => trim(($r->detail ?? '') . "\n" . self::MARK . ' ' . $note)];
                if ($done && $r->status !== 'done') { $update['status'] = 'done'; $update['done_at'] = now(); }
                DB::table('admin_todos')->where('id', $r->id)->update($update);
            }
        }
    }

    public function down(): void
    {
        // 데이터 보호: 할 일 목록을 되돌리지 않는다.
    }
};
