<?php
namespace App\Http\Controllers { class Controller {} }
namespace {
require '/tmp/CspReportController.php';
use App\Http\Controllers\API\CspReportController as C;
$ok = 0; $bad = 0;
function check($name, $cond, $extra = '') { global $ok, $bad; if ($cond) { $ok++; echo "ok   $name\n"; } else { $bad++; echo "FAIL $name $extra\n"; } }

// legacy format, URL with query/hash/secret, numeric id in path
$a = C::summarize('{"csp-report":{"document-uri":"https://awesomekorean.com/community/post/12345?x=1#h","violated-directive":"script-src-elem","effective-directive":"script-src-elem","blocked-uri":"https://evil.example/a.js?token=abc"}}');
check('legacy: directive', ($a['directive'] ?? '') === 'script-src-elem');
check('legacy: blocked reduced to host (no query/token)', ($a['blocked'] ?? '') === 'evil.example', json_encode($a));
check('legacy: page = first two segments', ($a['page'] ?? '') === '/community/post', json_encode($a));
$b = C::summarize('{"csp-report":{"document-uri":"https://awesomekorean.com/market/98765","effective-directive":"img-src","blocked-uri":"https://cdn.x.com/i.png"}}');
check('numeric id replaced with :id', ($b['page'] ?? '') === '/market/:id', json_encode($b));
// Reporting API format
$c = C::summarize('[{"type":"csp-violation","body":{"documentURL":"https://awesomekorean.com/","effectiveDirective":"img-src","blockedURL":"data","sample":"abc"}}]');
check('reporting API: parsed', ($c['directive'] ?? '') === 'img-src' && ($c['blocked'] ?? '') === 'data' && ($c['page'] ?? '') === '/', json_encode($c));
// inline script with sample
$d = C::summarize('{"csp-report":{"document-uri":"https://awesomekorean.com/info/abc","effective-directive":"script-src","blocked-uri":"inline","script-sample":"console.log(1)"}}');
check('inline keeps keyword + sample', ($d['blocked'] ?? '') === 'inline' && ($d['sample'] ?? '') === 'console.log(1)', json_encode($d));
// blob/data schemes collapse
$e = C::summarize('{"csp-report":{"document-uri":"https://a.com/","effective-directive":"media-src","blocked-uri":"blob:https://awesomekorean.com/uuid-123"}}');
check('blob: collapsed to scheme', ($e['blocked'] ?? '') === 'blob:', json_encode($e));
// garbage in -> null, never an exception
check('not json -> null', C::summarize('not json') === null);
check('empty object -> null', C::summarize('{}') === null);
check('missing directive -> null', C::summarize('{"csp-report":{"blocked-uri":"x"}}') === null);
check('wrong types -> null', C::summarize('{"csp-report":"oops"}') === null);
check('json scalar -> null', C::summarize('123') === null);
// size limits
$long = str_repeat('a', 500);
$f = C::summarize('{"csp-report":{"document-uri":"https://a.com/' . $long . '/' . $long . '","effective-directive":"' . $long . '","blocked-uri":"https://' . $long . '.com/x","script-sample":"' . $long . '"}}');
check('long values are truncated', strlen($f['directive']) <= 60 && strlen($f['blocked']) <= 120 && strlen($f['page']) <= 90 && strlen($f['sample']) <= 80, json_encode(array_map('strlen', $f)));
echo "\n$ok passed, $bad failed\n";
}
