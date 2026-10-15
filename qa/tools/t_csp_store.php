<?php
namespace App\Http\Controllers { class Controller {} }
namespace Illuminate\Http { class Request { function __construct(public string $body = '') {} function getContent() { return $this->body; } } }
namespace Illuminate\Support\Facades {
    class Cache { static $d = []; static function get($k, $def = null) { return self::$d[$k] ?? $def; } static function add($k, $v, $ttl) { if (isset(self::$d[$k])) return false; self::$d[$k] = $v; return true; } static function put($k, $v, $ttl) { self::$d[$k] = $v; } }
    class LogChan { function warning($m, $c = []) { echo "  LOG: $m " . json_encode($c, JSON_UNESCAPED_UNICODE) . "\n"; } }
    class Log { static function build($cfg) { echo "  Log::build path=" . basename($cfg['path']) . "\n"; return new LogChan; } }
}
namespace {
class FakeNow { function format($f) { return date($f); } }
function now() { return new FakeNow; }
function storage_path($p = '') { return '/var/www/storage/' . $p; }
function response($c = '', $s = 200) { return (object) ['content' => $c, 'status' => $s]; }
require '/tmp/CspReportController.php';
use App\Http\Controllers\API\CspReportController;
use Illuminate\Http\Request;

$c = new CspReportController();
foreach ([
  'empty body' => '',
  'garbage' => 'not json',
  'valid' => '{"csp-report":{"document-uri":"https://awesomekorean.com/","effective-directive":"img-src","blocked-uri":"https://example.invalid/x.png"}}',
  'same again (dedupe)' => '{"csp-report":{"document-uri":"https://awesomekorean.com/","effective-directive":"img-src","blocked-uri":"https://example.invalid/x.png"}}',
  'different' => '{"csp-report":{"document-uri":"https://awesomekorean.com/market/1","effective-directive":"script-src","blocked-uri":"inline"}}',
] as $name => $body) {
    echo "$name:\n";
    $r = $c->store(new Request($body));
    echo "  -> status {$r->status}\n";
}
}
