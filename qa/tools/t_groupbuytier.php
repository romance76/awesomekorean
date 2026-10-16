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
check('quantity over 50 is business even if people and total are small', T::tier(1, 0, 10, 51) === T::BUSINESS);
check('quantity 50 at $9 (450) is small', T::tier(9, 0, 10, 50) === T::SMALL);
check('total uses quantity when given: $30 x 10 people but 40 items = 1200 -> business', T::tier(30, 0, 10, 40) === T::BUSINESS);
check('total uses quantity when given: $30 x 50 people but only 10 items = 300 -> small', T::tier(30, 0, 50, 10) === T::SMALL);
check('items falls back to max people', T::items(30, null) === 30 && T::items(30, 0) === 30 && T::items(30, 12) === 12);
check('national is always business, even for a tiny free item', T::tier(0, 0, 5, 5, 'national') === T::BUSINESS && T::tier(1, 0, 2, 2, T::SCOPE_NATIONAL) === T::BUSINESS);
check('local scope behaves as before', T::tier(8, 0, 40, 40, 'local') === T::SMALL && T::tier(30, 0, 20, 20, 'local') === T::BUSINESS);
check('small deposit is $5 (local)', T::deposit(T::SMALL, 'local', 10) === 5);
check('business deposit by period: 3d $5, 7d $7, 14d $10, 15d+ $15', T::deposit(T::BUSINESS, 'local', 3) === 5 && T::deposit(T::BUSINESS, 'local', 7) === 7 && T::deposit(T::BUSINESS, 'local', 14) === 10 && T::deposit(T::BUSINESS, 'local', 15) === 15 && T::deposit(T::BUSINESS, 'local', 31) === 15);
check('national deposit is x3', T::deposit(T::BUSINESS, 'national', 3) === 15 && T::deposit(T::BUSINESS, 'national', 7) === 21 && T::deposit(T::BUSINESS, 'national', 14) === 30 && T::deposit(T::BUSINESS, 'national', 31) === 45);
check('national multiplier constant is 3', T::NATIONAL_DEPOSIT_MULTIPLIER === 3);
echo "\n$ok passed, $bad failed\n";
}
