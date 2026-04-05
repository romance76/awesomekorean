<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * POST /api/register
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:50',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'nickname' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '입력값을 확인해주세요.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'nickname' => $request->nickname ?? $request->name,
            'language' => $request->language ?? 'ko',
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => '회원가입이 완료되었습니다.',
            'data'    => [
                'token' => $token,
                'user'  => $this->formatUser($user),
            ],
        ], 201);
    }

    /**
     * POST /api/login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '이메일과 비밀번호를 입력해주세요.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => '이메일 또는 비밀번호가 올바르지 않습니다.',
            ], 401);
        }

        // Use is_banned instead of status
        if ($user->is_banned) {
            return response()->json([
                'success' => false,
                'message' => '정지된 계정입니다.',
            ], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => [
                'token' => $token,
                'user'  => $this->formatUser($user),
            ],
        ]);
    }

    /**
     * POST /api/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => '로그아웃되었습니다.',
        ]);
    }

    /**
     * GET /api/user
     */
    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($user),
        ]);
    }

    /**
     * Format user data for response.
     * Uses actual DB column names: points, zipcode, latitude, longitude, language, role
     */
    private function formatUser(User $user): array
    {
        return [
            'id'        => $user->id,
            'name'      => $user->name,
            'nickname'  => $user->nickname,
            'email'     => $user->email,
            'avatar'    => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'points'    => $user->points ?? 0,
            'bio'       => $user->bio,
            'phone'     => $user->phone,
            'city'      => $user->city,
            'state'     => $user->state,
            'zipcode'   => $user->zipcode,
            'latitude'  => $user->latitude,
            'longitude' => $user->longitude,
            'is_admin'  => $user->role === 'admin',
            'role'      => $user->role,
            'language'  => $user->language ?? 'ko',
            'created_at' => $user->created_at,
        ];
    }
}
