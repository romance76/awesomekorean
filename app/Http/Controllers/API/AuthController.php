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
}
