<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// 관리자 › 사이트 설정 › SNS 계정 (최고관리자 전용, 저장은 비밀번호 재확인)
// 비밀값은 내려보내지 않고 "저장됨 ••••1234" 만 보여 준다. 빈 칸·가려진 값(•)을 보내면 기존 값 유지.
class AdminSocialAccountController extends Controller
{
    private function present(SocialAccount $a): array
    {
        $tail = fn ($v) => $v ? str_repeat('•', 8) . mb_substr((string) $v, -4) : '';
        return [
            'platform' => $a->platform,
            'handle' => $a->handle, 'profile_url' => $a->profile_url, 'login_email' => $a->login_email,
            'account_id' => $a->account_id, 'page_id' => $a->page_id, 'app_id' => $a->app_id,
            'app_secret_masked' => $tail($a->app_secret),
            'access_token_masked' => $tail($a->access_token),
            'token_expires_at' => optional($a->token_expires_at)->toDateString(),
            'notes' => $a->notes, 'is_active' => $a->is_active, 'autopost_enabled' => $a->autopost_enabled,
            'updated_at' => optional($a->updated_at)->toDateTimeString(),
        ];
    }

    private function rows()
    {
        foreach (SocialAccount::PLATFORMS as $p) SocialAccount::firstOrCreate(['platform' => $p]);
        return SocialAccount::whereIn('platform', SocialAccount::PLATFORMS)->orderByRaw("FIELD(platform,'instagram','threads')")->get();
    }

    public function index()
    {
        return response()->json(['success' => true, 'data' => $this->rows()->map(fn ($a) => $this->present($a))->values()]);
    }

    public function update(Request $request, string $platform)
    {
        if (!in_array($platform, SocialAccount::PLATFORMS, true)) {
            return response()->json(['success' => false, 'message' => '알 수 없는 SNS 입니다.'], 404);
        }
        $plain = function ($attr, $v, $fail) { if (is_string($v) && preg_match('/[<>]/', $v)) $fail('< > 기호는 쓸 수 없어요.'); };
        $idRule = ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]*$/'];
        $data = $request->validate([
            'handle' => ['nullable', 'string', 'max:100', 'regex:/^@?[A-Za-z0-9_.]*$/'],
            'profile_url' => ['nullable', 'string', 'max:300', 'regex:#^(https://[^\s<>"]+)?$#'],
            'login_email' => ['nullable', 'email', 'max:200'],
            'account_id' => $idRule, 'page_id' => $idRule, 'app_id' => $idRule,
            'app_secret' => ['nullable', 'string', 'max:500', 'regex:/^[A-Za-z0-9_\-|•]*$/'],
            'access_token' => ['nullable', 'string', 'max:2000', 'regex:/^[A-Za-z0-9_\-|.•]*$/'],
            'clear_app_secret' => ['sometimes', 'boolean'],
            'clear_access_token' => ['sometimes', 'boolean'],
            'token_expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000', $plain],
            'is_active' => ['sometimes', 'boolean'],
            'autopost_enabled' => ['sometimes', 'boolean'],
        ], [
            'handle.regex' => '아이디는 영문·숫자·밑줄·점만 쓸 수 있어요 (예: @awesomekorean).',
            'profile_url.regex' => '프로필 주소는 https:// 로 시작해야 해요.',
            '*.regex' => '영문·숫자 등 허용된 글자만 넣어 주세요(공백 없이).',
        ]);

        $acc = SocialAccount::firstOrCreate(['platform' => $platform]);
        $changed = [];
        foreach (['handle', 'profile_url', 'login_email', 'account_id', 'page_id', 'app_id', 'token_expires_at', 'notes', 'is_active', 'autopost_enabled'] as $k) {
            if (!$request->has($k)) continue;
            $v = $data[$k] ?? null;
            if ($k === 'handle' && $v) $v = '@' . ltrim($v, '@');
            if ((string) $acc->getAttribute($k) !== (string) $v) $changed[] = $k;
            $acc->setAttribute($k, $v === '' ? null : $v);
        }
        // 비밀값: 새 값을 넣었을 때만 바꿈(빈 칸·가려진 값은 유지), "지우기" 체크 시에만 삭제
        foreach (['app_secret', 'access_token'] as $k) {
            if ($request->boolean('clear_' . $k)) { $acc->setAttribute($k, null); $changed[] = $k . '(지움)'; continue; }
            $v = $data[$k] ?? '';
            if (is_string($v) && $v !== '' && !str_contains($v, '•')) { $acc->setAttribute($k, $v); $changed[] = $k; }
        }
        $acc->updated_by = $request->user()->id;
        $acc->save();

        // 인스타 프로필 주소는 사이트 아래쪽(푸터) SNS 링크에도 같이 쓴다
        if ($platform === 'instagram' && in_array('profile_url', $changed, true) && $acc->profile_url) {
            SiteSetting::updateOrCreate(['key' => 'sns_instagram'], ['value' => $acc->profile_url]);
            Cache::forget(AdminSettingsController::SETTINGS_PUBLIC_CACHE_KEY);
        }

        // 감사 로그: 바뀐 칸 이름만 기록(값은 기록하지 않음)
        try {
            DB::table('admin_audit_log')->insert(['admin_id' => $request->user()->id, 'action' => 'UPDATE social-account', 'target_type' => 'social_accounts', 'target_id' => $acc->id,
                'after_value' => json_encode(['platform' => $platform, 'fields' => $changed], JSON_UNESCAPED_UNICODE), 'note' => $request->user()->role, 'ip' => $request->ip(), 'created_at' => now()]);
        } catch (\Throwable $e) { report($e); }

        return response()->json(['success' => true, 'message' => ($platform === 'instagram' ? '인스타그램' : '스레드') . ' 계정 정보가 저장되었습니다', 'data' => $this->present($acc->fresh())]);
    }
}
