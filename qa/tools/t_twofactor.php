<?php
// stubs for the facades TwoFactor imports (only used by the DB-touching methods, which are not called here)
namespace Illuminate\Support\Facades { class Crypt {} class DB {} class Log {} }
namespace {
require '/tmp/Totp.php';
require '/tmp/TwoFactor.php';
use App\Support\Totp;
use App\Support\TwoFactor as T;
$ok = 0; $bad = 0;
function check($name, $cond, $extra = '') { global $ok, $bad; if ($cond) { $ok++; echo "ok   $name\n"; } else { $bad++; echo "FAIL $name $extra\n"; } }

$key = 'base64:test-app-key';
$secret = Totp::base32Encode('12345678901234567890');
$now = 1111111109; $step = Totp::step($now); $code = Totp::code($secret, $step);

// --- enabled()
check('enabled: needs both confirmed_at and secret', T::enabled((object) ['two_factor_confirmed_at' => '2026-10-14', 'two_factor_secret' => 'x']) === true);
check('not enabled: no confirmed_at (setup unfinished)', T::enabled((object) ['two_factor_confirmed_at' => null, 'two_factor_secret' => 'x']) === false);
check('not enabled: plain user object without the columns', T::enabled((object) ['id' => 1]) === false);
check('not enabled: null user', T::enabled(null) === false);

// --- recovery code helpers
$codes = T::newRecoveryCodes(8);
check('8 recovery codes, format xxxxx-xxxxx', count($codes) === 8 && count(array_filter($codes, fn ($c) => preg_match('/^[a-f0-9]{5}-[a-f0-9]{5}$/', $c))) === 8);
check('recovery codes are unique', count(array_unique($codes)) === 8);
check('norm accepts dashed/uppercase', T::normRecovery(strtoupper($codes[0])) === str_replace('-', '', $codes[0]));
check('norm rejects wrong length/garbage', T::normRecovery('123456') === '' && T::normRecovery('zzzzzzzzzz') === '' && T::normRecovery('') === '');
check('hash depends on app key', T::hashRecovery('abcde12345', 'k1') !== T::hashRecovery('abcde12345', 'k2'));
$hashes = array_map(fn ($c) => T::hashRecovery(T::normRecovery($c), $key), $codes);

// --- check(): TOTP path
$r = T::check($secret, -1, $hashes, $code, $key, $now);
check('totp code accepted, step returned, recovery list untouched', $r['ok'] && $r['via'] === 'totp' && $r['step'] === $step && $r['recovery'] === $hashes);
$r2 = T::check($secret, $step, $hashes, $code, $key, $now);
check('same totp code is rejected after use (replay)', $r2['ok'] === false);
$r3 = T::check($secret, $step, $hashes, Totp::code($secret, $step + 1), $key, $now + 30);
check('next-window code accepted after the previous was used', $r3['ok'] && $r3['step'] === $step + 1);
$r4 = T::check($secret, -1, $hashes, '000000', $key, $now);
check('wrong code rejected', $r4['ok'] === false && $r4['recovery'] === $hashes);

// --- check(): recovery path
$r5 = T::check($secret, -1, $hashes, $codes[3], $key, $now);
check('recovery code accepted', $r5['ok'] && $r5['via'] === 'recovery' && $r5['step'] === null);
check('recovery code is removed after use (7 left)', count($r5['recovery']) === 7 && !in_array($hashes[3], $r5['recovery'], true));
$r6 = T::check($secret, -1, $r5['recovery'], $codes[3], $key, $now);
check('recovery code cannot be used twice', $r6['ok'] === false);
$r7 = T::check($secret, -1, $hashes, strtoupper(str_replace('-', ' ', $codes[5])), $key, $now);
check('recovery code accepted with spaces/uppercase', $r7['ok'] && $r7['via'] === 'recovery');
check('wrong-length hex is not a recovery code', T::check($secret, -1, $hashes, 'abcde123', $key, $now)['ok'] === false);
check('hash under another app key does not match', T::check($secret, -1, $hashes, $codes[0], 'other-key', $now)['ok'] === false);
check('empty recovery list + wrong code', T::check($secret, -1, [], '123456', $key, $now)['ok'] === false);
check('garbage in recovery list is ignored (no exception)', T::check($secret, -1, [null, 5, ['x']], $codes[0], $key, $now)['ok'] === false);

// --- challenge(): paths that never touch the DB
check('challenge: user without 2FA passes untouched', T::challenge((object) ['id' => 1], null) === null && T::challenge((object) ['id' => 1], '123456') === null);
$u = (object) ['id' => 9, 'two_factor_confirmed_at' => '2026-10-14', 'two_factor_secret' => 'enc'];
$c = T::challenge($u, '');
check('challenge: 2FA user without a code is asked for one (not "invalid")', is_array($c) && $c['invalid'] === false);
check('challenge: whitespace-only counts as missing', (T::challenge($u, "   ")['invalid'] ?? null) === false);
echo "\n$ok passed, $bad failed\n";
}
