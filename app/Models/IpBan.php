<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class IpBan extends Model
{
    protected $fillable = ['ip_address','reason','banned_by','expires_at'];
    protected $casts = ['expires_at'=>'datetime',];

    /** "1.2.3.4" 같은 IP 하나, 또는 "1.2.3.0/24"·"2001:db8::/32" 같은 대역(CIDR)이 올바른 모양인지 */
    public static function isValidTarget(string $v): bool
    {
        if (!str_contains($v, '/')) return filter_var($v, FILTER_VALIDATE_IP) !== false;
        [$ip, $bits] = explode('/', $v, 2);
        if (!ctype_digit($bits) || filter_var($ip, FILTER_VALIDATE_IP) === false) return false;
        $max = str_contains($ip, ':') ? 128 : 32;
        return (int) $bits <= $max;
    }

    /** 이 IP 가 차단 대상(IP 하나 또는 대역)에 들어가는지 */
    public static function matches(string $target, string $ip): bool
    {
        if (!str_contains($target, '/')) return $target === $ip;
        [$net, $bits] = explode('/', $target, 2);
        $a = @inet_pton($ip); $b = @inet_pton($net);
        if ($a === false || $b === false || strlen($a) !== strlen($b)) return false;   // IPv4 대 IPv6 는 맞지 않음
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8); $rest = $bits % 8;
        if ($bytes > 0 && substr($a, 0, $bytes) !== substr($b, 0, $bytes)) return false;
        if ($rest === 0) return true;
        $mask = (0xFF << (8 - $rest)) & 0xFF;
        return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
    }
}
