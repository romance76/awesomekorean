<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\Totp;
use App\Support\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * 관리자·운영자 2단계 인증 설정 (인증 앱 6자리 코드).
 *  - 켜기: setup(비밀키 받기) → 인증 앱에 등록 → confirm(앱의 코드를 한 번 입력) → 복구 코드 8개를 한 번만 보여 줌
 *  - 끄기: 비밀번호 + 현재 코드(또는 복구 코드)
 *  - 최고관리자는 다른 사람의 2단계 인증을 해제할 수 있다(폰을 잃어버렸을 때). 서버에서는 php artisan user:2fa-reset 이메일
 * 켜기를 끝낸 계정만 로그인 때 코드를 요구한다. AuthController::login / SocialAuthController 참고.
 */
class TwoFactorController extends Controller
{
    private function me()
    {
        $u = auth('api')->user();
        abort_unless($u, 401);
        abort_unless(in_array($u->role, ['admin', 'super_admin', 'moderator'], true), 403, '관리자·운영자만 쓸 수 있어요');
        return $u;
    }

    public function status()
    {
        $u = $this->me();
        $left = 0;
        if (TwoFactor::enabled($u) && $u->two_factor_recovery_codes) {
            try { $left = count(json_decode(Crypt::decryptString($u->two_factor_recovery_codes), true) ?: []); } catch (\Throwable $e) {}
        }
        return response()->json(['success' => true, 'data' => [
            'enabled' => TwoFactor::enabled($u),
            'pending' => !TwoFactor::enabled($u) && !empty($u->two_factor_secret),
            'recovery_left' => $left,
        ]]);
    }

    /** 새 비밀키를 만들어 보여 준다(아직 켜진 것은 아니다 — confirm 까지 해야 켜진다) */
    public function setup()
    {
        $u = $this->me();
        if (TwoFactor::enabled($u)) {
            return response()->json(['success' => false, 'message' => '이미 켜져 있어요. 다시 설정하려면 먼저 끄세요.'], 422);
        }
        $secret = Totp::generateSecret();
        DB::table('users')->where('id', $u->id)->update([
            'two_factor_secret' => Crypt::encryptString($secret), 'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null, 'two_factor_last_step' => null,
        ]);
        return response()->json(['success' => true, 'data' => [
            'secret' => $secret,
            'otpauth_url' => Totp::otpauthUrl($secret, $u->email),
        ]]);
    }

    /** 인증 앱이 보여 주는 코드를 한 번 맞게 입력하면 켜지고, 복구 코드 8개를 처음이자 마지막으로 보여 준다 */
    public function confirm(Request $request)
    {
        $u = $this->me();
        $request->validate(['code' => 'required|string|max:20']);
        if (TwoFactor::enabled($u) || empty($u->two_factor_secret)) {
            return response()->json(['success' => false, 'message' => '먼저 "켜기"를 눌러 비밀키를 받아 주세요.'], 422);
        }
        try { $secret = Crypt::decryptString($u->two_factor_secret); } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => '비밀키를 읽지 못했어요. 다시 "켜기"부터 해 주세요.'], 422);
        }
        $step = Totp::verify($secret, (string) $request->input('code'));
        if ($step === null) {
            return response()->json(['success' => false, 'message' => '코드가 맞지 않아요. 인증 앱의 최신 6자리 코드를 입력해 주세요.'], 422);
        }
        $codes = TwoFactor::newRecoveryCodes(8);
        $key = (string) config('app.key');
        $hashes = array_map(fn ($c) => TwoFactor::hashRecovery(TwoFactor::normRecovery($c), $key), $codes);
        DB::table('users')->where('id', $u->id)->update([
            'two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step,
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($hashes)),
        ]);
        Log::info('[2fa] enabled', ['user' => $u->id]);
        return response()->json(['success' => true, 'data' => ['recovery_codes' => $codes]]);
    }

    /** 복구 코드를 새로 받는다(옛 코드는 모두 무효). 현재 코드가 필요하다. */
    public function regenerate(Request $request)
    {
        $u = $this->me();
        $request->validate(['code' => 'required|string|max:20']);
        if (!TwoFactor::enabled($u)) return response()->json(['success' => false, 'message' => '2단계 인증이 꺼져 있어요.'], 422);
        if (!TwoFactor::verifyAndConsume($u, (string) $request->input('code'))) {
            return response()->json(['success' => false, 'message' => '코드가 맞지 않아요.'], 422);
        }
        $codes = TwoFactor::newRecoveryCodes(8);
        $key = (string) config('app.key');
        $hashes = array_map(fn ($c) => TwoFactor::hashRecovery(TwoFactor::normRecovery($c), $key), $codes);
        DB::table('users')->where('id', $u->id)->update(['two_factor_recovery_codes' => Crypt::encryptString(json_encode($hashes))]);
        return response()->json(['success' => true, 'data' => ['recovery_codes' => $codes]]);
    }

    /** 끄기: 비밀번호와 현재 코드(또는 복구 코드)를 둘 다 확인한다 */
    public function disable(Request $request)
    {
        $u = $this->me();
        $request->validate(['password' => 'required|string', 'code' => 'required|string|max:20']);
        if (!TwoFactor::enabled($u)) return response()->json(['success' => false, 'message' => '이미 꺼져 있어요.'], 422);
        if (!Hash::check((string) $request->input('password'), (string) $u->password)) {
            return response()->json(['success' => false, 'message' => '비밀번호가 맞지 않아요.'], 422);
        }
        if (!TwoFactor::verifyAndConsume($u, (string) $request->input('code'))) {
            return response()->json(['success' => false, 'message' => '코드가 맞지 않아요.'], 422);
        }
        TwoFactor::clear($u->id);
        Log::info('[2fa] disabled by owner', ['user' => $u->id]);
        return response()->json(['success' => true]);
    }

    /** 최고관리자 전용: 다른 사람의 2단계 인증 해제(폰·복구 코드를 모두 잃은 경우). 라우트에서 role:super_admin 으로 막는다. */
    public function adminReset($id)
    {
        $actor = auth('api')->user();
        $target = DB::table('users')->where('id', $id)->first(['id', 'email']);
        abort_unless($target, 404);
        TwoFactor::clear((int) $target->id);
        Log::warning('[2fa] reset by super admin', ['actor' => $actor?->id, 'target' => $target->id]);
        return response()->json(['success' => true, 'message' => $target->email . ' 의 2단계 인증을 해제했어요. 본인이 다시 켜도록 안내해 주세요.']);
    }
}
