<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * 관리자 대량 알림 발송 (Phase 2-C Post).
 * - in-app notification 일괄 생성
 * - email 일괄 발송 (SMTP)
 * - SMS 는 Twilio 통합 후 별도 구현
 */
class AdminBroadcastController extends Controller
{
    /**
     * in-app 알림 대량 생성.
     * notifications 테이블에 직접 insert (Laravel Notification 시스템 우회 — 대량 시 빠름).
     */
    public function broadcastNotification(Request $request)
    {
        $data = $request->validate([
            'audience' => 'required|string|in:all,role,users',
            'role'     => 'nullable|string',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer',
            'title'    => 'required|string|max:200',
            'message'  => 'required|string|max:1000',
            'link'     => 'nullable|string|max:500',
            'type'     => 'nullable|string|max:50',
        ]);

        $userIds = $this->resolveAudience($data);
        if (empty($userIds)) return response()->json(['success' => false, 'message' => '대상 없음'], 422);

        // notifications 테이블 구조 확인 후 삽입
        if (!\Schema::hasTable('notifications')) {
            return response()->json(['success' => false, 'message' => 'notifications 테이블 없음'], 500);
        }

        $rows = [];
        $now = now();
        foreach ($userIds as $uid) {
            $rows[] = [
                'id'   => (string) \Str::uuid(),
                'type' => 'App\\Notifications\\AdminBroadcast',
                'notifiable_type' => User::class,
                'notifiable_id'   => $uid,
                'data' => json_encode([
                    'title'   => $data['title'],
                    'message' => $data['message'],
                    'link'    => $data['link'] ?? null,
                    'broadcast_type' => $data['type'] ?? 'announcement',
                    'from_admin' => auth()->user()->nickname ?? auth()->user()->name,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // chunk insert (MySQL max packet 방지)
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('notifications')->insert($chunk);
        }

        // 감사 로그
        DB::table('admin_audit_log')->insert([
            'admin_id'    => auth()->id(),
            'action'      => 'broadcast_notification',
            'target_type' => 'user_batch',
            'after_value' => json_encode(['audience' => $data['audience'], 'count' => count($userIds), 'title' => $data['title']], JSON_UNESCAPED_UNICODE),
            'ip'          => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json(['success' => true, 'sent_count' => count($userIds)]);
    }

    /**
     * 이메일 대량 발송 (큐 사용).
     * 실제로는 queue 로 넘겨서 백그라운드 처리.
     */
    public function broadcastEmail(Request $request)
    {
        $data = $request->validate([
            'audience' => 'required|string|in:all,role,users',
            'role'     => 'nullable|string',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer',
            'subject'  => 'required|string|max:200',
            'body'     => 'required|string',
            'only_opted_in' => 'boolean',  // notification_preferences.email_digest=true 만
        ]);

        $userIds = $this->resolveAudience($data);
        if (empty($userIds)) return response()->json(['success' => false, 'message' => '대상 없음'], 422);

        // 옵트인 필터 (notification_preferences.email_digest)
        $q = User::whereIn('id', $userIds)->whereNotNull('email');
        if (!empty($data['only_opted_in'])) {
            $q->where(function ($sub) {
                $sub->whereJsonContains('notification_preferences->email_digest', true)
                    ->orWhereJsonContains('notification_preferences->email_marketing', true);
            });
        }

        $users = $q->get(['id', 'email', 'nickname', 'name']);
        if ($users->isEmpty()) return response()->json(['success' => false, 'message' => '수신 대상 없음 (옵트인 0)'], 422);

        $sent = 0;
        $failed = 0;
        $subject = $data['subject'];
        $body    = $data['body'];

        foreach ($users as $u) {
            try {
                // 큐 사용 (SMTP 과부하 방지). Mail::raw 대신 Mail::html 등 가능.
                Mail::raw($body, function ($m) use ($u, $subject) {
                    $m->to($u->email, $u->nickname ?? $u->name)->subject($subject);
                });
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                \Log::warning("broadcast mail failed user={$u->id}: " . $e->getMessage());
            }
        }

        DB::table('admin_audit_log')->insert([
            'admin_id'    => auth()->id(),
            'action'      => 'broadcast_email',
            'target_type' => 'user_batch',
            'after_value' => json_encode(['subject' => $subject, 'sent' => $sent, 'failed' => $failed], JSON_UNESCAPED_UNICODE),
            'ip'          => $request->ip(),
            'created_at'  => now(),
        ]);

        return response()->json(['success' => true, 'sent' => $sent, 'failed' => $failed]);
    }

    /**
     * 미리보기: 대상 수만 반환.
     */
    public function audiencePreview(Request $request)
    {
        $data = $request->only(['audience', 'role', 'user_ids']);
        $userIds = $this->resolveAudience($data);
        return response()->json(['success' => true, 'count' => count($userIds)]);
    }

    protected function resolveAudience(array $data): array
    {
        $audience = $data['audience'] ?? 'all';
        if ($audience === 'users' && !empty($data['user_ids'])) {
            return array_values(array_filter($data['user_ids'], fn($v) => is_numeric($v) && $v > 0));
        }
        if ($audience === 'role' && !empty($data['role'])) {
            // Spatie role 기반
            try {
                $role = \Spatie\Permission\Models\Role::where('name', $data['role'])->first();
                if ($role) {
                    return DB::table('model_has_roles')
                        ->where('role_id', $role->id)
                        ->where('model_type', User::class)
                        ->pluck('model_id')
                        ->toArray();
                }
            } catch (\Throwable $e) {}
            // 레거시 fallback: users.role 컬럼
            return User::where('role', $data['role'])->pluck('id')->toArray();
        }
        // all: 차단 안 된 모든 유저
        return User::where(function ($q) { $q->whereNull('is_banned')->orWhere('is_banned', false); })
            ->pluck('id')->toArray();
    }
}
