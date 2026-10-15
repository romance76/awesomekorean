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
        'shopping' => ['내돈내산 리뷰', '아마존 후기를 한국어로 나누는 내돈내산 리뷰.'],
        'about' => ['소개', '어썸코리안은 미국 한인을 위한 커뮤니티입니다.'],
        'contact' => ['문의하기', '어썸코리안에 문의하세요.'],
        'terms' => ['이용약관', '어썸코리안 이용약관.'],
        'privacy' => ['개인정보처리방침', '어썸코리안 개인정보처리방침.'],
    ];

    /** 검색에 노출하지 않을 화면(개인·관리·로그인·검색 결과) — 주소의 첫 마디 */
    public const NOINDEX = ['admin', 'login', 'register', 'forgot-password', 'email-verified', 'auth', 'dashboard', 'messages', 'notifications', 'points', 'entries', 'ad-apply', 'comms', 'search', '404'];

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
    public static function build(array $s, string $path, string $origin): array
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

        $logo = self::abs($s['logo_url'] ?? null, $origin);
        $image = self::abs($s['seo_og_image'] ?? null, $origin) ?: $logo;

        return [
            'title' => self::clip($title, 120),
            'description' => self::clip($desc, 300),
            'keywords' => self::clip((string) ($s['meta_keywords'] ?? ''), 500),
            'canonical' => $origin . ($isHome ? '/' : '/' . implode('/', $segs)),
            'noindex' => in_array($first, self::NOINDEX, true),
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
            return self::render(self::build(is_array($s) ? $s : [], $path, $origin));
        } catch (\Throwable $e) {
            return '    <title>' . self::e(self::DEFAULT_TITLE) . "</title>\n    <meta name=\"description\" content=\"" . self::e(self::DEFAULT_DESCRIPTION) . "\">\n";
        }
    }
}
