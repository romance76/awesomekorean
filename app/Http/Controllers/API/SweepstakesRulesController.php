<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

/**
 * 경품 추첨 공식 규칙(Official Rules) 문서.
 * 이용약관(terms_page)과 같은 방식 — site_settings 키-값에 저장하고,
 * 내용은 "HTML 태그가 있으면 HTML, 없으면 줄바꿈만 <br> 변환" 규칙으로 프론트가 표시한다.
 *
 * 저장 키: sweepstakes_rules_title / sweepstakes_rules_content / sweepstakes_rules_version
 * (값이 없으면 기본 문안으로 대체 — defaultContent())
 */
class SweepstakesRulesController extends Controller
{
    private const KEY_TITLE = 'sweepstakes_rules_title';
    private const KEY_CONTENT = 'sweepstakes_rules_content';
    private const KEY_VERSION = 'sweepstakes_rules_version';

    public static function defaultTitle(): string
    {
        return '경품 추첨 공식 규칙 (Official Rules)';
    }

    public static function defaultContent(): string
    {
        return <<<'TXT'
※ 공개 전 법률 검토 필요

1. 주최자
본 경품 추첨(Sweepstakes)은 어썸코리안(Awesome Korean, 이하 "주최자")이 주최하고 운영합니다.

2. 참가 자격
- 만 18세 이상인 어썸코리안 회원만 참가할 수 있습니다. (이벤트별로 더 높은 연령 기준이 있을 수 있습니다.)
- 미국 거주자로서 각 이벤트 페이지에 표시된 참가 가능 지역(주)에 거주해야 합니다.
- 주최자의 임직원 및 그 직계 가족은 참가할 수 없습니다.

3. 구매 불필요 (NO PURCHASE NECESSARY)
- 참가 및 당첨을 위해 어떠한 구매도 필요하지 않으며, 구매한다고 해서 당첨 확률이 높아지지 않습니다.
- Entry(응모권)는 구매할 수 없으며, 회원가입 보너스, 일일 출석 체크 등 무료 활동으로만 획득할 수 있습니다.

4. 참가 방법
- 로그인 후 해당 이벤트 페이지에서 보유한 Entry를 사용하여 응모합니다.
- 이벤트별 1인당 사용 가능한 Entry 한도가 있을 수 있으며, 한도는 이벤트 페이지에 표시됩니다.
- 한 번 사용한 Entry는 취소하거나 환불할 수 없습니다.

5. 응모 기간
- 응모 기간은 각 이벤트 페이지에 표시된 시작일부터 종료일까지이며, 기간 이후의 응모는 인정되지 않습니다.

6. 경품
- 경품의 종류, 수량, 대략적인 소매 가치(Approximate Retail Value)는 각 이벤트 페이지에 표시됩니다.
- 경품은 디지털 기프트카드로 제공됩니다.

7. 당첨 확률
- 당첨 확률은 해당 이벤트의 전체 Entry 수 대비 본인이 사용한 Entry 수에 따라 달라집니다.

8. 당첨자 선정
- 당첨자는 서버가 암호학적으로 안전한 난수 생성기(CSPRNG)를 사용하여 무작위로 추첨합니다.
- 추첨 결과는 감사 로그에 기록되며, 추첨 과정은 재생(replay)하여 확인할 수 있습니다.
- 여러 명을 추첨하는 이벤트는 등수별로 순서대로 추첨하며, 한 사람이 한 이벤트에서 두 번 이상 당첨되지 않습니다.

9. 당첨자 통지
- 당첨자에게는 앱 내 알림과 로그인 팝업으로 통지합니다.
- 당첨자는 통지 후 7일 이내에 이메일, 연락처, 주소 등 경품 수령에 필요한 정보를 확인해야 하며, 기한 내 확인하지 않으면 당첨이 취소되고 재추첨될 수 있습니다.

10. 경품 지급
- 디지털 기프트카드는 당첨자가 확인한 이메일로 발송됩니다.
- 경품은 현금으로 대체할 수 없으며, 타인에게 양도할 수 없습니다.
- 기프트카드 발행사의 이용 조건이 별도로 적용될 수 있습니다.

11. 세금
- 경품과 관련한 모든 세금은 당첨자 본인의 책임입니다.
- 경품 가치가 $600 이상인 경우 세금 신고를 위해 당첨자의 세금 관련 정보를 요청할 수 있습니다.

12. 개인정보
- 당첨 시 당첨자의 닉네임이 사이트에 공개 표시됩니다. 그 외 개인정보는 개인정보처리방침에 따라 처리됩니다.

13. 책임의 제한
- 참가자는 참가로써 본 규칙에 동의한 것으로 봅니다.
- 주최자는 통신 장애, 시스템 오류, 지연, 분실 등 주최자의 합리적 통제 범위를 벗어난 사유로 인한 손해에 대해 책임지지 않습니다.
- 부정한 방법(다중 계정, 자동화 도구 등)으로 참가한 것이 확인되면 참가 및 당첨이 무효 처리될 수 있습니다.
- 법률로 금지되거나 제한되는 지역에서는 무효입니다 (Void where prohibited).

14. 규칙의 변경
- 주최자는 필요한 경우 본 규칙을 변경할 수 있으며, 변경 내용은 이 페이지에 게시됩니다. 이미 시작된 이벤트의 핵심 조건은 참가자에게 불리하게 변경하지 않습니다.

15. 문의
- 본 규칙 및 경품 추첨에 관한 문의는 사이트 하단의 문의처로 연락해 주시기 바랍니다.
TXT;
    }

    /** 현재 저장값(없으면 기본값) */
    private function current(): array
    {
        $rows = SiteSetting::whereIn('key', [self::KEY_TITLE, self::KEY_CONTENT, self::KEY_VERSION])->get()->keyBy('key');
        $contentRow = $rows->get(self::KEY_CONTENT);

        $title = trim((string) ($rows->get(self::KEY_TITLE)?->value ?? ''));
        $content = (string) ($contentRow?->value ?? '');
        if ($content === '') {
            $content = self::defaultContent();
        }

        return [
            'title' => $title !== '' ? $title : self::defaultTitle(),
            'content' => $content,
            'updated_at' => $contentRow?->updated_at?->toIso8601String(),
            'version' => (int) ($rows->get(self::KEY_VERSION)?->value ?? 1) ?: 1,
        ];
    }

    /** 공개: GET /api/sweepstakes-rules */
    public function show()
    {
        return response()->json(['success' => true, 'data' => $this->current()]);
    }

    private function requireSuperAdmin(): void
    {
        if (auth()->user()?->role !== 'super_admin') {
            abort(response()->json(['success' => false, 'message' => '경품 추첨 규칙 관리는 사이트 최고관리자만 접근할 수 있습니다'], 403));
        }
    }

    /** 관리자: GET /api/admin/sweepstakes-rules */
    public function adminShow()
    {
        $this->requireSuperAdmin();

        return response()->json(['success' => true, 'data' => $this->current()]);
    }

    /** 관리자: PUT /api/admin/sweepstakes-rules  {title, content} */
    public function adminUpdate(Request $request)
    {
        $this->requireSuperAdmin();

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string|max:200000',
        ]);

        $prev = $this->current();
        SiteSetting::updateOrCreate(['key' => self::KEY_TITLE], ['value' => $data['title'], 'group' => 'sweepstakes']);
        SiteSetting::updateOrCreate(['key' => self::KEY_CONTENT], ['value' => $data['content'], 'group' => 'sweepstakes']);
        SiteSetting::updateOrCreate(['key' => self::KEY_VERSION], ['value' => (string) ($prev['version'] + 1), 'group' => 'sweepstakes']);

        return response()->json(['success' => true, 'message' => '공식 규칙이 저장되었습니다', 'data' => $this->current()]);
    }
}
