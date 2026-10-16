<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 공동구매 개설자·참가자 정보 제공에 관한 내용을 이용약관(제26조의2)과 개인정보처리방침(제3자 제공)에 추가한다 (2026-10-16, 사용자 요청).
// 금액·요율은 확정 전이라 약관에 숫자를 쓰지 않고 "회사가 공동구매 안내 화면에 따로 정한다"로 둔다.
// 아래 삽입 위치(다음 장 제목)가 그대로 있을 때만 넣고, 이미 들어 있으면 건너뛴다(여러 번 돌려도 안전). 관리자가 고친 본문은 건드리지 않는다.
return new class extends Migration
{
    private const TERMS_MARK = '제26조의2 (공동구매 서비스에 관한 특칙)';
    private const PRIVACY_MARK = '공동구매 참가자 정보의 제공';

    private const TERMS_ANCHOR = '<h4>제7장 계약해지 및 책임</h4>';
    private const PRIVACY_ANCHOR = '<h4>5. 개인정보 처리의 위탁</h4>';

    private const TERMS_ADD = <<<'HTML'
<h3>제26조의2 (공동구매 서비스에 관한 특칙)</h3>
<ol>
<li>공동구매를 개설할 수 있는 회원은 회사, 회사가 지정한 운영자, 그리고 관련 법령에 따른 사업자 자격을 갖춘 회원으로 합니다. 회사는 공동구매 총액 등 회사가 정한 기준에 따라 개설을 자동 승인하거나, 회사의 사전 심사·승인을 거치도록 할 수 있습니다.</li>
<li>개설자는 동시에 하나의 공동구매만 열어 둘 수 있습니다. 진행 중인 공동구매가 종료되거나 취소되기 전에는 새 공동구매를 개설할 수 없습니다.</li>
<li>회사는 공동구매의 장(場)을 제공할 뿐 판매자 또는 거래의 당사자가 아니며, 상품·서비스의 제공, 품질, 대금의 수령과 환불은 개설자가 책임집니다(제21조).</li>
<li>개설자는 공동구매 개설 시 회사가 정한 보증료를, 공동구매가 완료되어 참가자 정보를 제공받기 전에 회사가 정한 기간별 수수료와 총액 구간별 수수료를 회사에 지급하여야 합니다. 이미 낸 보증료는 완료 시 수수료에서 차감됩니다. 구체적인 금액과 요율은 회사가 공동구매 안내 화면에 게시하며, 변경 시 변경 이후 개설되는 공동구매부터 적용합니다.</li>
<li>개설자가 공동구매를 개설한 뒤 진행하지 않거나 철회하는 경우, 이미 낸 보증료와 개설 관련 수수료는 환불되지 않습니다.</li>
<li>공동구매가 목표 인원에 이르지 못하고 종료된 경우에도 개설자가 이를 수락하여 완료할 수 있으며, 이때의 수수료는 종료 시점의 참가 인원과 그 인원에 적용되는 표시 가격으로 계산한 금액을 기준으로 합니다.</li>
<li>참가자는 공동구매 참여 시, 공동구매가 완료되거나 개설자가 수락하는 경우 자신의 이름(서비스 아이디), 전화번호, 이메일이 개설자에게 제공됨에 동의하여야 하며, 동의하지 않으면 참여할 수 없습니다. 회사는 완료·수락 시 참가자에게 정보가 제공되었음을 알립니다.</li>
<li>개설자는 제공받은 참가자 정보를 해당 공동구매의 이행, 안내, 문의 응대 목적으로만 이용하여야 하며, 광고·마케팅 목적의 이용, 제3자에게의 제공·판매, 목적 달성 후의 보관을 하여서는 안 됩니다. 위반 시 회사는 이용제한, 공동구매 개설 금지 등 필요한 조치를 취할 수 있으며, 위반으로 인한 책임은 개설자에게 있습니다.</li>
</ol>

HTML;

    private const PRIVACY_ADD = <<<'HTML'
<h4>공동구매 참가자 정보의 제공</h4>
<p>공동구매에 참여하는 이용자는 참여 화면에서 별도로 동의한 경우에 한하여, 해당 공동구매가 완료되거나 개설자가 수락한 때 아래 정보가 그 공동구매의 개설자에게 제공됩니다. 동의하지 않으면 공동구매에 참여할 수 없습니다.</p>
<ul>
<li>제공받는 자: 이용자가 참여한 공동구매의 개설자(사업자 또는 회사가 지정한 운영자)</li>
<li>제공하는 항목: 이름(서비스 아이디), 전화번호, 이메일</li>
<li>이용 목적: 해당 공동구매의 상품·서비스 이행, 안내 및 문의 응대</li>
<li>보유·이용기간: 해당 공동구매의 이행이 끝날 때까지. 개설자는 목적 달성 후 지체 없이 파기하여야 하며, 광고·마케팅 목적으로 이용하거나 제3자에게 제공할 수 없습니다.</li>
</ul>

HTML;

    /** 순수 변환: 앵커 앞에 추가 본문을 넣는다. 앵커가 없거나 이미 들어 있으면 그대로 돌려준다 */
    public function insertBefore(string $html, string $anchor, string $mark, string $add): string
    {
        if (str_contains($html, $mark) || substr_count($html, $anchor) !== 1) return $html;
        return str_replace($anchor, $add . $anchor, $html);
    }

    public function up(): void
    {
        if (!Schema::hasTable('site_settings')) return;
        $changed = false;
        $targets = [
            'terms_page' => [self::TERMS_ANCHOR, self::TERMS_MARK, self::TERMS_ADD],
            'privacy_page' => [self::PRIVACY_ANCHOR, self::PRIVACY_MARK, self::PRIVACY_ADD],
        ];
        foreach ($targets as $key => [$anchor, $mark, $add]) {
            $row = DB::table('site_settings')->where('key', $key)->first();
            if (!$row || !is_string($row->value)) continue;
            $new = $this->insertBefore($row->value, $anchor, $mark, $add);
            if ($new === $row->value) continue;
            DB::table('site_settings')->where('key', $key)->update(['value' => $new]);
            $changed = true;
        }
        if ($changed) Cache::forget('site_settings_public');   // 공개 설정 캐시 (AdminSettingsController::SETTINGS_PUBLIC_CACHE_KEY)
    }

    public function down(): void
    {
        // 법적 문서라 자동으로 되돌리지 않는다
    }
};
