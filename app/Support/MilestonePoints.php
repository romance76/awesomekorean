<?php

namespace App\Support;

use App\Models\PointLog;
use App\Models\User;

/**
 * 게시판별 포인트 설정 2단계: 완료/참여형 보상(판매완료·클럽 신규가입·이벤트
 * 참가·공동구매 참여/완료·업소 클레임 승인 등). WritePoints(글쓰기)와는
 * 별개의 액션 카테고리라 공유 풀 대신 액션별 독립 설정값 + 독립 하루 한도를
 * 쓴다. 한도 추적은 PointLog.type을 액션 키로 재사용 — 스키마 변경 없음.
 */
class MilestonePoints
{
    public static function award(User $user, string $settingKey, string $modelClass, int $modelId, string $reason, ?string $dailyCapKey = null): void
    {
        $amount = PointRules::get($settingKey, 0);
        if ($amount <= 0) return;

        if ($dailyCapKey) {
            $dailyCap = PointRules::get($dailyCapKey, 0);
            if ($dailyCap > 0) {
                $todayCount = PointLog::where('user_id', $user->id)
                    ->whereDate('created_at', today())
                    ->where('type', $settingKey)
                    ->count();
                if ($todayCount >= $dailyCap) return;
            }
        }

        $user->addPoints($amount, $reason, $settingKey, ['type' => $modelClass, 'id' => $modelId]);
    }
}
