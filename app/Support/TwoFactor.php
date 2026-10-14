<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 관리자 2단계 인증(TOTP) — 스스로 켠 계정에만 적용된다. 켜지 않은 계정의 로그인은 전혀 달라지지 않는다.
 *
 * users 표의 칸(전부 API 응답에서는 숨김):
 *   two_factor_secret          인증 앱 비밀키 (APP_KEY 로 암호화해서 저장)
 *   two_factor_confirmed_at    앱에서 6자리 코드를 한 번 맞게 입력해 "켜기"가 끝난 시각 (비어 있으면 꺼진 상태)
 *   two_factor_recovery_codes  1회용 복구 코드의 해시 목록 (암호화 JSON, 평문 코드는 켤 때 한 번만 보여 줌)
 *   two_factor_last_step       마지막으로 쓴 30초 구간 번호 — 같은 코드를 다시 쓰는 것(재사용)을 막는다
 *
 * 폰을 잃어버렸을 때: 복구 코드 1개로 로그인 → 설정에서 다시 켜기 / 최고관리자가 해제 / 서버에서 php artisan user:2fa-reset 이메일
 */
class TwoFactor
{
    public static function enabled($user): bool
    {
        return $user && !empty($user->two_factor_confirmed_at) && !empty($user->two_factor_secret);
    }

    /** 복구 코드 입력 정규화: 소문자 16진수 10자리만 인정(하이픈·공백 무시), 아니면 '' */
    public static function normRecovery(string $s): string
    {
        $s = strtolower(preg_replace('/[\s-]+/', '', $s));
        return preg_match('/^[a-f0-9]{10}$/', $s) ? $s : '';
    }

    public static function hashRecovery(string $norm, string $appKey): string
    {
        return hash_hmac('sha256', $norm, $appKey);
    }

    /** 새 복구 코드 N개: [표시용 평문 'abcde-12345', ...] */
    public static function newRecoveryCodes(int $n = 8): array
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $h = bin2hex(random_bytes(5));
            $out[] = substr($h, 0, 5) . '-' . substr($h, 5);
        }
        return $out;
    }

    /**
     * 순수 판정(DB 없음): 인증 앱 코드가 맞는지 → 아니면 복구 코드가 맞는지.
     * 반환: ['ok'=>bool, 'via'=>'totp'|'recovery'|null, 'step'=>맞은 구간 번호|null, 'recovery'=>남은 복구 코드 해시 목록]
     */
    public static function check(string $secret, int $lastStep, array $recoveryHashes, string $input, string $appKey, ?int $now = null): array
    {
        $step = Totp::verify($secret, $input, $now, 1, $lastStep);
        if ($step !== null) return ['ok' => true, 'via' => 'totp', 'step' => $step, 'recovery' => $recoveryHashes];
        $norm = self::normRecovery($input);
        if ($norm !== '') {
            $h = self::hashRecovery($norm, $appKey);
            foreach ($recoveryHashes as $i => $stored) {
                if (is_string($stored) && hash_equals($stored, $h)) {
                    unset($recoveryHashes[$i]);   // 복구 코드는 한 번만 쓸 수 있다
                    return ['ok' => true, 'via' => 'recovery', 'step' => null, 'recovery' => array_values($recoveryHashes)];
                }
            }
        }
        return ['ok' => false, 'via' => null, 'step' => null, 'recovery' => $recoveryHashes];
    }

    /** 코드가 맞으면 true 이고, 맞은 코드를 "사용됨"으로 기록한다(재사용 방지·복구 코드 소진). 복호화 실패 등은 모두 실패(false). */
    public static function verifyAndConsume($user, string $input): bool
    {
        try {
            $secret = Crypt::decryptString((string) $user->two_factor_secret);
            $hashes = $user->two_factor_recovery_codes ? (json_decode(Crypt::decryptString((string) $user->two_factor_recovery_codes), true) ?: []) : [];
            $r = self::check($secret, (int) ($user->two_factor_last_step ?? -1), is_array($hashes) ? $hashes : [], $input, (string) config('app.key'));
            if (!$r['ok']) return false;
            $upd = [];
            if ($r['via'] === 'totp') $upd['two_factor_last_step'] = $r['step'];
            if ($r['via'] === 'recovery') $upd['two_factor_recovery_codes'] = Crypt::encryptString(json_encode($r['recovery']));
            if ($upd) DB::table('users')->where('id', $user->id)->update($upd);
            return true;
        } catch (\Throwable $e) {
            Log::warning('[2fa] verify failed', ['user' => $user->id ?? null, 'err' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 로그인 단계: 통과면 null, 아니면 ['invalid'=>bool, 'message'=>...] (invalid=true 는 코드가 틀린 경우).
     * 2단계 인증을 켜지 않은 계정은 곧바로 null.
     */
    public static function challenge($user, $input): ?array
    {
        if (!self::enabled($user)) return null;
        $input = trim((string) $input);
        if ($input === '') return ['invalid' => false, 'message' => '인증 앱의 6자리 코드(또는 복구 코드)를 입력해 주세요.'];
        if (self::verifyAndConsume($user, $input)) return null;
        return ['invalid' => true, 'message' => '인증 코드가 맞지 않아요. 인증 앱의 최신 6자리 코드(또는 복구 코드)를 입력해 주세요.'];
    }

    /** 2단계 인증 정보를 모두 지운다(끄기·비상 해제) */
    public static function clear(int $userId): void
    {
        DB::table('users')->where('id', $userId)->update([
            'two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null, 'two_factor_last_step' => null,
        ]);
    }
}
