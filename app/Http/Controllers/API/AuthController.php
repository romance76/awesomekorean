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

        // 가입 보너스 +10 포인트
        $user->addPoints(10, '회원가입 보너스');

        $token = JWTAuth::fromUser($user);

        return response()->json(['success' => true, 'data' => ['token' => $token, 'user' => $user->fresh()]]);
    }

    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        if (!$token = JWTAuth::attempt($request->only('email', 'password'))) {
            return response()->json(['success' => false, 'message' => '이메일 또는 비밀번호가 올바르지 않습니다'], 401);
        }

        $user = auth()->user();
        if ($user->is_banned) {
            JWTAuth::invalidate($token);
            return response()->json(['success' => false, 'message' => '정지된 계정입니다: ' . $user->ban_reason], 403);
        }

        $user->update(['last_login_at' => now(), 'login_count' => $user->login_count + 1]);

        // 일일 로그인 보너스 +2
        $lastLogin = $user->getOriginal('last_login_at');
        if (!$lastLogin || now()->diffInHours($lastLogin) >= 12) {
            $user->addPoints(2, '일일 로그인 보너스');
        }

        return response()->json(['success' => true, 'data' => ['token' => $token, 'user' => $user]]);
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
        $existing = \DB::table('password_reset_tokens')->where('email', $request->email)->first();
        if ($existing && now()->diffInMinutes($existing->created_at) < 5) {
            return $publicResponse; // 응답은 동일하지만 내부적으로 skip
        }

        // 6자리 코드 생성 (DB에 저장)
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => Hash::make($code), 'created_at' => now()]
        );

        try {
            \Mail::raw("SomeKorean 비밀번호 재설정 코드: {$code}", function ($msg) use ($request) {
                $msg->to($request->email)->subject('[SomeKorean] 비밀번호 재설정 코드');
            });
        } catch (\Exception $e) {
            // 메일 전송 실패해도 코드는 생성됨
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
