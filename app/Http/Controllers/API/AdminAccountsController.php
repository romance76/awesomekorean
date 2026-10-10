<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 관리자 "계정·이메일 현황" — 어썸코리안이 쓰는 이메일 계정을 한곳에 모아 보여주고, 각각 어디에 쓰이는지 알려준다.
 *
 * 서버가 알 수 있는 것(메일 발신 주소·연락처·알림 수신·운영진 로그인·구글/Firebase 서비스 계정)은 실제 설정에서 읽어오고,
 * 서버가 알 수 없는 것(도메인 업체, 서버 업체, GitHub 로그인 메일 등)은 관리자가 직접 적어 두는 메모로 관리한다.
 * 비밀번호·API 키 값은 어떤 응답에도 넣지 않는다(키 관리는 "API 키 관리" 화면에서).
 */
class AdminAccountsController extends Controller
{
    private const MEMO_KEY = 'account_memos';

    // api_keys 의 service 코드 → 어디에 쓰이는지 (키 값은 보여주지 않고 용도·상태만)
    private const SERVICE_USES = [
        'resend_api_key' => ['Resend (이메일 발송)', '회원가입 인증·비밀번호 재설정·업소 인증 메일 발송'],
        'google_client_id' => ['구글 로그인', '구글 계정으로 로그인/가입'],
        'google_client_secret' => ['구글 로그인 (비밀값)', '구글 로그인'],
        'amazon_client_id' => ['아마존 로그인', '아마존 계정으로 로그인/가입'],
        'amazon_client_secret' => ['아마존 로그인 (비밀값)', '아마존 로그인'],
        'youtube' => ['YouTube Data API', '음악·숏츠 자동 수집'],
        'google_places' => ['Google Places API', '업소록 자동 수집·장소 검색'],
        'google_analytics' => ['구글 애널리틱스 (측정 ID)', '방문 분석 — 사이트 추적 코드'],
        'ga_property_id' => ['구글 애널리틱스 (속성 ID)', '관리자 방문 분석 화면의 데이터 조회'],
        'ga_service_account' => ['구글 애널리틱스 서비스 계정', '관리자 방문 분석 화면의 데이터 조회'],
        'stripe_pub' => ['Stripe (공개 키)', '카드 결제 — 사용 중단 예정(CardPointe로 이전)'],
        'stripe_secret' => ['Stripe (비밀 키)', '카드 결제 — 사용 중단 예정(CardPointe로 이전)'],
        'ebay_client_id' => ['eBay', '중고장터 매물 자동 수집'],
        'ebay_client_secret' => ['eBay (비밀값)', '중고장터 매물 자동 수집'],
        'realtyapi' => ['RealtyAPI', '부동산 매물 자동 수집'],
        'spoonacular' => ['Spoonacular', '레시피 자동 수집'],
        'eventbrite' => ['Eventbrite', '이벤트 자동 수집'],
        'eventbrite_oauth' => ['Eventbrite (OAuth)', '이벤트 자동 수집'],
        'eventbrite_token' => ['Eventbrite (토큰)', '이벤트 자동 수집'],
        'arirang_news' => ['아리랑 뉴스 API', '뉴스 자동 수집'],
        'info_ingest_token' => ['정보 탭 자동 생성 토큰', '정보(/info) 글을 외부에서 자동 등록할 때 쓰는 인증'],
        'github' => ['GitHub 접근 토큰', '코드 저장소 접근(배포용)'],
        'digitalocean' => ['DigitalOcean', '서버(호스팅) 관리'],
        'reverb' => ['Reverb (실시간)', '채팅·알림 실시간 서버 내부 키'],
        'reverb_secret' => ['Reverb (실시간 비밀값)', '채팅·알림 실시간 서버 내부 키'],
        'jwt' => ['JWT 비밀값', '로그인 토큰 내부 서명'],
        'laravel' => ['Laravel 앱 키', '사이트 내부 암호화'],
        'mysql' => ['MySQL', '데이터베이스 접속 정보'],
        'redis' => ['Redis', '캐시·큐 접속 정보'],
    ];

    public function index()
    {
        $rows = [];

        // ── 1) 메일 발신 주소 ─────────────────────────────
        $transport = (string) config('mail.default');
        $resendKey = (bool) config('services.resend.key');
        if (!$resendKey) {
            try { $resendKey = ApiKey::where('service', 'resend_api_key')->where('is_active', true)->exists(); } catch (\Throwable $e) {}
        }
        $from = (string) config('mail.from.address');
        $sendingWorks = $transport !== 'log' && $transport !== 'array' && ($transport !== 'resend' || $resendKey);
        $rows[] = [
            'group' => 'sender', 'email' => $from, 'title' => '메일 발신 주소 (회원에게 가는 모든 메일의 보낸 사람)',
            'used_in' => [
                '회원가입 이메일 인증 메일', '비밀번호 재설정 코드 메일', '업소 소유(클레임) 인증·승인 메일',
                '관리자가 비밀번호를 재설정했을 때 안내 메일', 'API 키 변경 알림 메일', '관리자 메일 발송 테스트',
            ],
            'status' => $sendingWorks ? 'ok' : 'warn',
            'note' => ($sendingWorks
                    ? "발송 방식: {$transport}" . ($transport === 'resend' ? ' (Resend API 키 등록됨)' : '')
                    : "발송 방식이 '{$transport}' 이거나 Resend 키가 없어 메일이 실제로 나가지 않을 수 있어요")
                . ' · 보낸 사람 이름: ' . (string) config('mail.from.name')
                . ' · 이 도메인의 메일 인증(SPF/DKIM)은 Resend 사이트에서 확인해야 해요(서버에서는 알 수 없음)',
        ];

        // ── 2) 사이트에 공개된 연락처 ─────────────────────
        $setting = fn (string $k) => (string) (SiteSetting::where('key', $k)->value('value') ?? '');
        $contact = $setting('contact_email');
        $rows[] = [
            'group' => 'contact', 'email' => $contact ?: '', 'title' => '문의 연락처 (사이트 설정 "연락처 이메일")',
            'used_in' => ['"문의하기" 페이지에 표시', '"회사 소개" 페이지에 표시'],
            'status' => $contact ? 'ok' : 'warn',
            'note' => $contact ? '회원이 문의 메일을 보내는 주소예요. 이 메일함을 실제로 확인할 수 있어야 해요' : '설정된 값이 없어 화면에는 기본값 admin@awesomekorean.com 이 표시돼요',
        ];
        $support = $setting('email');
        if ($support !== '') {
            $rows[] = [
                'group' => 'contact', 'email' => $support, 'title' => '사이트 설정의 "email" 값',
                'used_in' => [],
                'status' => 'warn',
                'note' => '저장은 되어 있지만 현재 사이트 어디에서도 이 값을 읽어 쓰는 곳이 확인되지 않았어요 (정리 후보)',
            ];
        }

        // ── 3) 알림 수신 ──────────────────────────────────
        $rows[] = [
            'group' => 'notify', 'email' => (string) config('services.admin_report_email'), 'title' => 'API 키 변경 알림 수신 주소',
            'used_in' => ['"API 키 관리"에서 키를 등록·수정·삭제할 때마다, 현재 등록된 전체 API 키 현황을 이 주소로 메일 발송'],
            'status' => 'warn',
            'note' => '메일 본문에 API 키 값이 그대로 들어가요. 이 메일함은 본인만 접근하도록 꼭 관리해 주세요 (변경: 서버 설정 ADMIN_REPORT_EMAIL)',
        ];
        $rows[] = [
            'group' => 'notify', 'email' => '', 'title' => '관리자 알림 이메일 3종 (신규 가입 / 신고 접수 / 결제) — 사이트 설정 > 결제/알림 설정',
            'used_in' => [],
            'status' => 'warn',
            'note' => '설정 화면에 입력칸만 있고, 이 주소로 메일을 보내는 기능은 아직 연결돼 있지 않아요(새 신고·가입은 관리자에게 사이트 안 알림으로만 가요). 주소를 적어도 메일은 오지 않아요',
        ];

        // ── 4) 운영진 로그인 계정 ─────────────────────────
        $staff = User::whereIn('role', ['super_admin', 'admin', 'moderator'])
            ->orderByRaw("FIELD(role,'super_admin','admin','moderator')")->orderBy('id')
            ->get(['id', 'email', 'role', 'name', 'nickname', 'email_verified_at', 'last_login_at'])
            ->map(function ($u) {
                $domain = strtolower(substr(strrchr($u->email, '@') ?: '@', 1));
                $isTest = in_array($domain, ['test.com', 'example.com', 'somekorean.local'], true);
                $invalid = !filter_var($u->email, FILTER_VALIDATE_EMAIL) || preg_match('/\.(con|cmo|ocm|comm)$/i', $domain);
                return [
                    'id' => $u->id, 'email' => $u->email, 'name' => $u->nickname ?: $u->name, 'role' => $u->role,
                    'verified' => (bool) $u->email_verified_at, 'last_login_at' => optional($u->last_login_at)->toIso8601String(),
                    'is_test' => $isTest, 'invalid' => (bool) $invalid,
                ];
            })->values();

        // ── 5) 서비스 계정(구글·Firebase) ─────────────────
        $service = [];
        try {
            $path = (string) env('FIREBASE_CREDENTIALS', '');
            if ($path && is_file($path)) {
                $j = json_decode((string) file_get_contents($path), true);
                if (!empty($j['client_email'])) {
                    $service[] = [
                        'email' => $j['client_email'], 'title' => 'Firebase 서비스 계정 (프로젝트 ' . ($j['project_id'] ?? '?') . ')',
                        'used_in' => ['푸시 알림 발송(FCM) — 새 쪽지·알림을 폰으로 보낼 때 서버가 이 계정으로 Firebase에 접속'],
                    ];
                }
            }
        } catch (\Throwable $e) {}
        try {
            $ga = ApiKey::where('service', 'ga_service_account')->where('is_active', true)->first();
            $j = $ga ? json_decode((string) $ga->api_key, true) : null;
            if (!empty($j['client_email'])) {
                $service[] = [
                    'email' => $j['client_email'], 'title' => '구글 애널리틱스 서비스 계정 (프로젝트 ' . ($j['project_id'] ?? '?') . ')',
                    'used_in' => ['관리자 "방문 분석" 화면이 구글 애널리틱스 데이터를 읽어올 때 사용'],
                ];
            }
        } catch (\Throwable $e) {}

        // ── 6) API 키 관리에 등록된 연동 서비스(키 값은 숨김) ──
        $services = ApiKey::orderBy('service')->get(['service', 'name', 'is_active', 'updated_at'])->map(function ($k) {
            [$label, $use] = self::SERVICE_USES[$k->service] ?? [$k->name, '용도가 정리되지 않은 항목이에요'];
            return ['service' => $k->service, 'label' => $label, 'used_in' => $use, 'active' => (bool) $k->is_active,
                'updated_at' => optional($k->updated_at)->toIso8601String()];
        })->values();

        // ── 7) 관리자가 직접 적어 둔 계정 메모 ────────────
        $memos = [];
        try { $memos = json_decode($setting(self::MEMO_KEY) ?: '[]', true) ?: []; } catch (\Throwable $e) {}

        return response()->json(['success' => true, 'data' => [
            'rows' => $rows, 'staff' => $staff, 'service_accounts' => $service, 'services' => $services, 'memos' => $memos,
            'generated_at' => now()->toIso8601String(),
        ]]);
    }

    // 서버가 알 수 없는 계정(도메인·서버·GitHub 로그인 메일 등)을 관리자가 직접 적어 두는 메모 저장
    public function saveMemos(Request $request)
    {
        $d = $request->validate([
            'memos' => 'present|array|max:40',
            'memos.*.label' => 'required|string|max:80',
            'memos.*.email' => 'nullable|email|max:190',
            'memos.*.note' => 'nullable|string|max:300',
        ]);
        $clean = collect($d['memos'])->map(fn ($m) => [
            'label' => trim($m['label']), 'email' => trim((string) ($m['email'] ?? '')), 'note' => trim((string) ($m['note'] ?? '')),
        ])->values()->all();
        SiteSetting::updateOrCreate(['key' => self::MEMO_KEY], ['value' => json_encode($clean, JSON_UNESCAPED_UNICODE), 'group' => 'admin']);
        return response()->json(['success' => true, 'data' => $clean]);
    }
}
