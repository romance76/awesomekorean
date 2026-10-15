<?php
namespace App\Http\Controllers { class Controller {} }
namespace {
require '/tmp/AdminPrizeDeliveryController.php';
use App\Http\Controllers\API\AdminPrizeDeliveryController as C;
$ok = 0; $bad = 0;
function check($name, $cond, $extra = '') { global $ok, $bad; if ($cond) { $ok++; echo "ok   $name\n"; } else { $bad++; echo "FAIL $name $extra\n"; } }
$row = fn ($t, $usd) => (object) ['sent_at' => $t, 'usd' => $usd];
$months = fn (array $rows, $limit = 12) => array_column(C::monthlyBuckets($rows, $limit), null, 'month');

// winter (EST, UTC-5): 04:59:59 UTC on Dec 1 is still Nov 30 23:59:59 in New York
$w = $months([$row('2026-12-01 04:59:59', 10), $row('2026-12-01 05:00:00', 20), $row('2026-12-01 04:30:00', 5)]);
check('winter: 04:59:59Z on Dec 1 belongs to November', ($w['2026-11']['count'] ?? 0) === 2 && abs(($w['2026-11']['usd'] ?? 0) - 15) < 1e-9, json_encode($w));
check('winter: 05:00:00Z on Dec 1 belongs to December', ($w['2026-12']['count'] ?? 0) === 1 && ($w['2026-12']['usd'] ?? 0) == 20, json_encode($w));
// (the old fixed -04:00 offset would have put 04:30Z on Dec 1 into December — wrong in winter)
$old = (new DateTimeImmutable('2026-12-01 04:30:00', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('-04:00'))->format('Y-m');
check('sanity: the old fixed offset really was wrong in winter', $old === '2026-12');

// summer (EDT, UTC-4)
$s = $months([$row('2026-07-01 03:59:59', 7), $row('2026-07-01 04:00:00', 9)]);
check('summer: 03:59:59Z on Jul 1 belongs to June', ($s['2026-06']['count'] ?? 0) === 1, json_encode($s));
check('summer: 04:00:00Z on Jul 1 belongs to July', ($s['2026-07']['count'] ?? 0) === 1, json_encode($s));

// DST change days
$d = $months([$row('2026-03-08 06:59:59', 1), $row('2026-03-08 07:00:00', 1), $row('2026-11-01 05:59:59', 1), $row('2026-11-01 06:00:00', 1)]);
check('DST days stay in the right month', ($d['2026-03']['count'] ?? 0) === 2 && ($d['2026-11']['count'] ?? 0) === 2, json_encode($d));

// ordering, limit, rounding, empty
$many = []; for ($m = 1; $m <= 12; $m++) { $many[] = $row(sprintf('2025-%02d-15 12:00:00', $m), 0.1); $many[] = $row(sprintf('2025-%02d-16 12:00:00', $m), 0.2); }
$many[] = $row('2026-01-15 12:00:00', 1); $many[] = $row('2026-02-15 12:00:00', 1);
$r = C::monthlyBuckets($many, 12);
check('newest first, limited to 12', count($r) === 12 && $r[0]['month'] === '2026-02' && $r[1]['month'] === '2026-01' && $r[11]['month'] === '2025-03', json_encode(array_column($r, 'month')));
check('sums are rounded to cents (0.1+0.2)', ($r[2]['usd'] ?? null) === 0.3, json_encode($r[2] ?? null));
check('empty input -> empty list', C::monthlyBuckets([]) === []);
check('null/odd usd values count as 0', (C::monthlyBuckets([$row('2026-05-05 12:00:00', null)])[0]['usd'] ?? -1) === 0.0);
echo "\n$ok passed, $bad failed\n";
}
