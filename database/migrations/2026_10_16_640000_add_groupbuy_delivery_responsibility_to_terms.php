<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 이용약관 제26조의2 에 "택배·배송·교환·반품·환불·세금·분쟁은 개설자 책임" 문구와 공동구매 범위(기본/전국) 문구를 추가한다 (2026-10-16, 사용자 결정).
// 앵커 문장이 그대로 있고 아직 추가 문구가 없을 때만 넣는다(여러 번 돌려도 안전). 관리자가 고친 본문은 건드리지 않는다.
return new class extends Migration
{
    private const ANCHOR = '대금의 수령과 환불은 개설자가 책임집니다(제21조).</li>';
    private const MARK = '택배·배송비';

    private const ADD = <<<'HTML'

<li>택배용 공동구매의 배송, 배송비, 교환·반품, 환불, 판매에 따른 세금과 분쟁 처리는 모두 개설자가 스스로 책임지며, 회사는 이에 대해 책임을 지지 않습니다.</li>
<li>공동구매의 범위는 개설자가 속한 주와 이에 맞닿은 주를 포함하는 기본 범위와 전국으로 나뉩니다. 전국 공동구매는 사업자 회원만 열 수 있고, 회사가 정한 범위·수령 방식(현재 쿠폰용)에 한해 운영하며, 회사의 사전 승인을 받아야 합니다.</li>
HTML;

    /** 순수 변환 */
    public function addClause(string $html): string
    {
        if (str_contains($html, self::MARK) || substr_count($html, self::ANCHOR) !== 1) return $html;
        return str_replace(self::ANCHOR, self::ANCHOR . self::ADD, $html);
    }

    public function up(): void
    {
        if (!Schema::hasTable('site_settings')) return;
        $row = DB::table('site_settings')->where('key', 'terms_page')->first();
        if (!$row || !is_string($row->value)) return;
        $new = $this->addClause($row->value);
        if ($new === $row->value) return;
        DB::table('site_settings')->where('key', 'terms_page')->update(['value' => $new]);
        Cache::forget('site_settings_public');   // 공개 설정 캐시
    }

    public function down(): void
    {
        // 법적 문서라 자동으로 되돌리지 않는다
    }
};
