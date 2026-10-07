<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\RateLimiter;

/**
 * 관리자 보안 화면용:
 *  - 웹사이트 로그인 잠금(같은 계정·IP 에서 비밀번호를 계속 틀림) 현황과 해제
 *  - 서버(SSH) 접속을 반복 시도해 fail2ban 이 자동 차단한 IP 목록과 해제
 */
class AdminSecurityController extends Controller
{
    private const FAIL2BAN = '/usr/local/sbin/ak-fail2ban';

    // AuthController::login 과 같은 키 규칙
    private static function acctKey(string $email): string { return 'login-acct:' . sha1($email); }
    private static function acctIpKey(string $email, string $ip): string { return 'login-acct-ip:' . sha1($email . '|' . $ip); }

    public function loginLocks()
    {
        $rows = DB::table('login_failures')->where('created_at', '>=', now()->subDay())
            ->selectRaw('email, ip, COUNT(*) as fails, MAX(created_at) as last_at')
            ->groupBy('email', 'ip')->orderByDesc('last_at')->limit(60)->get();

        $data = $rows->map(function ($r) {
            $acct = self::acctKey($r->email);
            $pair = self::acctIpKey($r->email, $r->ip);
            $locked = RateLimiter::tooManyAttempts($acct, 20) || RateLimiter::tooManyAttempts($pair, 6);
            $wait = 0;
            if ($locked) $wait = max(RateLimiter::tooManyAttempts($acct, 20) ? RateLimiter::availableIn($acct) : 0, RateLimiter::tooManyAttempts($pair, 6) ? RateLimiter::availableIn($pair) : 0);
            return ['email' => $r->email, 'ip' => $r->ip, 'fails' => (int) $r->fails, 'last_at' => $r->last_at, 'locked' => $locked, 'retry_min' => (int) ceil($wait / 60)];
        })->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function unlockLogin(Request $request)
    {
        $d = $request->validate(['email' => 'required|string|max:190', 'ip' => 'nullable|string|max:45']);
        $email = mb_strtolower(trim($d['email']));
        RateLimiter::clear(self::acctKey($email));
        $ips = !empty($d['ip']) ? [$d['ip']] : DB::table('login_failures')->where('email', $email)->distinct()->pluck('ip')->all();
        foreach ($ips as $ip) RateLimiter::clear(self::acctIpKey($email, $ip));
        return response()->json(['success' => true, 'message' => '로그인 잠금을 풀었습니다']);
    }

    private function fail2ban(array $args): array
    {
        if (!is_file(self::FAIL2BAN)) return [false, '서버 차단 도구가 아직 설치되지 않았습니다'];
        try {
            $res = Process::timeout(10)->run(array_merge(['sudo', '-n', self::FAIL2BAN], $args));
        } catch (\Throwable $e) {
            return [false, '실행하지 못했습니다'];
        }
        return $res->successful() ? [true, trim($res->output())] : [false, trim($res->errorOutput() ?: $res->output()) ?: '실행하지 못했습니다'];
    }

    public function serverBans()
    {
        [$ok, $out] = $this->fail2ban(['list']);
        if (!$ok) return response()->json(['success' => true, 'available' => false, 'message' => $out, 'data' => []]);
        $ips = array_values(array_filter(preg_split('/\s+/', $out), fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP)));
        return response()->json(['success' => true, 'available' => true, 'data' => $ips]);
    }

    public function serverUnban(Request $request)
    {
        $d = $request->validate(['ip' => 'required|ip']);
        [$ok, $out] = $this->fail2ban(['unban', $d['ip']]);
        return response()->json(['success' => $ok, 'message' => $ok ? '차단을 풀었습니다' : $out], $ok ? 200 : 500);
    }
}
