<?php
namespace Illuminate\Database\Eloquent { class Model {} }
namespace Illuminate\Support\Facades {
  class Cache { public static $store = []; public static function get($k) { return self::$store[$k] ?? null; } public static function forever($k, $v) { self::$store[$k] = $v; return true; } }
  class Schema { public static $has = true; public static $calls = 0; public static function hasColumn($t, $c) { self::$calls++; return self::$has && $t === 'businesses' && $c === 'hidden_at'; } }
}
namespace App\Models { class User {} class BusinessReview {} class BusinessClaim {} class BusinessMenu {} require '/tmp/Business.php'; }
namespace {
use App\Models\Business; use Illuminate\Support\Facades\Cache; use Illuminate\Support\Facades\Schema;
$ok = 0; $bad = 0;
function check($name, $cond, $extra = '') { global $ok, $bad; if ($cond) { $ok++; echo "ok   $name\n"; } else { $bad++; echo "FAIL $name $extra\n"; } }
class Q { public $wheres = []; function whereNull($c) { $this->wheres[] = $c; return $this; } }

$b = new Business;
Schema::$has = false; Cache::$store = [];
$q = $b->scopeVisible(new Q);
check('column missing (deploy window): no filter, no error', $q->wheres === [] && !isset(Cache::$store['biz_hidden_col']));
Schema::$has = true;
$q = $b->scopeVisible(new Q);
check('column present: hides rows with hidden_at set', $q->wheres === ['businesses.hidden_at']);
check('presence is remembered', Cache::$store['biz_hidden_col'] === true);
$before = Schema::$calls; $b->scopeVisible(new Q); $b->scopeVisible(new Q);
check('once remembered, no more schema lookups', Schema::$calls === $before);
echo "\n$ok passed, $bad failed\n";
}
