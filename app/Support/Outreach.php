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

    public const DEFAULTS = [
        'ko' => [
            'subject' => '어썸코리안이 새로 문을 열었어요 - 많은 이용 부탁드립니다',
            'body' => "안녕하세요, 미국 한인 커뮤니티 어썸코리안입니다.\n\n어썸코리안이 새로 문을 열었습니다. 많은 이용 부탁드립니다.\n\n이곳에서는 이런 것을 할 수 있어요.\n- 한인 업소록: 우리 동네 한인 업체를 찾고, 내 가게도 무료로 알릴 수 있어요\n- 구인구직 · 중고장터 · 부동산: 한인끼리 믿고 거래하고 일자리를 찾아요\n- 공동구매 · 이벤트: 한인 업체의 할인과 경품 소식을 모아 봐요\n- 커뮤니티 · 모임 · Q&A: 미국 생활 궁금증을 물어보고 이웃을 만나요\n- 생활정보(MONEY) · 뉴스 · 레시피 · 음악: 보험, 세금, 혜택 같은 생활 정보를 쉽게 정리해 드려요\n\n미국 한인 커뮤니티 1등 사이트가 되는 것이 어썸코리안의 목표입니다. 한인 사업체와 단체 여러분도 편하게 이용해 보세요.\n\n감사합니다.",
            'button' => '어썸코리안 둘러보기',
        ],
        'en' => [
            'subject' => 'Awesome Korean has just launched - we would love to have you',
            'body' => "Hello, this is Awesome Korean, a community site for Koreans living in the U.S.\n\nWe have just opened our doors, and we would love for you to take a look.\n\nHere is what you can do on the site:\n- Business directory: find Korean businesses near you, and list your own for free\n- Jobs, marketplace and real estate: trade and find work within the community\n- Group buys and events: see deals and giveaways from Korean businesses\n- Community, clubs and Q&A: ask questions about life in the U.S. and meet neighbors\n- Guides (MONEY), news, recipes and music: easy-to-follow info on insurance, taxes, benefits and more\n\nOur goal is to become the number one Korean community site in the U.S. Korean businesses and organizations are warmly welcome.\n\nThank you.",
            'button' => 'Visit Awesome Korean',
        ],
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

    /** 고친 문구에 기본값을 겹친 최종 문구. $lang 은 ko|en */
    public static function texts(string $lang): array
    {
        $saved = json_decode(self::setting(self::SETTING_TEXTS) ?: '{}', true) ?: [];
        return array_merge(self::DEFAULTS[$lang], array_filter($saved[$lang] ?? [], fn($v) => is_string($v) && trim($v) !== ''));
    }

    public static function saveTexts(string $lang, array $values): void
    {
        $saved = json_decode(self::setting(self::SETTING_TEXTS) ?: '{}', true) ?: [];
        foreach (['subject', 'body', 'button'] as $k) {
            $v = trim((string) ($values[$k] ?? ''));
            if ($v === '' || $v === self::DEFAULTS[$lang][$k]) unset($saved[$lang][$k]);
            else $saved[$lang][$k] = $v;
        }
        self::save(self::SETTING_TEXTS, json_encode($saved, JSON_UNESCAPED_UNICODE));
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

    public static function payload(string $email, ?bool $isTest = false, ?string $lang = null): array
    {
        $lang = $lang ?: self::lang();
        $langs = $lang === 'both' ? ['ko', 'en'] : [$lang];
        $urls = self::unsubscribeUrls($email);
        $blocks = [];
        foreach ($langs as $l) $blocks[] = ['lang' => $l, 'texts' => self::texts($l), 'parts' => self::bodyParts(self::texts($l)['body'])];
        $subjects = array_map(fn($l) => self::texts($l)['subject'], $langs);
        $addr = WeeklyDigest::postalAddress();
        return [
            'subject' => '(광고) ' . implode(' / ', $subjects),
            'blocks' => $blocks, 'langs' => $langs, 'email' => $email, 'isTest' => (bool) $isTest,
            'siteUrl' => rtrim(url('/'), '/'), 'logoUrl' => WeeklyDigest::logo(),
            'unsubscribeUrl' => $urls['page'], 'unsubscribeApi' => $urls['api'],
            'address' => $addr !== '' ? $addr : '(우편 주소 미설정 — 시험용 메일입니다 / Postal address not set — test email)',
        ];
    }

    /** 본문을 문단·글머리표로 나눈다: 빈 줄 = 문단 구분, "- " 로 시작하는 줄 = 글머리표 */
    public static function bodyParts(string $body): array
    {
        $parts = [];
        foreach (preg_split('/\R/u', trim($body)) as $line) {
            $line = trim($line);
            if ($line === '') { $parts[] = ['type' => 'gap']; continue; }
            if (preg_match('/^[-•]\s+(.*)$/u', $line, $m)) {
                $last = count($parts) - 1;
                if ($last >= 0 && $parts[$last]['type'] === 'list') $parts[$last]['items'][] = $m[1];
                else $parts[] = ['type' => 'list', 'items' => [$m[1]]];
            } else {
                $parts[] = ['type' => 'p', 'text' => $line];
            }
        }
        return array_values(array_filter($parts, fn($p) => $p['type'] !== 'gap'));
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
