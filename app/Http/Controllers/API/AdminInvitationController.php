<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * 관리자 초대 시스템 (Phase 2-C Post).
 * super_admin 이 이메일로 신규 관리자 초대 → 7일 이내 링크 클릭 → 역할 자동 부여.
 */
class AdminInvitationController extends Controller
{
    /** 관리자 초대 목록 */
    public function index()
    {
        $rows = DB::table('admin_invitations')
            ->leftJoin('users', 'users.id', '=', 'admin_invitations.invited_by')
            ->select('admin_invitations.*', 'users.nickname as invited_by_name', 'users.email as invited_by_email')
            ->orderByDesc('admin_invitations.created_at')
            ->limit(100)
            ->get();
        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** 초대 생성 + 이메일 발송 */
    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:191',
            'role'  => 'required|string|in:super_admin,manager,moderator',
            'note'  => 'nullable|string|max:500',
        ]);

        // 기존 유저 존재 확인
        if (User::where('email', $data['email'])->exists()) {
            return response()->json(['success' => false, 'message' => '이미 가입된 이메일입니다. 회원관리에서 역할을 부여하세요.'], 422);
        }

        // 활성 초대 중복 방지
        $existing = DB::table('admin_invitations')
            ->where('email', $data['email'])
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();
        if ($existing) {
            return response()->json(['success' => false, 'message' => '이미 유효한 초대가 존재합니다'], 422);
        }

        $token = Str::random(48);
        $expires = now()->addDays(7);

        $id = DB::table('admin_invitations')->insertGetId([
            'email'      => $data['email'],
            'role'       => $data['role'],
            'token'      => $token,
            'invited_by' => auth()->id(),
            'status'     => 'pending',
            'expires_at' => $expires,
            'note'       => $data['note'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 이메일 발송
        $acceptUrl = url("/admin/accept-invitation?token={$token}");
        try {
            Mail::html($this->emailBody($data['role'], $acceptUrl, $expires), function ($m) use ($data) {
                $m->to($data['email'])->subject('🎉 SomeKorean 관리자로 초대되었습니다');
            });
        } catch (\Throwable $e) {
            // 이메일 실패해도 초대 레코드는 유지 (수동 링크 공유 가능)
            \Log::warning('Admin invitation email failed: ' . $e->getMessage());
        }

        DB::table('admin_audit_log')->insert([
            'admin_id'    => auth()->id(),
            'action'      => 'admin_invite_sent',
            'target_type' => 'admin_invitation',
            'target_id'   => $id,
            'after_value' => json_encode(['email' => $data['email'], 'role' => $data['role']], JSON_UNESCAPED_UNICODE),
            'ip'          => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $id,
                'accept_url' => $acceptUrl,
                'expires_at' => $expires->toIso8601String(),
            ],
        ]);
    }

    /** 초대 취소 */
    public function revoke($id)
    {
        DB::table('admin_invitations')->where('id', $id)->update(['status' => 'revoked', 'updated_at' => now()]);
        return response()->json(['success' => true]);
    }

    /**
     * 공개: 초대 수락 (토큰 검증 + 유저 계정 생성 또는 역할 부여).
     */
    public function accept(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'name'  => 'required|string|max:255',
            'nickname' => 'nullable|string|max:100',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $inv = DB::table('admin_invitations')->where('token', $data['token'])->first();
        if (!$inv) return response()->json(['success' => false, 'message' => '유효하지 않은 초대'], 404);
        if ($inv->status !== 'pending') return response()->json(['success' => false, 'message' => '이미 처리된 초대'], 422);
        if (now()->greaterThan($inv->expires_at)) {
            DB::table('admin_invitations')->where('id', $inv->id)->update(['status' => 'expired']);
            return response()->json(['success' => false, 'message' => '만료된 초대'], 422);
        }

        // 유저 생성 (mass assignment 우회 to set role)
        $user = new User();
        $user->forceFill([
            'email'    => $inv->email,
            'name'     => $data['name'],
            'nickname' => $data['nickname'] ?? $data['name'],
            'password' => Hash::make($data['password']),
            'role'     => $inv->role,  // legacy 컬럼
            'email_verified_at' => now(),
        ])->save();

        // Spatie 역할 부여
        try {
            $user->assignRole($inv->role);
        } catch (\Throwable $e) {}

        DB::table('admin_invitations')->where('id', $inv->id)->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'accepted_user_id' => $user->id,
            'updated_at' => now(),
        ]);

        DB::table('admin_audit_log')->insert([
            'admin_id'    => $inv->invited_by,
            'action'      => 'admin_invite_accepted',
            'target_type' => 'user',
            'target_id'   => $user->id,
            'note'        => "초대자 #{$inv->invited_by} → 수락자 #{$user->id} ({$inv->email})",
            'ip'          => $request->ip(),
            'created_at'  => now(),
        ]);

        // 즉시 로그인 토큰
        $token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);

        return response()->json([
            'success' => true,
            'data' => ['token' => $token, 'user' => $user->fresh()],
        ]);
    }

    protected function emailBody(string $role, string $acceptUrl, \Carbon\Carbon $expires): string
    {
        $roleLabel = ['super_admin' => '최고 관리자', 'manager' => '관리자', 'moderator' => '모더레이터'][$role] ?? $role;
        $expiresStr = $expires->toDateTimeString();
        return <<<HTML
<!DOCTYPE html>
<html lang="ko"><head><meta charset="UTF-8"></head>
<body style="font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
  <div style="background: linear-gradient(135deg, #f59e0b, #fb923c); color: white; padding: 30px; border-radius: 12px; text-align: center;">
    <h1 style="margin: 0;">🎉 관리자 초대</h1>
    <p style="margin: 10px 0 0;">SomeKorean <strong>{$roleLabel}</strong> 역할로 초대되었습니다</p>
  </div>
  <div style="background: #fff; padding: 24px; margin-top: 16px; border-radius: 12px;">
    <p>아래 버튼을 클릭하여 계정을 생성하고 관리자 권한을 활성화하세요.</p>
    <p style="text-align: center; margin: 24px 0;">
      <a href="{$acceptUrl}" style="display: inline-block; background: #f59e0b; color: white; padding: 12px 32px; border-radius: 8px; text-decoration: none; font-weight: bold;">초대 수락하기</a>
    </p>
    <p style="font-size: 12px; color: #999;">
      <strong>만료 일시:</strong> {$expiresStr}<br>
      링크가 동작하지 않으면 주소창에 복사하세요:<br>
      <code style="word-break: break-all;">{$acceptUrl}</code>
    </p>
  </div>
</body></html>
HTML;
    }
}
