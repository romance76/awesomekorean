<?php
namespace Illuminate\Cache\RateLimiting {
  class Limit {
    public $key = ''; public $max; public $decay; public $cb = null; public $none = false;
    public static function perMinute($n) { $l = new self; $l->max = $n; $l->decay = 60; return $l; }
    public static function perDay($n) { $l = new self; $l->max = $n; $l->decay = 86400; return $l; }
    public static function none() { $l = new self; $l->none = true; return $l; }
    public function by($k) { $this->key = $k; return $this; }
    public function response(callable $c) { $this->cb = $c; return $this; }
  }
}
namespace Illuminate\Http { class Request { public function ip() { return '1.2.3.4'; } } }
namespace App\Support { require '/tmp/WriteLimits.php'; }
namespace {
use App\Support\WriteLimits;
function response() { return new class { public function json($d, $s = 200, $h = []) { return ['data' => $d, 'status' => $s, 'headers' => $h]; } }; }
$ok = 0; $bad = 0;
function check($name, $cond, $extra = '') { global $ok, $bad; if ($cond) { $ok++; echo "ok   $name\n"; } else { $bad++; echo "FAIL $name $extra\n"; } }

$l = WriteLimits::limits(7, 'user', '9.9.9.9');
check('two rules: per-minute and per-day', count($l) === 2 && $l[0]->max === 5 && $l[0]->decay === 60 && $l[1]->max === 50 && $l[1]->decay === 86400);
check('keys are per user and differ per window', $l[0]->key === 'write-m:u:7' && $l[1]->key === 'write-d:u:7' && $l[0]->key !== $l[1]->key);
check('different users do not share a bucket', WriteLimits::limits(8, 'user', null)[0]->key !== $l[0]->key);
foreach (['admin', 'super_admin', 'moderator'] as $r) { $x = WriteLimits::limits(1, $r, null); check("$r is exempt", count($x) === 1 && $x[0]->none === true); }
check('unknown/empty role is limited', count(WriteLimits::limits(1, '', null)) === 2 && count(WriteLimits::limits(1, null, null)) === 2);
$g = WriteLimits::limits(null, null, '5.6.7.8');
check('guest falls back to IP key', $g[0]->key === 'write-m:ip:5.6.7.8');
$r = new \Illuminate\Http\Request;
$m = ($l[0]->cb)($r, ['Retry-After' => 12]); $d = ($l[1]->cb)($r, []);
check('minute limit -> 429 with its own message and headers kept', $m['status'] === 429 && $m['data']['success'] === false && $m['data']['message'] === WriteLimits::MSG_MINUTE && $m['headers']['Retry-After'] === 12);
check('day limit -> 429 with the day message', $d['status'] === 429 && $d['data']['message'] === WriteLimits::MSG_DAY && str_contains($d['data']['message'], '50'));
check('the day message states the real limit', str_contains(WriteLimits::MSG_DAY, (string) WriteLimits::PER_DAY));
echo "\n$ok passed, $bad failed\n";
}
