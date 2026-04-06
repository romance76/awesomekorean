<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed',
            'nickname' => 'nullable|string|max:50',
        ]);

        $user = User::create([
            'name' => $request->name,
            'nickname' => $request->nickname ?? $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'language' => 'ko',
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

    // 비밀번호 재설정 요청 (코드 발송)
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => '해당 이메일로 등록된 계정이 없습니다'], 404);
        }
        // 6자리 코드 생성 (DB에 저장)
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => Hash::make($code), 'created_at' => now()]
        );
        // 실제 환경에서는 이메일 전송. 지금은 코드를 응답에 포함 (개발용)
        try {
            \Mail::raw("SomeKorean 비밀번호 재설정 코드: {$code}", function ($msg) use ($request) {
                $msg->to($request->email)->subject('[SomeKorean] 비밀번호 재설정 코드');
            });
        } catch (\Exception $e) {
            // 메일 전송 실패해도 코드는 생성됨
        }
        return response()->json(['success' => true, 'message' => '재설정 코드가 이메일로 전송되었습니다', 'dev_code' => app()->environment('local') ? $code : null]);
    }

    // 비밀번호 재설정 (코드 확인 + 새 비밀번호)
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|min:6|confirmed',
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
            'password' => 'required|min:6|confirmed',
        ]);
        $user = auth()->user();
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['success' => false, 'message' => '현재 비밀번호가 올바르지 않습니다'], 422);
        }
        $user->update(['password' => Hash::make($request->password)]);
        return response()->json(['success' => true, 'message' => '비밀번호가 변경되었습니다']);
    }
}
