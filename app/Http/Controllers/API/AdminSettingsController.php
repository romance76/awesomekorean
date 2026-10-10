<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminSettingsController extends Controller
{
    // /api/settings/public 캐시 키 — 도메인에 의존하지 않는 고정 키.
    // 예전엔 캐시 미들웨어(cache.api)가 요청 전체 URL(md5)로 키를 만들고,
    // 저장할 때는 하드코딩된 도메인 문자열 몇 개를 추측해 그 키를 지우려
    // 했는데, 실제 접속 도메인이 그 목록과 하나라도 다르면(www. 유무 등)
    // 캐시가 전혀 지워지지 않아 메뉴 순서/회사정보/약관 등을 저장해도
    // 최대 30분간 예전 값이 계속 보이던 버그가 있었음. 고정 키로 직접
    // 캐시하고 저장 시 그 키 하나만 지우는 방식으로 교체.
    const SETTINGS_PUBLIC_CACHE_KEY = 'site_settings_public';

    // 전체 설정 로드 (이전 버전 호환)
    // 화면에 내려보낼 때 비밀값은 끝 4자리만 남기고 가린다 (원본은 서버에만 있고, "키 보기"는 재확인을 거친 별도 경로로만 가능)
    private function maskSecretValue($v): string {
        $v = (string) $v;
        if ($v === '') return '';
        return str_repeat('•', 8) . mb_substr($v, -4);
    }

    private function isSecretKey(string $key): bool {
        return (bool) preg_match('/(secret|private|token|password|webhook|credential)/i', $key) || in_array($key, ['stripe_secret_key'], true);
    }

    // 값 안의 JSON(결제 설정 등)까지 포함해 비밀 칸을 가린다
    private function maskSettingsArray(array $settings): array {
        foreach ($settings as $key => $val) {
            if (!is_string($key)) continue;
            if ($key === 'api_keys') { unset($settings[$key]); continue; }   // 예전에 평문으로 복사해 둔 키 목록 — 내려보내지 않는다
            if ($this->isSecretKey($key) && !is_array($val)) { $settings[$key] = $this->maskSecretValue($val); continue; }
            if (is_array($val)) {
                foreach ($val as $k2 => $v2) {
                    if (is_string($k2) && is_scalar($v2) && $this->isSecretKey($k2)) { $val[$k2] = $this->maskSecretValue($v2); }
                }
                $settings[$key] = $val;
            } elseif (is_string($val) && strlen($val) > 1 && $val[0] === '{') {
                $d = json_decode($val, true);
                if (is_array($d)) {
                    foreach ($d as $k2 => $v2) { if (is_string($k2) && is_scalar($v2) && $this->isSecretKey($k2)) $d[$k2] = $this->maskSecretValue($v2); }
                    $settings[$key] = json_encode($d);
                }
            }
        }
        return $settings;
    }

    public function index() {
        $settings = $this->maskSettingsArray(SiteSetting::all()->pluck('value', 'key')->toArray());
        return response()->json(['success'=>true,'data'=>$settings]);
    }

    // 전체 설정 한번에 로드 (이전 SiteSettings.vue 호환)
    public function getAll() {
        $settings = SiteSetting::all()->pluck('value', 'key')->toArray();
        // JSON 값들 디코딩
        foreach ($settings as $key => $val) {
            $decoded = json_decode($val, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $settings[$key] = $decoded;
            }
        }

        // API 키 목록은 이 엔드포인트로 중복 노출할 필요가 없음 — getApiKeys()
        // 전용 엔드포인트(마스킹 처리됨)로만 노출. (Stripe 키 등 나머지 값은 설정
        // 편집 화면이 현재값을 불러와 그대로 재저장하는 구조라 여기서 마스킹하면
        // 저장 시 마스킹된 값으로 덮어써지므로 건드리지 않음 — 접근 자체를
        // super_admin으로 제한하는 것으로 대응)
        unset($settings['api_keys']);
        $settings = $this->maskSettingsArray($settings);   // 비밀값(Stripe 비밀키·웹훅 비밀 등)은 끝 4자리만 보이게

        // SiteSettings.vue(관리자 설정 화면)는 회사정보/사이트설정/푸터편집/
        // 약관관리/알림설정 탭을 각각 data.company / data.site / data.footer /
        // data.terms / data.notifications 처럼 탭별로 묶인 객체로 기대하는데,
        // 실제 저장은 평평한 site_settings 키-값(회사/사이트)이거나 단일 JSON
        // blob 키(footer_config/notification_config)라서 모양이 한 번도 서로
        // 안 맞았음 — 그래서 각 탭을 열 때마다 이미 저장돼 있는 값이 전혀
        // 안 불러와지고 항상 빈 채로 보였음(약관 관리가 내용이 저장돼 있는데도
        // 빈 에디터로 보인 것도 이 버그). 프론트가 기대하는 모양대로 묶어서
        // 같이 내려줌.
        $companyKeys = ['site_name','site_subtitle','company_name','ceo_name','business_number','address','phone','email','founded_date','logo_url','logo_dark_url','app_icon_url','favicon_url','meta_description','meta_keywords'];
        $siteKeys = ['allow_signup','require_email_verify','auto_approve','allow_withdrawal','min_password_length','max_upload_mb','allowed_file_types','maintenance_mode','maintenance_reason','maintenance_until'];
        $settings['company'] = array_intersect_key($settings, array_flip($companyKeys));
        $settings['site'] = array_intersect_key($settings, array_flip($siteKeys));
        $settings['footer'] = $settings['footer_config'] ?? null;
        $settings['notifications'] = $settings['notification_config'] ?? null;
        $settings['terms'] = [
            'terms' => ['content' => $settings['terms_page'] ?? ''],
            'privacy' => ['content' => $settings['privacy_page'] ?? ''],
        ];

        return response()->json(['success'=>true,'data'=>$settings]);
    }

    public function getPublic() {
        $settings = Cache::remember(self::SETTINGS_PUBLIC_CACHE_KEY, 1800, function () {
            $keys = ['easy_view_enabled','site_name','site_subtitle','logo_url','logo_dark_url','primary_color','footer_text','about_page','terms_page','privacy_page','meta_description','meta_keywords','company_name','contact_email','contact_phone','company_address','sns_facebook','sns_instagram','sns_twitter','sns_youtube','sns_kakao','menu_config','footer_config'];
            return SiteSetting::whereIn('key', $keys)->pluck('value','key');
        });
        return response()->json(['success'=>true,'data'=>$settings]);
    }

    // 이 경로로 바꿀 수 없는 칸: 비밀번호·키·결제·푸시 비밀값 (전용 저장 경로만 허용) / 키 이름은 영문·숫자·_ . - 만
    private function safeSettingKey($key): bool {
        if (!is_string($key) || !preg_match('/^[A-Za-z0-9_.\-]{1,100}$/', $key)) return false;
        return !preg_match('/(stripe|secret|payment_config|vapid_private|firebase|api[_-]?key|password|token|private|credential)/i', $key);
    }

    // 일괄 저장용으로 걸러낸 값 (안전하지 않은 칸은 건너뛰고 기록)
    private function filteredSettings(Request $request): array {
        $out = [];
        foreach ($request->all() as $key => $value) {
            if (!$this->safeSettingKey($key)) { \Log::warning('[설정저장] 허용되지 않는 칸을 건너뜀: ' . (is_string($key) ? mb_substr($key, 0, 60) : '(비문자)') . ' by ' . (auth()->id() ?? '?')); continue; }
            if (is_array($value)) { $value = json_encode($value); }
            if (is_string($value) && strlen($value) > 30000) { continue; }
            $out[$key] = $value;
        }
        return $out;
    }

    // 본문 크기 제한 (JSON 설정 한 덩어리)
    private function payloadTooBig(Request $request, int $max = 60000): bool {
        return strlen(json_encode($request->all())) > $max;
    }

    // 일괄 업데이트
    public function update(Request $request) {
        foreach ($this->filteredSettings($request) as $key => $storeValue) {
            SiteSetting::updateOrCreate(['key'=>$key], ['value'=>$storeValue]);
        }
        Cache::forget(self::SETTINGS_PUBLIC_CACHE_KEY);
        return response()->json(['success'=>true,'message'=>'설정이 저장되었습니다']);
    }

    // 회사 정보 저장
    public function saveCompany(Request $request) {
        foreach ($this->filteredSettings($request) as $key => $value) {
            SiteSetting::updateOrCreate(['key'=>$key], ['value'=>$value]);
        }
        Cache::forget(self::SETTINGS_PUBLIC_CACHE_KEY);
        return response()->json(['success'=>true,'message'=>'회사 정보가 저장되었습니다']);
    }

    // 사이트 설정 저장
    public function saveSite(Request $request) {
        foreach ($request->all() as $key => $value) {
            if (!$this->safeSettingKey($key)) { \Log::warning('[설정저장] 허용되지 않는 칸을 건너뜀: ' . (is_string($key) ? mb_substr($key, 0, 60) : '(비문자)') . ' by ' . (auth()->id() ?? '?')); continue; }
            $storeValue = is_bool($value) ? ($value ? '1' : '0') : (is_array($value) ? json_encode($value) : $value);
            if (is_string($storeValue) && strlen($storeValue) > 30000) continue;
            SiteSetting::updateOrCreate(['key'=>$key], ['value'=>$storeValue]);
        }
        Cache::forget(self::SETTINGS_PUBLIC_CACHE_KEY);   // 저장했는데 회원 화면에 안 바뀌던 문제(최대 30분) 방지
        return response()->json(['success'=>true,'message'=>'사이트 설정이 저장되었습니다']);
    }

    // 푸터 저장
    public function saveFooter(Request $request) {
        if ($this->payloadTooBig($request)) return response()->json(['success'=>false,'message'=>'내용이 너무 커요'], 422);
        SiteSetting::updateOrCreate(['key'=>'footer_config'], ['value'=>json_encode($request->all())]);
        Cache::forget(self::SETTINGS_PUBLIC_CACHE_KEY);   // 공개 설정 캐시를 비워야 실제 사이트 푸터에 바로 반영됨
        return response()->json(['success'=>true,'message'=>'푸터가 저장되었습니다']);
    }

    // 약관 저장
    public function saveTerms(Request $request, $type) {
        $key = $type === 'privacy' ? 'privacy_page' : 'terms_page';
        SiteSetting::updateOrCreate(['key'=>$key], ['value'=>$request->content]);
        Cache::forget(self::SETTINGS_PUBLIC_CACHE_KEY);
        return response()->json(['success'=>true,'message'=>'약관이 저장되었습니다']);
    }

    // 알림 설정 저장
    public function saveNotifications(Request $request) {
        if ($this->payloadTooBig($request, 20000)) return response()->json(['success'=>false,'message'=>'내용이 너무 커요'], 422);
        SiteSetting::updateOrCreate(['key'=>'notification_config'], ['value'=>json_encode($request->all())]);
        Cache::forget(self::SETTINGS_PUBLIC_CACHE_KEY);
        return response()->json(['success'=>true,'message'=>'알림 설정이 저장되었습니다']);
    }

    // Stripe 키 저장
    public function saveStripe(Request $request) {
        $request->validate([
            'stripe_publishable_key' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_\-•]*$/'],
            'stripe_secret_key' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_\-•]*$/'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_\-•]*$/'],
        ]);
        foreach (['stripe_publishable_key','stripe_secret_key','stripe_webhook_secret','stripe_test_mode'] as $k) {
            if ($request->has($k)) {
                if (is_string($request->$k) && str_contains($request->$k, '•')) continue;   // 화면에서 가려 보여준 값을 그대로 보낸 것 — 바꾸지 않음
                SiteSetting::updateOrCreate(['key'=>$k], ['value'=>$request->$k]);
            }
        }
        // .env 파일에도 반영 (가려진 값·빈 값은 건드리지 않음)
        foreach ([['STRIPE_KEY', $request->stripe_publishable_key], ['STRIPE_SECRET', $request->stripe_secret_key]] as [$ek, $ev]) {
            if (is_string($ev) && $ev !== '' && !str_contains($ev, '•')) $this->updateEnv($ek, $ev);
        }
        return response()->json(['success'=>true,'message'=>'Stripe 키가 저장되었습니다']);
    }

    // 결제 게이트웨이 설정
    public function savePaymentGateway(Request $request) {
        if ($this->payloadTooBig($request, 20000)) return response()->json(['success'=>false,'message'=>'내용이 너무 커요'], 422);
        $incoming = [];
        foreach ($request->all() as $k => $v) {
            if (!is_string($k) || !preg_match('/^[A-Za-z0-9_.\-]{1,60}$/', $k)) continue;   // 이상한 칸 이름은 버린다
            $incoming[$k] = $v;
        }
        $cur = SiteSetting::where('key', 'payment_config')->value('value');
        $merged = array_merge(is_string($cur) ? (json_decode($cur, true) ?: []) : [], $incoming);   // 보내지 않은 기존 칸은 그대로 둔다
        SiteSetting::updateOrCreate(['key'=>'payment_config'], ['value'=>json_encode($merged)]);
        Cache::forget(self::SETTINGS_PUBLIC_CACHE_KEY);
        return response()->json(['success'=>true,'message'=>'결제 설정이 저장되었습니다']);
    }

    // SEO 설정 저장
    public function saveSeo(Request $request) {
        foreach ($request->all() as $key => $value) {
            if (!is_string($key) || !preg_match('/^[A-Za-z0-9_]{1,60}$/', $key) || is_array($value)) continue;
            if (is_string($value) && strlen($value) > 5000) continue;
            SiteSetting::updateOrCreate(['key'=>'seo_'.$key], ['value'=>$value]);
        }
        Cache::forget(self::SETTINGS_PUBLIC_CACHE_KEY);
        return response()->json(['success'=>true,'message'=>'SEO 설정이 저장되었습니다']);
    }

    // VAPID 키 생성
    public function generateVapid() {
        // 이미 푸시 키가 있으면 새로 만들지 않는다 — 덮어쓰면 기존 구독자 전원의 푸시가 끊기고, 이 함수는 진짜 VAPID 키도 아니다(임시)
        if (SiteSetting::where('key', 'vapid_public')->whereNotNull('value')->where('value', '!=', '')->exists()) {
            return response()->json(['success' => false, 'message' => '푸시 키가 이미 있어요. 덮어쓰면 기존 푸시 구독이 모두 끊겨서 막아 두었어요.'], 422);
        }
        // 간단한 더미 키 생성 (실제로는 web-push 라이브러리 사용)
        $public = base64_encode(random_bytes(65));
        $private = base64_encode(random_bytes(32));
        SiteSetting::updateOrCreate(['key'=>'vapid_public'], ['value'=>$public]);
        SiteSetting::updateOrCreate(['key'=>'vapid_private'], ['value'=>$private]);
        return response()->json(['success'=>true,'data'=>['public'=>$public,'private'=>$private]]);
    }

    // 메뉴 목록
    public function getMenus() {
        $setting = SiteSetting::where('key', 'menu_config')->first();
        $menus = $setting ? json_decode($setting->value, true) : $this->defaultMenus();
        return response()->json(['success'=>true,'data'=>$menus]);
    }

    // 메뉴 일괄 저장
    public function saveMenus(Request $request) {
        // 메뉴 항목 검증: 키·이름 필수, 링크는 안전한 주소만 (javascript: 같은 경로가 메뉴로 저장되지 않게)
        $menusIn = $request->menus ?? [];
        if (!is_array($menusIn) || count($menusIn) > 100) return response()->json(['success' => false, 'message' => '메뉴 목록 형식이 올바르지 않아요'], 422);
        foreach ($menusIn as $i => $m) {
            if (!is_array($m) || !isset($m['key']) || !is_string($m['key']) || !preg_match('/^[A-Za-z0-9_\-]{1,40}$/', $m['key'])) return response()->json(['success' => false, 'message' => '메뉴 ' . ($i + 1) . '번: 키가 올바르지 않아요'], 422);
            if (!isset($m['label']) || !is_string($m['label']) || trim($m['label']) === '' || mb_strlen($m['label']) > 30 || preg_match('/[<>]/', $m['label'])) return response()->json(['success' => false, 'message' => '메뉴 ' . ($i + 1) . '번: 이름은 1~30자, < > 없이 입력해 주세요'], 422);
            if (isset($m['path']) && !\App\Support\SafeUrl::ok($m['path'])) return response()->json(['success' => false, 'message' => '메뉴 ' . ($i + 1) . '번: 링크가 올바르지 않아요'], 422);
        }
        // 기존 DB 에만 있는 key 는 보존 (프론트 allMenuDefs 에 없는 항목 실수 삭제 방지)
        $incoming = collect($request->menus ?? []);
        $incomingKeys = $incoming->pluck('key')->filter()->all();
        $existing = SiteSetting::where('key','menu_config')->first();
        $existingArr = $existing ? (json_decode($existing->value, true) ?: []) : [];
        $preserved = collect($existingArr)
            ->filter(fn($m) => isset($m['key']) && !in_array($m['key'], $incomingKeys))
            ->values()
            ->all();
        $merged = array_merge($incoming->all(), $preserved);
        SiteSetting::updateOrCreate(['key'=>'menu_config'], ['value'=>json_encode($merged)]);
        Cache::forget(self::SETTINGS_PUBLIC_CACHE_KEY);
        return response()->json(['success'=>true,'message'=>'메뉴 설정이 저장되었습니다']);
    }

    // API 키 관리
    //
    // 예전엔 이 네 메서드가 site_settings 테이블의 'api_keys' JSON 블롭에 썼는데,
    // market:scrape/places:import 등 실제 소비자들(resolveCredential())은 전부
    // 진짜 api_keys 테이블(ApiKey 모델)을 조회하고 있어서 — 관리자 페이지에서
    // 키를 등록해도 어떤 수집기도 그 값을 영영 찾지 못하던 버그. ApiKey 모델로 통일.
    public function getApiKeys() {
        $keys = ApiKey::orderByDesc('id')->get()->map(function ($k) {
            return [
                'id' => $k->id,
                'name' => $k->name,
                'service' => $k->service,
                'description' => $k->description,
                'is_active' => $k->is_active,
                'created_at' => $k->created_at,
                // 목록 응답에는 마스킹된 값만 내려줌 — 원본 api_key는 여기 포함하면 안 됨
                // (reveal() 전용 엔드포인트에서만, super_admin 한정으로 노출)
                'masked_key' => substr($k->api_key ?? '', 0, 8) . '••••••••',
                'showFull' => false,
            ];
        });
        return response()->json(['success'=>true,'data'=>$keys]);
    }

    // 임시 진단용 — Resend 키를 등록해도 메일이 전혀 안 나가는 문제 원인 파악.
    // 관리자 본인 메일로 실제 테스트 메일을 보내고, 실패하면 Resend 가 던진 예외 메시지를 그대로 돌려준다.
    public function mailTestSend(Request $request) {
        $to = $request->user()->email;
        try {
            \Illuminate\Support\Facades\Mail::raw(
                '어썸코리안 메일 발송 테스트입니다. 이 메일이 보이면 정상 작동 중입니다.',
                function ($msg) use ($to) {
                    $msg->to($to)->subject('[AwesomeKorean] 메일 발송 테스트');
                }
            );
            return response()->json(['success' => true, 'message' => "{$to} 로 테스트 메일 발송 성공 (예외 없음)"]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => get_class($e) . ': ' . $e->getMessage()], 500);
        }
    }

    public function storeApiKey(Request $request) {
        $request->validate(['name'=>'required|string|max:100','service'=>['required','string','regex:/^[A-Za-z0-9_]{1,40}$/'],'api_key'=>'required|string|max:10000']);
        if ($request->service === \App\Support\Analytics::SERVICE && !\App\Support\Analytics::isValid($request->api_key)) {
            return response()->json(['success'=>false,'message'=>'구글 Analytics 측정 ID 형식이 아닙니다 (예: G-ABC123DEF4)'], 422);
        }
        // 구글 Analytics 는 사이트에 하나만 쓰므로 이미 있으면 새 행을 만들지 않고 그 값을 교체
        $existing = $request->service === \App\Support\Analytics::SERVICE ? ApiKey::where('service', $request->service)->first() : null;
        if ($existing) {
            $existing->update(['name' => $request->name, 'api_key' => $request->api_key, 'description' => $request->description ?? '', 'is_active' => true]);
            \App\Support\Analytics::forget();
            \App\Support\KeyReport::send("{$existing->name} ({$existing->service}) 수정", auth()->user()->email ?? '관리자');
            return response()->json(['success'=>true,'data'=>$existing,'message'=>'구글 Analytics 측정 ID가 변경되었습니다']);
        }
        $newKey = ApiKey::create([
            'name' => $request->name,
            'service' => $request->service,
            'api_key' => $request->api_key,
            'description' => $request->description ?? '',
            'is_active' => true,
        ]);
        // .env에도 반영 (서비스별)
        $envKey = strtoupper($request->service) . '_API_KEY';
        $this->updateEnv($envKey, $request->api_key);
        \App\Support\Analytics::forget();
        \App\Support\KeyReport::send("{$newKey->name} ({$newKey->service}) 등록", auth()->user()->email ?? '관리자');
        return response()->json(['success'=>true,'data'=>$newKey,'message'=>'API 키가 등록되었습니다']);
    }

    public function deleteApiKey($id) {
        $gone = ApiKey::find($id);
        ApiKey::where('id', $id)->delete();
        \App\Support\Analytics::forget();
        if ($gone) \App\Support\KeyReport::send("{$gone->name} ({$gone->service}) 삭제", auth()->user()->email ?? '관리자');
        return response()->json(['success'=>true,'message'=>'삭제되었습니다']);
    }

    // is_active 토글뿐 아니라 이름/서비스 코드/키 값/설명도 수정 가능하도록 확장
    // (예전엔 보기·삭제·활성화 토글만 있고 값 자체를 고칠 방법이 없었음)
    public function updateApiKey(Request $request, $id) {
        $key = ApiKey::find($id);
        if (!$key) return response()->json(['success'=>false,'message'=>'키를 찾을 수 없습니다'],404);

        $request->validate([
            'name' => 'sometimes|required|string',
            'service' => 'sometimes|required|string',
            'api_key' => 'sometimes|required|string',
            'description' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $newService = $request->input('service', $key->service);
        if ($newService === \App\Support\Analytics::SERVICE && $request->filled('api_key') && !\App\Support\Analytics::isValid($request->api_key)) {
            return response()->json(['success'=>false,'message'=>'구글 Analytics 측정 ID 형식이 아닙니다 (예: G-ABC123DEF4)'], 422);
        }

        $key->update($request->only(['name', 'service', 'api_key', 'description', 'is_active']));
        \App\Support\Analytics::forget();

        if ($request->has('api_key')) {
            $envKey = strtoupper($key->service) . '_API_KEY';
            $this->updateEnv($envKey, $request->api_key);
        }

        \App\Support\KeyReport::send("{$key->name} ({$key->service}) 수정", auth()->user()->email ?? '관리자');

        return response()->json(['success'=>true, 'message'=>'수정되었습니다']);
    }

    public function revealApiKey($id) {
        $key = ApiKey::find($id);
        if (!$key) return response()->json(['success'=>false,'message'=>'키를 찾을 수 없습니다'],404);
        // 누가 언제 열어 봤는지 기록 (값은 기록하지 않음)
        try {
            \DB::table('admin_audit_log')->insert(['admin_id' => auth()->id(), 'action' => 'REVEAL api-key', 'target_type' => 'api_keys', 'target_id' => (int) $id,
                'after_value' => json_encode(['service' => $key->service]), 'note' => auth()->user()->role, 'ip' => request()->ip(), 'created_at' => now()]);
        } catch (\Throwable $e) { report($e); }
        return response()->json(['success'=>true,'data'=>['key'=>$key->api_key]]);
    }

    // public/images/logo.png는 git에 커밋돼있어서 배포 때마다 git reset으로
    // 파일이 통째로 다시 체크아웃되고, 그러면 소유권도 배포 사용자(www-data가
    // 아님) 걸로 리셋됨 — deploy.sh에서 매번 chown을 해줘도 배포 사용자가 다른
    // 계정으로 chown할 권한이 없으면(sudo 없이 배포하는 서버 흔함) 조용히
    // 실패해서(`|| true`) "Can't write image...not writable" 에러가 반복됨.
    // 이 앱의 다른 업로드 기능들(채팅 파일, 아바타 등)이 전부 쓰고 있는
    // Storage::disk('public')(= storage/app/public, git에 안 잡히고 한 번
    // chown되면 배포가 반복돼도 안 바뀜) 패턴으로 통일해서 근본적으로 해결.
    public function uploadLogo(Request $request) {
        $request->validate(['logo'=>'required|image|mimes:jpg,jpeg,png,webp|max:4096']);

        try {
            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            $image = $manager->read($request->file('logo')->getRealPath());
            \Illuminate\Support\Facades\Storage::disk('public')->put('branding/logo.png', (string) $image->toPng());
        } catch (\Throwable $e) {
            \Log::error("로고 업로드 실패: " . $e->getMessage());
            return response()->json(['success'=>false,'message'=>'로고 저장 실패: '.$e->getMessage()], 500);
        }

        // 브라우저/이메일 클라이언트가 같은 파일명(logo.png)을 캐시하고 있어서
        // 업로드 직후에도 옛날 로고가 계속 보이는 문제 방지용 캐시 버스터
        $version = now()->timestamp;
        SiteSetting::updateOrCreate(['key'=>'logo_url'], ['value'=>"/storage/branding/logo.png?v={$version}"]);

        return response()->json(['success'=>true,'data'=>['url'=>"/storage/branding/logo.png?v={$version}"]]);
    }

    // 다크 배경(푸터 등 bg-slate-800 같은 어두운 섹션)에서 쓰는 흰색/밝은 톤
    // 로고 — 기본 로고(밝은 배경용)와 별도로 관리. uploadLogo()와 동일한
    // Storage::disk('public') 패턴.
    public function uploadLogoDark(Request $request) {
        $request->validate(['logo'=>'required|image|mimes:jpg,jpeg,png,webp|max:4096']);

        try {
            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            $image = $manager->read($request->file('logo')->getRealPath());
            \Illuminate\Support\Facades\Storage::disk('public')->put('branding/logo-dark.png', (string) $image->toPng());
        } catch (\Throwable $e) {
            \Log::error("다크 배경용 로고 업로드 실패: " . $e->getMessage());
            return response()->json(['success'=>false,'message'=>'로고 저장 실패: '.$e->getMessage()], 500);
        }

        $version = now()->timestamp;
        SiteSetting::updateOrCreate(['key'=>'logo_dark_url'], ['value'=>"/storage/branding/logo-dark.png?v={$version}"]);

        return response()->json(['success'=>true,'data'=>['url'=>"/storage/branding/logo-dark.png?v={$version}"]]);
    }

    // 핸드폰 홈 화면에 "바로가기(PWA)"로 추가할 때 쓰이는 정사각형 앱 아이콘 —
    // 로고(가로형 워드마크)와는 별도로 관리. manifest.json이 요구하는 전 사이즈
    // (72/96/128/192/512) + iOS용 apple-touch-icon(180x180)까지 한 번에 생성.
    // uploadLogo()와 동일한 이유로 Storage::disk('public')에 저장(public/icons는
    // git에 커밋돼있어 배포마다 소유권이 리셋되는 동일 문제가 있었음).
    public function uploadAppIcon(Request $request) {
        $request->validate(['icon'=>'required|image|mimes:jpg,jpeg,png,webp|max:4096']);

        $sizes = [72, 96, 128, 192, 512];
        try {
            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            $source = $request->file('icon')->getRealPath();
            $disk = \Illuminate\Support\Facades\Storage::disk('public');

            foreach ($sizes as $size) {
                $png = (string) $manager->read($source)->cover($size, $size)->toPng();
                $disk->put("branding/icon-{$size}x{$size}.png", $png);
            }
            $touchIcon = (string) $manager->read($source)->cover(180, 180)->toPng();
            $disk->put('branding/apple-touch-icon.png', $touchIcon);
        } catch (\Throwable $e) {
            \Log::error("앱 아이콘 업로드 실패: " . $e->getMessage());
            return response()->json(['success'=>false,'message'=>'아이콘 저장 실패: '.$e->getMessage()], 500);
        }

        $version = now()->timestamp;
        SiteSetting::updateOrCreate(['key'=>'app_icon_url'], ['value'=>"/storage/branding/icon-512x512.png?v={$version}"]);

        return response()->json(['success'=>true,'data'=>['url'=>"/storage/branding/icon-512x512.png?v={$version}"]]);
    }

    // 브라우저 탭에 표시되는 파비콘. Intervention Image엔 .ico 인코더가 없어서
    // PNG로 저장하고 welcome.blade.php에서 <link rel="icon" type="image/png">로
    // 명시 — 구형 /favicon.ico 암묵 탐색에 의존하지 않음(최신 브라우저는 전부
    // PNG favicon 지원). uploadLogo()와 동일한 이유로 Storage::disk('public') 사용.
    public function uploadFavicon(Request $request) {
        $request->validate(['favicon'=>'required|image|mimes:jpg,jpeg,png,webp|max:2048']);

        try {
            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            $source = $request->file('favicon')->getRealPath();
            $disk = \Illuminate\Support\Facades\Storage::disk('public');

            foreach ([16, 32, 48] as $size) {
                $png = (string) $manager->read($source)->cover($size, $size)->toPng();
                $disk->put("branding/favicon-{$size}x{$size}.png", $png);
            }
        } catch (\Throwable $e) {
            \Log::error("파비콘 업로드 실패: " . $e->getMessage());
            return response()->json(['success'=>false,'message'=>'파비콘 저장 실패: '.$e->getMessage()], 500);
        }

        $version = now()->timestamp;
        SiteSetting::updateOrCreate(['key'=>'favicon_url'], ['value'=>"/storage/branding/favicon-32x32.png?v={$version}"]);

        return response()->json(['success'=>true,'data'=>['url'=>"/storage/branding/favicon-32x32.png?v={$version}"]]);
    }

    // .env 파일 업데이트 헬퍼
    // .env 는 사이트 전체 설정 파일이라 한 줄짜리 안전한 값만 쓴다 (줄바꿈이 들어가면 다른 설정이 만들어질 수 있고,
    // $1 같은 문자는 치환 때 깨진다). 원본 값은 DB 에 그대로 있으므로 .env 에 못 쓰는 값은 건너뛴다.
    private function updateEnv($key, $value) {
        if (!is_string($key) || !preg_match('/^[A-Z][A-Z0-9_]{0,60}$/', $key)) return;
        $value = (string) $value;
        if ($value === '' || strlen($value) > 2000 || preg_match('/[\r\n\0]/', $value)) return;
        $envPath = base_path('.env');
        if (!file_exists($envPath)) return;
        if (preg_match('/[\s#"\'\\\\$]/', $value)) $value = '"' . addcslashes($value, '"\\$') . '"';
        $content = file_get_contents($envPath);
        $line = $key . '=' . $value;
        if (preg_match('/^' . preg_quote($key, '/') . '=/m', $content)) {
            $content = preg_replace_callback('/^' . preg_quote($key, '/') . '=.*/m', fn () => $line, $content);
        } else {
            $content .= "\n" . $line;
        }
        file_put_contents($envPath, $content);
    }

    private function defaultMenus() {
        return [
            ['key'=>'home','label'=>'홈','icon'=>'🏠','path'=>'/','enabled'=>true,'login_required'=>false,'admin_only'=>false],
            ['key'=>'community','label'=>'커뮤니티','icon'=>'💬','path'=>'/community','enabled'=>true,'login_required'=>false,'admin_only'=>false],
            ['key'=>'qa','label'=>'Q&A','icon'=>'❓','path'=>'/qa','enabled'=>true,'login_required'=>false,'admin_only'=>false],
            ['key'=>'jobs','label'=>'구인구직','icon'=>'💼','path'=>'/jobs','enabled'=>true,'login_required'=>false,'admin_only'=>false],
            ['key'=>'market','label'=>'중고장터','icon'=>'🛒','path'=>'/market','enabled'=>true,'login_required'=>false,'admin_only'=>false],
            ['key'=>'directory','label'=>'업소록','icon'=>'🏪','path'=>'/directory','enabled'=>true,'login_required'=>false,'admin_only'=>false],
            ['key'=>'realestate','label'=>'부동산','icon'=>'🏠','path'=>'/realestate','enabled'=>true,'login_required'=>false,'admin_only'=>false],
            ['key'=>'events','label'=>'이벤트','icon'=>'🎉','path'=>'/events','enabled'=>true,'login_required'=>false,'admin_only'=>false],
            ['key'=>'flyers','label'=>'NEW','icon'=>'🆕','path'=>'/new','enabled'=>true,'login_required'=>false,'admin_only'=>false],
            ['key'=>'news','label'=>'뉴스','icon'=>'📰','path'=>'/news','enabled'=>true,'login_required'=>false,'admin_only'=>false],
            ['key'=>'info','label'=>'정보','icon'=>'📘','path'=>'/info','enabled'=>true,'login_required'=>false,'admin_only'=>false],
            ['key'=>'recipes','label'=>'레시피','icon'=>'🍳','path'=>'/recipes','enabled'=>true,'login_required'=>false,'admin_only'=>false],
            ['key'=>'clubs','label'=>'동호회','icon'=>'👥','path'=>'/clubs','enabled'=>true,'login_required'=>false,'admin_only'=>false],
            ['key'=>'games','label'=>'게임','icon'=>'🎮','path'=>'/games','enabled'=>true,'login_required'=>false,'admin_only'=>false],
            ['key'=>'shorts','label'=>'숏츠','icon'=>'📱','path'=>'/shorts','enabled'=>true,'login_required'=>false,'admin_only'=>false],
            ['key'=>'music','label'=>'음악','icon'=>'🎵','path'=>'/music','enabled'=>true,'login_required'=>false,'admin_only'=>false],
            ['key'=>'chat','label'=>'채팅','icon'=>'💭','path'=>'/chat','enabled'=>true,'login_required'=>true,'admin_only'=>false],
        ];
    }

    // ─── Firebase 설정 ───────────────────────────────────────────────────────────

    public function getFirebase()
    {
        $s = fn($k) => SiteSetting::where('key', $k)->value('value');
        $credPath = config('services.firebase.credentials');

        return response()->json([
            'apiKey'            => $s('firebase_api_key') ?: '',
            'authDomain'        => $s('firebase_auth_domain') ?: '',
            'projectId'         => $s('firebase_project_id') ?: '',
            'storageBucket'     => $s('firebase_storage_bucket') ?: '',
            'messagingSenderId' => $s('firebase_sender_id') ?: '',
            'appId'             => $s('firebase_app_id') ?: '',
            'vapidKey'          => $s('firebase_vapid_key') ?: '',
            'credentialsPath'   => $credPath,
            'credentialsExists' => $credPath && file_exists($credPath),
            // 서비스 계정 파일 속 프로젝트 ID (위 Project ID 와 같아야 푸시가 감) — 비밀 값은 내려주지 않음
            'credentialsProject' => $credPath && file_exists($credPath) ? (json_decode((string) @file_get_contents($credPath), true)['project_id'] ?? null) : null,
        ]);
    }

    /**
     * GET /push/config (공개) — 브라우저가 푸시 알림을 켜는 데 쓰는 Firebase 웹 설정(원래 공개되는 값).
     * 사이트를 다시 만들지 않아도 관리자가 입력한 값이 바로 쓰이도록 빌드 변수 대신 여기서 내려준다.
     * 필수 값(apiKey/projectId/appId/senderId/VAPID)이 다 있어야 enabled.
     */
    public function pushConfig()
    {
        $s = fn($k) => trim((string) SiteSetting::where('key', $k)->value('value'));
        $cfg = [
            'apiKey' => $s('firebase_api_key'), 'authDomain' => $s('firebase_auth_domain'), 'projectId' => $s('firebase_project_id'),
            'storageBucket' => $s('firebase_storage_bucket'), 'messagingSenderId' => $s('firebase_sender_id'), 'appId' => $s('firebase_app_id'),
        ];
        $vapid = $s('firebase_vapid_key');
        $enabled = $cfg['apiKey'] && $cfg['projectId'] && $cfg['appId'] && $cfg['messagingSenderId'] && $vapid;
        return response()->json(['enabled' => (bool) $enabled, 'config' => $enabled ? array_filter($cfg) : null, 'vapidKey' => $enabled ? $vapid : null]);
    }

    /** POST /admin/firebase/credentials — Firebase 서비스 계정 JSON 업로드 (최고 관리자만, 서버 안쪽 폴더에만 저장) */
    public function uploadFirebaseCredentials(Request $request)
    {
        $request->validate(['file' => 'required|file|max:100']);
        $raw = (string) file_get_contents($request->file('file')->getRealPath());
        $j = json_decode($raw, true);
        $need = ['type', 'project_id', 'private_key', 'client_email'];
        if (!is_array($j) || ($j['type'] ?? '') !== 'service_account' || array_diff($need, array_keys(array_filter($j)))) {
            return response()->json(['success' => false, 'message' => 'Firebase 서비스 계정 JSON 파일이 아니에요. (Firebase 콘솔 → 프로젝트 설정 → 서비스 계정 → 새 비공개 키 생성으로 받은 파일)'], 422);
        }
        $path = config('services.firebase.credentials');
        if (!is_dir(dirname($path))) @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $raw);
        @chmod($path, 0600);

        $projectSetting = trim((string) SiteSetting::where('key', 'firebase_project_id')->value('value'));
        $msg = '서비스 계정 파일을 저장했어요. (프로젝트: ' . $j['project_id'] . ')';
        if ($projectSetting && $projectSetting !== $j['project_id']) {
            $msg .= ' ⚠️ 위 Project ID(' . $projectSetting . ')와 달라요 — 같은 프로젝트의 파일인지 확인해주세요.';
        }
        return response()->json(['success' => true, 'message' => $msg, 'projectId' => $j['project_id']]);
    }

    /** POST /admin/firebase/test — 내 기기(이 브라우저)로 테스트 알림을 보내 설정이 끝까지 되는지 확인 */
    public function testPush(Request $request)
    {
        $user = $request->user();
        $credPath = config('services.firebase.credentials');
        if (!$credPath || !file_exists($credPath)) {
            return response()->json(['success' => false, 'message' => '서버에 Firebase 서비스 계정 파일이 없어요. (storage/app/firebase-service-account.json)']);
        }
        if (!$user->fcm_token) {
            return response()->json(['success' => false, 'message' => '이 계정으로 등록된 기기가 아직 없어요. 위 설정을 저장한 뒤 사이트를 새로고침하고, 브라우저의 알림 허용을 누른 다음 다시 눌러주세요.']);
        }
        $push = app(\App\Services\PushNotificationService::class);
        $err = $push->sendToToken($user->fcm_token, '테스트 알림', '푸시 알림이 정상으로 설정됐어요!', ['type' => 'test', 'url' => '/']);
        return response()->json($err === null
            ? ['success' => true, 'message' => '테스트 알림을 보냈어요. 잠시 뒤 이 기기에 알림이 뜨는지 확인해주세요. (사이트 탭이 열려 있으면 안 보일 수 있어요 — 다른 탭/창으로 바꿔서 확인)']
            : ['success' => false, 'message' => '보내지 못했어요: ' . $err]);
    }

    public function saveFirebase(Request $request)
    {
        // .env 에도 쓰는 값이라 줄바꿈·따옴표·공백이 들어가면 설정 파일이 깨진다 → 안전한 문자만 허용
        $safe = ['nullable', 'string', 'max:300', 'regex:/^[A-Za-z0-9_\-\.:@\/]*$/'];
        $request->validate(['apiKey' => $safe, 'authDomain' => $safe, 'projectId' => $safe, 'storageBucket' => $safe, 'messagingSenderId' => $safe, 'appId' => $safe, 'vapidKey' => $safe]);
        $fields = [
            'firebase_api_key'      => $request->apiKey,
            'firebase_auth_domain'  => $request->authDomain,
            'firebase_project_id'   => $request->projectId,
            'firebase_storage_bucket' => $request->storageBucket,
            'firebase_sender_id'    => $request->messagingSenderId,
            'firebase_app_id'       => $request->appId,
            'firebase_vapid_key'    => $request->vapidKey,
        ];

        foreach ($fields as $key => $value) {
            SiteSetting::updateOrCreate(['key' => $key], ['value' => $value ?? '', 'group' => 'firebase']);
        }

        // .env 파일도 업데이트 (VITE_ 환경변수 — 빌드 시 필요)
        $envMap = [
            'VITE_FIREBASE_API_KEY'            => $request->apiKey,
            'VITE_FIREBASE_AUTH_DOMAIN'         => $request->authDomain,
            'VITE_FIREBASE_PROJECT_ID'          => $request->projectId,
            'VITE_FIREBASE_STORAGE_BUCKET'      => $request->storageBucket,
            'VITE_FIREBASE_MESSAGING_SENDER_ID' => $request->messagingSenderId,
            'VITE_FIREBASE_APP_ID'              => $request->appId,
            'VITE_FIREBASE_VAPID_KEY'           => $request->vapidKey,
        ];

        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            $env = file_get_contents($envPath);
            foreach ($envMap as $envKey => $envVal) {
                $envVal = $envVal ?? '';
                if (preg_match("/^{$envKey}=.*/m", $env)) {
                    $env = preg_replace("/^{$envKey}=.*/m", "{$envKey}={$envVal}", $env);
                } else {
                    $env .= "\n{$envKey}={$envVal}";
                }
            }
            file_put_contents($envPath, $env);
        }

        return response()->json(['success' => true, 'message' => 'Firebase 설정 저장 완료']);
    }

    // ─── 광고 페이지 설정 ───
    public function getAdPageSettings() {
        $setting = SiteSetting::where('key', 'ad_page_config')->first();
        $config = $setting ? json_decode($setting->value, true) : $this->defaultAdPageConfig();
        return response()->json(['success' => true, 'data' => $config]);
    }

    public function saveAdPageSettings(Request $request) {
        if ($err = \App\Support\SettingsValidator::adConfig($request->config)) {
            return response()->json(['success' => false, 'message' => $err], 422);
        }
        // 보내지 않은 페이지가 사라지지 않도록 기존 설정 위에 합쳐 저장
        $cur = SiteSetting::where('key', 'ad_page_config')->value('value');
        $merged = array_replace(is_string($cur) ? (json_decode($cur, true) ?: $this->defaultAdPageConfig()) : $this->defaultAdPageConfig(), $request->config);
        SiteSetting::updateOrCreate(['key' => 'ad_page_config'], ['value' => json_encode($merged)]);
        Cache::forget(self::SETTINGS_PUBLIC_CACHE_KEY);
        return response()->json(['success' => true, 'message' => '광고 페이지 설정이 저장되었습니다']);
    }

    private function defaultAdPageConfig() {
        $pages = ['home','community','qa','jobs','market','realestate','directory','clubs','news','recipes','groupbuy','events'];
        $config = [];
        foreach ($pages as $p) {
            $config[$p] = ['left_slots' => 2, 'right_slots' => 2, 'label' => $this->pageLabel($p)];
        }
        return $config;
    }

    public function getSlotMinPrices() {
        $setting = SiteSetting::where('key', 'ad_slot_min_prices')->first();
        return $setting ? json_decode($setting->value, true) : ['left' => 50, 'right' => 50];
    }

    public function saveSlotMinPrices(Request $request) {
        if ($err = \App\Support\SettingsValidator::adPrices($request->prices, $request->geo_markup)) {
            return response()->json(['success' => false, 'message' => $err], 422);
        }
        SiteSetting::updateOrCreate(['key' => 'ad_slot_min_prices'], ['value' => json_encode($request->prices)]);
        Cache::forget(self::SETTINGS_PUBLIC_CACHE_KEY);
        if ($request->geo_markup) {
            SiteSetting::updateOrCreate(['key' => 'ad_geo_markup'], ['value' => json_encode($request->geo_markup)]);
        }
        return response()->json(['success' => true, 'message' => '가격 설정 저장됨']);
    }

    private function pageLabel($key) {
        return match($key) {
            'home' => '홈', 'community' => '커뮤니티', 'qa' => 'Q&A', 'jobs' => '구인구직',
            'market' => '중고장터', 'realestate' => '부동산', 'directory' => '업소록',
            'clubs' => '동호회', 'news' => '뉴스', 'recipes' => '레시피',
            'groupbuy' => '공동구매', 'events' => '이벤트', default => $key,
        };
    }

    // 공개 API — 프론트엔드에서 광고 슬롯 수 + 최소 가격 가져오기
    public function getAdPageSettingsPublic() {
        $setting = SiteSetting::where('key', 'ad_page_config')->first();
        $config = $setting ? json_decode($setting->value, true) : $this->defaultAdPageConfig();

        $pricesSetting = SiteSetting::where('key', 'ad_slot_min_prices')->first();
        $config['slot_min_prices'] = $pricesSetting ? json_decode($pricesSetting->value, true) : [
            'left_premium' => 8000, 'left_standard' => 7000, 'left_economy' => 4000,
            'right_premium' => 10000, 'right_economy' => 6000
        ];

        $geoSetting = SiteSetting::where('key', 'ad_geo_markup')->first();
        $config['geo_markup'] = $geoSetting ? json_decode($geoSetting->value, true) : ['state' => 2000, 'national' => 3000];

        return response()->json(['success' => true, 'data' => $config]);
    }

    // ─── 포인트 설정 ───
    public function getPointSettings() {
        $settings = \DB::table('point_settings')->orderBy('category')->orderBy('id')->get();
        $grouped = $settings->groupBy('category');
        return response()->json(['success' => true, 'data' => $grouped]);
    }

    public function savePointSettings(Request $request) {
        $items = $request->input('settings', []);
        if (!is_array($items) || count($items) > 600) return response()->json(['success' => false, 'message' => '설정 목록 형식이 올바르지 않아요'], 422);
        $current = \DB::table('point_settings')->pluck('value', 'key')->all();
        $errors = []; $changes = [];
        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['key']) || !array_key_exists('value', $item)) continue;
            $k = (string) $item['key'];
            if (!array_key_exists($k, $current)) { $errors[$k] = '알 수 없는 설정이에요'; continue; }
            $newVal = is_scalar($item['value']) || $item['value'] === null ? trim((string) $item['value']) : null;
            if ($newVal !== null && $newVal === trim((string) $current[$k])) continue;   // 바뀌지 않은 칸은 검사하지 않음
            if ($err = \App\Support\SettingsValidator::point($k, $item['value'])) { $errors[$k] = $err; continue; }
            $changes[$k] = $newVal;
        }
        if (!$errors && $changes && ($g = \App\Support\SettingsValidator::gradeOrder($current, $changes))) { $errors['grade'] = $g; }
        if ($errors) {
            return response()->json(['success' => false, 'message' => '값이 올바르지 않은 칸이 있어요: ' . collect($errors)->map(fn ($m, $k) => "{$k} — {$m}")->take(3)->implode(' / '), 'errors' => $errors], 422);
        }
        foreach ($changes as $k => $val) {
            \DB::table('point_settings')->where('key', $k)->update(['value' => $val, 'updated_at' => now()]);
        }
        // 상위노출 설정 캐시 즉시 무효화
        \App\Support\PromotionSettings::flush();
        // P2B-2: PointRules 캐시도 무효화
        \App\Support\PointRules::flush();
        return response()->json(['success' => true, 'message' => '포인트 설정이 저장되었습니다.']);
    }

    // Entry 설정 — point_settings와 완전히 분리된 entry_settings 테이블
    public function getEntrySettings() {
        $settings = \DB::table('entry_settings')->orderBy('id')->get();
        return response()->json(['success' => true, 'data' => $settings]);
    }

    public function saveEntrySettings(Request $request) {
        $items = $request->input('settings', []);
        if (!is_array($items) || count($items) > 100) return response()->json(['success' => false, 'message' => '설정 목록 형식이 올바르지 않아요'], 422);
        $current = \DB::table('entry_settings')->pluck('value', 'key')->all();
        $errors = []; $changes = [];
        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['key']) || !array_key_exists('value', $item)) continue;
            $k = (string) $item['key'];
            if (!array_key_exists($k, $current)) { $errors[$k] = '알 수 없는 설정이에요'; continue; }
            $newVal = is_scalar($item['value']) || $item['value'] === null ? trim((string) $item['value']) : null;
            if ($newVal !== null && $newVal === trim((string) $current[$k])) continue;   // 바뀌지 않은 칸은 검사하지 않음
            if ($err = \App\Support\SettingsValidator::entry($k, $item['value'])) { $errors[$k] = $err; continue; }
            $changes[$k] = $newVal;
        }
        if ($errors) {
            return response()->json(['success' => false, 'message' => '값이 올바르지 않은 칸이 있어요: ' . collect($errors)->map(fn ($m, $k) => "{$k} — {$m}")->take(3)->implode(' / '), 'errors' => $errors], 422);
        }
        foreach ($changes as $k => $val) {
            \DB::table('entry_settings')->where('key', $k)->update(['value' => $val, 'updated_at' => now()]);
        }
        \App\Support\EntrySettings::flush();
        return response()->json(['success' => true, 'message' => 'Entry 설정이 저장되었습니다.']);
    }

    // 관리자 "시스템" 페이지 캐시 초기화 — 버튼만 있고 실제로는 아무 동작도
    // 하지 않던 장식용 UI였던 것을 라이브 재감사로 발견해 실제 동작하도록 연결.
    public function clearCache() {
        \Artisan::call('optimize:clear');
        return response()->json(['success' => true, 'message' => '캐시가 초기화되었습니다.']);
    }

    /**
     * 뉴스/헤드라인/주식/쇼츠/음악/레시피/업소록 7개 자동 수집을 버튼 하나로
     * 실행. 7개를 한 HTTP 요청 안에서 순서대로 다 기다리게 했더니 nginx
     * 타임아웃에 걸려 "수집 실패"로 끊기는 문제가 실측 확인돼, content:sync-all
     * 커맨드를 백그라운드 프로세스로 띄우고 즉시 응답만 반환하도록 변경.
     * 진행 결과는 syncAllContentStatus() 로 로그 파일을 읽어 확인.
     */
    public function syncAllContent() {
        $logPath = storage_path('logs/manual-sync-all.log');
        $progressPath = storage_path('logs/manual-sync-all-progress.json');

        // 버튼이 중복 클릭되거나(네트워크가 느려 응답이 안 왔다고 착각해 다시
        // 누름), 모바일 네트워크 재시도로 같은 POST가 두 번 들어오면 백그라운드
        // 프로세스 두 개가 동시에 같은 진행 파일에 번갈아 쓰면서 "완료된 단계가
        // 갑자기 다시 진행 중으로 보이는" 레이스 컨디션이 생길 수 있어 — 이미
        // 실행 중(20분 이내에 갱신된, 아직 안 끝난 진행 파일이 있음)이면 새로
        // 시작하지 않고 기존 진행 상황을 그대로 이어서 보여줌.
        if (file_exists($progressPath)) {
            $existing = json_decode(file_get_contents($progressPath), true);
            $updatedAt = $existing['updated_at'] ?? null;
            if ($existing && !($existing['done'] ?? true) && $updatedAt
                && \Carbon\Carbon::parse($updatedAt)->diffInMinutes(now()) < 20) {
                return response()->json([
                    'success' => true,
                    'message' => '이미 실행 중입니다 — 기존 진행 상황을 이어서 보여줍니다.',
                    'already_running' => true,
                ]);
            }
        }

        file_put_contents($logPath, "=== 시작: " . now() . " ===\n");
        // 이전 실행의 진행 파일이 남아있으면 폴링 시작 직후 "완료됨"으로
        // 잘못 보일 수 있어 매 실행 시작 시 제거.
        @unlink($progressPath);

        // exec()가 서버에서 막혀 있을 수 있어(공유 호스팅 등), 쉘 실행 대신
        // PHP-FPM의 fastcgi_finish_request()로 응답만 먼저 클라이언트에
        // 보내고 같은 프로세스에서 계속 실행하는 방식 사용 (exec 권한 불필요).
        $respond = response()->json(['success' => true, 'message' => '백그라운드에서 시작됐습니다. 진행 상황이 실시간으로 표시됩니다.']);
        if (function_exists('fastcgi_finish_request')) {
            $respond->send();
            fastcgi_finish_request();
        }

        // fastcgi_finish_request()는 연결만 끊을 뿐 PHP의 max_execution_time
        // 제한은 그대로 적용돼서, RSS 11개+이미지 다운로드 등으로 이어지는
        // 뉴스 수집 하나만으로도 php.ini 기본값(보통 30~60초)을 넘겨 중간에
        // 죽어버리는 문제가 있었음(진행 상황이 "뉴스 진행 중"에서 영원히
        // 멈춘 것처럼 보이던 원인) — 백그라운드 구간은 시간 제한 해제.
        set_time_limit(0);

        \Artisan::call('content:sync-all');
        file_put_contents($logPath, \Artisan::output(), FILE_APPEND);

        return $respond;
    }

    /**
     * 위 syncAllContent() 가 백그라운드로 남긴 단계별 진행 상태(steps)와 원본
     * 로그(log)를 함께 보여줌. '정보' 단계는 요청만 등록하고 바로 끝나므로
     * (실제 생성은 시간당 체크인 루틴이 수행), 실시간 생성 진행률(완료/목표)을
     * info_generation_status에서 가져와 detail로 덧붙인다.
     */
    public function syncAllContentStatus() {
        $logPath = storage_path('logs/manual-sync-all.log');
        $progressPath = storage_path('logs/manual-sync-all-progress.json');

        $log = file_exists($logPath) ? file_get_contents($logPath) : '';
        $progress = file_exists($progressPath) ? (json_decode(file_get_contents($progressPath), true) ?: []) : [];
        $steps = $progress['steps'] ?? [];

        $infoRaw = SiteSetting::where('key', 'info_generation_status')->value('value');
        $infoStatus = $infoRaw ? json_decode($infoRaw, true) : null;
        foreach ($steps as &$step) {
            if (($step['key'] ?? null) === 'info' && $infoStatus) {
                $step['detail'] = [
                    'status' => $infoStatus['status'] ?? null,
                    'completed' => $infoStatus['completed'] ?? null,
                    'target' => $infoStatus['target'] ?? null,
                ];
            }
        }
        unset($step);

        // 폴링 응답이 모바일 브라우저/중간 프록시에 캐시되면 몇 초마다 새로
        // 불러와도 예전 스냅샷만 계속 보이는 것처럼 보일 수 있어 명시적으로
        // 캐시를 금지.
        return response()->json([
            'success' => true,
            'log' => $log,
            'steps' => $steps,
            // 마지막으로 진행 파일에 실제로 쓰여진 시각 — 화면에서 "진행이
            // 멈췄는지" 판단할 때 브라우저 자체 상태(새로고침하면 리셋됨)가
            // 아니라 이 서버 시각 기준으로 계산해야 새로고침해도 정확함.
            'updated_at' => $progress['updated_at'] ?? null,
            'done' => (bool) ($progress['done'] ?? str_contains($log, '=== 전체 완료 ===')),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
          ->header('Pragma', 'no-cache');
    }
}
