<?php

namespace App\Support;

use App\Mail\OutreachMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * 손님 메일(소개 메일): 관리자가 붙여 넣은 주소로 어썸코리안 소개 메일을 noreply 주소로 보낸다.
 *  - 한 주소에는 기본적으로 한 번만 보낸다(이미 보낸 주소는 다시 넣어도 건너뜀).
 *  - 메일 속 수신 거부 페이지에서 확인하면 email_suppressions 에 영구 기록되고, 이후 어떤 경로로 다시 넣어도 걸러진다.
 *  - 이미 가입한 회원이 메일 수신을 끈 적이 있으면 그 주소도 건너뛴다.
 *  - 실제 발송은 ① 우편 주소 저장 ② "실제 발송" 스위치 ON 이 모두 있어야 열린다(WeeklyDigest 와 같은 우편 주소를 쓴다).
 *  - 발송은 스케줄러(mail:outreach, 매분)가 하루 한도 안에서 조금씩 나눠 보낸다(새 도메인 워밍업·서버 부담 방지).
 */
class Outreach
{
    public const SETTING_LIVE = 'outreach_live';
    public const SETTING_CAP = 'outreach_daily_cap';
    public const SETTING_LANG = 'outreach_lang';          // ko | en | both
    public const SETTING_TEXTS = 'outreach_texts';        // 고친 제목·본문(JSON)
    public const DEFAULT_CAP = 100;                        // 하루 최대 발송 수(새 도메인은 적게 시작해 서서히 늘린다)

    /** 메일 한 통을 이루는 문구. features 는 카드 목록(아이콘·제목·설명), body 는 에디터로 쓰는 HTML */
    public const DEFAULTS = [
        'ko' => [
            'subject' => '어썸코리안이 새로 문을 열었어요 - 미국 한인 생활의 모든 것',
            'hero_title' => "미국 한인 생활의 모든 것,\n어썸코리안이 문을 열었습니다",
            'hero_sub' => '찾고, 거래하고, 물어보고, 함께 나누는 미국 한인 커뮤니티',
            'body' => '<p>안녕하세요, 어썸코리안입니다.</p><p>미국에 사시는 한인 여러분이 필요한 <b>정보와 이웃, 일자리와 거래</b>를 한곳에서 편하게 찾으실 수 있도록 어썸코리안을 새로 열었습니다.</p><p>한인 업체와 단체 여러분께는 <b>우리 동네 손님을 만나는 홍보 공간</b>이 되어 드립니다. 아래에서 어떤 일을 할 수 있는지 살펴보세요.</p>',
            'features_title' => '이런 것을 할 수 있어요',
            'features' => [
                ['icon' => '🏪', 'title' => '한인 업소록', 'desc' => '우리 동네 한인 식당·병원·미용실·변호사를 찾고, 내 가게도 등록해 알릴 수 있어요.', 'link' => '/directory'],
                ['icon' => '💼', 'title' => '구인구직', 'desc' => '한인 업체의 채용 공고를 한눈에 보고, 사람을 구하는 글도 쉽게 올려요.', 'link' => '/jobs'],
                ['icon' => '🛒', 'title' => '중고장터 · 부동산', 'desc' => '이웃끼리 믿고 사고팔고, 렌트·매물 정보도 함께 찾아요.', 'link' => '/market'],
                ['icon' => '🎁', 'title' => '공동구매 · 이벤트', 'desc' => '한인 업체의 특별 할인과 경품 이벤트 소식을 한곳에서 확인해요.', 'link' => '/groupbuy'],
                ['icon' => '💬', 'title' => '커뮤니티 · Q&A', 'desc' => '비자, 세금, 육아, 맛집까지 미국 생활 궁금증을 물어보고 답해 주세요.', 'link' => '/community'],
                ['icon' => '💡', 'title' => '생활정보 MONEY', 'desc' => '보험·세금·정부 혜택을 화면 캡처와 함께 따라 하기 쉽게 정리했어요.', 'link' => '/money'],
                ['icon' => '📰', 'title' => '뉴스 · 한인 유튜버', 'desc' => '미국 한인 소식과 한인 유튜버 채널을 한곳에 모아 봐요.', 'link' => '/news'],
                ['icon' => '🍳', 'title' => '레시피 · 음악', 'desc' => '집밥 레시피와 즐겨 듣는 한국 음악까지, 쉬어 가는 공간도 있어요.', 'link' => '/recipes'],
            ],
            'styles' => [],
            'icon_pos' => 'inline',
            'card_cols' => '2',
            'button_link' => '',
            'promo_link' => '/ad-apply',
            'promo_show' => '1',
            'promo_badge' => '🎉 오픈 기념',
            'promo_title' => '2026년은 광고가 무료예요 — 미리 자리를 잡으세요',
            'promo_text' => '오픈을 기념해 2026년 동안 광고를 무료로 열어 두었어요. 지역별 광고 자리는 한정되어 있으니 미리 신청해 두세요.',
            'promo_button' => '무료 광고 신청하기',
            'promo_note' => '※ 무료 광고는 오픈 기념 혜택으로, 운영 상황에 따라 기간·범위·조건이 달라지거나 종료될 수 있어요. 최신 내용은 광고 신청 페이지를 기준으로 해요.',
            'button' => '어썸코리안 둘러보기',
            'closing' => "미국 한인 커뮤니티 1등,\n그것이 어썸코리안이 꿈꾸는 모습입니다.\n여러분의 첫 방문이 큰 힘이 됩니다. 감사합니다.",
        ],
        'en' => [
            'subject' => 'Awesome Korean is now open - everything for Korean life in the U.S.',
            'hero_title' => "Everything for Korean life in the U.S.\nAwesome Korean has just opened",
            'hero_sub' => 'Find, trade, ask and share - the community for Koreans across America',
            'body' => '<p>Hello, and welcome to Awesome Korean.</p><p>We have opened a new home for Koreans living in the U.S. to find <b>information, neighbors, jobs and deals</b> in one place.</p><p>For Korean businesses and organizations, it is a <b>place to reach local customers</b>. Take a look at what you can do below.</p>',
            'features_title' => 'What you can do here',
            'features' => [
                ['icon' => '🏪', 'title' => 'Business directory', 'desc' => 'Find Korean restaurants, clinics, salons and lawyers near you - and list your own business too.', 'link' => '/directory'],
                ['icon' => '💼', 'title' => 'Jobs', 'desc' => 'Browse openings from Korean businesses, or post a listing to find the right person.', 'link' => '/jobs'],
                ['icon' => '🛒', 'title' => 'Marketplace & real estate', 'desc' => 'Buy and sell with trusted neighbors, and look for rentals and listings.', 'link' => '/market'],
                ['icon' => '🎁', 'title' => 'Group buys & events', 'desc' => 'See special deals and giveaways from Korean businesses in one place.', 'link' => '/groupbuy'],
                ['icon' => '💬', 'title' => 'Community & Q&A', 'desc' => 'Ask and answer questions about visas, taxes, parenting, restaurants and everyday life.', 'link' => '/community'],
                ['icon' => '💡', 'title' => 'MONEY guides', 'desc' => 'Insurance, taxes and government benefits, explained step by step with screenshots.', 'link' => '/money'],
                ['icon' => '📰', 'title' => 'News & YouTubers', 'desc' => 'Korean-American news and Korean YouTuber channels, collected in one place.', 'link' => '/news'],
                ['icon' => '🍳', 'title' => 'Recipes & music', 'desc' => 'Home-cooking recipes and Korean music for when you want to relax.', 'link' => '/recipes'],
            ],
            'styles' => [],
            'icon_pos' => 'inline',
            'card_cols' => '2',
            'button_link' => '',
            'promo_link' => '/ad-apply',
            'promo_show' => '1',
            'promo_badge' => '🎉 Grand opening',
            'promo_title' => 'Advertising is free in 2026 - reserve your spot early',
            'promo_text' => 'To celebrate our opening, advertising is free throughout 2026. Ad spots are limited in each region, so apply early.',
            'promo_button' => 'Apply for a free ad',
            'promo_note' => '* Free advertising is an opening promotion. Its period, scope and conditions may change or end depending on how we operate. The ad application page always has the latest details.',
            'button' => 'Visit Awesome Korean',
            'closing' => "Our goal: the number one Korean community in the U.S.\nYour first visit means a lot to us. Thank you!",
        ],
    ];

    private const STR_LIMITS = ['subject' => 150, 'hero_title' => 120, 'hero_sub' => 200, 'features_title' => 60, 'button' => 40, 'closing' => 600, 'promo_show' => 1, 'promo_badge' => 40, 'promo_title' => 100, 'promo_text' => 400, 'promo_button' => 40, 'promo_note' => 300, 'card_cols' => 1, 'button_link' => 200, 'promo_link' => 200, 'icon_pos' => 6];
    private const ALLOWED_TAGS = '<p><br><b><strong><i><em><u><h2><h3><ul><ol><li><a><span><div><blockquote>';
    /** 메일 클라이언트는 CSS 클래스를 믿을 수 없어서, 에디터가 만든 태그마다 기본 모양을 인라인으로 입힌다(관리자가 고른 색·정렬이 뒤에 붙어 우선) */
    private const EMAIL_STYLES = [
        'p' => 'margin:0 0 14px;font-size:15px;line-height:1.85;color:#3a3a3a;',
        'div' => 'font-size:15px;line-height:1.85;color:#3a3a3a;',
        'h2' => 'margin:22px 0 10px;font-size:21px;line-height:1.4;color:#111;font-weight:800;',
        'h3' => 'margin:18px 0 8px;font-size:17px;line-height:1.45;color:#111;font-weight:800;',
        'ul' => 'margin:0 0 14px;padding:0 0 0 22px;font-size:15px;line-height:1.85;color:#3a3a3a;',
        'ol' => 'margin:0 0 14px;padding:0 0 0 22px;font-size:15px;line-height:1.85;color:#3a3a3a;',
        'li' => 'margin:0 0 4px;',
        'a' => 'color:#FC226B;font-weight:700;text-decoration:underline;',
        'blockquote' => 'margin:0 0 14px;padding:10px 16px;border-left:4px solid #FC226B;background:#fff5f8;color:#555;font-size:15px;line-height:1.8;',
    ];

    public static function setting(string $key): string { return WeeklyDigest::setting($key); }
    public static function save(string $key, string $value): void { WeeklyDigest::save($key, $value); }

    public static function lang(): string
    {
        $l = self::setting(self::SETTING_LANG);
        return in_array($l, ['ko', 'en', 'both'], true) ? $l : 'ko';
    }

    public static function dailyCap(): int
    {
        $v = (int) self::setting(self::SETTING_CAP);
        return $v > 0 ? min($v, 2000) : self::DEFAULT_CAP;
    }

    /** 글상자 하나의 위치·간격·정렬·글자 크기 조절값을 인라인 style 문자열로(여백은 어느 메일 앱에서나 먹는 margin 으로만 표현) */
    public static function styleCss($styles, string $key): string
    {
        $v = is_array($styles) ? ($styles[$key] ?? null) : null;
        if (!is_array($v)) return '';
        $css = '';
        if (!empty($v['align'])) $css .= 'text-align:' . $v['align'] . ';';
        foreach (['mt' => 'margin-top', 'mb' => 'margin-bottom', 'ml' => 'margin-left', 'mr' => 'margin-right', 'fs' => 'font-size'] as $f => $prop) {
            if (isset($v[$f])) $css .= $prop . ':' . max(0, (int) $v[$f]) . 'px;';   // 음수 여백은 Gmail 이 무시해서 쓰지 않는다
        }
        return $css;
    }

    /** 링크는 "/사이트안주소" 또는 http(s):// 주소만 허용(그 밖의 값은 비움) */
    public static function cleanLink(string $link): string
    {
        $link = mb_substr(trim($link), 0, 200);
        return preg_match('#^(/[^\s]*|https?://[^\s]+)$#i', $link) ? $link : '';
    }

    /** 메일에 넣을 완전한 주소. 비어 있거나 잘못되면 $fallback */
    public static function resolveLink(?string $link, string $siteUrl, string $fallback = ''): string
    {
        $link = self::cleanLink((string) $link);
        if ($link === '') return $fallback;
        return str_starts_with($link, '/') ? rtrim($siteUrl, '/') . $link : $link;
    }

    /** 에디터·화면에서 들어온 값을 모양에 맞게 정리(길이 제한, HTML 정리). 보내지 않은 항목은 건드리지 않는다 */
    public static function normalize(array $raw): array
    {
        $out = [];
        foreach (self::STR_LIMITS as $k => $max) {
            if (array_key_exists($k, $raw)) $out[$k] = mb_substr(trim((string) $raw[$k]), 0, $max);
        }
        foreach (['button_link', 'promo_link'] as $k) if (isset($out[$k])) $out[$k] = self::cleanLink($out[$k]);
        if (array_key_exists('styles', $raw) && is_array($raw['styles'])) {
            $styles = [];
            foreach ($raw['styles'] as $k => $v) {
                if (!is_string($k) || !is_array($v) || !preg_match('/^(hero_title|hero_sub|features_title|closing|promo_title|promo_text|promo_note|f\.\d{1,2}\.(title|desc))$/', $k)) continue;
                $one = [];
                if (in_array($v['align'] ?? '', ['left', 'center', 'right'], true)) $one['align'] = $v['align'];
                foreach (['mt' => [0, 120], 'mb' => [0, 120], 'ml' => [0, 200], 'mr' => [0, 200], 'fs' => [9, 60]] as $f => $range) {
                    if (isset($v[$f]) && is_numeric($v[$f])) $one[$f] = max($range[0], min($range[1], (int) $v[$f]));
                }
                if ($one) $styles[$k] = $one;
            }
            $out['styles'] = $styles;
        }
        if (isset($out['icon_pos']) && !in_array($out['icon_pos'], ['top', 'inline'], true)) $out['icon_pos'] = '';
        if (isset($out['card_cols']) && !in_array($out['card_cols'], ['2', '3', '4'], true)) $out['card_cols'] = '';
        if (array_key_exists('body', $raw)) $out['body'] = self::cleanHtml((string) $raw['body']);
        if (array_key_exists('features', $raw) && is_array($raw['features'])) {
            $list = [];
            foreach (array_slice($raw['features'], 0, 8) as $f) {
                if (!is_array($f)) continue;
                $title = mb_substr(trim((string) ($f['title'] ?? '')), 0, 60);
                if ($title === '') continue;
                $list[] = ['icon' => mb_substr(trim((string) ($f['icon'] ?? '')), 0, 8), 'title' => $title, 'desc' => mb_substr(trim((string) ($f['desc'] ?? '')), 0, 220), 'link' => self::cleanLink((string) ($f['link'] ?? '')), 'h' => max(0, min(600, (int) ($f['h'] ?? 0)))];
            }
            $out['features'] = $list;
        }
        return $out;
    }

    /** 고친 문구에 기본값을 겹친 최종 문구. $lang 은 ko|en. $override 가 있으면 저장된 값 대신 그것을 쓴다(저장 전 미리보기) */
    public static function texts(string $lang, ?array $override = null): array
    {
        if ($override !== null) {
            $mine = self::normalize($override);
        } else {
            $saved = json_decode(self::setting(self::SETTING_TEXTS) ?: '{}', true) ?: [];
            $mine = is_array($saved[$lang] ?? null) ? $saved[$lang] : [];
        }
        $out = self::DEFAULTS[$lang];
        foreach (array_keys($out) as $k) {
            $v = $mine[$k] ?? null;
            if (is_array($v) ? count($v) > 0 : (is_string($v) && trim($v) !== '')) $out[$k] = $v;
        }
        return $out;
    }

    /** 고친 문구 저장 — 기본값과 같거나 비어 있으면 저장하지 않아(기본 문구가 나중에 개선되면 따라가도록) 지운다 */
    public static function saveTexts(string $lang, array $values): void
    {
        $saved = json_decode(self::setting(self::SETTING_TEXTS) ?: '{}', true) ?: [];
        $norm = self::normalize($values);
        foreach (array_keys(self::DEFAULTS[$lang]) as $k) {
            if (!array_key_exists($k, $norm)) continue;
            $v = $norm[$k];
            if ($v === '' || $v === [] || $v === self::DEFAULTS[$lang][$k]) unset($saved[$lang][$k]);
            else $saved[$lang][$k] = $v;
        }
        self::save(self::SETTING_TEXTS, json_encode($saved, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 에디터 HTML 정리: 허용한 태그만 남기고, 속성은 href(http·https·mailto)와 안전한 style(색·글자 크기·정렬 등)만 남긴다.
     * $forEmail 이면 태그마다 메일용 기본 모양(인라인 style)을 먼저 입힌다.
     */
    public static function cleanHtml(string $html, bool $forEmail = false): string
    {
        $html = trim($html);
        if ($html === '') return '';
        if (!str_contains($html, '<')) {   // 에디터를 쓰기 전의 일반 글: 줄 단위 문단으로
            $html = '<p>' . implode('</p><p>', array_map(fn($l) => e($l), preg_split('/\R+/u', $html))) . '</p>';
        }
        $html = preg_replace('#<(script|style|iframe|object|embed)\b.*?</\1>#is', '', $html);
        $html = strip_tags($html, self::ALLOWED_TAGS);
        $html = preg_replace_callback('#<(/?)([a-z0-9]+)([^>]*)>#i', function ($m) use ($forEmail) {
            $tag = strtolower($m[2]);
            if ($m[1] === '/') return '</' . $tag . '>';
            if ($tag === 'br') return '<br>';
            $href = null; $style = '';
            if ($tag === 'a' && preg_match('/\bhref\s*=\s*"([^"]*)"/i', $m[3], $a)) {
                $u = trim(html_entity_decode($a[1]));
                if (preg_match('#^(https?://|mailto:)#i', $u)) $href = $u;
            }
            if (preg_match('/\bstyle\s*=\s*"([^"]*)"/i', $m[3], $a)) $style = self::cleanStyle(html_entity_decode($a[1]));
            $all = ($forEmail ? (self::EMAIL_STYLES[$tag] ?? '') : '') . $style;
            $out = '<' . $tag;
            if ($href !== null) $out .= ' href="' . htmlspecialchars($href, ENT_QUOTES) . '"';
            if ($all !== '') $out .= ' style="' . htmlspecialchars($all, ENT_QUOTES) . '"';
            return $out . '>';
        }, $html);
        return trim((string) $html);
    }

    /** style 속성에서 안전한 것만 남김 */
    private static function cleanStyle(string $style): string
    {
        $color = '/^(#[0-9a-f]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*[0-9.]+\s*)?\)|[a-z]{3,20})$/i';
        $rules = [
            'color' => $color, 'background-color' => $color,
            'font-size' => '/^\d{1,2}(\.\d+)?(px|pt|em|rem)$/i',
            'text-align' => '/^(left|center|right|justify)$/i',
            'font-weight' => '/^(normal|bold|[1-9]00)$/i',
            'font-style' => '/^(normal|italic)$/i',
            'text-decoration' => '/^(none|underline|line-through)$/i',
        ];
        $out = '';
        foreach (explode(';', $style) as $decl) {
            $parts = explode(':', $decl, 2);
            if (count($parts) !== 2) continue;
            $prop = strtolower(trim($parts[0])); $val = trim($parts[1]);
            if (isset($rules[$prop]) && preg_match($rules[$prop], $val)) $out .= $prop . ':' . $val . ';';
        }
        return $out;
    }

    public static function isLive(): bool { return self::lockReason() === null; }

    public static function lockReason(): ?string
    {
        if (WeeklyDigest::postalAddress() === '') return '우편 주소가 아직 없어서 실제 발송이 잠겨 있어요(법적으로 광고 메일 맨 아래에 실제 우편 주소가 필요해요). 관리자 › 메일 발송 화면에서 저장하세요.';
        if (self::setting(self::SETTING_LIVE) !== '1') return '"실제 발송" 스위치가 꺼져 있어요.';
        return null;
    }

    // ─── 주소 처리 ───

    /** 붙여 넣은 글에서 이메일 주소만 뽑는다(줄바꿈·쉼표·세미콜론·공백 구분, <> 와 따옴표 제거). 반환: [유효한 주소(중복 제거), 잘못된 조각 목록] */
    public static function parse(string $raw): array
    {
        $valid = []; $invalid = [];
        foreach (preg_split('/[\s,;]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) as $piece) {
            $e = mb_strtolower(trim($piece, " \t\n\r\0\x0B<>\"'()[]"));
            if ($e === '' || !str_contains($e, '@')) continue;   // "홍길동 <a@b.com>" 처럼 섞인 이름 조각은 조용히 무시, @ 가 있는데 형식이 틀린 것만 "잘못된 주소"로 알림
            if (filter_var($e, FILTER_VALIDATE_EMAIL) && mb_strlen($e) <= 190) $valid[$e] = true;
            else $invalid[] = mb_substr($piece, 0, 60);
        }
        return [array_keys($valid), array_values(array_unique($invalid))];
    }

    public static function isSuppressed(string $email): bool
    {
        return DB::table('email_suppressions')->where('email', mb_strtolower($email))->exists();
    }

    /** 이미 가입한 회원 중 메일 수신을 끈 적이 있는 주소 */
    public static function memberOptedOut(array $emails): array
    {
        if (!$emails) return [];
        return User::query()->whereIn('email', $emails)
            ->whereExists(fn($q) => $q->select(DB::raw(1))->from('email_consent_logs')->whereColumn('email_consent_logs.user_id', 'users.id')->where('action', 'revoked'))
            ->where(fn($q) => $q->whereNull('marketing_email_consent')->orWhere('marketing_email_consent', false))
            ->pluck('email')->map(fn($e) => mb_strtolower($e))->all();
    }

    /**
     * 주소 목록을 보낼 대기열에 넣는다. 반환: 개수 요약.
     * 걸러지는 것: 수신 거부 목록 / 수신을 끈 회원 / 이미 보낸 주소(resend=false 일 때) / 이미 대기 중인 주소
     */
    public static function enqueue(string $raw, bool $resend = false): array
    {
        [$emails, $invalid] = self::parse($raw);
        $suppressed = DB::table('email_suppressions')->whereIn('email', $emails)->pluck('email')->all();
        $optedOut = self::memberOptedOut($emails);
        $existing = DB::table('outreach_recipients')->whereIn('email', $emails)->get()->keyBy('email');
        $r = ['found' => count($emails), 'added' => 0, 'requeued' => 0, 'suppressed' => 0, 'member_opted_out' => 0, 'already_sent' => 0, 'already_queued' => 0, 'invalid' => $invalid];
        $now = now();
        foreach ($emails as $e) {
            if (in_array($e, $suppressed, true)) { $r['suppressed']++; continue; }
            if (in_array($e, $optedOut, true)) { $r['member_opted_out']++; continue; }
            $row = $existing[$e] ?? null;
            if (!$row) {
                DB::table('outreach_recipients')->insert(['email' => $e, 'status' => 'queued', 'created_at' => $now, 'updated_at' => $now]);
                $r['added']++;
            } elseif ($row->status === 'queued') {
                $r['already_queued']++;
            } elseif ($row->status === 'sent' && !$resend) {
                $r['already_sent']++;
            } else {   // 실패했던 주소는 다시 시도, 보낸 주소는 "다시 보내기"를 고른 경우만
                DB::table('outreach_recipients')->where('id', $row->id)->update(['status' => 'queued', 'error' => null, 'updated_at' => $now]);
                $r['requeued']++;
            }
        }
        return $r;
    }

    /** 수신 거부 기록(이미 있어도 오류 없이). 가입 회원이면 회원 쪽 수신 동의도 함께 끈다 */
    public static function suppress(string $email, string $source = 'unsubscribe_link', ?string $ip = null): void
    {
        $email = mb_strtolower(trim($email));
        DB::table('email_suppressions')->insertOrIgnore(['email' => $email, 'source' => $source, 'ip' => $ip, 'created_at' => now()]);
        DB::table('outreach_recipients')->where('email', $email)->where('status', 'queued')->delete();
        $user = User::where('email', $email)->first();
        if ($user && $user->marketing_email_consent) EmailConsent::revoke($user, 'outreach_unsubscribe_link', $ip);
    }

    // ─── 메일 만들기 ───

    /** 서명된 수신 거부 주소 두 가지: api(메일 앱의 원클릭 POST 용) / page(메일 속 버튼이 여는 확인 페이지) */
    public static function unsubscribeUrls(string $email): array
    {
        $api = URL::signedRoute('email.outreach-unsubscribe', ['email' => mb_strtolower($email)]);   // 만료 없음
        $query = (string) parse_url($api, PHP_URL_QUERY);
        return ['api' => $api, 'page' => url('/outreach-unsubscribe') . '?' . $query];
    }

    /** $override: ['ko' => [...], 'en' => [...]] 저장 전 문구로 미리보기할 때만 */
    public static function payload(string $email, ?bool $isTest = false, ?string $lang = null, ?array $override = null, bool $edit = false): array
    {
        $lang = $lang ?: self::lang();
        $langs = $lang === 'both' ? ['ko', 'en'] : [$lang];
        $urls = self::unsubscribeUrls($email);
        $blocks = []; $subjects = [];
        foreach ($langs as $l) {
            $t = self::texts($l, $override[$l] ?? null);
            $blocks[] = ['lang' => $l, 't' => $t, 'bodyHtml' => self::cleanHtml($t['body'], !$edit)];
            $subjects[] = $t['subject'];
        }
        $addr = WeeklyDigest::postalAddress();
        return [
            'subject' => '(광고) ' . implode(' / ', $subjects),
            'blocks' => $blocks, 'langs' => $langs, 'email' => $email, 'isTest' => (bool) $isTest, 'edit' => $edit,
            'siteUrl' => rtrim(url('/'), '/'), 'logoUrl' => WeeklyDigest::logo(),
            'unsubscribeUrl' => $urls['page'], 'unsubscribeApi' => $urls['api'],
            'address' => $addr !== '' ? $addr : '(우편 주소 미설정 — 시험용 메일입니다 / Postal address not set — test email)',
        ];
    }

    // ─── 보내기 ───

    public static function sentToday(): int
    {
        $start = now('America/New_York')->startOfDay()->utc();
        return (int) DB::table('outreach_recipients')->where('last_sent_at', '>=', $start)->count();
    }

    /** 대기 중인 주소를 하루 한도 안에서 최대 $limit 통 보낸다. 반환: [보냄, 실패, 건너뜀] */
    public static function runBatch(int $limit = 10): array
    {
        $sent = 0; $failed = 0; $skipped = 0;
        if (!self::isLive()) return [0, 0, 0];
        $room = self::dailyCap() - self::sentToday();
        $take = min($limit, $room);
        if ($take <= 0) return [0, 0, 0];
        $rows = DB::table('outreach_recipients')->where('status', 'queued')->orderBy('id')->limit($take)->get();
        foreach ($rows as $row) {
            // 보내기 직전에 한 번 더 확인 — 그 사이 수신 거부했거나 지워졌을 수 있다
            if (self::isSuppressed($row->email)) {
                DB::table('outreach_recipients')->where('id', $row->id)->delete();
                $skipped++; continue;
            }
            // 겹쳐 실행돼도 두 번 보내지 않게: queued 인 것을 내가 먼저 집어간 경우만 진행
            $claimed = DB::table('outreach_recipients')->where('id', $row->id)->where('status', 'queued')->update(['status' => 'sent', 'updated_at' => now()]);
            if (!$claimed) continue;
            try {
                Mail::to($row->email)->send(new OutreachMail(self::payload($row->email)));
                DB::table('outreach_recipients')->where('id', $row->id)->update(['sent_count' => DB::raw('sent_count + 1'), 'last_sent_at' => now(), 'error' => null]);
                $sent++;
            } catch (\Throwable $e) {
                DB::table('outreach_recipients')->where('id', $row->id)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 190)]);
                \Log::warning("[손님 메일] 발송 실패 {$row->email}: " . $e->getMessage());
                $failed++;
            }
            usleep(600000);   // 발송 서비스의 초당 요청 제한 보호
        }
        return [$sent, $failed, $skipped];
    }
}
