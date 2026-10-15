<?php
namespace Illuminate\Support\Facades { class Cache {} }
namespace {
require '/tmp/SeoHead.php';
use App\Support\SeoHead as S;
$ok = 0; $bad = 0;
function check($name, $cond, $extra = '') { global $ok, $bad; if ($cond) { $ok++; echo "ok   $name\n"; } else { $bad++; echo "FAIL $name $extra\n"; } }
$O = 'https://awesomekorean.com';
$render = fn (array $s, string $path) => S::render(S::build($s, $path, $O));

// 1) empty settings behave exactly like the old hard-coded head
$h = $render([], '/');
check('empty settings: old default title', str_contains($h, '<title>AwesomeKorean — 미국 한인 커뮤니티</title>'));
check('empty settings: old default description', str_contains($h, '<meta name="description" content="미국 한인 커뮤니티 플랫폼. 커뮤니티, 구인구직, 중고장터, 한인 업소록을 한 곳에서.">'));
check('empty settings: no keywords tag, no verification tags', !str_contains($h, 'name="keywords"') && !str_contains($h, 'site-verification'));

// 2) settings are used
$s = ['seo_meta_title' => '어썸코리안 | 미국 한인 커뮤니티', 'seo_meta_description' => '설명 문구입니다.', 'meta_keywords' => '미국 한인 커뮤니티, 구인구직', 'site_name' => 'AwesomeKorean', 'logo_url' => '/storage/branding/logo.png'];
$h = $render($s, '/');
check('title from settings', str_contains($h, '<title>어썸코리안 | 미국 한인 커뮤니티</title>'));
check('description from SEO tab', str_contains($h, 'content="설명 문구입니다."'));
check('keywords from settings', str_contains($h, '<meta name="keywords" content="미국 한인 커뮤니티, 구인구직">'));
$h2 = $render(['meta_description' => '회사 정보 탭의 설명'], '/');
check('falls back to company-tab description when SEO-tab one is empty', str_contains($h2, 'content="회사 정보 탭의 설명"'));
check('SEO-tab description wins over company-tab one', str_contains($render(['seo_meta_description' => 'A', 'meta_description' => 'B'], '/'), 'name="description" content="A"'));

// 3) canonical + og
check('home canonical', str_contains($h, '<link rel="canonical" href="https://awesomekorean.com/">'));
$m = $render($s, '/market/123?utm=x');
check('canonical strips query and keeps path', str_contains($m, 'href="https://awesomekorean.com/market/123"'), $m);
check('og:url equals canonical', str_contains($m, 'property="og:url" content="https://awesomekorean.com/market/123"'));
check('relative logo becomes absolute og:image', str_contains($h, 'property="og:image" content="https://awesomekorean.com/storage/branding/logo.png"'));
check('twitter card is large when an image exists', str_contains($h, 'summary_large_image'));
check('no image -> plain summary card, no og:image', str_contains($render([], '/'), 'content="summary"') && !str_contains($render([], '/'), 'og:image'));
check('seo_og_image overrides logo', str_contains($render($s + ['seo_og_image' => 'https://cdn.example.com/og.png'], '/'), 'og:image" content="https://cdn.example.com/og.png"'));
check('javascript: image is rejected', !str_contains($render(['seo_og_image' => 'javascript:alert(1)'], '/'), 'javascript:'));

// 4) sections
$c = $render($s, '/community');
check('section title', str_contains($c, '<title>커뮤니티 | 어썸코리안</title>'));
check('section description', str_contains($c, '이민·생활·맛집'));
check('detail page uses the section title', str_contains($render($s, '/qa/55'), '<title>Q&amp;A | 어썸코리안</title>'));
check('unknown path falls back to home title/description', str_contains($render($s, '/shorts'), '<title>어썸코리안 | 미국 한인 커뮤니티</title>'));
check('JSON-LD only on home', str_contains($h, 'application/ld+json') && !str_contains($c, 'application/ld+json'));
check('every section has a label and a description', count(array_filter(S::SECTIONS, fn ($v) => is_array($v) && count($v) === 2 && $v[0] !== '' && $v[1] !== '')) === count(S::SECTIONS));

// 5) noindex
foreach (['/admin', '/admin/members', '/login', '/register', '/dashboard', '/messages', '/search', '/forgot-password', '/market', '/market/943', '/realestate', '/realestate/12'] as $p)
    check("noindex on $p", str_contains($render($s, $p), 'name="robots" content="noindex,follow"'));
foreach (['/', '/community', '/jobs', '/info', '/qa', '/directory'] as $p)
    check("indexable $p", !str_contains($render($s, $p), 'noindex'));

// 6) verification tags + escaping/injection
$v = $render(['seo_google_verification' => 'abc_DEF-123', 'seo_naver_verification' => 'n4v3r'], '/');
check('verification tags rendered', str_contains($v, 'name="google-site-verification" content="abc_DEF-123"') && str_contains($v, 'name="naver-site-verification" content="n4v3r"'));
check('bad verification value is dropped', !str_contains($render(['seo_google_verification' => '"><script>x</script>'], '/'), 'google-site-verification'));
$x = $render(['seo_meta_title' => '"><script>alert(1)</script>', 'seo_meta_description' => "a\"b'c<d>&e", 'meta_keywords' => '<b>k</b>', 'site_name' => '<i>x</i>'], '/');
check('title/description/keywords are HTML-escaped', !str_contains($x, '<script>alert') && str_contains($x, '&quot;&gt;&lt;script&gt;') && !str_contains($x, '<b>k</b>') && str_contains($x, 'a&quot;b&#039;c&lt;d&gt;&amp;e'));
check('JSON-LD cannot be broken out of the script tag', substr_count($x, '</script>') === 1, substr_count($x, '</script>'));
preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $x, $mm);
check('JSON-LD is valid JSON with WebSite + Organization', ($j = json_decode($mm[1] ?? '', true)) && ($j['@graph'][0]['@type'] ?? '') === 'WebSite' && ($j['@graph'][1]['@type'] ?? '') === 'Organization', $mm[1] ?? 'none');
check('JSON-LD search target', ($j['@graph'][0]['potentialAction']['target'] ?? '') === 'https://awesomekorean.com/search?q={search_term_string}');

// 7) length limits
$long = str_repeat('가', 400);
$L = S::build(['seo_meta_title' => $long, 'seo_meta_description' => $long, 'meta_keywords' => str_repeat('나', 900)], '/', $O);
check('length limits (title<=120, desc<=300, keywords<=500)', mb_strlen($L['title']) <= 120 && mb_strlen($L['description']) <= 300 && mb_strlen($L['keywords']) <= 500);
check('whitespace/newlines collapsed', S::build(['seo_meta_description' => "a\n\n  b\tc"], '/', $O)['description'] === 'a b c');
check('path with trailing slash / odd case', S::build([], '/Market/', $O)['canonical'] === 'https://awesomekorean.com/Market' && str_contains(S::render(S::build($s, '/Market/', $O)), '<title>중고장터 | 어썸코리안</title>'));
// 8) ads allowed / excluded
foreach (['', '/', 'info', 'info/some-article', 'community', 'jobs/5', 'directory', 'money', 'shopping', 'events', 'clubs', 'groupbuy'] as $p)
    check("ads allowed on '$p'", S::adsAllowed($p) === true);
foreach (['admin', 'admin/members', 'login', 'register', 'dashboard', 'messages', 'search', 'shorts', 'shorts/9', 'music', 'market', 'market/12', 'realestate', 'qa', 'qa/77', 'news', 'Shorts'] as $p)
    check("no ads on '$p'", S::adsAllowed($p) === false);
check('noindex pages never get ads', count(array_filter(S::NOINDEX, fn ($s) => S::adsAllowed($s))) === 0);

// 9) real 404 for unknown paths
foreach (['', '/', 'community', 'community/12', 'Community', 'market/5?x=1', 'admin/members', 'info/some-slug', 'shopping', 'money', 'sweepstakes', 'poker7', 'games', 'elder'] as $p)
    check("known path '$p' -> 200", S::isKnownPath($p) === true);
foreach (['404', 'this-page-does-not-exist-12345', 'api/nothing', 'index.php', 'wp-login.php', '.env', 'phpmyadmin', 'admin.php', 'communitys', 'a/b/c'] as $p)
    check("unknown path '$p' -> 404", S::isKnownPath($p) === false);
check('unknown path is also noindex in the head', str_contains(S::render(S::build([], '/wp-login.php', 'https://awesomekorean.com')), 'noindex'));
check('known indexable path is not noindex', !str_contains(S::render(S::build([], '/community', 'https://awesomekorean.com')), 'noindex'));
check('every sitemap section page is a known path (so it is served with 200)', count(array_filter(['/community', '/jobs', '/directory', '/events', '/clubs', '/groupbuy', '/about', '/contact', '/terms', '/privacy'], fn ($p) => !S::isKnownPath($p))) === 0);
check('every SECTIONS key is a known path', count(array_filter(array_keys(S::SECTIONS), fn ($k) => !S::isKnownPath($k))) === 0);
echo "\n$ok passed, $bad failed\n";
}
