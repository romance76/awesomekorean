<?php
namespace App\Support { require '/tmp/UsStates.php'; }
namespace {
use App\Support\UsStates as S;
$ok = 0; $bad = 0;
function check($name, $cond, $extra = '') { global $ok, $bad; if ($cond) { $ok++; echo "ok   $name\n"; } else { $bad++; echo "FAIL $name $extra\n"; } }

$map = json_decode(file_get_contents('/tmp/us_state_adjacency.json'), true);
S::useMap($map);

check('51 entries (50 states + DC)', count($map) === 51, 'got ' . count($map));
$asym = [];
foreach ($map as $a => $ns) { foreach ($ns as $b) { if (!isset($map[$b])) { $asym[] = "$a->$b(unknown)"; continue; } if (!in_array($a, $map[$b], true)) $asym[] = "$a->$b"; } }
check('adjacency is symmetric and every neighbour is a known state', !$asym, implode(',', $asym));
$self = array_filter(array_keys($map), fn($k) => in_array($k, $map[$k], true));
check('no state is its own neighbour', !$self);
check('no duplicate neighbours', !array_filter($map, fn($ns) => count($ns) !== count(array_unique($ns))));

check('GA region is GA first then AL FL NC SC TN', S::region('GA') === ['GA', 'AL', 'FL', 'NC', 'SC', 'TN']);
check('lowercase and spaces are accepted', S::region(' ga ') === S::region('GA') && S::isState(' ga '));
check('AK and HI have only themselves', S::region('AK') === ['AK'] && S::region('HI') === ['HI']);
check('DC touches MD and VA', S::region('DC') === ['DC', 'MD', 'VA']);
check('four-corner states are not neighbours', !in_array('CO', $map['AZ'], true) && !in_array('UT', $map['NM'], true));
check('unknown or empty state gives empty region', S::region('XX') === [] && S::region('') === [] && S::region(null) === []);
check('isState', S::isState('TX') && !S::isState('Texas') && !S::isState(''));
check('region keeps own state first and has no duplicates', S::region('NY')[0] === 'NY' && count(S::region('NY')) === count(array_unique(S::region('NY'))));
echo "\n$ok passed, $bad failed\n";
}
