<?php

namespace App\Support;

use App\Models\BannerAd;
use App\Models\InfoPost;
use App\Models\MoneyPost;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 월요일 정기 소식 메일(qa/EMAIL_AD_PLAN.md) — 한국어·영어 두 버전.
 *  - 본문: 이번 주 생활정보 3개 + 게시판별 최근 7일 새 글 간단 목록
 *  - 맨 아래: "우리 동네 광고" 띠 — 사이드(양옆) 배너 광고 중 받는 회원의 주·인접 주에 맞는 것을 무작위로 최대 6개(정지 배너)
 *  - 언어: 회원이 정한 언어(users.language, 'en' 이면 영문)로 보낸다. 관리자 화면에서 두 버전을 따로 미리보기·시험 발송·문구 수정.
 * 실제 발송은 두 가지가 모두 갖춰져야 열린다: ① 우편 주소 저장(법적으로 광고 메일에 필수) ② 관리자가 "실제 발송"을 켬.
 */
class WeeklyDigest
{
    public const SETTING_LIVE = 'weekly_digest_live';        // '1' 이면 실제 발송 허용
    public const SETTING_ADDRESS = 'mail_postal_address';    // 메일 맨 아래에 적는 우편 주소
    public const SETTING_NOTICE_DIGEST = 'digest_mail_notice';  // 소식 메일 안내 문구(한국어). 영문은 뒤에 _en
    public const SETTING_NOTICE_FLYER = 'flyer_mail_notice';    // 전면광고 메일 안내 문구(한국어). 영문은 뒤에 _en
    public const SETTING_TEXTS = 'mail_texts';                    // 미리보기 안에서 직접 고친 문구들(JSON)
    public const SEND_HOUR = 9;                                   // 받는 사람 현지 시각(월요일 오전 9시)

    /** 메일 안에서 관리자가 직접 고칠 수 있는 문구와 기본값(종류 digest|flyer, 영문은 _en). 법적 안내(수신 동의 설명·수신 거부·우편 주소)는 넣지 않는다 */
    public const TEXT_DEFAULTS = [
        'digest' => ['intro' => '이번 주에 올라온 미국 한인 생활 정보를 모았어요.', 'ad_title' => '우리 동네 광고'],
        'flyer' => ['intro' => '{지역}의 한인 업체 {종류} 소식을 알려 드려요.'],   // {지역}·{종류} 는 광고마다 자동으로 바뀜
        'digest_en' => ['intro' => 'Here is a roundup of what was posted for the Korean community in the U.S. this week.', 'ad_title' => 'Local ads'],
        'flyer_en' => ['intro' => 'News from a Korean business in {region}: {kind}.'],   // {region}·{kind} 는 광고마다 자동으로 바뀜
    ];

    public static function normLang(?string $l): string { return $l === 'en' ? 'en' : 'ko'; }
    public static function langOf(?User $u): string { return self::normLang($u?->language); }
    private static function sfx(string $lang): string { return $lang === 'en' ? '_en' : ''; }

    public static function noticeKey(string $type, string $lang): string
    {
        return ($type === 'flyer' ? self::SETTING_NOTICE_FLYER : self::SETTING_NOTICE_DIGEST) . self::sfx($lang);
    }

    /** 저장된 문구에 기본값을 겹친 최종 문구. $type 은 digest|flyer 또는 digest_en|flyer_en */
    public static function texts(string $type): array
    {
        $saved = json_decode(self::setting(self::SETTING_TEXTS) ?: '{}', true) ?: [];
        return array_merge(self::TEXT_DEFAULTS[$type] ?? [], array_filter($saved[$type] ?? [], fn($v) => is_string($v) && trim($v) !== ''));
    }

    /** 문구 하나 저장(빈 값이면 기본값으로 되돌림) */
    public static function saveText(string $type, string $key, string $value): void
    {
        $saved = json_decode(self::setting(self::SETTING_TEXTS) ?: '{}', true) ?: [];
        $value = trim($value);
        if ($value === '' || $value === (self::TEXT_DEFAULTS[$type][$key] ?? null)) unset($saved[$type][$key]);
        else $saved[$type][$key] = $value;
        self::save(self::SETTING_TEXTS, json_encode($saved, JSON_UNESCAPED_UNICODE));
    }

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

    /** 메일 안의 고정 문구(언어별). 수정 가능한 문구는 TEXT_DEFAULTS, 여기는 틀을 이루는 말들 */
    public static function labels(string $lang): array
    {
        if ($lang === 'en') {
            return [
                'title_sub' => "This week's Korean community news", 'greeting' => 'Hi :name,',
                'test_banner' => 'This is a test email (not sent to members)', 'sample_note' => ' · Sample content',
                'info_h' => '📘 Life info this week', 'info_more' => 'More life info →',
                'boards_h' => '📋 New posts this week', 'boards_sub' => 'Posts from the last 7 days', 'new_posts' => ':n new', 'more' => 'See more →',
                'ad_label' => '(Ad)', 'flyer_sub' => 'Local ad news', 'flyer_btn' => 'View details',
                'footer_consent' => 'You are receiving this message because you agreed to receive Awesome Korean news and promotional emails.',
                'unsub_btn' => 'Unsubscribe', 'unsub_note' => 'One click and you will stop receiving these emails.',
                'unsub_sample' => '(Test link — in real member emails this turns the emails off right away.)',
                'subject_digest' => "[Awesome Korean] This week's Korean news · :date", 'subject_flyer' => '[Awesome Korean Ad] :kind · :title',
                'test_prefix' => '[Test] ', 'member' => 'there',
            ];
        }
        return [
            'title_sub' => '이번 주 한인 소식', 'greeting' => ':name님, 안녕하세요!',
            'test_banner' => '시험 발송 메일입니다 (회원에게 나가는 메일이 아니에요)', 'sample_note' => ' · 내용은 샘플입니다',
            'info_h' => '📘 이번 주 생활정보', 'info_more' => '생활정보 더 보기 →',
            'boards_h' => '📋 이번 주 게시판 새 글', 'boards_sub' => '최근 7일 동안 올라온 글이에요', 'new_posts' => '새 글 :n개', 'more' => '더 보기 →',
            'ad_label' => '(광고)', 'flyer_sub' => '우리 동네 광고 소식', 'flyer_btn' => '자세히 보기',
            'footer_consent' => '이 메일은 어썸코리안에서 광고·소식 메일 수신에 동의하신 회원께 보내는 광고성 정보입니다.',
            'unsub_btn' => '수신 거부', 'unsub_note' => '버튼을 누르면 바로 수신이 꺼져요.',
            'unsub_sample' => '(시험용 링크 — 실제 회원 메일에서는 누르면 바로 수신이 꺼져요.)',
            'subject_digest' => '[어썸코리안] 이번 주 한인 소식 · :date', 'subject_flyer' => '[어썸코리안 광고] :kind · :title',
            'test_prefix' => '[시험] ', 'member' => '회원',
        ];
    }

    private const INFO_CATEGORY_EN = [
        '생활정보' => 'Life', '이민·비자' => 'Immigration & Visa', '세금' => 'Taxes', '금융' => 'Finance', '보험' => 'Insurance', '부동산' => 'Real Estate',
        '교통' => 'Transportation', '교육' => 'Education', '날씨·안전' => 'Weather & Safety', '통신' => 'Telecom', '창업·비즈니스' => 'Business',
    ];

    /**
     * 본문에 넣을 글들.
     *  - info: 이번 주 생활정보 3개(카드). 영문 메일에서도 글 제목·요약은 원문(한국어) 그대로다(번역 글이 없음)
     *  - boards: 게시판(카테고리)별로 최근 7일 동안 올라온 글 수와 제목 몇 개를 간단히 나열
     *    (글이 하나도 없는 게시판은 뺀다. 장터·부동산·구인·동호회는 받는 사람의 주·인접 주 글만, 주를 모르면 전체)
     */
    public static function articles(?string $state = null, string $lang = 'ko'): array
    {
        $en = $lang === 'en';
        $out = ['info' => [], 'boards' => []];
        $since = now()->subDays(7);
        $info = InfoPost::published()->where('published_at', '>=', $since)->orderByDesc('published_at')->limit(3)->get();
        if ($info->count() < 3) $info = InfoPost::published()->orderByDesc('published_at')->limit(3)->get();
        foreach ($info as $p) {
            $out['info'][] = [
                'title' => $p->title, 'tag' => $en ? (self::INFO_CATEGORY_EN[$p->category] ?? $p->category) : $p->category,
                'excerpt' => \Illuminate\Support\Str::limit(trim((string) ($p->excerpt ?: strip_tags((string) $p->body))), 90),
                'url' => url('/info/' . $p->slug), 'image' => $p->cover_image_url ? self::absolute($p->cover_image_url) : null,
            ];
        }

        $st = strtoupper(trim((string) $state));
        $near = $st !== '' ? StateNeighbors::neighbors($st) : [];
        $local = fn($q) => $near ? $q->whereIn('state', $near) : $q;   // 주 컬럼이 있는 게시판만 지역으로 거른다
        $notHidden = fn($q) => $q->where(fn($w) => $w->whereNull('is_hidden')->orWhere('is_hidden', false));
        $L = fn($ko, $e) => $en ? $e : $ko;

        $sections = [
            ['icon' => '💬', 'label' => $L('커뮤니티', 'Community'), 'more' => '/community', 'q' => fn() => $notHidden(\App\Models\Post::query()), 'url' => fn($r) => url('/community/free/' . $r->id)],
            ['icon' => '❓', 'label' => 'Q&A', 'more' => '/qa', 'q' => fn() => $notHidden(\App\Models\QaPost::query()), 'url' => fn($r) => url('/qa/' . $r->id)],
            ['icon' => '💼', 'label' => $L('구인구직', 'Jobs'), 'more' => '/jobs', 'q' => fn() => $local(\App\Models\JobPost::active()), 'url' => fn($r) => url('/jobs/' . $r->id)],
            ['icon' => '🛒', 'label' => $L('중고장터', 'Marketplace'), 'more' => '/market', 'q' => fn() => $local(\App\Models\MarketItem::where(fn($w) => $w->whereNull('source')->orWhere('source', '!=', 'scraped'))->where('status', 'active')), 'url' => fn($r) => url('/market/' . $r->id)],
            ['icon' => '🏠', 'label' => $L('부동산', 'Real Estate'), 'more' => '/realestate', 'q' => fn() => $local(\App\Models\RealEstateListing::active()->where(fn($w) => $w->whereNull('source')->orWhere('source', '!=', 'scraped'))), 'url' => fn($r) => url('/realestate/' . $r->id)],
            ['icon' => '👥', 'label' => $L('동호회(새로 생김)', 'Clubs (new)'), 'more' => '/clubs', 'q' => fn() => $local(\App\Models\Club::where('is_active', true)->where('is_public', true)), 'url' => fn($r) => url('/clubs/' . $r->id), 'titleCol' => 'name'],
            ['icon' => '🤝', 'label' => $L('공동구매', 'Group Buy'), 'more' => '/groupbuy', 'q' => fn() => \App\Models\GroupBuy::approved()->where('status', 'recruiting'), 'url' => fn($r) => url('/groupbuy/' . $r->id)],
            ['icon' => '💰', 'label' => 'MONEY+', 'more' => '/money', 'q' => fn() => $notHidden(MoneyPost::query()), 'url' => fn($r) => url('/money/post/' . $r->id)],
        ];
        foreach ($sections as $sec) {
            try {
                $q = ($sec['q'])()->where('created_at', '>=', $since);
                $count = (clone $q)->count();
                if ($count < 1) continue;
                $col = $sec['titleCol'] ?? 'title';
                $rows = $q->orderByDesc('created_at')->limit(3)->get();
                $out['boards'][] = [
                    'icon' => $sec['icon'], 'label' => $sec['label'], 'count' => $count, 'more' => url($sec['more']),
                    'items' => $rows->map(fn($r) => ['title' => \Illuminate\Support\Str::limit((string) $r->{$col}, 46), 'url' => ($sec['url'])($r)])->all(),
                ];
            } catch (\Throwable $e) {
                \Log::warning('[주간 소식 메일] ' . $sec['label'] . ' 목록을 만들지 못했어요: ' . $e->getMessage());   // 한 게시판 오류가 메일 전체를 막지 않게
            }
        }
        return $out;
    }

    /**
     * 맨 아래 광고 띠에 실을 광고 최대 6개.
     * 후보: 사이드(left/right) 배너 중 진행 중이고, 범위가 전국이거나 받는 회원의 주·인접 주(또는 같은 도시)인 것.
     * 최근 28일 동안 메일에 많이 실린 광고일수록 뽑힐 확률을 낮춰 골고루 돌린다. 한 광고주당 1개만.
     */
    public static function pickAds(?string $state, ?string $city = null, int $max = 6): array
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

        $n = min(count($pool), $max);   // 광고 띠는 최대 6개(후보가 더 적으면 있는 만큼)
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

    /** 실제 광고가 없어도 광고 띠 모양을 볼 수 있게 하는 "샘플 광고"(미리보기·시험 발송 전용, 클릭 링크 없음) */
    public static function sampleAds(string $lang = 'ko'): array
    {
        return array_map(fn($i) => [
            'id' => 0, 'title' => $lang === 'en' ? "Sample ad {$i} (layout preview)" : "샘플 광고 {$i} (모양 확인용)",
            'img' => url("/images/email/sample-ad-{$i}.jpg"), 'href' => null,
        ], [1, 2, 3, 4, 5, 6]);
    }

    /** 수신 거부 링크: 실제 회원이면 진짜 링크, 시험·미리보기(회원 아님)면 모양만 보이는 시험용 링크 */
    private static function unsubUrl(?User $user): string
    {
        return $user ? EmailConsent::unsubscribeUrl($user) : url('/email-unsubscribed?sample=1');
    }

    private static function addressText(string $lang): string
    {
        $addr = self::postalAddress();
        if ($addr !== '') return $addr;
        return $lang === 'en' ? '(Postal address not set — test email)' : '(우편 주소 미설정 — 시험용 메일입니다)';
    }

    private static function dateLabel(string $lang): string
    {
        $d = now('America/New_York');
        return $lang === 'en' ? $d->format('F j, Y') : $d->format('Y년 n월 j일');
    }

    private static function logo(): ?string
    {
        return self::absolute(SiteSetting::where('key', 'logo_url')->value('value') ?: '/images/logo.png?v=20261015');
    }

    /** 메일 한 통에 필요한 값 묶음. $user 가 null 이면 미리보기용. $sample 이면 광고 띠를 샘플 광고로 6개까지 채운다 */
    public static function payload(?User $user, ?string $state = null, ?string $city = null, bool $isTest = false, bool $sample = false, ?string $lang = null): array
    {
        $lang = $lang ? self::normLang($lang) : self::langOf($user);
        $t = self::labels($lang);
        $st = $state ?: ($user?->state);
        $ads = self::pickAds($st, $city ?: $user?->city);
        if ($sample && count($ads) < 6) $ads = array_merge($ads, array_slice(self::sampleAds($lang), 0, 6 - count($ads)));
        return [
            'lang' => $lang, 't' => $t,
            'name' => $user?->nickname ?: $user?->name ?: $t['member'],
            'articles' => self::articles($st, $lang),
            'ads' => $ads,
            'notice' => self::setting(self::noticeKey('digest', $lang)),
            'texts' => self::texts('digest' . self::sfx($lang)),
            'editMode' => false,
            'unsubscribeUrl' => self::unsubUrl($user),
            'unsubIsSample' => !$user,
            'address' => self::addressText($lang),
            'isTest' => $isTest || self::postalAddress() === '',
            'dateLabel' => self::dateLabel($lang),
            'logoUrl' => self::logo(),
            'siteUrl' => url('/'),
        ];
    }

    /** 전면광고 메일 한 통의 값. $ad 가 없으면 샘플 전면광고(미리보기·시험 발송용) */
    public static function flyerPayload(?User $user, ?\App\Models\FlyerAd $ad = null, bool $isTest = false, ?string $lang = null): array
    {
        $lang = $lang ? self::normLang($lang) : self::langOf($user);
        $en = $lang === 'en';
        $t = self::labels($lang);
        $kinds = $en ? ['open' => 'Grand Opening', 'closing' => 'Closing Sale', 'sale' => 'Sale'] : ['open' => '신장개업', 'closing' => '폐업 정리', 'sale' => '세일'];
        $sample = !$ad;
        if ($ad) $region = $ad->scope === 'national' ? ($en ? 'Nationwide' : '전국') : strtoupper((string) $ad->region_key) . ($en ? ' & nearby areas' : ' 및 인접 지역');
        else $region = $en ? 'your area' : '우리 동네';
        $fmt = $en ? 'M j' : 'n월 j일';
        return [
            'lang' => $lang, 't' => $t,
            'name' => $user?->nickname ?: $user?->name ?: $t['member'],
            'sample' => $sample,
            'title' => $ad->title ?? ($en ? 'Sample ad — Grand opening in your neighborhood!' : '샘플 전면광고 — 우리 동네 신장개업!'),
            'kindLabel' => $ad ? ($kinds[$ad->kind] ?? ($en ? 'News' : '소식')) : $kinds['open'],
            'description' => $ad->description ?? ($en ? 'The advertiser\'s description goes here. This paragraph is a sample to show the layout.' : '전면광고를 신청한 광고주의 소개글이 여기에 들어가요. 이 문단은 모양을 보여 주기 위한 샘플입니다.'),
            'phone' => $ad->phone ?? '(000) 000-0000',
            'linkUrl' => $ad ? ($ad->link_url ?: null) : null,
            'image' => $ad ? self::absolute($ad->image_url) : url('/images/email/sample-flyer.jpg'),
            'region' => $region,
            'period' => $ad ? ($ad->start_date?->format($fmt) . ($en ? ' – ' : ' ~ ') . $ad->end_date?->format($fmt)) : ($en ? 'Mon DD – Mon DD' : '○월 ○일 ~ ○월 ○일'),
            'notice' => self::setting(self::noticeKey('flyer', $lang)),
            'texts' => self::texts('flyer' . self::sfx($lang)),
            'editMode' => false,
            'unsubscribeUrl' => self::unsubUrl($user),
            'unsubIsSample' => !$user,
            'address' => self::addressText($lang),
            'isTest' => $isTest || self::postalAddress() === '' || $sample,
            'dateLabel' => self::dateLabel($lang),
            'logoUrl' => self::logo(),
            'siteUrl' => url('/'),
        ];
    }

    /** 관리자 미리보기·시험 발송용 메일 만들기. type: digest(월요일 소식 메일) | flyer(전면광고 메일), lang: ko | en */
    public static function testMailable(string $type, ?User $user, ?string $state, bool $sample, ?string $lang = null): \Illuminate\Mail\Mailable
    {
        $lang = self::normLang($lang);
        if ($type === 'flyer') {
            $ad = \App\Models\FlyerAd::where('status', 'approved')->orderByDesc('approved_at')->first();   // 승인된 실제 전면광고가 있으면 그것으로, 없으면 샘플
            return new \App\Mail\FlyerAdMail(self::flyerPayload($user, $sample ? null : $ad, true, $lang));
        }
        return new \App\Mail\WeeklyDigestMail(self::payload($user, $state, null, true, $sample, $lang));
    }

    private static function absolute(?string $u): ?string
    {
        if (!$u) return null;
        return preg_match('#^https?://#i', $u) ? $u : url($u);
    }
}
