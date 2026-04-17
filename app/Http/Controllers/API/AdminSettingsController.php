<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AdminSettingsController extends Controller
{
    /**
     * 설정 저장 후 캐시·config 재생성 공통 훅 (2026-04-17 Phase 2-C 묶음 1·7).
     * .env 를 건드린 경우 $envChanged=true 로 config:cache 재실행해 php-fpm 워커 동기화.
     * 이벤트 브로드캐스트로 활성 클라이언트 siteStore 실시간 재로드 유도.
     */
    protected function afterSettingsSave(bool $envChanged = false, array $keys = [], string $scope = 'general'): void
    {
        // public 설정 캐시 무효화 (60초 이내 자동 반영)
        Cache::forget('public_settings');
        Cache::forget('site_settings_public');
        if (class_exists(\App\Support\PointRules::class)) \App\Support\PointRules::flush();
        if (class_exists(\App\Support\PromotionSettings::class)) \App\Support\PromotionSettings::flush();

        if ($envChanged) {
            try {
                Artisan::call('config:clear');
                Artisan::call('config:cache');
            } catch (\Throwable $e) {
                Log::warning('config cache rebuild after env change failed: ' . $e->getMessage());
            }
        }

        // Reverb 실시간 브로드캐스트 (프론트 siteStore 자동 재로드)
        try {
            event(new \App\Events\SiteSettingsUpdated($keys, $scope));
        } catch (\Throwable $e) {
            Log::info('SiteSettingsUpdated broadcast skipped: ' . $e->getMessage());
        }
    }

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
        return response()->json(['success'=>true,'data'=>$settings]);
    }

    public function getPublic() {
        $keys = ['site_name','site_subtitle','logo_url','primary_color','footer_text','about_page','terms_page','privacy_page','meta_description','meta_keywords','company_name','contact_email','contact_phone','company_address','sns_facebook','sns_instagram','sns_twitter','sns_youtube','sns_kakao','menu_config'];
        $settings = SiteSetting::whereIn('key', $keys)->pluck('value','key');
        return response()->json(['success'=>true,'data'=>$settings]);
    }

    // 일괄 업데이트
    public function update(Request $request) {
        foreach ($request->all() as $key => $value) {
            $storeValue = is_array($value) ? json_encode($value) : $value;
            SiteSetting::updateOrCreate(['key'=>$key], ['value'=>$storeValue]);
        }
        return response()->json(['success'=>true,'message'=>'설정이 저장되었습니다']);
    }

    // 회사 정보 저장
    public function saveCompany(Request $request) {
        $keys = array_keys($request->all());
        foreach ($request->all() as $key => $value) {
            SiteSetting::updateOrCreate(['key'=>$key], ['value'=>$value]);
        }
        $this->afterSettingsSave(false, $keys, 'company');
        return response()->json(['success'=>true,'message'=>'회사 정보가 저장되었습니다']);
    }

    // 사이트 설정 저장
    public function saveSite(Request $request) {
        $keys = array_keys($request->all());
        foreach ($request->all() as $key => $value) {
            $storeValue = is_bool($value) ? ($value ? '1' : '0') : (is_array($value) ? json_encode($value) : $value);
            SiteSetting::updateOrCreate(['key'=>$key], ['value'=>$storeValue]);
        }
        $this->afterSettingsSave(false, $keys, 'site');
        return response()->json(['success'=>true,'message'=>'사이트 설정이 저장되었습니다']);
    }

    // 푸터 저장
    public function saveFooter(Request $request) {
        SiteSetting::updateOrCreate(['key'=>'footer_config'], ['value'=>json_encode($request->all())]);
        $this->afterSettingsSave();
        return response()->json(['success'=>true,'message'=>'푸터가 저장되었습니다']);
    }

    // 약관 저장
    public function saveTerms(Request $request, $type) {
        $key = $type === 'privacy' ? 'privacy_page' : 'terms_page';
        SiteSetting::updateOrCreate(['key'=>$key], ['value'=>$request->content]);
        $this->afterSettingsSave();
        return response()->json(['success'=>true,'message'=>'약관이 저장되었습니다']);
    }

    // 알림 설정 저장
    public function saveNotifications(Request $request) {
        SiteSetting::updateOrCreate(['key'=>'notification_config'], ['value'=>json_encode($request->all())]);
        $this->afterSettingsSave();
        return response()->json(['success'=>true,'message'=>'알림 설정이 저장되었습니다']);
    }

    // Stripe 키 저장 (.env 변경 → config:cache 재생성 필수)
    public function saveStripe(Request $request) {
        foreach (['stripe_publishable_key','stripe_secret_key','stripe_webhook_secret','stripe_test_mode'] as $k) {
            if ($request->has($k)) {
                SiteSetting::updateOrCreate(['key'=>$k], ['value'=>$request->$k]);
            }
        }
        // .env 파일에도 반영
        $this->updateEnv('STRIPE_KEY', $request->stripe_publishable_key);
        $this->updateEnv('STRIPE_SECRET', $request->stripe_secret_key);
        // config:cache 재생성 (.env 변경 시 필수)
        $this->afterSettingsSave(envChanged: true);
        return response()->json(['success'=>true,'message'=>'Stripe 키가 저장되었습니다','config_cache_rebuilt'=>true]);
    }

    // 결제 게이트웨이 설정
    public function savePaymentGateway(Request $request) {
        SiteSetting::updateOrCreate(['key'=>'payment_config'], ['value'=>json_encode($request->all())]);
        $this->afterSettingsSave();
        return response()->json(['success'=>true,'message'=>'결제 설정이 저장되었습니다']);
    }

    // SEO 설정 저장
    public function saveSeo(Request $request) {
        foreach ($request->all() as $key => $value) {
            SiteSetting::updateOrCreate(['key'=>'seo_'.$key], ['value'=>$value]);
        }
        $this->afterSettingsSave();
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
        SiteSetting::updateOrCreate(['key'=>'menu_config'], ['value'=>json_encode($request->menus)]);
        return response()->json(['success'=>true,'message'=>'메뉴 설정이 저장되었습니다']);
    }

    // API 키 관리
    public function getApiKeys() {
        $setting = SiteSetting::where('key', 'api_keys')->first();
        $keys = $setting ? json_decode($setting->value, true) : [];
        // 키 마스킹
        foreach ($keys as &$k) {
            $k['masked_key'] = substr($k['api_key'] ?? '', 0, 8) . '••••••••';
            $k['showFull'] = false;
        }
        return response()->json(['success'=>true,'data'=>$keys]);
    }

    public function storeApiKey(Request $request) {
        $request->validate(['name'=>'required','service'=>'required','api_key'=>'required']);
        $setting = SiteSetting::where('key', 'api_keys')->first();
        $keys = $setting ? json_decode($setting->value, true) : [];
        $newKey = [
            'id' => count($keys) + 1,
            'name' => $request->name,
            'service' => $request->service,
            'api_key' => $request->api_key,
            'description' => $request->description ?? '',
            'is_active' => true,
            'created_at' => now()->toDateTimeString(),
        ];
        $keys[] = $newKey;
        SiteSetting::updateOrCreate(['key'=>'api_keys'], ['value'=>json_encode($keys)]);
        // .env에도 반영 (서비스별)
        $envKey = strtoupper($request->service) . '_API_KEY';
        $this->updateEnv($envKey, $request->api_key);
        return response()->json(['success'=>true,'data'=>$newKey,'message'=>'API 키가 등록되었습니다']);
    }

    public function deleteApiKey($id) {
        $setting = SiteSetting::where('key', 'api_keys')->first();
        $keys = $setting ? json_decode($setting->value, true) : [];
        $keys = array_values(array_filter($keys, fn($k) => $k['id'] != $id));
        SiteSetting::updateOrCreate(['key'=>'api_keys'], ['value'=>json_encode($keys)]);
        return response()->json(['success'=>true,'message'=>'삭제되었습니다']);
    }

    public function updateApiKey(Request $request, $id) {
        $setting = SiteSetting::where('key', 'api_keys')->first();
        $keys = $setting ? json_decode($setting->value, true) : [];
        foreach ($keys as &$k) {
            if ($k['id'] == $id) {
                if ($request->has('is_active')) $k['is_active'] = $request->is_active;
            }
        }
        SiteSetting::updateOrCreate(['key'=>'api_keys'], ['value'=>json_encode($keys)]);
        return response()->json(['success'=>true]);
    }

    public function revealApiKey($id) {
        $setting = SiteSetting::where('key', 'api_keys')->first();
        $keys = $setting ? json_decode($setting->value, true) : [];
        foreach ($keys as $k) {
            if ($k['id'] == $id) return response()->json(['success'=>true,'data'=>['key'=>$k['api_key']]]);
        }
        return response()->json(['success'=>false,'message'=>'키를 찾을 수 없습니다'],404);
    }

    public function uploadLogo(Request $request) {
        $request->validate(['logo'=>'required|image']);
        $path = $request->file('logo')->storeAs('public', 'logo_00.jpg');
        copy(storage_path('app/' . $path), public_path('images/logo_00.jpg'));
        SiteSetting::updateOrCreate(['key'=>'logo_url'], ['value'=>'/images/logo_00.jpg']);
        return response()->json(['success'=>true,'data'=>['url'=>'/images/logo_00.jpg']]);
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
            ['key'=>'news','label'=>'뉴스','icon'=>'📰','path'=>'/news','enabled'=>true,'login_required'=>false,'admin_only'=>false],
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
}
