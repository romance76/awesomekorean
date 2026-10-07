<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    // Issue #3: 비밀번호 공통 정책 — 8자 이상 + 대소문자/숫자 혼합, 일반 취약 비번 차단
    protected function passwordRules(): array
    {
        return [
            'required',
            'confirmed',
            Password::min(8)->mixedCase()->numbers(),
        ];
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'email' => 'required|email|unique:users',
            'password' => $this->passwordRules(),
            'nickname' => 'nullable|string|max:50',
        ]);

        $user = User::create([
            'name' => $request->name,
            'nickname' => $request->nickname ?? $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'language' => 'ko',
            'allow_friend_request' => $request->allow_friend_request ?? true,
        ]);

        // 가입 보너스 (P2B-2: DB 동적)
        $signupBonus = \App\Support\PointRules::get('signup_bonus', 10);
        if ($signupBonus > 0) $user->addPoints($signupBonus, '회원가입 보너스');

        // Entry 가입 보너스 — Point와 완전히 별도 지급(교환/합산 아님).
        $entrySignupBonus = \App\Support\EntrySettings::get('signup_bonus', 1);
        if ($entrySignupBonus > 0) {
            \App\Support\EntryService::award($user, $entrySignupBonus, 'SIGNUP_BONUS', '회원가입 Entry 보너스', 'signup');
        }

        // 이메일 인증 절차 자체가 없어 무제한 가입이 가능하던 문제 수정 —
        // 가입을 막지는 않고(비차단), 인증 메일만 발송해 이메일 소유를 확인.
        try {
            $verifyUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'auth.verify-email', now()->addDays(7), ['user' => $user->id]
            );
            \Illuminate\Support\Facades\Mail::to($user->email)->send(
                new \App\Mail\EmailVerificationMail($user->name, $verifyUrl)
            );
        } catch (\Exception $e) {
            \Log::warning("가입 인증 메일 발송 실패 (user_id={$user->id}): " . $e->getMessage());
        }

        $token = JWTAuth::fromUser($user);

        return response()->json(['success' => true, 'data' => ['token' => $token, 'user' => $user->fresh()]]);
    }

    // 이메일 인증 링크 클릭 시 접속 (서명된 URL로 보호, 가입 자체를 막지는 않음)
    public function verifyEmail(User $user)
    {
        if (!$user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
            try { \App\Support\EntryService::awardEmailVerified($user); }
            catch (\Throwable $e) { \Log::warning("이메일 인증 Entry 보너스 실패 (user_id={$user->id}): " . $e->getMessage()); }
        }
        // 빈 안내 페이지 대신 사이트로 복귀 — 프론트(/email-verified)가 완료 토스트를 띄우고
        // 재발송을 눌렀던 페이지로 다시 보내줌
        return redirect('/email-verified');
    }

    /**
     * 인증 메일 재발송 — 글쓰기를 이메일 인증 여부로 막기 시작하면서, 가입
     * 시 1회만 발송되던 기존 메일을 놓쳤거나(7일 후 서명 링크 만료 포함)
     * 기존 회원(이 기능 도입 전 가입)은 재발송받을 방법이 전혀 없어 영구히
     * 글쓰기가 막히는 문제를 방지하기 위해 신규 추가.
     */
    public function resendVerification(Request $request)
    {
        $user = $request->user();
        if ($user->email_verified_at) {
            return response()->json(['success' => false, 'message' => '이미 인증된 이메일입니다.'], 422);
        }

        $cacheKey = "resend_verify_email_{$user->id}";
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            return response()->json(['success' => false, 'message' => '잠시 후 다시 시도해주세요.'], 429);
        }

        try {
            $verifyUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'auth.verify-email', now()->addDays(7), ['user' => $user->id]
            );
            \Illuminate\Support\Facades\Mail::to($user->email)->send(
                new \App\Mail\EmailVerificationMail($user->name, $verifyUrl)
            );
        } catch (\Exception $e) {
            // 발송 실패 시엔 쿨다운을 소모하지 않음 — 그래야 일시적 메일 장애 때 60초를 헛되이 날리지 않음.
            return response()->json(['success' => false, 'message' => '메일 발송에 실패했습니다.'], 500);
        }

        \Illuminate\Support\Facades\Cache::put($cacheKey, true, 60);

        return response()->json(['success' => true, 'message' => '인증 메일을 다시 보냈습니다.']);
    }

    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        // 비밀번호 대입 공격 방어: 같은 계정을 여러 곳에서 시도해도(계정별), 한 곳에서 계속 시도해도(계정+IP별) 막는다
        $email = mb_strtolower(trim($request->email));
        $keys = ['login-acct:' . sha1($email) => [20, 900], 'login-acct-ip:' . sha1($email . '|' . $request->ip()) => [6, 300]];
        foreach ($keys as $k => [$max, $decay]) {
            if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($k, $max)) {
                $wait = \Illuminate\Support\Facades\RateLimiter::availableIn($k);
                return response()->json(['success' => false, 'message' => '로그인 시도가 너무 많아요. ' . ceil($wait / 60) . '분 후에 다시 시도해 주세요.'], 429);
            }
        }

        if (!$token = JWTAuth::attempt($request->only('email', 'password'))) {
            foreach ($keys as $k => [$max, $decay]) \Illuminate\Support\Facades\RateLimiter::hit($k, $decay);
            try { \Illuminate\Support\Facades\DB::table('login_failures')->insert(['email' => mb_substr($email, 0, 190), 'ip' => (string) $request->ip(), 'created_at' => now()]); } catch (\Throwable $e) {}
            return response()->json(['success' => false, 'message' => '이메일 또는 비밀번호가 올바르지 않습니다'], 401);
        }
        \Illuminate\Support\Facades\RateLimiter::clear('login-acct-ip:' . sha1($email . '|' . $request->ip()));

        $user = auth()->user();
        if ($user->is_banned) {
            // JWTAuth::attempt()가 반환하는 $token은 문자열이라 invalidate()에 그대로
            // 넘기면 타입 오류로 500이 남 — logout()과 동일하게 getToken()으로 넘김
            try { JWTAuth::invalidate(JWTAuth::getToken()); } catch (\Exception $e) {}
            return response()->json(['success' => false, 'message' => '정지된 계정입니다: ' . $user->ban_reason], 403);
        }

        $user->update(['last_login_at' => now(), 'login_count' => $user->login_count + 1]);

        // 일일 로그인 보너스 (P2B-2: DB 동적)
        // Carbon 3의 diffInHours() 부호 변경으로 과거 시각과 비교 시 항상 음수가
        // 나와서 >= 12 비교가 영원히 거짓이 되어(최초 로그인 이후 다시는) 보너스가
        // 지급되지 않던 버그 — abs()로 절대값 비교하도록 수정.
        $lastLogin = $user->getOriginal('last_login_at');
        if (!$lastLogin || abs(now()->diffInHours($lastLogin)) >= 12) {
            $loginBonus = \App\Support\PointRules::get('daily_login_bonus', 2);
            if ($loginBonus > 0) $user->addPoints($loginBonus, '일일 로그인 보너스');
        }

        return response()->json(['success' => true, 'data' => ['token' => $token, 'user' => $user]]);
    }

    /**
     * 토큰 갱신: 사용 중인 사람의 로그인이 1시간마다 끊기지 않도록, 만료 직전(또는 만료 후 갱신 가능 기간 안)의 토큰으로
     * 새 토큰을 받는다. 정지된 계정은 갱신하지 않고, 이전 토큰은 즉시 폐기된다.
     */
    public function refresh()
    {
        try {
            $new = JWTAuth::parseToken()->refresh();
            $user = JWTAuth::setToken($new)->toUser();
            if (!$user || $user->is_banned) {
                try { JWTAuth::setToken($new)->invalidate(); } catch (\Exception $e) {}
                return response()->json(['success' => false, 'message' => '정지되었거나 없는 계정입니다.'], 401);
            }
            return response()->json(['success' => true, 'data' => ['token' => $new]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => '세션이 만료되었어요. 다시 로그인해 주세요.'], 401);
        }
    }

    public function logout()
    {
        try { JWTAuth::invalidate(JWTAuth::getToken()); } catch (\Exception $e) {}
        return response()->json(['success' => true, 'message' => '로그아웃 완료']);
    }

    public function user()
    {
        return response()->json(['success' => true, 'data' => auth()->user()]);
    }

    // 비밀번호 재설정 요청 (코드 발송) — Issue #7: 계정 열거 방어 + Issue #8: 쿨다운
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();

        // 공통 응답 (계정 존재 여부 노출 금지)
        $publicResponse = response()->json([
            'success' => true,
            'message' => '해당 이메일이 등록되어 있다면 재설정 코드를 전송했습니다',
        ]);

        if (!$user) {
            // 타이밍 공격 완화용 소량 지연 (비활성 경로 속도 차이 숨김)
            usleep(random_int(50_000, 150_000));
            return $publicResponse;
        }

        // 5분 쿨다운 — 동일 이메일 연속 요청 차단
        // Carbon 3부터 diffInMinutes()가 기본적으로 부호 있는 값을 반환함(과거
        // 시각과 비교하면 음수) — abs() 없이 비교하면 음수는 항상 5보다 작아서
        // 한 번이라도 요청한 이메일은 영원히 쿨다운에 걸린 것으로 판정되어
        // 다시는 메일이 발송되지 않던 버그(이번 이메일 미발송 사태의 진짜 원인).
        $existing = \DB::table('password_reset_tokens')->where('email', $request->email)->first();
        if ($existing && abs(now()->diffInMinutes($existing->created_at)) < 5) {
            return $publicResponse; // 응답은 동일하지만 내부적으로 skip
        }

        // 6자리 코드 생성 (DB에 저장)
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => Hash::make($code), 'created_at' => now()]
        );

        try {
            \Illuminate\Support\Facades\Mail::to($request->email)->send(new \App\Mail\PasswordResetCodeMail($code));
        } catch (\Exception $e) {
            // 메일 전송 실패해도 코드는 생성됨(응답은 그대로 성공처럼 보임) — 원인 추적용 로그만 남김
            \Log::warning("비밀번호 재설정 코드 메일 발송 실패 ({$request->email}): " . $e->getMessage());
        }

        // 로컬 환경에서만 코드 노출 (테스트 편의)
        if (app()->environment('local')) {
            return response()->json([
                'success' => true,
                'message' => '해당 이메일이 등록되어 있다면 재설정 코드를 전송했습니다',
                'dev_code' => $code,
            ]);
        }
        return $publicResponse;
    }

    // 비밀번호 재설정 (코드 확인 + 새 비밀번호)
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => $this->passwordRules(),
        ]);
        $record = \DB::table('password_reset_tokens')->where('email', $request->email)->first();
        if (!$record || !Hash::check($request->code, $record->token)) {
            // 6자리 코드를 계속 대입하는 공격 방어: 한 코드당 5번 틀리면 그 코드를 폐기(다시 요청해야 함)
            if ($record) {
                $k = 'reset-fail:' . sha1(mb_strtolower($request->email));
                \Illuminate\Support\Facades\RateLimiter::hit($k, 1800);
                if (\Illuminate\Support\Facades\RateLimiter::attempts($k) >= 5) {
                    \DB::table('password_reset_tokens')->where('email', $request->email)->delete();
                    \Illuminate\Support\Facades\RateLimiter::clear($k);
                    return response()->json(['success' => false, 'message' => '틀린 횟수가 많아 코드를 폐기했어요. 코드를 다시 요청해 주세요.'], 422);
                }
            }
            return response()->json(['success' => false, 'message' => '인증 코드가 올바르지 않습니다'], 422);
        }
        if (now()->diffInMinutes($record->created_at) > 30) {
            return response()->json(['success' => false, 'message' => '코드가 만료되었습니다. 다시 요청해주세요'], 422);
        }
        $user = User::where('email', $request->email)->first();
        if (!$user) return response()->json(['success' => false, 'message' => '사용자를 찾을 수 없습니다'], 404);
        $user->update(['password' => Hash::make($request->password)]);
        \DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        return response()->json(['success' => true, 'message' => '비밀번호가 성공적으로 변경되었습니다']);
    }

    // 비밀번호 변경 (로그인 상태)
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => $this->passwordRules(),
        ]);
        $user = auth()->user();
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['success' => false, 'message' => '현재 비밀번호가 올바르지 않습니다'], 422);
        }
        $user->update(['password' => Hash::make($request->password)]);
        return response()->json(['success' => true, 'message' => '비밀번호가 변경되었습니다']);
    }
}
