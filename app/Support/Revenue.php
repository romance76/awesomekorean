<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * 매출 계산 기준 한 곳 — "매출/결제 현황"(AdminRevenueController)과 "결제/오더"(AdminController::payments)가 같은 숫자를 내도록.
 *  - 포인트 구매: status=completed 의 amount (환불되면 refunded 라 빠짐)
 *  - 직접 결제(전단·경품 의뢰): status=captured/refunded 의 amount - refunded_amount (청구 뒤 남은 금액)
 * 카드 보류(authorized)는 아직 매출이 아님. 달·날짜 경계는 미국 동부시간(애틀랜타) 기준.
 */
class Revenue
{
    public const TZ = 'America/New_York';

    public const NET_SQL = "CASE
        WHEN kind = 'points' AND status = 'completed' THEN amount
        WHEN kind <> 'points' AND status IN ('captured','refunded') THEN amount - refunded_amount
        ELSE 0 END";

    /** 이번 달 1일 0시(애틀랜타)를 UTC 로 */
    public static function monthStartUtc(): Carbon
    {
        return Carbon::now(self::TZ)->startOfMonth()->utc();
    }
}
