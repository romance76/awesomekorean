<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * 관리자 "사이트 설정 > 회원 설정 / 점검 모드" 의 켜기·끄기 값을 사이트 동작에 연결하는 한 곳.
 *
 * 예전에는 화면에서 저장만 되고 어디에서도 읽지 않아(껍데기) 점검 모드를 켜도, 가입을 막아도 아무 일도 일어나지 않았다.
 * 이제 아래 값을 가입·로그인·글쓰기·모든 API 요청이 이 클래스로 읽는다.
 *
 *  - maintenance_mode      점검 모드 (켜지면 관리자·운영자 외에는 503 + 안내) — 기본 꺼짐
 *  - allow_signup          회원가입 허용 (꺼지면 이메일 가입·소셜 신규 가입 차단, 기존 회원 로그인은 그대로) — 기본 허용
 *  - require_email_verify  이메일 인증 필수 (꺼지면 미인증 회원도 글쓰기 가능) — 기본 필수
 *  - min_password_length   비밀번호 최소 길이 — 기본 8, 보안 기준상 8 미만으로는 내려가지 않고 64 를 넘지 않는다
 *
 * 읽기는 60초 캐시이고, SiteSetting 이 저장·삭제될 때마다 자동으로 비워진다(그래서 관리자에서 끄면 바로 풀린다).
 * 설정을 읽지 못하는 사고(DB·캐시 장애)가 나면 "평소대로 동작" 값을 돌려준다 — 설정 때문에 사이트가 막히면 안 된다.
 */
class SiteFlags
{
    public const CACHE_KEY = 'site_flags_v1';
    public const KEYS = ['allow_signup', 'require_email_verify', 'min_password_length', 'maintenance_mode', 'maintenance_reason', 'maintenance_until'];

    public const PASSWORD_MIN_FLOOR = 8;    // 보안 기준(가입 규칙)보다 약해지지 않게
    public const PASSWORD_MAX_LEN = 64;

    public const DEFAULT_MAINTENANCE_MESSAGE = '지금은 사이트를 점검하고 있어요. 잠시 후 다시 이용해 주세요.';

    private const OFF_WORDS = ['0', 'false', 'off', 'no', 'n'];

    /** @return array{allow_signup:bool, require_email_verify:bool, min_password_length:int, maintenance_mode:bool, maintenance_reason:string, maintenance_until:?string} */
    public static function all(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 60, function () {
                return self::normalize(SiteSetting::whereIn('key', self::KEYS)->pluck('value', 'key')->all());
            });
        } catch (\Throwable $e) {
            return self::normalize([]);
        }
    }

    public static function forget(): void
    {
        try { Cache::forget(self::CACHE_KEY); } catch (\Throwable $e) {}
    }

    /** 저장된 문자열 값 → 안전한 값 (비어 있거나 알 수 없으면 기본값) */
    public static function normalize(array $rows): array
    {
        $flag = function (string $key, bool $default) use ($rows): bool {
            if (!array_key_exists($key, $rows) || $rows[$key] === null) return $default;
            $v = is_bool($rows[$key]) ? ($rows[$key] ? '1' : '0') : strtolower(trim((string) $rows[$key]));
            if ($v === '') return $default;
            return !in_array($v, self::OFF_WORDS, true);
        };
        $len = isset($rows['min_password_length']) && is_numeric($rows['min_password_length']) ? (int) $rows['min_password_length'] : self::PASSWORD_MIN_FLOOR;
        $until = isset($rows['maintenance_until']) ? trim((string) $rows['maintenance_until']) : '';
        $reason = isset($rows['maintenance_reason']) ? trim((string) $rows['maintenance_reason']) : '';

        return [
            'allow_signup' => $flag('allow_signup', true),
            'require_email_verify' => $flag('require_email_verify', true),
            'min_password_length' => max(self::PASSWORD_MIN_FLOOR, min(self::PASSWORD_MAX_LEN, $len)),
            'maintenance_mode' => $flag('maintenance_mode', false),
            'maintenance_reason' => $reason,
            'maintenance_until' => $until !== '' ? $until : null,
        ];
    }

    public static function signupAllowed(): bool
    {
        return self::all()['allow_signup'];
    }

    public static function emailVerifyRequired(): bool
    {
        return self::all()['require_email_verify'];
    }

    public static function minPasswordLength(): int
    {
        return self::all()['min_password_length'];
    }

    public static function maintenanceOn(): bool
    {
        return self::all()['maintenance_mode'];
    }

    /** 회원에게 보여 줄 점검 안내 {on, message, until} */
    public static function maintenance(): array
    {
        $a = self::all();
        return [
            'on' => $a['maintenance_mode'],
            'message' => $a['maintenance_reason'] !== '' ? $a['maintenance_reason'] : self::DEFAULT_MAINTENANCE_MESSAGE,
            'until' => $a['maintenance_until'],
        ];
    }

    /** 점검 중 API 응답 본문 — 프런트(axios 처리기)가 maintenance:true 로 알아본다 */
    public static function maintenancePayload(): array
    {
        $m = self::maintenance();
        return ['success' => false, 'maintenance' => true, 'message' => $m['message'], 'until' => $m['until']];
    }

    /** 점검 중에도 사이트를 쓸 수 있는 사람: 최고관리자·관리자·운영자 */
    public static function isStaff($user): bool
    {
        return $user && in_array($user->role ?? null, ['admin', 'super_admin', 'moderator'], true);
    }
}
