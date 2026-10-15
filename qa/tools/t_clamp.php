<?php
namespace Illuminate\Http { class Request {} }
namespace {
require '/tmp/ClampPerPage.php';
use App\Http\Middleware\ClampPerPage as C;
$ok = 0; $bad = 0;
function check($name, $cond, $extra = '') { global $ok, $bad; if ($cond) { $ok++; echo "ok   $name\n"; } else { $bad++; echo "FAIL $name $extra\n"; } }

check('absent -> untouched', C::clamp(null) === null);
check('20 stays 20', C::clamp('20') === 20);
check('1 stays 1', C::clamp('1') === 1);
check('100 stays 100', C::clamp('100') === 100);
check('101 -> 100', C::clamp('101') === 100);
check('500 -> 100', C::clamp('500') === 100);
check('1000 -> 100', C::clamp('1000') === 100);
check('huge int -> 100', C::clamp('99999999999999999999') === 100);
check('-5 -> default 20 (this was the 500 error)', C::clamp('-5') === 20);
check('0 -> default 20', C::clamp('0') === 20);
check('empty string -> default 20', C::clamp('') === 20);
check('text -> default 20', C::clamp('abc') === 20);
check('sql-ish -> default 20', C::clamp("1;drop table users") === 20);
check('array per_page[]=1 -> default 20', C::clamp(['1']) === 20);
check('decimal 50.7 -> 50', C::clamp('50.7') === 50);
check('float string 1e9 -> 100', C::clamp('1e9') === 100);
check('numeric with spaces " 30 "', in_array(C::clamp(' 30 '), [30, 20], true));

check('admin lists are skipped', C::skips('api/admin/members') === true && C::skips('/api/admin/todos') === true);
check('bookmarks skipped (screen asks for 500)', C::skips('api/bookmarks') === true);
check('music tracks skipped (screen asks for 200)', C::skips('api/music/tracks/5') === true);
foreach (['api/posts', 'api/market', 'api/qa', 'api/news', 'api/jobs', 'api/realestate', 'api/recipes', 'api/events', 'api/clubs', 'api/businesses', 'api/search'] as $p)
    check("public list $p is clamped", C::skips($p) === false);
echo "\n$ok passed, $bad failed\n";
}
