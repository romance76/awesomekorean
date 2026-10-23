<?php

namespace App\Support;

use App\Models\FlyerAd;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 전면광고 광고주 특권 메일(무료) — 승인된 전면광고를 수신 동의 회원에게 자동 발송(qa/EMAIL_AD_PLAN.md 3-A).
 * 규칙(확정):
 *  - 광고 기간을 시작일부터 7일 단위로 나눠 "각 주의 마지막 날"에 1회. 3주 광고면 7·14·21일째 3번, 마지막 주가 짧으면 종료일.
 *  - 받는 사람: 전국 광고는 동의 회원 전체, 주 광고는 그 주 + 인접 주 + 광고주가 추가한 주의 회원(주를 모르는 회원은 전국 광고만).
 *  - 회원 1명이 받는 전면광고 메일은 주 1통 이하(최근 6일 안에 받았으면 건너뜀). 몰린 광고는 한 통에 최대 3건으로 묶고, 남은 것은 다음 날 이후로 미룬다.
 *  - 월요일은 정기 소식 메일이 가는 날이라 쉬고 다음 날 보낸다. 받는 사람 현지 시각 오전 10시.
 *  - 미룬 몫은 원래 날짜로부터 13일 안에서만 보내고, 광고가 끝나면 더는 보내지 않는다.
 */
class FlyerMailer
{
    public const LOCAL_HOUR = 10;
    public const GRACE_DAYS = 13;
    public const MAX_PER_MAIL = 3;

    /** 이 광고의 "주 마지막 날" 목록(Y-m-d) */
    public static function dueDates(FlyerAd $ad): array
    {
        if (!$ad->start_date || !$ad->end_date) return [];
        $s = $ad->start_date->copy()->startOfDay();
        $e = $ad->end_date->copy()->startOfDay();
        $out = [];
        for ($d = $s->copy()->addDays(6); $d->lte($e); $d->addDays(7)) $out[] = $d->toDateString();
        if ((($s->diffInDays($e)) + 1) % 7 !== 0) $out[] = $e->toDateString();   // 마지막 주가 7일보다 짧으면 종료일에
        return array_values(array_unique($out));
    }

    /** 받는 주 목록(null 이면 전국) */
    public static function audienceStates(FlyerAd $ad): ?array
    {
        $key = strtoupper(trim((string) $ad->region_key));
        if ($ad->scope === 'national' || $key === 'ALL' || $key === '') return null;
        $extra = array_map('strtoupper', (array) ($ad->extra_states ?: []));
        return array_values(array_unique(array_merge(StateNeighbors::neighbors($key), $extra)));
    }

    /** 이 광고를 받을 수 있는 회원(동의 + 이메일 인증 + 지역) */
    public static function audienceQuery(FlyerAd $ad)
    {
        $q = EmailConsent::eligibleQuery();
        $states = self::audienceStates($ad);
        if ($states !== null) $q->whereIn('state', $states);
        return $q;
    }

    /** 오늘 이 회원에게 보낼 몫 목록. [['ad'=>FlyerAd,'due'=>[날짜들]], …] 가장 오래된 순, 최대 3건 */
    public static function pendingFor(User $u, string $localDate): array
    {
        $ads = FlyerAd::where('status', 'approved')->whereDate('start_date', '<=', $localDate)->whereDate('end_date', '>=', $localDate)->get();
        if ($ads->isEmpty()) return [];
        $userState = strtoupper(trim((string) $u->state));
        $sent = DB::table('email_flyer_sends')->where('user_id', $u->id)->whereIn('flyer_ad_id', $ads->pluck('id'))->get()->groupBy('flyer_ad_id');
        $floor = Carbon::parse($localDate)->subDays(self::GRACE_DAYS)->toDateString();
        $items = [];
        foreach ($ads as $ad) {
            $states = self::audienceStates($ad);
            if ($states !== null && ($userState === '' || !in_array($userState, $states, true))) continue;
            $done = ($sent[$ad->id] ?? collect())->pluck('due_date')->map(fn($d) => substr((string) $d, 0, 10))->all();
            $due = array_values(array_filter(self::dueDates($ad), fn($d) => $d <= $localDate && $d >= $floor && !in_array($d, $done, true)));
            if ($due) $items[] = ['ad' => $ad, 'due' => $due];
        }
        usort($items, fn($a, $b) => strcmp($a['due'][0], $b['due'][0]));
        return array_slice($items, 0, self::MAX_PER_MAIL);
    }

    /** 최근 6일 안에 전면광고 메일을 받았는지(주 1통 상한) */
    public static function receivedThisWeek(int $userId): bool
    {
        return DB::table('email_flyer_sends')->where('user_id', $userId)->where('status', 'sent')->where('sent_at', '>=', now()->subDays(6))->exists();
    }

    /** 관리자 화면용 발송 예정표 */
    public static function schedule(int $limit = 20): array
    {
        $today = now('America/New_York')->toDateString();
        return FlyerAd::where('status', 'approved')->whereDate('end_date', '>=', $today)->orderBy('start_date')->limit($limit)->get()->map(function ($ad) use ($today) {
            $due = self::dueDates($ad);
            $next = collect($due)->first(fn($d) => $d >= $today);
            return [
                'id' => $ad->id, 'title' => $ad->title, 'scope' => $ad->scope === 'national' ? '전국' : strtoupper((string) $ad->region_key) . ' + 인접',
                'start' => $ad->start_date?->toDateString(), 'end' => $ad->end_date?->toDateString(), 'due_dates' => $due, 'next_due' => $next,
                'recipients' => self::audienceQuery($ad)->count(),
                'sent' => DB::table('email_flyer_sends')->where('flyer_ad_id', $ad->id)->where('status', 'sent')->count(),
            ];
        })->all();
    }
}
