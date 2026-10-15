<?php
namespace {
require '/tmp/Totp.php';
use App\Support\Totp;
$ok = 0; $bad = 0;
function check($name, $cond, $extra = '') { global $ok, $bad; if ($cond) { $ok++; echo "ok   $name\n"; } else { $bad++; echo "FAIL $name $extra\n"; } }

// RFC 4648 base32 vectors
check('base32("foobar")', Totp::base32Encode('foobar') === 'MZXW6YTBOI');
check('base32("fo")', Totp::base32Encode('fo') === 'MZXQ');
check('decode(MZXW6YTBOI)', Totp::base32Decode('MZXW6YTBOI') === 'foobar');
check('decode ignores case/space/padding', Totp::base32Decode('mzxw 6ytb oi======') === 'foobar');
check('decode rejects bad chars', Totp::base32Decode('MZXW1YTB') === '');

// RFC 6238 Appendix B (SHA1, secret "12345678901234567890"); 8-digit values -> the 6-digit code is their last 6 digits
$secret = Totp::base32Encode('12345678901234567890');
$vectors = [59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471', 1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'];
foreach ($vectors as $t => $eight) {
    check("RFC6238 t=$t 8-digit", Totp::code($secret, Totp::step($t), 8) === $eight, Totp::code($secret, Totp::step($t), 8));
    check("RFC6238 t=$t 6-digit", Totp::code($secret, Totp::step($t)) === substr($eight, -6));
}
// RFC 4226 HOTP vectors (counter 0..4) 6 digits
$h = ['755224', '287082', '359152', '969429', '338314'];
foreach ($h as $c => $exp) check("RFC4226 counter=$c", Totp::code($secret, $c) === $exp);

// verify(): window, replay, formatting
$t0 = 1111111109; $good = Totp::code($secret, Totp::step($t0));
check('verify current code', Totp::verify($secret, $good, $t0) === Totp::step($t0));
check('verify tolerates "081 804" and "081-804"', Totp::verify($secret, '081 804', $t0) !== null && Totp::verify($secret, '081-804', $t0) !== null);
check('verify accepts previous 30s window', Totp::verify($secret, $good, $t0 + 30) === Totp::step($t0));
check('verify accepts next 30s window', Totp::verify($secret, $good, $t0 - 30) === Totp::step($t0));
check('verify rejects 2 windows late', Totp::verify($secret, $good, $t0 + 90) === null);
check('verify rejects wrong code', Totp::verify($secret, '000000', $t0) === null);
check('verify rejects non-digits / wrong length', Totp::verify($secret, 'abcdef', $t0) === null && Totp::verify($secret, '12345', $t0) === null && Totp::verify($secret, '1234567', $t0) === null);
$used = Totp::verify($secret, $good, $t0);
check('replay: same code rejected once its step was used', Totp::verify($secret, $good, $t0, 1, $used) === null);
check('replay: next window code is still accepted', Totp::verify($secret, Totp::code($secret, $used + 1), $t0 + 30, 1, $used) === $used + 1);
check('bad secret -> null (no exception)', Totp::verify('!!!', '123456', $t0) === null);

// secret + url
$s = Totp::generateSecret();
check('generated secret is 32 base32 chars', preg_match('/^[A-Z2-7]{32}$/', $s) === 1 && strlen(Totp::base32Decode($s)) === 20);
check('two secrets differ', Totp::generateSecret() !== Totp::generateSecret());
$u = Totp::otpauthUrl('ABCDEFGH', 'kay@example.com');
check('otpauth url', $u === 'otpauth://totp/AwesomeKorean%3Akay%40example.com?secret=ABCDEFGH&issuer=AwesomeKorean&algorithm=SHA1&digits=6&period=30', $u);
echo "\n$ok passed, $bad failed\n";
}
