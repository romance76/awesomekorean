<?php
namespace App\Http\Controllers { class Controller {} }
namespace Illuminate\Http { class Response { public $content; public $status; public $headers = []; function __construct($c, $s) { $this->content = $c; $this->status = $s; } function header($k, $v) { $this->headers[$k] = $v; return $this; } } }
namespace App\Models {
    class FakeDate { function __construct(public string $d) {} function toAtomString() { return $this->d; } }
    class FakeQuery { function __construct(public array $rows) {} function orderByDesc($c) { return $this; } function get($cols) { return $this->rows; } }
    class InfoPost { static function published() { return new FakeQuery([
        (object) ['slug' => 'a-b', 'updated_at' => new FakeDate('2026-10-01T00:00:00+00:00')],
        (object) ['slug' => '한글 & <x>', 'updated_at' => new FakeDate('2026-10-02T00:00:00+00:00')],
    ]); } }
}
namespace {
function e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function url($p = '/') { return 'https://awesomekorean.com' . ($p === '/' ? '' : $p); }
function route($n, $p = null) { return $n === 'info.index' ? 'https://awesomekorean.com/info' : 'https://awesomekorean.com/info/' . rawurlencode($p); }
function response($c, $s) { return new \Illuminate\Http\Response($c, $s); }
require '/tmp/SitemapController.php';
use App\Http\Controllers\SitemapController as C;
$ok = 0; $bad = 0;
function check($name, $cond, $extra = '') { global $ok, $bad; if ($cond) { $ok++; echo "ok   $name\n"; } else { $bad++; echo "FAIL $name $extra\n"; } }

$r = (new C)->index();
check('200 + xml content type', $r->status === 200 && $r->headers['Content-Type'] === 'application/xml');
libxml_use_internal_errors(true);
$x = simplexml_load_string($r->content);
check('XML is well-formed (even with & < > in a slug)', $x !== false, json_encode(array_map(fn ($e) => $e->message, libxml_get_errors())));
$locs = []; foreach ($x->url as $u) $locs[] = (string) $u->loc;
check('home + info index + sections + 2 posts', count($locs) === 2 + count(C::SECTION_PAGES) + 2, count($locs));
check('no duplicate URLs', count($locs) === count(array_unique($locs)));
foreach (['/community', '/jobs', '/directory', '/events', '/clubs', '/groupbuy', '/about', '/contact', '/terms', '/privacy'] as $p)
    check("section $p present", in_array('https://awesomekorean.com' . $p, $locs, true));
foreach (['/qa', '/market', '/realestate', '/news', '/recipes', '/shorts', '/music', '/admin', '/login', '/search', '/dashboard'] as $p)
    check("excluded $p absent", !in_array('https://awesomekorean.com' . $p, $locs, true));
check('post lastmod kept', str_contains($r->content, '<lastmod>2026-10-01T00:00:00+00:00</lastmod>'));
check('no section path collides with noindex list', count(array_intersect(array_map(fn ($p) => ltrim($p, '/'), C::SECTION_PAGES), ['admin', 'login', 'register', 'search', 'dashboard', 'messages'])) === 0);
echo "\n$ok passed, $bad failed\n";
}
