<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\EntryService;
use App\Support\EntrySettings;
use App\Support\PointRules;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Google/Amazon "~로 로그인" — 일반 회원가입(AuthController::register)처럼 이메일/비밀번호를
 * 새로 만들지 않고, 공급자가 이미 본인 확인한 이메일로 바로 로그인/가입시킴.
 *
 * 세션 기반 state 검증을 쓰려고 일부러 routes/api.php가 아니라 routes/web.php에 둠
 * (stateless()를 쓰면 CSRF 방지용 state 파라미터 검증이 빠짐).
 */
class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'amazon'];

    public function redirect(string $provider)
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            abort(404);
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            abort(404);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            Log::warning("소셜 로그인 실패 ({$provider}): " . $e->getMessage());
            return redirect('/login?social_error=1');
        }

        $email = $socialUser->getEmail();
        if (!$email) {
            // Amazon은 사용자가 이메일 공유에 동의하지 않으면 이메일을 안 줄 수 있음
            return redirect('/login?social_error=no_email');
        }

        $user = User::where('provider', $provider)->where('provider_id', $socialUser->getId())->first();

        if (!$user) {
            // 이미 같은 이메일로 가입된 계정이 있으면 소셜 로그인 정보만 연결(계정 통합).
            // Google/Amazon 모두 자체적으로 본인 확인한 이메일만 내려주므로 안전함.
            $user = User::where('email', $email)->first();

            if ($user) {
                $user->update(['provider' => $provider, 'provider_id' => $socialUser->getId()]);
            } else {
                $name = $socialUser->getName() ?: $socialUser->getNickname() ?: explode('@', $email)[0];

                $user = User::create([
                    'name' => $name,
                    'nickname' => $name,
                    'email' => $email,
                    'password' => null,
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'email_verified_at' => now(), // 공급자가 이미 본인 확인을 마쳤으므로 별도 인증메일 불필요
                    'language' => 'ko',
                    'allow_friend_request' => true,
                ]);

                $signupBonus = PointRules::get('signup_bonus', 10);
                if ($signupBonus > 0) $user->addPoints($signupBonus, '회원가입 보너스');

                $entrySignupBonus = EntrySettings::get('signup_bonus', 1);
                if ($entrySignupBonus > 0) {
                    EntryService::award($user, $entrySignupBonus, 'SIGNUP_BONUS', '회원가입 Entry 보너스', 'signup');
                }
            }
        }

        if ($user->is_banned) {
            return redirect('/login?social_error=banned');
        }

        $user->update(['last_login_at' => now(), 'login_count' => $user->login_count + 1]);

        $lastLogin = $user->getOriginal('last_login_at');
        if (!$lastLogin || now()->diffInHours($lastLogin) >= 12) {
            $loginBonus = PointRules::get('daily_login_bonus', 2);
            if ($loginBonus > 0) $user->addPoints($loginBonus, '일일 로그인 보너스');
        }

        $token = JWTAuth::fromUser($user);

        // SPA가 URL에서 토큰을 읽어 저장하도록 전달 (resources/js/pages/auth/SocialCallback.vue)
        return redirect('/auth/social-callback?token=' . urlencode($token));
    }
}
