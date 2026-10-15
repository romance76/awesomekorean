<?php
namespace Illuminate\Database\Eloquent { class Model { public $attributes = []; public function __get($k) { return $this->attributes[$k] ?? null; } } }
namespace App\Models { class User {} class GroupBuyParticipant {} require '/tmp/GroupBuy.php'; }
namespace {
use App\Models\GroupBuy;
$ok = 0; $bad = 0;
function check($name, $cond, $extra = '') { global $ok, $bad; if ($cond) { $ok++; echo "ok   $name\n"; } else { $bad++; echo "FAIL $name $extra\n"; } }
class D { function __construct(public bool $past) {} function isPast() { return $this->past; } }
function gb($status, $deadline) { $g = new GroupBuy; $g->attributes = ['status' => $status, 'deadline' => $deadline]; return $g; }

check('recruiting + deadline passed -> ended', gb('recruiting', new D(true))->getDisplayStatusAttribute() === 'ended');
check('recruiting + deadline in the future -> recruiting', gb('recruiting', new D(false))->getDisplayStatusAttribute() === 'recruiting');
check('recruiting + no deadline -> recruiting', gb('recruiting', null)->getDisplayStatusAttribute() === 'recruiting');
foreach (['confirmed', 'completed', 'cancelled'] as $s) check("$s stays $s even if deadline passed", gb($s, new D(true))->getDisplayStatusAttribute() === $s);
check('missing status does not crash', (new GroupBuy)->getDisplayStatusAttribute() === null);
check('display_status is appended to JSON', (function () { $r = new ReflectionProperty(GroupBuy::class, 'appends'); $r->setAccessible(true); return $r->getValue(new GroupBuy) === ['display_status']; })());
echo "\n$ok passed, $bad failed\n";
}
