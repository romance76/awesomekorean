<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * 관리자 전역 검색 + Impersonation (Phase 2-C Post).
 */
class AdminGlobalController extends Controller
{
    /**
     * Ctrl+K 헤더 검색 — users / posts / payments / businesses 통합 검색.
     * 각 카테고리 5건씩 반환.
     */
    public function search(Request $request)
    {
        $q = trim($request->query('q', ''));
        if (strlen($q) < 2) return response()->json(['success' => true, 'data' => []]);

        $results = [];

        // Users
        if (\Schema::hasTable('users')) {
            $users = DB::table('users')
                ->where(function ($w) use ($q) {
                    $w->where('email', 'like', "%{$q}%")
                      ->orWhere('nickname', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%")
                      ->orWhere('phone', 'like', "%{$q}%");
                })
                ->select('id', 'email', 'nickname', 'name', 'avatar', 'role', 'is_banned')
                ->limit(5)->get();

            if ($users->count()) {
                $results['users'] = $users->map(fn($u) => [
                    'id'    => $u->id,
                    'title' => $u->nickname ?: $u->name ?: $u->email,
                    'meta'  => $u->email . ($u->is_banned ? ' · 🚫 차단' : ''),
                    'url'   => "/admin/v2/users",
                    'action_url' => "/profile/{$u->id}",
                    'image' => $u->avatar,
                ]);
            }
        }

        // Posts
        if (\Schema::hasTable('posts') && is_numeric($q) || strlen($q) >= 3) {
            $posts = DB::table('posts')
                ->where(function ($w) use ($q) {
                    $w->where('title', 'like', "%{$q}%");
                    if (is_numeric($q)) $w->orWhere('id', $q);
                })
                ->select('id', 'title', 'user_id', 'created_at')
                ->orderByDesc('id')
                ->limit(5)->get();

            if ($posts->count()) {
                $results['posts'] = $posts->map(fn($p) => [
                    'id'    => $p->id,
                    'title' => $p->title,
                    'meta'  => "유저#{$p->user_id} · " . \Carbon\Carbon::parse($p->created_at)->diffForHumans(),
                    'url'   => "/admin/v2/content",
                    'action_url' => "/community/{$p->id}",
                ]);
            }
        }

        // Payments
        if (\Schema::hasTable('payments') && (is_numeric($q) || str_starts_with($q, 'pi_') || str_starts_with($q, 'ch_'))) {
            $payments = DB::table('payments')
                ->where(function ($w) use ($q) {
                    if (is_numeric($q)) $w->where('id', $q);
                    $w->orWhere('stripe_payment_id', 'like', "%{$q}%");
                })
                ->select('id', 'user_id', 'amount', 'status', 'stripe_payment_id')
                ->limit(5)->get();

            if ($payments->count()) {
                $results['payments'] = $payments->map(fn($p) => [
                    'id'    => $p->id,
                    'title' => "결제 #{$p->id} \${$p->amount}",
                    'meta'  => "{$p->status} · 유저#{$p->user_id}",
                    'url'   => "/admin/v2/payments",
                ]);
            }
        }

        // Businesses
        if (\Schema::hasTable('businesses') && strlen($q) >= 2) {
            $biz = DB::table('businesses')
                ->where('name', 'like', "%{$q}%")
                ->select('id', 'name', 'city', 'state')
                ->limit(5)->get();

            if ($biz->count()) {
                $results['businesses'] = $biz->map(fn($b) => [
                    'id'    => $b->id,
                    'title' => $b->name,
                    'meta'  => "{$b->city}, {$b->state}",
                    'url'   => "/admin/v2/directory",
                    'action_url' => "/directory/{$b->id}",
                ]);
            }
        }

        return response()->json(['success' => true, 'data' => $results, 'total' => collect($results)->flatten(1)->count()]);
    }

    /**
     * 유저 impersonation — 관리자가 문제 재현·CS 지원용으로 유저 계정 로그인.
     * 1회용 토큰 발급 + 감사 기록. super_admin·manager 만.
     */
    public function impersonate(Request $request, $userId)
    {
        $admin = auth()->user();
        $target = User::find($userId);
        if (!$target) return response()->json(['success' => false, 'message' => 'User not found'], 404);
        if ($target->is_banned) return response()->json(['success' => false, 'message' => '차단된 계정은 impersonate 불가'], 422);

        // impersonation 토큰 생성 (원본 관리자 ID claim 포함)
        $token = JWTAuth::customClaims([
            'impersonated_by' => $admin->id,
            'impersonation'   => true,
            'exp'             => now()->addMinutes(30)->timestamp,  // 30분 제한
        ])->fromUser($target);

        DB::table('admin_audit_log')->insert([
            'admin_id'   => $admin->id,
            'action'     => 'impersonate_user',
            'target_type'=> 'user',
            'target_id'  => $target->id,
            'note'       => "Admin {$admin->email} impersonating user {$target->email}",
            'ip'         => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user'  => $target->fresh(),
                'expires_in' => 30 * 60,
                'warning' => '30분 후 자동 만료. 사용 후 원래 계정으로 복귀하세요.',
            ],
        ]);
    }

    /**
     * 관리자가 impersonation 을 종료하고 원래 계정으로 복귀.
     */
    public function stopImpersonation(Request $request)
    {
        try {
            $payload = JWTAuth::getPayload();
            $originalAdminId = $payload->get('impersonated_by');
            if (!$originalAdminId) return response()->json(['success' => false, 'message' => 'Not impersonating'], 400);

            $admin = User::find($originalAdminId);
            if (!$admin) return response()->json(['success' => false, 'message' => 'Original admin not found'], 404);

            JWTAuth::invalidate(JWTAuth::getToken());
            $token = JWTAuth::fromUser($admin);

            return response()->json([
                'success' => true,
                'data' => ['token' => $token, 'user' => $admin],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
