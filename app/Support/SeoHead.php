<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * 사이트(SPA) 화면의 <head> 검색 태그를 만든다: 제목·설명·키워드·정규 주소(canonical)·공유 미리보기(OG)·검색 제외·구조화 데이터.
 *
 * 예전에는 제목과 설명이 welcome.blade.php 에 고정돼 있어서, 관리자 > 사이트 설정의 "메타 설명/키워드/SEO 제목"을 바꿔도
 * 검색 결과에는 아무 변화가 없었다. 이제 그 값을 읽어 쓰고, 값이 없으면 예전과 똑같은 기본 문구를 쓴다.
 *
 * 읽는 설정(site_settings): seo_meta_title, seo_meta_description, meta_description(회사 정보 탭), meta_keywords,
 *   seo_og_image, seo_google_verification, seo_naver_verification, site_name, logo_url
 * 만드는 쪽(build/render)은 DB 를 건드리지 않는 순수 함수이고, tags() 만 설정을 읽는다. 어떤 오류가 나도 기본 태그로 돌아간다.
 */
class SeoHead
{
    public const DEFAULT_TITLE = 'AwesomeKorean — 미국 한인 커뮤니티';
    public const DEFAULT_DESCRIPTION = '미국 한인 커뮤니티 플랫폼. 커뮤니티, 구인구직, 중고장터, 한인 업소록을 한 곳에서.';
    public const SHORT_NAME = '어썸코리안';

    /** 주요 메뉴별 제목·설명 (주소의 첫 마디 기준). 상세 글 화면도 그 메뉴의 제목을 쓴다. */
    public const SECTIONS = [
        'community' => ['커뮤니티', '미국 한인 커뮤니티 게시판 — 이민·생활·맛집·자녀교육·생활꿀팁 이야기를 이웃과 나눠요.'],
        'qa' => ['Q&A', '미국 생활이 궁금할 때 한인 이웃에게 물어보세요. 영주권·세금·운전면허·병원 등 질문과 답변을 모았어요.'],
        'jobs' => ['구인구직', '미국 한인 구인구직 — 한인 업체 채용 공고와 일자리를 찾아보세요.'],
        'market' => ['중고장터', '한인 이웃과 중고 물품·중고차를 사고파는 중고장터.'],
        'realestate' => ['부동산', '미국 한인 부동산 — 매매·렌트 매물 정보를 확인하세요.'],
        'directory' => ['한인 업소록', '미국 한인 업소록 — 식당·병원·한의원·미용실 등 한인 업체를 찾아보세요.'],
        'news' => ['뉴스', '미국과 한국 뉴스를 한곳에서 확인하세요.'],
        'recipes' => ['레시피', '집에서 만들어 먹는 레시피 모음.'],
        'events' => ['이벤트', '어썸코리안 이벤트와 경품 추첨 소식.'],
        'sweepstakes' => ['경품 추첨', '어썸코리안 경품 추첨에 참여하고 당첨 소식을 확인하세요.'],
        'clubs' => ['동호회', '취미와 관심사가 같은 한인 동호회 모임.'],
        'groupbuy' => ['공동구매', '한인 이웃과 함께 사면 더 저렴한 공동구매.'],
        'money' => ['MONEY', '미국 생활 돈 정보 — 세금·정부 혜택, 보험, 신용카드, 자녀 교육비, 주식, 은퇴·노후를 공식 자료로 정리했어요.'],
        'youtubers' => ['한인 유튜버', '미국 한인 유튜버 채널 모음 — 생활·이민·먹방·육아·재테크 분야별로 찾아보고 영상과 쇼츠를 바로 보세요.'],
        'shopping' => ['내돈내산 리뷰', '아마존 후기를 한국어로 나누는 내돈내산 리뷰.'],
        'about' => ['소개', '어썸코리안은 미국 한인을 위한 커뮤니티입니다.'],
        'contact' => ['문의하기', '어썸코리안에 문의하세요.'],
        'terms' => ['이용약관', '어썸코리안 이용약관.'],
        'privacy' => ['개인정보처리방침', '어썸코리안 개인정보처리방침.'],
    ];

    /**
     * 화면(SPA 라우터, resources/js/router/index.js)이 실제로 보여 주는 주소의 첫 마디. 여기에 없는 주소는 서버가 HTTP 404 로 내려
     * 검색엔진이 "없는 페이지"로 알게 한다(전에는 모든 주소가 200 이라 "소프트 404"로 잡혔다). 'info'·'go' 는 서버가 따로 처리하는 주소.
     * ★ 라우터에 새 최상위 주소를 추가하면 여기에도 추가해야 한다(안 하면 그 화면은 열리지만 상태가 404 라 검색에 안 잡힌다).
     *   (qa/scripts/post_deploy_smoke.sh 는 목록의 주요 화면이 200 인지 확인한다)
     */
    public const SPA_SEGMENTS = ['about', 'ad-apply', 'admin', 'auth', 'chat', 'clubs', 'comms', 'community', 'contact', 'dashboard', 'directory', 'easy-test', 'elder',
        'email-verified', 'entries', 'events', 'forgot-password', 'friends', 'games', 'go', 'grades', 'groupbuy', 'info', 'jobs', 'login', 'market', 'messages', 'money', 'music',
        'new', 'news', 'notifications', 'points', 'poker7', 'privacy', 'profile', 'qa', 'realestate', 'recipes', 'register', 'search', 'shopping', 'shorts', 'stocks', 'sweepstakes', 'terms', 'youtubers'];

    /** 홈('')이거나 화면이 있는 주소이면 true. '404' 화면 자체와 목록에 없는 주소는 false(→ 서버가 404 상태로 내림) */
    public static function isKnownPath(string $path): bool
    {
        $path = (string) preg_replace('/[?#].*$/s', '', $path);
        $first = strtolower(explode('/', trim($path, '/'))[0] ?? '');
        return $first === '' || in_array($first, self::SPA_SEGMENTS, true);
    }

    /**
     * 검색에 노출하지 않을 화면(개인·관리·로그인·검색 결과) — 주소의 첫 마디.
     * market·realestate: 지금은 전부 외부(eBay·realtor.com)에서 가져온 상품·매물이라 구글 색인에서 제외(2026-10-14 사용자 결정).
     * 회원이 올린 글이 쌓이면 여기서 빼고 색인을 다시 연다.
     */
    public const NOINDEX = ['admin', 'login', 'register', 'forgot-password', 'email-verified', 'auth', 'dashboard', 'messages', 'notifications', 'points', 'entries', 'ad-apply', 'comms', 'search', '404', 'market', 'realestate'];

    /**
     * 광고(구글 애드센스) 코드를 아예 불러오지 않을 화면 — 주소의 첫 마디. 검색 제외 화면(로그인·관리자·개인 화면) +
     * 자동으로 모아 온 콘텐츠(숏츠·음악·수집 매물)와, 처리 방침이 아직 정해지지 않은 가져온 콘텐츠(지식인 Q&A·번역 뉴스).
     * 애드센스는 복사·수집된 콘텐츠나 로그인·관리자 화면에 광고를 붙이면 정책 위반이다. 방침이 정해져 광고를 켜도 되면 여기서 뺀다.
     */
    public static function noAdsSections(): array
    {
        return array_merge(self::NOINDEX, ['shorts', 'music', 'market', 'realestate', 'qa', 'news']);
    }

    public static function adsAllowed(string $path): bool
    {
        $first = strtolower(explode('/', trim($path, '/'))[0] ?? '');
        return !in_array($first, self::noAdsSections(), true);
    }

    /** 상세 글 주소 → [모델, 주소 끝의 id]. 글 상세가 아닌 주소(목록·작성·수정 등)는 null */
    public static function detailTarget(string $path): ?array
    {
        $path = (string) preg_replace('/[?#].*$/s', '', $path);
        $segs = array_values(array_filter(explode('/', trim($path, '/')), fn ($x) => $x !== ''));
        $first = strtolower($segs[0] ?? '');
        $n = count($segs);
        $isId = fn ($v) => is_string($v) && ctype_digit($v) && strlen($v) <= 10;
        if ($n === 2 && $isId($segs[1])) {
            $map = ['jobs' => \App\Models\JobPost::class, 'recipes' => \App\Models\RecipePost::class, 'events' => \App\Models\Event::class,
                'qa' => \App\Models\QaPost::class, 'clubs' => \App\Models\Club::class, 'groupbuy' => \App\Models\GroupBuy::class,
                'youtubers' => \App\Models\YoutubeChannel::class];
            if (isset($map[$first])) return [$map[$first], (int) $segs[1]];
        }
        if ($n === 3 && $first === 'community' && $isId($segs[2])) return [\App\Models\Post::class, (int) $segs[2]];
        if ($n === 3 && $first === 'money' && $segs[1] === 'post' && $isId($segs[2])) return [\App\Models\MoneyPost::class, (int) $segs[2]];
        return null;
    }

    /**
     * 상세 글의 검색용 제목·설명을 DB 에서 읽는다. 반환: null(상세 주소가 아님/오류) · ['missing'=>true](글이 없음 → 서버가 404) ·
     * ['title','description','noindex'](글이 있음). 숨김·비활성 글은 noindex. 어떤 오류가 나도 null 이라 기존 메뉴별 문구로 돌아간다.
     */
    public static function detail(string $path): ?array
    {
        try {
            $t = self::detailTarget($path);
            if (!$t) return null;
            [$model, $id] = $t;
            $row = $model::query()->find($id);
            if (!$row || ($row instanceof \App\Models\YoutubeChannel && $row->status !== 'approved')) return ['missing' => true];
            $title = (string) ($row->title ?? $row->name ?? '');
            $body = (string) ($row->content ?? $row->description ?? '');
            $body = html_entity_decode(strip_tags((string) preg_replace('#<(br|/p|/div|/li)[^>]*>#i', ' ', $body)), ENT_QUOTES, 'UTF-8');
            $hidden = (isset($row->is_hidden) && $row->is_hidden) || (isset($row->is_active) && !$row->is_active)
                || (isset($row->is_public) && !$row->is_public) || (isset($row->is_approved) && !$row->is_approved);
            return ['title' => self::clip($title, 70), 'description' => self::clip($body, 155), 'noindex' => $hidden];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function clip(string $s, int $max): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s));
        return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, $max - 1)) . '…' : $s;
    }

    private static function abs(?string $url, string $origin): ?string
    {
        $url = trim((string) $url);
        if ($url === '') return null;
        if (preg_match('#^https?://#i', $url)) return $url;
        return str_starts_with($url, '/') ? $origin . $url : null;
    }

    /** 순수 계산: 설정($s)·요청 경로·사이트 주소 → 태그에 들어갈 값 */
    public static function build(array $s, string $path, string $origin, ?array $detail = null): array
    {
        $origin = rtrim($origin, '/');
        $path = (string) preg_replace('/[?#].*$/s', '', $path);   // 주소의 ?검색어·#해시는 canonical 에 넣지 않는다
        $segs = array_values(array_filter(explode('/', trim($path, '/')), fn ($x) => $x !== ''));
        $first = strtolower($segs[0] ?? '');
        $isHome = $first === '';

        $siteName = trim((string) ($s['site_name'] ?? '')) ?: 'AwesomeKorean';
        $homeTitle = trim((string) ($s['seo_meta_title'] ?? '')) ?: self::DEFAULT_TITLE;
        $homeDesc = trim((string) ($s['seo_meta_description'] ?? '')) ?: (trim((string) ($s['meta_description'] ?? '')) ?: self::DEFAULT_DESCRIPTION);

        $title = $homeTitle;
        $desc = $homeDesc;
        if (!$isHome && isset(self::SECTIONS[$first])) {
            [$label, $sectionDesc] = self::SECTIONS[$first];
            $title = $label . ' | ' . self::SHORT_NAME;
            $desc = $sectionDesc;
        }

        if ($detail) {   // 상세 글: 그 글의 제목·본문 앞부분을 쓴다(없으면 위의 메뉴별 문구 그대로)
            if (($detail['title'] ?? '') !== '') $title = $detail['title'] . ' | ' . self::SHORT_NAME;
            if (($detail['description'] ?? '') !== '') $desc = $detail['description'];
        }

        $logo = self::abs($s['logo_url'] ?? null, $origin);
        $image = self::abs($s['seo_og_image'] ?? null, $origin) ?: $logo;

        return [
            'title' => self::clip($title, 120),
            'description' => self::clip($desc, 300),
            'keywords' => self::clip((string) ($s['meta_keywords'] ?? ''), 500),
            'canonical' => $origin . ($isHome ? '/' : '/' . implode('/', $segs)),
            'noindex' => in_array($first, self::NOINDEX, true) || !self::isKnownPath($path) || !empty($detail['noindex']),
            'site_name' => $siteName,
            'image' => $image,
            'logo' => $logo,
            'google_verification' => preg_match('/^[A-Za-z0-9_\-=.:]+$/', (string) ($s['seo_google_verification'] ?? '')) ? $s['seo_google_verification'] : '',
            'naver_verification' => preg_match('/^[A-Za-z0-9_\-=.:]+$/', (string) ($s['seo_naver_verification'] ?? '')) ? $s['seo_naver_verification'] : '',
            'is_home' => $isHome,
            'origin' => $origin,
        ];
    }

    private static function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** 순수 계산: build() 결과 → <head> 안에 넣을 HTML */
    public static function render(array $b): string
    {
        $o = [];
        $o[] = '<title>' . self::e($b['title']) . '</title>';
        $o[] = '<meta name="description" content="' . self::e($b['description']) . '">';
        if ($b['keywords'] !== '') $o[] = '<meta name="keywords" content="' . self::e($b['keywords']) . '">';
        $o[] = '<link rel="canonical" href="' . self::e($b['canonical']) . '">';
        if ($b['noindex']) $o[] = '<meta name="robots" content="noindex,follow">';
        if ($b['google_verification'] !== '') $o[] = '<meta name="google-site-verification" content="' . self::e($b['google_verification']) . '">';
        if ($b['naver_verification'] !== '') $o[] = '<meta name="naver-site-verification" content="' . self::e($b['naver_verification']) . '">';

        $o[] = '<meta property="og:type" content="website">';
        $o[] = '<meta property="og:locale" content="ko_KR">';
        $o[] = '<meta property="og:site_name" content="' . self::e($b['site_name']) . '">';
        $o[] = '<meta property="og:title" content="' . self::e($b['title']) . '">';
        $o[] = '<meta property="og:description" content="' . self::e($b['description']) . '">';
        $o[] = '<meta property="og:url" content="' . self::e($b['canonical']) . '">';
        $o[] = '<meta name="twitter:card" content="' . ($b['image'] ? 'summary_large_image' : 'summary') . '">';
        $o[] = '<meta name="twitter:title" content="' . self::e($b['title']) . '">';
        $o[] = '<meta name="twitter:description" content="' . self::e($b['description']) . '">';
        if ($b['image']) {
            $o[] = '<meta property="og:image" content="' . self::e($b['image']) . '">';
            $o[] = '<meta name="twitter:image" content="' . self::e($b['image']) . '">';
        }

        if ($b['is_home']) {   // 홈에만: 사이트 이름·검색창·조직 정보 (구글이 사이트를 이해하는 데 쓰는 구조화 데이터)
            $graph = [
                ['@type' => 'WebSite', 'name' => $b['site_name'], 'alternateName' => self::SHORT_NAME, 'url' => $b['origin'] . '/', 'inLanguage' => 'ko',
                    'potentialAction' => ['@type' => 'SearchAction', 'target' => $b['origin'] . '/search?q={search_term_string}', 'query-input' => 'required name=search_term_string']],
                array_filter(['@type' => 'Organization', 'name' => $b['site_name'], 'alternateName' => self::SHORT_NAME, 'url' => $b['origin'] . '/', 'logo' => $b['logo']]),
            ];
            $json = json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
            if ($json !== false) $o[] = '<script type="application/ld+json">' . $json . '</script>';
        }
        return "    " . implode("\n    ", $o) . "\n";
    }

    /** welcome.blade.php 에서 한 줄로 부른다. 설정을 읽다가 어떤 오류가 나도 예전과 같은 기본 제목·설명만 내보낸다. */
    public static function tags($request = null): string
    {
        try {
            $s = Cache::remember('seo_head_settings', 600, function () {
                return \App\Models\SiteSetting::whereIn('key', ['seo_meta_title', 'seo_meta_description', 'meta_description', 'meta_keywords', 'seo_og_image',
                    'seo_google_verification', 'seo_naver_verification', 'site_name', 'logo_url'])->pluck('value', 'key')->all();
            });
            $path = $request ? '/' . ltrim((string) $request->path(), '/') : '/';
            $origin = rtrim((string) config('app.url'), '/') ?: ($request ? $request->getSchemeAndHttpHost() : '');
            return self::render(self::build(is_array($s) ? $s : [], $path, $origin, self::detail($path)));
        } catch (\Throwable $e) {
            return '    <title>' . self::e(self::DEFAULT_TITLE) . "</title>\n    <meta name=\"description\" content=\"" . self::e(self::DEFAULT_DESCRIPTION) . "\">\n";
        }
    }
}
