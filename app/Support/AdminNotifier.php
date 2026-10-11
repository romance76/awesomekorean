<?php

namespace App\Support;

use App\Events\NewNotification;
use App\Models\Notification;
use App\Models\User;

/**
 * 관리자(운영진)에게 보내는 알림 한 곳 — 알림을 누르면 일반 회원 화면이 아니라 해당 "관리자 처리 화면"이 열리도록
 * data.url 에 /admin/... 주소를 넣는다 (NavBar·알림 페이지가 data.url 을 그대로 연다).
 *
 * 받는 사람은 "그 화면을 열 수 있는 등급"만 — 운영자(1)는 신고만, 관리자(2)는 광고·전단·소유권·보상·쇼핑 등, 최고관리자(3)는 경품·시스템.
 * (AdminTier 의 등급표와 같은 기준. 못 여는 화면으로 가는 알림을 보내지 않는다)
 * 알림을 만들다 실패해도 원래 요청(신고 접수·광고 신청 등)은 절대 실패하지 않는다.
 */
class AdminNotifier
{
    private const ROLES_FOR_RANK = [
        1 => ['moderator', 'admin', 'super_admin'],
        2 => ['admin', 'super_admin'],
        3 => ['super_admin'],
    ];

    /** @return int 알림을 보낸 운영진 수 */
    public static function send(int $minRank, string $type, string $title, string $content, string $url, array $data = []): int
    {
        $sent = 0;
        try {
            $ids = User::whereIn('role', self::ROLES_FOR_RANK[$minRank] ?? self::ROLES_FOR_RANK[2])->pluck('id');
            foreach ($ids as $adminId) {
                try {
                    Notification::create([
                        'user_id' => $adminId, 'type' => $type, 'title' => $title, 'content' => mb_substr($content, 0, 500),
                        'data' => array_merge($data, ['url' => $url]),
                    ]);
                    $unread = Notification::where('user_id', $adminId)->whereNull('read_at')->count();
                    broadcast(new NewNotification((int) $adminId, $unread, $title));
                    $sent++;
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
        return $sent;
    }
}
