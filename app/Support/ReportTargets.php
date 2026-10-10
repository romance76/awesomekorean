<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * 신고 대상 종류 한 곳 관리.
 * 화면마다 'post'·'comment'·'user' 같은 짧은 이름과 'App\Models\…' 전체 이름을 섞어 보내므로
 * 접수할 때 전체 이름으로 맞춰 저장한다(관리 화면의 종류 표시·콘텐츠 숨김이 전체 이름 기준).
 */
class ReportTargets
{
    private const ALIASES = [
        'post' => \App\Models\Post::class,
        'comment' => \App\Models\Comment::class,
        'user' => User::class,
        'market' => \App\Models\MarketItem::class,
        'realestate' => \App\Models\RealEstateListing::class,
        'groupbuy' => \App\Models\GroupBuy::class,
        'shopping' => \App\Models\AmazonProduct::class,
    ];

    /** 받은 이름을 전체 클래스 이름으로. 신고할 수 없는 종류면 null */
    public static function resolve(?string $type): ?string
    {
        if ($type === null || $type === '') return null;
        $type = ltrim($type, '\\');
        $class = self::ALIASES[strtolower($type)] ?? $type;
        return in_array($class, self::ALIASES, true) && class_exists($class) ? $class : null;
    }

    /** 대상의 작성자(주인) 회원 id — 회원 신고면 그 회원 자신 */
    public static function ownerId(Model $target): ?int
    {
        if ($target instanceof User) return (int) $target->id;
        $id = $target->getAttribute('user_id');
        return $id !== null ? (int) $id : null;
    }

    /**
     * 운영자는 관리자·최고관리자(그리고 다른 운영자)가 쓴 글을 숨길 수 없다. 막아야 하면 안내 문구, 아니면 null.
     */
    public static function moderatorHideDenied(?Model $target, ?User $actor): ?string
    {
        if (!$target || !$actor || $actor->role !== 'moderator') return null;
        $ownerId = self::ownerId($target);
        if (!$ownerId || $ownerId === (int) $actor->id) return null;
        $ownerRole = User::whereKey($ownerId)->value('role');
        return in_array($ownerRole, ['moderator', 'admin', 'super_admin'], true)
            ? '운영진이 쓴 글은 관리자 이상만 숨길 수 있어요.'
            : null;
    }
}
