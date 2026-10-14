<?php

namespace App\Support;

/**
 * 시간 기반 일회용 비밀번호(TOTP, RFC 6238 / HOTP RFC 4226) — 구글 OTP·Authy·Microsoft Authenticator 등과 호환.
 * 외부 라이브러리 없이 hash_hmac 만 쓴다. 이 클래스는 DB·세션을 건드리지 않는 순수 계산이다.
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function base32Encode(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 5) as $chunk) $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        return $out;
    }

    public static function base32Decode(string $s): string
    {
        $s = strtoupper(preg_replace('/[\s=-]+/', '', $s));
        $bits = '';
        foreach (str_split($s) as $c) {
            $v = strpos(self::ALPHABET, $c);
            if ($v === false) return '';
            $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
        return $out;
    }

    /** 새 비밀키: 20바이트(160비트) 난수를 base32 로 (32글자) */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /** 30초 단위 시간 번호 */
    public static function step(?int $time = null, int $period = 30): int
    {
        return intdiv($time ?? time(), $period);
    }

    /** 특정 시간 번호의 N자리 코드 */
    public static function code(string $secretBase32, int $step, int $digits = 6): string
    {
        $key = self::base32Decode($secretBase32);
        $counter = pack('N2', ($step >> 32) & 0xFFFFFFFF, $step & 0xFFFFFFFF);
        $hash = hash_hmac('sha1', $counter, $key, true);
        $o = ord($hash[19]) & 0x0F;
        $n = ((ord($hash[$o]) & 0x7F) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);
        return str_pad((string) ($n % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * 입력 코드가 맞으면 맞은 시간 번호를, 틀리면 null.
     * - 앞뒤 $window(기본 1) 구간(±30초)까지 허용해 폰 시계가 조금 어긋나도 통과.
     * - $minStepExclusive 이하의 시간 번호는 거부 → 이미 한 번 쓴 코드를 다시 쓰는 것(재사용 공격)을 막는다.
     * - 공백·하이픈은 무시, 6자리 숫자가 아니면 바로 null.
     */
    public static function verify(string $secretBase32, string $input, ?int $now = null, int $window = 1, int $minStepExclusive = -1): ?int
    {
        $input = preg_replace('/[\s-]+/', '', $input);
        if (!preg_match('/^\d{6}$/', $input) || self::base32Decode($secretBase32) === '') return null;
        $cur = self::step($now);
        $match = null;
        for ($i = -$window; $i <= $window; $i++) {
            $s = $cur + $i;
            if ($s <= $minStepExclusive) continue;
            if (hash_equals(self::code($secretBase32, $s), $input)) $match = $s;   // 일찍 끝내지 않고 끝까지 비교
        }
        return $match;
    }

    /** 인증 앱 등록용 주소 (폰에서 누르면 인증 앱이 열리고, QR 로도 만들 수 있다) */
    public static function otpauthUrl(string $secretBase32, string $account, string $issuer = 'AwesomeKorean'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secretBase32 . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }
}
