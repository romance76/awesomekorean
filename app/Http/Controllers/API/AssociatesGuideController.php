<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Traits\CompressesUploads;
use Illuminate\Http\Request;

/**
 * "Amazon Associates 가입 방법" 안내 페이지. 단계별 글 + 화면 캡처 이미지.
 * 내용은 site_settings.associates_guide(JSON)에 저장 — 저장된 게 없으면 기본 글(DEFAULT_STEPS)을 보여준다.
 * 캡처 이미지는 관리자가 단계마다 올린다.
 */
class AssociatesGuideController extends Controller
{
    use CompressesUploads;

    private const KEY = 'associates_guide';

    private const DEFAULT_STEPS = [
        ['title' => '1. 미국 Amazon 계정 준비', 'body' => "amazon.com(미국 사이트)에서 쓰는 계정이 있으면 그대로 쓰면 돼요. 없으면 amazon.com 에서 'Create account'로 먼저 만드세요.\n한국 Amazon이나 다른 나라 사이트 계정이 아니라 미국 amazon.com 계정이어야 해요.", 'image' => ''],
        ['title' => '2. Associates 가입 페이지 열기', 'body' => "주소창에 affiliate-program.amazon.com 을 입력해서 들어가요. 화면의 'Sign up(가입)' 버튼을 누르고, 위에서 준비한 Amazon 계정으로 로그인해요.", 'image' => ''],
        ['title' => '3. 계정 정보 입력', 'body' => "이름, 주소, 전화번호를 입력해요. 이 정보는 나중에 수익을 받을 때 쓰이니 실제 정보로 정확히 적어주세요.", 'image' => ''],
        ['title' => '4. 링크를 올릴 곳(웹사이트/앱) 등록', 'body' => "Amazon은 링크를 올릴 곳을 미리 등록하도록 요구해요. 내 블로그·유튜브·SNS가 있으면 그 주소를 적어요.\n\nAwesome Korean에 리뷰를 올릴 거라면 awesomekorean.com 주소도 함께 등록해두세요. (Associates 가입 후 'Account Settings → Manage Your Websites and Apps'에서 나중에 추가할 수도 있어요.) 등록하지 않은 곳에 링크를 올리면 Amazon 약관 위반이 될 수 있어요.", 'image' => ''],
        ['title' => '5. 스토어 이름과 Store ID 정하기', 'body' => "프로필 단계에서 스토어(표시) 이름을 정하면 Amazon이 Store ID를 만들어줘요. 형식은 myshop-20 처럼 영문/숫자 + 하이픈, 끝에 -20 같은 숫자 두 자리예요.\n\n이 Store ID가 바로 Awesome Korean에 등록하는 '태그'예요. 가입이 끝나면 Associates 화면 왼쪽 위에서 확인할 수 있어요.", 'image' => ''],
        ['title' => '6. 내 사이트 설명과 방문자 늘리는 방법 적기', 'body' => "어떤 주제의 글/영상/리뷰를 올리는지, 방문자를 어떻게 모을 건지(블로그, SNS, 커뮤니티 등)를 간단히 적어요. 영어로 짧게 쓰면 돼요. 예) I share honest reviews of products I bought myself.", 'image' => ''],
        ['title' => '7. 전화 인증 · 결제/세금 정보', 'body' => "전화번호 인증을 하고, 수익을 받을 방법(은행 계좌 직접입금 또는 기프트카드)과 세금 정보(Tax Interview)를 입력해요. 이 단계는 가입 직후 바로 못 끝내도 되지만, 수익을 받으려면 꼭 해야 해요. 외국 거주자는 W-8BEN 같은 서류가 나올 수 있으니 화면 안내를 따라주세요.", 'image' => ''],
        ['title' => '8. 가입 완료 후 — 180일 안에 3건 판매', 'body' => "가입이 승인되면 바로 링크를 쓸 수 있어요. 단, 가입 후 180일 안에 내 링크로 3건 이상의 구매가 나와야 계정이 유지돼요. 내돈내산 리뷰를 꾸준히 올리는 게 가장 좋은 방법이에요.\n\n수익·클릭 현황은 Associates Central의 Reports(보고서)에서 확인해요.", 'image' => ''],
        ['title' => '9. Awesome Korean에 태그 등록하고 리뷰 쓰기', 'body' => "Awesome Korean 마이페이지 → '내 리뷰' 탭에서 Store ID(예: myshop-20)를 등록하면 리뷰를 쓸 수 있어요. 리뷰 속 'Amazon에서 보기' 링크에 내 태그가 붙고, 수익은 내 Associates 계정으로 들어가요.", 'image' => ''],
    ];

    private function steps(): array
    {
        $raw = SiteSetting::where('key', self::KEY)->value('value');
        $steps = $raw ? json_decode($raw, true) : null;
        return is_array($steps) && $steps ? $steps : self::DEFAULT_STEPS;
    }

    /** GET /shopping/associates-guide (공개) */
    public function show()
    {
        return response()->json(['success' => true, 'data' => ['steps' => $this->steps()]]);
    }

    /** PUT /admin/associates-guide */
    public function save(Request $request)
    {
        $data = $request->validate([
            'steps' => 'required|array|min:1|max:30',
            'steps.*.title' => 'required|string|max:120',
            'steps.*.body' => 'nullable|string|max:3000',
            'steps.*.image' => 'nullable|string|max:300',
        ]);
        $clean = array_map(fn($s) => [
            'title' => trim(strip_tags($s['title'])),
            'body' => trim(strip_tags($s['body'] ?? '')),
            // 우리가 올린 이미지(/storage/...)만 허용
            'image' => preg_match('#^/storage/guide/[\w./-]+$#', $s['image'] ?? '') ? $s['image'] : '',
        ], $data['steps']);
        SiteSetting::updateOrCreate(['key' => self::KEY], ['value' => json_encode($clean, JSON_UNESCAPED_UNICODE), 'group' => 'shopping']);
        return response()->json(['success' => true, 'message' => '저장했어요.', 'data' => ['steps' => $clean]]);
    }

    /** POST /admin/associates-guide/image — 캡처 이미지 한 장 올리기 */
    public function upload(Request $request)
    {
        $request->validate(['image' => 'required|image|max:10240']);
        $url = $this->storeCompressedImage($request->file('image'), 'guide', 1400, 85);
        return response()->json(['success' => true, 'data' => ['url' => $url]]);
    }
}
