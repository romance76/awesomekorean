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
    public function index() {
        $settings = SiteSetting::all()->pluck('value', 'key');
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
            $keys = ['site_name','site_subtitle','logo_url','logo_dark_url','primary_color','footer_text','about_page','terms_page','privacy_page','meta_description','meta_keywords','company_name','contact_email','contact_phone','company_address','sns_facebook','sns_instagram','sns_twitter','sns_youtube','sns_kakao','menu_config','footer_config'];
            return SiteSetting::whereIn('key', $keys)->pluck('value','key');
        });
        return response()->json(['success'=>true,'data'=>$settings]);
    }

    // 일괄 업데이트
    public function update(Request $request) {
        foreach ($request->all() as $key => $value) {
            $storeValue = is_array($value) ? json_encode($value) : $value;
            SiteSetting::updateOrCreate(['key'=>$key], ['value'=>$storeValue]);
        }
        Cache::forget(self::SETTINGS_PUBLIC_CACHE_KEY);
        return response()->json(['success'=>true,'message'=>'설정이 저장되었습니다']);
    }

    // 회사 정보 저장
    public function saveCompany(Request $request) {
        foreach ($request->all() as $key => $value) {
            SiteSetting::updateOrCreate(['key'=>$key], ['value'=>$value]);
        }
        Cache::forget(self::SETTINGS_PUBLIC_CACHE_KEY);
        return response()->json(['success'=>true,'message'=>'회사 정보가 저장되었습니다']);
    }

    // 사이트 설정 저장
    public function saveSite(Request $request) {
        foreach ($request->all() as $key => $value) {
            $storeValue = is_bool($value) ? ($value ? '1' : '0') : (is_array($value) ? json_encode($value) : $value);
            SiteSetting::updateOrCreate(['key'=>$key], ['value'=>$storeValue]);
        }
        return response()->json(['success'=>true,'message'=>'사이트 설정이 저장되었습니다']);
    }

    // 푸터 저장
    public function saveFooter(Request $request) {
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
        SiteSetting::updateOrCreate(['key'=>'notification_config'], ['value'=>json_encode($request->all())]);
        return response()->json(['success'=>true,'message'=>'알림 설정이 저장되었습니다']);
    }

    // Stripe 키 저장
    public function saveStripe(Request $request) {
        foreach (['stripe_publishable_key','stripe_secret_key','stripe_webhook_secret','stripe_test_mode'] as $k) {
            if ($request->has($k)) {
                SiteSetting::updateOrCreate(['key'=>$k], ['value'=>$request->$k]);
            }
        }
        // .env 파일에도 반영
        $this->updateEnv('STRIPE_KEY', $request->stripe_publishable_key);
        $this->updateEnv('STRIPE_SECRET', $request->stripe_secret_key);
        return response()->json(['success'=>true,'message'=>'Stripe 키가 저장되었습니다']);
    }

    // 결제 게이트웨이 설정
    public function savePaymentGateway(Request $request) {
        SiteSetting::updateOrCreate(['key'=>'payment_config'], ['value'=>json_encode($request->all())]);
        return response()->json(['success'=>true,'message'=>'결제 설정이 저장되었습니다']);
    }

    // SEO 설정 저장
    public function saveSeo(Request $request) {
        foreach ($request->all() as $key => $value) {
            SiteSetting::updateOrCreate(['key'=>'seo_'.$key], ['value'=>$value]);
        }
        return response()->json(['success'=>true,'message'=>'SEO 설정이 저장되었습니다']);
    }

    // VAPID 키 생성
    public function generateVapid() {
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
    // 서버 .env를 직접 못 보는 상황이라, 실제 요청 시점에 Laravel이 무엇을 읽고
    // 있는지(마스킹된 값)를 여기서 바로 확인. 원인 확정되면 삭제 예정.
    public function mailDebug() {
        $envMailer = env('MAIL_MAILER');
        $envResendKey = env('RESEND_API_KEY');
        $dbRow = ApiKey::where('service', 'resend_api_key')->first();

        return response()->json(['success' => true, 'data' => [
            'resolved_mail_default' => config('mail.default'),
            'resolved_resend_key_masked' => config('services.resend.key') ? substr(config('services.resend.key'), 0, 8) . '...' : null,
            'env_MAIL_MAILER_raw' => $envMailer === null ? '(미설정)' : $envMailer,
            'env_RESEND_API_KEY_raw' => $envResendKey ? substr($envResendKey, 0, 8) . '...' : '(미설정)',
            'db_row_exists' => (bool) $dbRow,
            'db_row_is_active' => $dbRow?->is_active,
            'db_row_key_masked' => $dbRow?->api_key ? substr($dbRow->api_key, 0, 8) . '...' : null,
            'mail_from_address' => config('mail.from.address'),
        ]]);
    }

    // 설정값은 전부 정상인데도 실제 발송이 계속 실패해서, 추측 대신 실제로
    // 발송을 시도해 Resend 클라이언트가 던지는 진짜 예외 메시지를 그대로
    // 반환 — 서버 .env/로그에 직접 접근할 방법이 없는 상황의 최후 수단.
    // 원인 확정되면 mailDebug()와 함께 제거 예정.
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

    // 임시 진단용 — "비밀번호 찾기" 요청 시 5분 쿨다운에 걸려 실제로는 발송이
    // 스킵됐는지 확인 (화면엔 쿨다운 여부와 무관하게 항상 "전송했습니다"로
    // 뜨게 설계되어 있어 프론트에서는 구분이 안 됨). 원인 확정되면 제거 예정.
    public function passwordResetDebug(Request $request) {
        $email = $request->query('email');
        if (!$email) return response()->json(['success' => false, 'message' => 'email 쿼리 파라미터 필요'], 422);

        $row = \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $email)->first();
        if (!$row) {
            return response()->json(['success' => true, 'data' => ['row_exists' => false]]);
        }

        return response()->json(['success' => true, 'data' => [
            'row_exists' => true,
            'created_at' => $row->created_at,
            'server_now' => now()->toDateTimeString(),
            'diff_in_minutes_raw' => now()->diffInMinutes($row->created_at),
            'diff_in_minutes_abs' => abs(now()->diffInMinutes($row->created_at)),
            'would_skip_cooldown' => abs(now()->diffInMinutes($row->created_at)) < 5,
        ]]);
    }

    public function storeApiKey(Request $request) {
        $request->validate(['name'=>'required','service'=>'required','api_key'=>'required']);
        if ($request->service === \App\Support\Analytics::SERVICE && !\App\Support\Analytics::isValid($request->api_key)) {
            return response()->json(['success'=>false,'message'=>'구글 Analytics 측정 ID 형식이 아닙니다 (예: G-ABC123DEF4)'], 422);
        }
        // 구글 Analytics 는 사이트에 하나만 쓰므로 이미 있으면 새 행을 만들지 않고 그 값을 교체
        $existing = $request->service === \App\Support\Analytics::SERVICE ? ApiKey::where('service', $request->service)->first() : null;
        if ($existing) {
            $existing->update(['name' => $request->name, 'api_key' => $request->api_key, 'description' => $request->description ?? '', 'is_active' => true]);
            \App\Support\Analytics::forget();
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
        return response()->json(['success'=>true,'data'=>$newKey,'message'=>'API 키가 등록되었습니다']);
    }

    public function deleteApiKey($id) {
        ApiKey::where('id', $id)->delete();
        \App\Support\Analytics::forget();
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

        return response()->json(['success'=>true, 'message'=>'수정되었습니다']);
    }

    public function revealApiKey($id) {
        $key = ApiKey::find($id);
        if (!$key) return response()->json(['success'=>false,'message'=>'키를 찾을 수 없습니다'],404);
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
    private function updateEnv($key, $value) {
        $envPath = base_path('.env');
        if (!file_exists($envPath)) return;
        $content = file_get_contents($envPath);
        if (strpos($content, $key.'=') !== false) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        } else {
            $content .= "\n{$key}={$value}";
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
        ]);
    }

    public function saveFirebase(Request $request)
    {
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
        SiteSetting::updateOrCreate(['key' => 'ad_page_config'], ['value' => json_encode($request->config)]);
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
        SiteSetting::updateOrCreate(['key' => 'ad_slot_min_prices'], ['value' => json_encode($request->prices)]);
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
        foreach ($items as $item) {
            if (!isset($item['key'], $item['value'])) continue;
            \DB::table('point_settings')->where('key', $item['key'])->update([
                'value' => $item['value'],
                'updated_at' => now(),
            ]);
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
        foreach ($items as $item) {
            if (!isset($item['key'], $item['value'])) continue;
            \DB::table('entry_settings')->where('key', $item['key'])->update([
                'value' => $item['value'],
                'updated_at' => now(),
            ]);
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
