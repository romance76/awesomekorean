<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * MyPage 홈 통합 API (Phase 2-C Post UX).
 * 유저 대시보드 첫 화면 한 번에 필요한 모든 집계 데이터.
 */
class MyPageHomeController extends Controller
{
    public function summary()
    {
        $user = auth()->user();
        $uid  = $user->id;

        $tbl = fn($t) => \Schema::hasTable($t);

        return response()->json(['success' => true, 'data' => [
            'user' => [
                'id'       => $user->id,
                'name'     => $user->name,
                'nickname' => $user->nickname,
                'email'    => $user->email,
                'avatar'   => $user->avatar,
                'points'   => $user->points ?? 0,
                'game_points' => $user->game_points ?? 0,
                'role'     => $user->role,
                'joined_at' => $user->created_at,
            ],
            'profile_completion' => $this->profileCompletion($user),
            'counts' => [
                'posts'        => $tbl('posts') ? DB::table('posts')->where('user_id', $uid)->count() : 0,
                'comments'     => $tbl('comments') ? DB::table('comments')->where('user_id', $uid)->count() : 0,
                'market'       => $tbl('market_items') ? DB::table('market_items')->where('user_id', $uid)->count() : 0,
                'realestate'   => $tbl('real_estate_listings') ? DB::table('real_estate_listings')->where('user_id', $uid)->count() : 0,
                'jobs'         => $tbl('jobs') ? DB::table('jobs')->where('user_id', $uid)->count() : 0,
                'bookmarks'    => $tbl('bookmarks') ? DB::table('bookmarks')->where('user_id', $uid)->count() : 0,
                'friends'      => $tbl('friends') ? DB::table('friends')->where(fn($q) => $q->where('user_id', $uid)->orWhere('friend_id', $uid))->where('status', 'accepted')->count() : 0,
            ],
            'unread' => [
                'notifications' => $tbl('notifications') ? DB::table('notifications')->where('notifiable_id', $uid)->whereNull('read_at')->count() : 0,
                'messages'      => $tbl('messages') ? DB::table('messages')->where('recipient_id', $uid)->whereNull('read_at')->count() : 0,
                'friend_requests' => $tbl('friends') ? DB::table('friends')->where('friend_id', $uid)->where('status', 'pending')->count() : 0,
            ],
            'recent_activity' => $this->recentActivity($uid),
            'this_month' => $this->thisMonthStats($uid),
            'announcement' => $this->topAnnouncement(),
        ]]);
    }

    /** 프로필 완성도 — 8개 필수/권장 필드 체크 */
    protected function profileCompletion(User $user): array
    {
        $checks = [
            'name'     => !empty($user->name),
            'nickname' => !empty($user->nickname),
            'phone'    => !empty($user->phone),
            'bio'      => !empty($user->bio),
            'avatar'   => !empty($user->avatar),
            'city'     => !empty($user->city),
            'zipcode'  => !empty($user->zipcode),
            'email_verified' => $user->email_verified_at !== null,
        ];
        $done  = count(array_filter($checks));
        $total = count($checks);
        $missing = array_keys(array_filter($checks, fn($v) => !$v));

        return [
            'percentage' => (int) ($total > 0 ? round($done / $total * 100) : 0),
            'done'       => $done,
            'total'      => $total,
            'missing'    => $missing,
            'suggestions' => array_map(fn($k) => [
                'key' => $k,
                'label' => [
                    'name'=>'이름 등록', 'nickname'=>'닉네임 설정', 'phone'=>'전화번호 추가',
                    'bio'=>'자기소개 작성', 'avatar'=>'프로필 사진 업로드',
                    'city'=>'거주 도시', 'zipcode'=>'우편번호', 'email_verified'=>'이메일 인증',
                ][$k] ?? $k,
            ], $missing),
        ];
    }

    /** 최근 활동 10건 — event_log 우선, 없으면 posts/comments 조합 */
    protected function recentActivity(int $uid): array
    {
        if (\Schema::hasTable('event_log')) {
            return DB::table('event_log')
                ->where('user_id', $uid)
                ->orderByDesc('occurred_at')
                ->limit(10)
                ->get(['event_type', 'target_type', 'target_id', 'meta', 'occurred_at'])
                ->toArray();
        }

        // fallback
        $out = [];
        if (\Schema::hasTable('posts')) {
            DB::table('posts')->where('user_id', $uid)->orderByDesc('created_at')->limit(5)->get()
                ->each(function ($p) use (&$out) {
                    $out[] = ['event_type'=>'post.created','target_type'=>'post','target_id'=>$p->id,'meta'=>json_encode(['title'=>$p->title]),'occurred_at'=>$p->created_at];
                });
        }
        return $out;
    }

    /** 이번 달 활동 집계 */
    protected function thisMonthStats(int $uid): array
    {
        $start = now()->startOfMonth();
        return [
            'posts_this_month'    => \Schema::hasTable('posts') ? DB::table('posts')->where('user_id', $uid)->where('created_at', '>=', $start)->count() : 0,
            'comments_this_month' => \Schema::hasTable('comments') ? DB::table('comments')->where('user_id', $uid)->where('created_at', '>=', $start)->count() : 0,
            'points_earned_this_month' => \Schema::hasTable('point_histories') ? (int) DB::table('point_histories')->where('user_id', $uid)->where('created_at', '>=', $start)->where('amount', '>', 0)->sum('amount') : 0,
            'points_spent_this_month'  => \Schema::hasTable('point_histories') ? (int) abs(DB::table('point_histories')->where('user_id', $uid)->where('created_at', '>=', $start)->where('amount', '<', 0)->sum('amount')) : 0,
        ];
    }

    /** 최신 1개 활성 공지 */
    protected function topAnnouncement(): ?array
    {
        if (!\Schema::hasTable('announcements')) return null;
        $row = DB::table('announcements')
            ->where('is_active', true)
            ->where(function ($q) { $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()); })
            ->where(function ($q) { $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()); })
            ->orderByDesc('created_at')
            ->first(['title', 'message', 'level', 'link_url', 'link_label']);
        return $row ? (array) $row : null;
    }
}
