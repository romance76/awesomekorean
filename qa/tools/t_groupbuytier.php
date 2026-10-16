<?php
namespace App\Support { require '/tmp/GroupBuyTier.php'; }
namespace {
use App\Support\GroupBuyTier as T;
$ok = 0; $bad = 0;
function check($name, $cond, $extra = '') { global $ok, $bad; if ($cond) { $ok++; echo "ok   $name\n"; } else { $bad++; echo "FAIL $name $extra\n"; } }

check('$10 x 49 = 490 is small', T::tier(10, 0, 49) === T::SMALL);
check('$10 x 50 = 500 is business (500 is not "under 500")', T::tier(10, 0, 50) === T::BUSINESS);
check('$9.99 x 50 = 499.5 is small', T::tier(9.99, 0, 50) === T::SMALL);
check('over 50 people is always business', T::tier(1, 0, 51) === T::BUSINESS);
check('no max people (unbounded) is business', T::tier(1, 0, null) === T::BUSINESS && T::tier(1, 0, 0) === T::BUSINESS);
check('group price wins over original price', T::tier(5, 400, 50) === T::SMALL);
check('falls back to original price when no group price', T::tier(null, 20, 30) === T::BUSINESS && T::tier(null, 8, 30) === T::SMALL);
check('free item (price 0) with a max is small', T::tier(0, 0, 40) === T::SMALL);
check('estimated total', T::estimatedTotal(30, 0, 100) === 3000.0 && T::estimatedTotal(null, 12.5, 4) === 50.0);
check('max days per tier', T::maxDays(T::SMALL) === 14 && T::maxDays(T::BUSINESS) === 31);
check('small fit: $30 -> 16 people (480)', T::maxPeopleForSmall(30, 0) === 16);
check('small fit: $10 -> 49 people', T::maxPeopleForSmall(10, 0) === 49);
check('small fit capped at 50', T::maxPeopleForSmall(1, 0) === 50 && T::maxPeopleForSmall(0, 0) === 50);
check('small fit: $300 fits only 1 person -> 0 (not possible)', T::maxPeopleForSmall(300, 0) === 0);
check('small fit result is really small', T::tier(30, 0, T::maxPeopleForSmall(30, 0)) === T::SMALL && T::tier(30, 0, T::maxPeopleForSmall(30, 0) + 1) === T::BUSINESS);
echo "\n$ok passed, $bad failed\n";
}
