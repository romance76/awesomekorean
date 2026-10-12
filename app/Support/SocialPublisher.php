<?php

namespace App\Support;

use App\Models\SocialAccount;
use App\Models\SocialPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 대기열 글을 실제로 인스타그램·스레드에 올린다 (Meta 공식 API).
 *  - 스레드: POST /{user}/threads (TEXT 또는 IMAGE) → POST /{user}/threads_publish
 *  - 인스타: POST /{ig}/media (image_url, caption) → 처리 끝날 때까지 확인 → POST /{ig}/media_publish
 * 토큰은 social_accounts(암호화)에서 읽고, 로그·오류 메시지에 토큰을 남기지 않는다.
 */
class SocialPublisher
{
    private const IG = 'https://graph.instagram.com/v23.0';
    private const TH = 'https://graph.threads.net/v1.0';

    public static function account(string $platform): ?SocialAccount
    {
        $a = SocialAccount::where('platform', $platform)->first();
        return ($a && $a->is_active && $a->access_token && $a->account_id) ? $a : null;
    }

    /** 성공하면 true. 실패하면 error 칸에 이유를 적고 false */
    public static function publish(SocialPost $p): bool
    {
        $acc = self::account($p->platform);
        if (!$acc) return self::fail($p, '계정 정보(ID·토큰)가 없거나 꺼져 있어요. 관리자 › 사이트 설정 › SNS 계정을 확인하세요.');
        $p->attempts++;
        $p->save();
        try {
            [$id, $url] = $p->platform === 'instagram' ? self::instagram($p, $acc) : self::threads($p, $acc);
            $p->forceFill(['status' => 'posted', 'posted_at' => now(), 'external_id' => $id, 'external_url' => $url, 'error' => null])->save();
            return true;
        } catch (\Throwable $e) {
            return self::fail($p, $e->getMessage());
        }
    }

    private static function fail(SocialPost $p, string $msg): bool
    {
        $msg = mb_substr(preg_replace('/access_token=[^&\s"]+/', 'access_token=***', $msg), 0, 900);
        // 3번까지는 다음 시간대에 다시 시도, 그 뒤엔 실패로 멈춤
        $p->forceFill(['error' => $msg, 'status' => $p->attempts >= 3 ? 'failed' : $p->status])->save();
        Log::warning('[social] publish failed', ['id' => $p->id, 'platform' => $p->platform, 'error' => $msg]);
        return false;
    }

    private static function check($res, string $step): array
    {
        $j = $res->json() ?: [];
        if (!$res->successful() || isset($j['error'])) {
            $err = $j['error']['error_user_msg'] ?? $j['error']['message'] ?? ('HTTP ' . $res->status());
            throw new \RuntimeException("{$step}: {$err}");
        }
        return $j;
    }

    private static function threads(SocialPost $p, SocialAccount $a): array
    {
        $uid = $a->account_id; $tok = $a->access_token;
        $params = ['text' => $p->caption, 'access_token' => $tok];
        if ($p->image_url) { $params['media_type'] = 'IMAGE'; $params['image_url'] = $p->image_url; }
        else { $params['media_type'] = 'TEXT'; }
        $c = self::check(Http::asForm()->timeout(30)->post(self::TH . "/{$uid}/threads", $params), '스레드 글 만들기');
        if ($p->image_url) sleep(10);   // 사진은 처리 시간이 필요 (Meta 권장 대기)
        $pub = self::check(Http::asForm()->timeout(30)->post(self::TH . "/{$uid}/threads_publish", ['creation_id' => $c['id'], 'access_token' => $tok]), '스레드 게시');
        $link = null;
        try { $link = Http::timeout(15)->get(self::TH . '/' . $pub['id'], ['fields' => 'permalink', 'access_token' => $tok])->json('permalink'); } catch (\Throwable) {}
        return [$pub['id'], $link];
    }

    private static function instagram(SocialPost $p, SocialAccount $a): array
    {
        if (!$p->image_url) throw new \RuntimeException('인스타그램은 사진이 꼭 있어야 해요(카드뉴스 다시 만들기를 눌러 주세요).');
        $ig = $a->account_id; $tok = $a->access_token;
        $c = self::check(Http::asForm()->timeout(40)->post(self::IG . "/{$ig}/media", ['image_url' => $p->image_url, 'caption' => $p->caption, 'access_token' => $tok]), '인스타 사진 올리기');
        // 처리 끝날 때까지 최대 60초 확인
        for ($i = 0; $i < 12; $i++) {
            $st = Http::timeout(15)->get(self::IG . '/' . $c['id'], ['fields' => 'status_code', 'access_token' => $tok])->json('status_code');
            if ($st === 'FINISHED') break;
            if ($st === 'ERROR' || $st === 'EXPIRED') throw new \RuntimeException('인스타가 사진을 처리하지 못했어요(' . $st . '). 사진 주소가 열리는지 확인하세요.');
            sleep(5);
        }
        $pub = self::check(Http::asForm()->timeout(30)->post(self::IG . "/{$ig}/media_publish", ['creation_id' => $c['id'], 'access_token' => $tok]), '인스타 게시');
        $link = null;
        try { $link = Http::timeout(15)->get(self::IG . '/' . $pub['id'], ['fields' => 'permalink', 'access_token' => $tok])->json('permalink'); } catch (\Throwable) {}
        return [$pub['id'], $link];
    }

    /** 토큰 연장 (60일). 만료 20일 이내이거나 만료일 모름 + 마지막 갱신 6일 지난 경우. 결과 문구 반환 */
    public static function refreshTokens(bool $force = false): array
    {
        $out = [];
        foreach (SocialPost::PLATFORMS as $pf) {
            $a = SocialAccount::where('platform', $pf)->first();
            if (!$a || !$a->access_token) { $out[$pf] = '토큰 없음'; continue; }
            $due = $force || !$a->token_expires_at || $a->token_expires_at->lt(now()->addDays(20)) || $a->updated_at->lt(now()->subDays(6));
            if (!$due) { $out[$pf] = '연장 필요 없음'; continue; }
            $res = $pf === 'instagram'
                ? Http::timeout(20)->get('https://graph.instagram.com/refresh_access_token', ['grant_type' => 'ig_refresh_token', 'access_token' => $a->access_token])
                : Http::timeout(20)->get('https://graph.threads.net/refresh_access_token', ['grant_type' => 'th_refresh_token', 'access_token' => $a->access_token]);
            $j = $res->json() ?: [];
            if ($res->successful() && !empty($j['access_token'])) {
                $a->access_token = $j['access_token'];
                $a->token_expires_at = now()->addSeconds((int) ($j['expires_in'] ?? 5184000));
                $a->save();
                $out[$pf] = '연장됨 → ' . $a->token_expires_at->toDateString();
            } else {
                $out[$pf] = '연장 실패: ' . ($j['error']['message'] ?? 'HTTP ' . $res->status());
                Log::warning('[social] token refresh failed', ['platform' => $pf, 'msg' => $out[$pf]]);
            }
        }
        return $out;
    }
}
