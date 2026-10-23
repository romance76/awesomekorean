<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * 광고·소식 메일 수신 동의(옵트인) 기록과 수신 거부 링크.
 * 규칙(qa/EMAIL_AD_PLAN.md): 동의한 회원에게만 광고 메일을 보내고, 모든 광고 메일에 수신 거부 링크를 넣는다.
 */
class EmailConsent
{
    /** 동의 문구를 바꾸면 올린다 — 동의 이력에 어떤 문구에 동의했는지 남기기 위함 */
    public const TEXT_VERSION = 'v1';

    public static function grant(User $user, string $source, ?string $ip = null): void
    {
        $now = now();
        $user->forceFill([
            'marketing_email_consent' => true, 'marketing_consent_at' => $now, 'marketing_consent_asked_at' => $now,
        ])->save();
        self::log($user->id, 'granted', $source, $ip);
    }

    public static function revoke(User $user, string $source, ?string $ip = null): void
    {
        $user->forceFill(['marketing_email_consent' => false, 'marketing_consent_asked_at' => $user->marketing_consent_asked_at ?: now()])->save();
        self::log($user->id, 'revoked', $source, $ip);
    }

    private static function log(int $userId, string $action, string $source, ?string $ip): void
    {
        DB::table('email_consent_logs')->insert([
            'user_id' => $userId, 'action' => $action, 'source' => $source,
            'ip' => $ip, 'text_version' => self::TEXT_VERSION, 'created_at' => now(),
        ]);
    }

    /** 메일 맨 아래에 넣는 수신 거부 링크(만료 없음 — 오래된 메일에서도 눌러서 끊을 수 있어야 함) */
    public static function unsubscribeUrl(User $user): string
    {
        return URL::signedRoute('email.unsubscribe', ['user' => $user->id]);
    }

    /** 광고 메일을 받을 수 있는 회원(동의 + 이메일 인증 + 정지 안 됨). 발송 단계에서 이 조건을 그대로 쓴다 */
    public static function eligibleQuery()
    {
        return User::query()
            ->where('marketing_email_consent', true)
            ->whereNotNull('email_verified_at')
            ->where(fn($q) => $q->whereNull('is_banned')->orWhere('is_banned', false));
    }
}
