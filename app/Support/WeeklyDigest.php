<?php

namespace App\Support;

use App\Models\BannerAd;
use App\Models\InfoPost;
use App\Models\MoneyPost;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * 월요일 정기 소식 메일(qa/EMAIL_AD_PLAN.md).
 *  - 본문: 이번 주 새 정보 글 + MONEY+ 새 글
 *  - 맨 아래: "우리 동네 광고" 띠 — 사이드(양옆) 배너 광고 중 받는 회원의 주·인접 주에 맞는 것을 무작위로 3~5개(정지 배너)
 * 실제 발송은 두 가지가 모두 갖춰져야 열린다: ① 우편 주소 저장(법적으로 광고 메일에 필수) ② 관리자가 "실제 발송"을 켬.
 */
class WeeklyDigest
{
    public const SETTING_LIVE = 'weekly_digest_live';        // '1' 이면 실제 발송 허용
    public const SETTING_ADDRESS = 'mail_postal_address';    // 메일 맨 아래에 적는 우편 주소
    public const SEND_HOUR = 9;                              // 받는 사람 현지 시각(월요일 오전 9시)

    public static function setting(string $key): string
    {
        return trim((string) SiteSetting::where('key', $key)->value('value'));
    }

    public static function save(string $key, string $value): void
    {
        SiteSetting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'email']);
    }

    public static function postalAddress(): string { return self::setting(self::SETTING_ADDRESS); }

    /** 실제 발송이 막혀 있는 이유(열려 있으면 null) */
    public static function lockReason(): ?string
    {
        if (self::postalAddress() === '') return '우편 주소가 아직 없어서 실제 발송이 잠겨 있어요(법적으로 광고 메일 맨 아래에 실제 우편 주소가 필요해요).';
        if (self::setting(self::SETTING_LIVE) !== '1') return '"실제 발송" 스위치가 꺼져 있어요.';
        return null;
    }

    public static function isLive(): bool { return self::lockReason() === null; }

    /** 주(state) → 현지 시간대. 모르면 동부 */
    public static function timezoneOf(?string $state): string
    {
        $s = strtoupper(trim((string) $state));
        $map = [
            'America/Los_Angeles' => ['CA', 'WA', 'OR', 'NV'],
            'America/Denver' => ['CO', 'UT', 'NM', 'WY', 'MT', 'ID'],
            'America/Phoenix' => ['AZ'],
            'America/Chicago' => ['TX', 'IL', 'MN', 'WI', 'IA', 'MO', 'AR', 'LA', 'MS', 'AL', 'OK', 'KS', 'NE', 'SD', 'ND'],
            'America/Anchorage' => ['AK'],
            'Pacific/Honolulu' => ['HI'],
        ];
        foreach ($map as $tz => $states) if (in_array($s, $states, true)) return $tz;
        return 'America/New_York';
    }

    /** 해당 주를 가리키는 키(월요일 기준) */
    public static function weekKey(?\DateTimeInterface $at = null): string
    {
        $d = \Illuminate\Support\Carbon::instance($at ?: now())->startOfWeek();
        return $d->format('o') . '-W' . $d->format('W');
    }

    /** 본문에 넣을 글들 */
    public static function articles(): array
    {
        $out = ['info' => [], 'money' => []];
        $since = now()->subDays(7);
        $info = InfoPost::published()->where('published_at', '>=', $since)->orderByDesc('published_at')->limit(5)->get();
        if ($info->count() < 3) $info = InfoPost::published()->orderByDesc('published_at')->limit(5)->get();
        foreach ($info as $p) {
            $out['info'][] = [
                'title' => $p->title, 'tag' => $p->category, 'excerpt' => \Illuminate\Support\Str::limit(trim((string) ($p->excerpt ?: strip_tags((string) $p->body))), 90),
                'url' => url('/info/' . $p->slug), 'image' => $p->cover_image_url ? self::absolute($p->cover_image_url) : null,
            ];
        }
        $money = MoneyPost::where(fn($q) => $q->whereNull('is_hidden')->orWhere('is_hidden', false))->orderByDesc('created_at')->limit(3)->get();
        foreach ($money as $m) {
            $out['money'][] = ['title' => $m->title, 'url' => url('/money/post/' . $m->id)];
        }
        return $out;
    }

    /**
     * 맨 아래 광고 띠에 실을 광고 3~5개.
     * 후보: 사이드(left/right) 배너 중 진행 중이고, 범위가 전국이거나 받는 회원의 주·인접 주(또는 같은 도시)인 것.
     * 최근 28일 동안 메일에 많이 실린 광고일수록 뽑힐 확률을 낮춰 골고루 돌린다. 한 광고주당 1개만.
     */
    public static function pickAds(?string $state, ?string $city = null, int $max = 5): array
    {
        $st = strtoupper(trim((string) $state));
        $near = $st !== '' ? StateNeighbors::neighbors($st) : [];
        $cty = mb_strtolower(trim((string) $city));
        $cands = BannerAd::active()->whereIn('position', ['left', 'right'])
            ->whereNotNull('image_url')->where('image_url', '!=', '')->get()
            ->filter(function ($a) use ($near, $cty) {
                if (($a->geo_scope ?? 'all') === 'all') return true;
                $v = trim((string) $a->geo_value);
                if ($a->geo_scope === 'state') return $v !== '' && in_array(strtoupper($v), $near, true);
                return $cty !== '' && mb_strtolower($v) === $cty;   // county·city 광고는 같은 도시일 때만(군 정보는 회원에게 없음)
            })->unique('user_id')->values();
        if ($cands->isEmpty()) return [];

        $recent = DB::table('email_ad_stats')->where('stat_date', '>=', now()->subDays(28)->toDateString())
            ->whereIn('banner_ad_id', $cands->pluck('id'))->groupBy('banner_ad_id')->pluck(DB::raw('SUM(sent)'), 'banner_ad_id');
        $pool = $cands->map(fn($a) => ['ad' => $a, 'w' => 1 / (1 + (int) ($recent[$a->id] ?? 0))])->all();

        $n = min(count($pool), random_int(3, max(3, $max)));
        $picked = [];
        while (count($picked) < $n && $pool) {
            $sum = array_sum(array_column($pool, 'w'));
            $r = mt_rand() / mt_getrandmax() * $sum; $acc = 0; $idx = array_key_last($pool);
            foreach ($pool as $i => $row) { $acc += $row['w']; if ($r <= $acc) { $idx = $i; break; } }
            $picked[] = $pool[$idx]['ad'];
            unset($pool[$idx]);
        }
        return array_map(fn($a) => [
            'id' => $a->id, 'title' => $a->title, 'img' => self::absolute($a->image_url),
            'href' => $a->link_url ? url('/api/email/ad-click/' . $a->id) : null,
        ], $picked);
    }

    /** 이 메일에 실린 광고의 "실린 횟수"를 올린다(시험 발송은 올리지 않음) */
    public static function recordSent(array $ads): void
    {
        $today = now()->toDateString();
        foreach ($ads as $a) {
            DB::table('email_ad_stats')->updateOrInsert(['banner_ad_id' => $a['id'], 'stat_date' => $today], ['sent' => DB::raw('COALESCE(sent,0)+1')]);
        }
    }

    public static function recordClick(int $adId): void
    {
        $today = now()->toDateString();
        DB::table('email_ad_stats')->updateOrInsert(['banner_ad_id' => $adId, 'stat_date' => $today], ['clicks' => DB::raw('COALESCE(clicks,0)+1')]);
    }

    /** 메일 한 통에 필요한 값 묶음. $user 가 null 이면 미리보기용(수신 거부 링크 없음) */
    public static function payload(?User $user, ?string $state = null, ?string $city = null, bool $isTest = false): array
    {
        $st = $state ?: ($user?->state);
        $addr = self::postalAddress();
        return [
            'name' => $user?->nickname ?: $user?->name ?: '회원',
            'articles' => self::articles(),
            'ads' => self::pickAds($st, $city ?: $user?->city),
            'unsubscribeUrl' => $user ? EmailConsent::unsubscribeUrl($user) : null,
            'address' => $addr !== '' ? $addr : '(우편 주소 미설정 — 시험용 메일입니다)',
            'isTest' => $isTest || $addr === '',
            'dateLabel' => now('America/New_York')->format('Y년 n월 j일'),
            'logoUrl' => self::absolute(SiteSetting::where('key', 'logo_url')->value('value') ?: '/images/logo.png?v=20261015'),
            'siteUrl' => url('/'),
        ];
    }

    private static function absolute(?string $u): ?string
    {
        if (!$u) return null;
        return preg_match('#^https?://#i', $u) ? $u : url($u);
    }
}
