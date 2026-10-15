<?php

namespace App\Support;

use App\Models\Event;
use App\Models\MoneyPost;
use App\Models\SiteSetting;
use App\Models\SocialPost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * SNS 자동 게시 대기열에 글을 넣는 곳 (캡션·카드뉴스 만들기 포함).
 *  - MONEY 운영팀 글: 새 글만 자동(social:enqueue), 예전 글은 관리자가 골라서 넣기
 *  - 어썸코리안 이벤트(event_type awesomekorean·sweepstakes): 만들어지면 자동, 우선순위 높게
 *  - 관리자 직접 쓰기
 * 같은 원본+플랫폼은 한 번만 들어간다. 계획서 qa/sns_autopost_plan.md
 */
class SocialQueue
{
    public const SETTINGS_KEY = 'social_autopost';
    public const DEFAULTS = [
        'enabled' => true, 'require_approval' => true, 'instagram_daily' => 2, 'threads_daily' => 3,
        'times' => ['08:00', '12:00', '19:00'], 'hashtags' => '#미국생활 #애틀랜타한인 #미국한인 #어썸코리안',
        'auto_events' => true, 'auto_money' => true,
    ];
    public const EVENT_TYPES = ['awesomekorean', 'sweepstakes'];

    // 카페별 해시태그 하나씩 더
    private const CAFE_TAGS = [
        'earn' => '#부업', 'save' => '#생활비절약', 'grow' => '#재테크', 'home' => '#내집마련', 'insurance' => '#미국보험',
        'tax' => '#미국세금', 'cards' => '#신용카드', 'stocks' => '#미국주식', 'kids' => '#교육비', 'retire' => '#은퇴준비',
        'relay' => '#부수입인증', 'talk' => '#돈이야기',
    ];

    public static function settings(): array
    {
        $raw = SiteSetting::where('key', self::SETTINGS_KEY)->value('value');
        $s = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        return array_merge(self::DEFAULTS, $s);
    }

    public static function saveSettings(array $s): array
    {
        $merged = array_merge(self::settings(), $s);
        SiteSetting::updateOrCreate(['key' => self::SETTINGS_KEY], ['value' => json_encode($merged, JSON_UNESCAPED_UNICODE)]);
        return $merged;
    }

    private static function exists(string $type, ?int $id, string $platform): bool
    {
        return $id !== null && SocialPost::where('source_type', $type)->where('source_id', $id)->where('platform', $platform)->exists();
    }

    private static function initialStatus(): string
    {
        return self::settings()['require_approval'] ? 'pending' : 'approved';
    }

    /** HTML 본문에서 첫 문단과 목록(최대 3개)을 뽑음 */
    public static function summarize(?string $html): array
    {
        $html = (string) $html;
        preg_match_all('#<li[^>]*>(.*?)</li>#su', $html, $m);
        $bullets = array_values(array_filter(array_map(fn ($s) => trim(html_entity_decode(strip_tags($s), ENT_QUOTES)), $m[1] ?? [])));
        preg_match('#<p[^>]*>(.*?)</p>#su', $html, $p);
        $intro = trim(html_entity_decode(strip_tags($p[1] ?? ''), ENT_QUOTES));
        if ($intro === '' && !$bullets) $intro = trim(mb_substr(html_entity_decode(strip_tags($html), ENT_QUOTES), 0, 200));
        return [$intro, array_slice($bullets, 0, 3)];
    }

    private static function cut(string $s, int $n): string
    {
        return mb_strlen($s) > $n ? rtrim(mb_substr($s, 0, $n - 1)) . '…' : $s;
    }

    /** MONEY 글 하나를 두 플랫폼에 넣음. 넣은 개수 반환 */
    public static function enqueueMoneyPost(MoneyPost $post, ?int $byUser = null, array $platforms = SocialPost::PLATFORMS): int
    {
        if ($post->is_hidden) return 0;
        $post->loadMissing('cafe');
        $cafe = $post->cafe;
        [$intro, $bullets] = self::summarize($post->content);
        $url = rtrim(config('app.url'), '/') . '/money/post/' . $post->id;
        $cafeName = $cafe?->name ?? 'MONEY';
        $tags = trim(self::settings()['hashtags'] . ' ' . (self::CAFE_TAGS[$cafe?->slug] ?? ''));
        $n = 0;

        foreach ($platforms as $pf) {
            if (self::exists('money_post', $post->id, $pf)) continue;
            if ($pf === 'instagram') {
                $img = SocialCard::make([
                    'tag' => 'MONEY · ' . $cafeName, 'title' => $post->title, 'lines' => $bullets ?: [$intro],
                    'theme' => $cafe?->theme ?: 'emerald', 'footer' => '단계별 안내 전체는 프로필 링크에서 보세요',
                ], 'money-' . $post->id);
                $caption = $post->title . "\n\n" . ($intro ? self::cut($intro, 400) . "\n\n" : '')
                    . implode("\n", array_map(fn ($b) => '✔ ' . $b, $bullets)) . "\n\n"
                    . "📌 화면 캡처와 함께 따라 하는 전체 글은 프로필 링크(awesomekorean.com) › MONEY › {$cafeName} 카페에서 볼 수 있어요.\n\n"
                    . "※ 일반 정보예요. 금융·세무·법률 조언이 아니니 꼭 공식 안내를 확인하세요.\n\n" . $tags;
                $caption = self::cut($caption, 2150);
            } else {
                $img = null;
                $body = $post->title . "\n\n" . implode("\n", array_map(fn ($b) => '✔ ' . self::cut($b, 90), $bullets));
                $caption = self::cut($body, 440) . "\n\n자세히 👉 " . $url;
            }
            SocialPost::create([
                'source_type' => 'money_post', 'source_id' => $post->id, 'platform' => $pf, 'title' => self::cut($post->title, 200),
                'caption' => $caption, 'link_url' => $url, 'image_url' => $img, 'status' => self::initialStatus(),
                'priority' => 0, 'created_by' => $byUser,
            ]);
            $n++;
        }
        return $n;
    }

    /**
     * 우리가 정보에서 SNS용으로 다시 만든 글(database/data/social_pieces.json 한 건)을 대기열에 넣음.
     * kind: video(릴스·스레드 영상) | site(사이트 소개) | talk(스레드 대화 유도, 사진 없음) | (기본) 돈 정보 카드.
     * 같은 piece id + 플랫폼은 한 번만. 넣은 개수 반환.
     */
    public static function enqueuePiece(array $p, string $status = 'approved', ?int $byUser = null): int
    {
        $base = rtrim(config('app.url'), '/');
        $link = $base . ($p['link'] ?? '/');
        if (!empty($p['money_title'])) {
            $id = MoneyPost::where('is_hidden', false)->where('title', 'like', '%' . $p['money_title'] . '%')->orderBy('id')->value('id');
            if (!$id) throw new \RuntimeException('MONEY 글을 못 찾았어요: ' . $p['money_title']);
            $link = $base . '/money/post/' . $id;
        }
        $kind = $p['kind'] ?? 'money';
        $tags = self::settings()['hashtags'];
        $n = 0;
        foreach ($p['platforms'] ?? SocialPost::PLATFORMS as $pf) {
            if (self::exists('piece', (int) $p['id'], $pf)) continue;
            $img = null; $video = null; $images = null; $parts = null;
            if ($kind === 'carousel') {
                if ($pf === 'instagram') {
                    $images = self::carouselImages($p);
                    $img = $images[0];
                    $outro = "\n\n📌 더 자세한 단계별 안내는 프로필 링크 awesomekorean.com 에서 볼 수 있어요.\n\n※ 일반 정보예요(2026년 10월 기준). 금융·세무·법률 조언이 아니니 공식 안내를 꼭 확인하세요.";
                    $list = [];
                    foreach (($p['slides'] ?? []) as $i => $sl) $list[] = ($i + 1) . '. ' . $sl['h'];
                    $cap = $p['ig'] . "\n\n넘겨서 보세요 👉\n" . implode("\n", $list) . $outro . "\n\n" . $tags . ' ' . ($p['tags'] ?? '');
                    $cap = self::cut(trim($cap), 2150);
                } else {
                    $cap = self::cut($p['th'], 500);
                    $parts = array_map(fn ($t) => self::cut(str_replace('{link}', $link, $t), 500), $p['th_parts'] ?? []);
                    $img = self::v2Images($p)[0] ?? null;   // 디자인된 표지가 있으면 스레드 첫 글에도 붙인다
                }
                SocialPost::create([
                    'source_type' => 'piece', 'source_id' => (int) $p['id'], 'platform' => $pf, 'title' => self::cut($p['title'], 200),
                    'caption' => $cap, 'link_url' => $link, 'image_url' => $img, 'images' => $images, 'thread_parts' => $parts, 'status' => $status,
                    'priority' => (int) ($p['priority'] ?? 8), 'created_by' => $byUser, 'approved_by' => $status === 'approved' ? $byUser : null,
                ]);
                $n++;
                continue;
            }
            if ($pf === 'instagram') {
                $cap = $p['ig'];
                if ($kind === 'money') {
                    $cap .= "\n\n📌 전체 글(화면 캡처·단계별 안내)은 프로필 링크 awesomekorean.com › MONEY 에서 볼 수 있어요.\n\n※ 일반 정보예요(2026년 10월 기준). 금융·세무·법률 조언이 아니니 공식 안내를 꼭 확인하세요.";
                }
                if (!str_contains($cap, '#')) $cap .= "\n\n" . $tags . ' ' . ($p['tags'] ?? '#생활정보 #한인정보');
                $cap = self::cut($cap, 2150);
                if ($kind === 'video') $video = $base . $p['video'];
                else $img = SocialCard::make(['tag' => $p['tag'] ?? '어썸코리안', 'title' => $p['title'], 'lines' => $p['lines'] ?? [],
                    'theme' => $p['theme'] ?? 'orange', 'footer' => '저장해 두고 필요할 때 꺼내 보세요'], 'piece-' . $p['id']);
            } else {
                $cap = self::cut($p['th'], 440) . ($kind === 'video' || $kind === 'talk' ? '' : "\n\n자세히 👉 " . $link);
                if ($kind === 'video') $video = $base . $p['video'];
            }
            SocialPost::create([
                'source_type' => 'piece', 'source_id' => (int) $p['id'], 'platform' => $pf, 'title' => self::cut($p['title'], 200),
                'caption' => $cap, 'link_url' => $link, 'image_url' => $img, 'video_url' => $video, 'status' => $status,
                'priority' => (int) ($p['priority'] ?? 5), 'created_by' => $byUser, 'approved_by' => $status === 'approved' ? $byUser : null,
            ]);
            $n++;
        }
        return $n;
    }

    /** 디자인된 카드 파일이 있으면 [표지, 1…N, 마지막] 주소 목록, 없으면 빈 배열 */
    public static function v2Images(array $p): array
    {
        $disk = Storage::disk('public');
        $n = count($p['slides'] ?? []);
        $names = array_merge(range(0, $n), [9]);
        $urls = [];
        foreach ($names as $i) {
            $rel = 'social/v2/piece-' . $p['id'] . '-' . $i . '.jpg';
            if (!$disk->exists($rel)) return [];
            $urls[] = rtrim(config('app.url'), '/') . '/storage/' . $rel . '?v=' . $disk->lastModified($rel);
        }
        return $urls;
    }

    /** 캐러셀: 표지 + 장마다 한 장 + 마지막 안내 장 → 사진 주소 목록 */
    public static function carouselImages(array $p): array
    {
        // 미리 디자인해서 올려 둔 카드(storage/app/public/social/v2/piece-ID-N.jpg)가 있으면 그걸 쓴다
        $v2 = self::v2Images($p);
        if ($v2) return $v2;
        $slides = $p['slides'] ?? [];
        $total = count($slides);
        $theme = $p['theme'] ?? 'orange';
        $urls = [SocialCard::slide('cover', ['tag' => $p['tag'] ?? '어썸코리안', 'title' => $p['title'], 'sub' => $p['sub'] ?? '', 'theme' => $theme], 'piece-' . $p['id'] . '-0')];
        foreach ($slides as $i => $sl) {
            $urls[] = SocialCard::slide('item', ['n' => $i + 1, 'total' => $total, 'title' => $sl['h'], 'lines' => $sl['b'] ?? [], 'theme' => $theme], 'piece-' . $p['id'] . '-' . ($i + 1));
        }
        $urls[] = SocialCard::slide('cta', ['title' => $p['cta_title'] ?? '더 자세한 내용은 어썸코리안에서', 'lines' => $p['cta_lines'] ?? ['단계별 안내와 계산표를 모아 뒀어요', '가입 없이 읽을 수 있어요'], 'theme' => $theme], 'piece-' . $p['id'] . '-9');
        return $urls;
    }

    /** 어썸코리안 이벤트를 두 플랫폼에 넣음(우선순위 10). 넣은 개수 반환 */
    public static function enqueueEvent(Event $ev, ?int $byUser = null): int
    {
        if (!in_array($ev->event_type, self::EVENT_TYPES, true) || !$ev->is_active) return 0;
        if ($ev->end_date && $ev->end_date->isPast()) return 0;
        $url = rtrim(config('app.url'), '/') . '/events/' . $ev->id;
        $when = $ev->start_date ? $ev->start_date->timezone('America/New_York')->format('n/j') : '';
        if ($ev->end_date) $when .= ' ~ ' . $ev->end_date->timezone('America/New_York')->format('n/j');
        $desc = trim(html_entity_decode(strip_tags((string) ($ev->description ?: $ev->content)), ENT_QUOTES));
        $label = $ev->event_type === 'sweepstakes' ? '🎁 어썸코리안 경품 이벤트' : '🎉 어썸코리안 이벤트';
        $n = 0;
        foreach (SocialPost::PLATFORMS as $pf) {
            if (self::exists('event', $ev->id, $pf)) continue;
            if ($pf === 'instagram') {
                $img = SocialCard::make([
                    'tag' => $ev->event_type === 'sweepstakes' ? '경품 이벤트' : '어썸코리안 이벤트', 'title' => $ev->title,
                    'lines' => array_values(array_filter([$when ? '기간 ' . $when : null, self::cut($desc, 70) ?: null, '참여 방법은 프로필 링크에서'])),
                    'theme' => $ev->event_type === 'sweepstakes' ? 'pink' : 'orange', 'footer' => '참여·자세히 보기는 프로필 링크에서',
                ], 'event-' . $ev->id);
                $caption = self::cut($label . "\n" . $ev->title . "\n\n" . ($when ? "📅 기간: {$when} (애틀랜타 시간)\n\n" : '')
                    . ($desc ? self::cut($desc, 900) . "\n\n" : '') . "👉 참여·자세히: 프로필 링크(awesomekorean.com) › 이벤트\n\n"
                    . self::settings()['hashtags'] . ' #이벤트 #경품', 2150);
            } else {
                $img = null;
                $caption = self::cut($label . "\n" . $ev->title . ($when ? "\n📅 {$when}" : '') . ($desc ? "\n\n" . self::cut($desc, 200) : ''), 420) . "\n\n참여 👉 " . $url;
            }
            SocialPost::create([
                'source_type' => 'event', 'source_id' => $ev->id, 'platform' => $pf, 'title' => self::cut($ev->title, 200),
                'caption' => $caption, 'link_url' => $url, 'image_url' => $img, 'status' => self::initialStatus(),
                'priority' => 10, 'created_by' => $byUser,
            ]);
            $n++;
        }
        return $n;
    }

    /** 관리자 직접 쓰기. 사진이 없으면 카드뉴스를 만들어 씀 */
    public static function enqueueManual(array $d, int $byUser): int
    {
        $n = 0;
        $card = null;
        foreach ($d['platforms'] as $pf) {
            $img = $d['image_url'] ?? null;
            if ($pf === 'instagram' && !$img) {
                $card ??= SocialCard::make(['tag' => $d['tag'] ?: '어썸코리안 소식', 'title' => $d['title'], 'lines' => $d['lines'] ?? [],
                    'theme' => $d['theme'] ?? 'orange', 'footer' => '자세히 보기는 프로필 링크에서'], 'manual-' . now()->format('YmdHis') . '-' . $byUser);
                $img = $card;
            }
            $caption = $pf === 'threads'
                ? self::cut($d['caption'], 440) . (!empty($d['link_url']) ? "\n\n👉 " . $d['link_url'] : '')
                : self::cut($d['caption'] . (!empty($d['link_url']) ? "\n\n👉 프로필 링크(awesomekorean.com)에서 자세히" : ''), 2150);
            SocialPost::create([
                'source_type' => 'manual', 'source_id' => null, 'platform' => $pf, 'title' => self::cut($d['title'], 200),
                'caption' => $caption, 'link_url' => $d['link_url'] ?? null, 'image_url' => $pf === 'threads' ? ($d['image_url'] ?? null) : $img,
                'status' => self::initialStatus(), 'priority' => (int) ($d['priority'] ?? 5), 'created_by' => $byUser,
                'not_before' => $d['not_before'] ?? null,
            ]);
            $n++;
        }
        return $n;
    }

    /** 새 MONEY 운영팀 글(기준점 이후)과 놓친 이벤트를 대기열에 — social:enqueue 가 매시간 부름 */
    public static function enqueueNew(): array
    {
        $s = self::settings();
        $added = ['money' => 0, 'events' => 0];
        if ($s['auto_money']) {
            $last = (int) SiteSetting::where('key', 'social_money_last_id')->value('value');
            $opsId = DB::table('users')->where('email', 'money-ops@awesomekorean.local')->value('id');
            $q = MoneyPost::where('id', '>', $last)->where('is_hidden', false)->orderBy('id');
            if ($opsId) $q->where('user_id', $opsId);
            $max = $last;
            foreach ($q->limit(100)->get() as $p) { $added['money'] += self::enqueueMoneyPost($p); $max = max($max, $p->id); }
            if ($max > $last) SiteSetting::updateOrCreate(['key' => 'social_money_last_id'], ['value' => (string) $max]);
        }
        if ($s['auto_events']) {
            foreach (Event::whereIn('event_type', self::EVENT_TYPES)->where('is_active', true)->where('created_at', '>=', now()->subDays(3))->get() as $ev) {
                $added['events'] += self::enqueueEvent($ev);
            }
        }
        return $added;
    }
}
