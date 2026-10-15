<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

// 운영하지 않는 "게임"이 이용약관·개인정보처리방침에 남아 있던 것을 지운다 (2026-10-14 사이트 점검 B-2, 사용자 승인).
//  - 제24조는 "게임 및 가상재화" → "포인트 및 가상재화"로 바꾸고, 게임·도박 관련 문장만 뺀다.
//    포인트가 현금이 아니고 환전·출금되지 않는다는 안내는 게임과 무관하게 필요해서 남긴다(조 번호도 그대로 — 다른 조항의 인용이 어긋나지 않게).
//  - 서비스 목록·가상재화 정의·금지행위·개인정보 이용 목적에 들어 있던 "게임" 단어만 뺀다.
// 관리자가 이미 고쳐 둔 문장은 건드리지 않는다(바꿀 문장이 그대로 있을 때만 바뀜). 여러 번 돌려도 안전하다.
return new class extends Migration
{
    private const REPLACEMENTS = [
        '공동구매, 실시간 채팅, 게임, 숏츠, 이벤트, 친구, 음악듣기 등' => '공동구매, 실시간 채팅, 숏츠, 이벤트, 친구, 음악듣기 등',
        '"가상재화"란 게임, 이벤트 등 서비스 내에서 지급·사용되는 포인트, 게임머니, 코인, 등급 등' => '"가상재화"란 이벤트 등 서비스 내에서 지급·사용되는 포인트, 코인, 등급 등',
        '뉴스, 레시피, 음악듣기, 숏츠, 게임, 이벤트 등 콘텐츠 서비스' => '뉴스, 레시피, 음악듣기, 숏츠, 이벤트 등 콘텐츠 서비스',
        '부정한 방법으로 포인트, 게임머니 등 가상재화를' => '부정한 방법으로 포인트 등 가상재화를',
        '제24조 (게임 및 가상재화에 관한 특칙)' => '제24조 (포인트 및 가상재화에 관한 특칙)',
        '서비스 내 참여를 유도하기 위해 미니게임과 포인트·등급 시스템을 운영할 수 있습니다. 게임 내에서 사용되는 가상재화는' => '서비스 내 참여를 유도하기 위해 포인트·등급 시스템을 운영할 수 있습니다. 서비스 내에서 사용되는 가상재화는',
        ' 본 서비스의 게임은 오락 목적으로만 제공되며 실제 도박, 사행행위에 해당하지 않습니다.' => '',
        '채팅, 친구, 게임 등 서비스 제공' => '채팅, 친구 등 서비스 제공',
    ];

    /** 순수 변환: 약관 본문에서 게임 관련 문구만 뺀다 */
    public function scrub(string $html): string
    {
        return strtr($html, self::REPLACEMENTS);
    }

    public function up(): void
    {
        if (!Schema::hasTable('site_settings')) return;
        $changed = false;
        foreach (['terms_page', 'privacy_page'] as $key) {
            $row = SiteSetting::where('key', $key)->first();
            if (!$row || !is_string($row->value)) continue;
            $new = $this->scrub($row->value);
            if ($new === $row->value) continue;
            $row->update(['value' => $new]);
            $changed = true;
        }
        if ($changed) Cache::forget('site_settings_public');   // 공개 설정 캐시 (AdminSettingsController::SETTINGS_PUBLIC_CACHE_KEY)
    }

    public function down(): void
    {
        // 법적 문서라 자동으로 되돌리지 않는다
    }
};
