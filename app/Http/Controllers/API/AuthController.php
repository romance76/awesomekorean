<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:50',
            'username' => 'required|string|max:30|unique:users|alpha_dash',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'username' => $request->username,
            'email'    => $request->email,
            'password' => $request->password,
            'lang'     => $request->lang ?? 'ko',
        ]);

        // DB 기본값(level=씨앗, points=0, cash=0)을 반영하기 위해 fresh() 사용
        $user = $user->fresh();

        // 포인트 적립: 회원가입
        $user->addPoints(10, 'register', 'earn', null, '회원가입');

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => '회원가입이 완료되었습니다. 이메일 인증을 해주세요.',
            'token'   => $token,
            'user'    => $this->userResource($user),
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['message' => '이메일 또는 비밀번호가 올바르지 않습니다.'], 401);
        }
        $user = JWTAuth::user();
        if ($user->status === 'banned') {
            return response()->json(['message' => '정지된 계정입니다.'], 403);
        }
        // 로그인 일일 포인트 (오늘 첫 로그인 시)
        $todayFirstLogin = !$user->last_login_at || !$user->last_login_at->isToday();
        \DB::table('users')->where('id', $user->id)->update(['last_login_at' => now()]);
        if ($todayFirstLogin) {
            $user->addPoints(2, 'daily_login', 'earn', null, '일일 로그인');
        }
        return response()->json(['token' => $token, 'user' => $this->userResource($user)]);
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['message' => '로그아웃되었습니다.']);
    }

    public function refresh()
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            return response()->json(['token' => $token]);
        } catch (\Exception $e) {
            return response()->json(['message' => '토큰 갱신 실패'], 401);
        }
    }

    public function me()
    {
        return response()->json(['user' => $this->userResource(JWTAuth::user())]);
    }

    // 회원탈퇴 (소프트 삭제: status를 banned로 변경, email/username 익명화)
    public function deleteAccount(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = JWTAuth::user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => '비밀번호가 올바르지 않습니다.'], 400);
        }

        // JWT 토큰 무효화
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (\Exception $e) {
            // 이미 무효화된 토큰은 무시
        }

        // 계정 비활성화 (소프트 삭제 방식)
        $user->update([
            'status'   => 'banned',
            'email'    => 'deleted_' . $user->id . '_' . time() . '@deleted.com',
            'username' => 'deleted_' . $user->id,
            'name'     => '탈퇴회원',
            'avatar'   => null,
            'bio'      => null,
        ]);

        return response()->json(['message' => '회원탈퇴가 완료되었습니다.']);
    }

    private function userResource(User $user): array
    {
        return [
            'id'        => $user->id,
            'name'      => $user->name,
            'nickname'  => $user->nickname,
            'username'  => $user->username,
            'email'     => $user->email,
            'avatar'    => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'level'     => $user->level,
            'points'    => $user->points_total,
            'points_total' => $user->points_total,
            'cash'      => $user->cash_balance,
            'bio'       => $user->bio,
            'phone'     => $user->phone,
            'address'   => $user->address,
            'address2'  => $user->address2,
            'city'      => $user->city,
            'state'     => $user->state,
            'zip_code'  => $user->zip_code,
            'region'    => $user->region,
            'default_radius' => $user->default_radius,
            'lat'       => $user->lat,
            'lng'       => $user->lng,
            'is_admin'  => $user->is_admin,
            'verified'  => !is_null($user->email_verified_at),
            'lang'      => $user->lang,
            'payment_method' => $user->payment_method,
            'payment_last4'  => $user->payment_last4,
        ];
    }
}
