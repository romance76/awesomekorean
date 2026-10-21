<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 광고 신청 한정 프로모션 (2026-10-21 요청) — 일찍 신청할수록 더 크게 깎아 주는 광고비 할인.
//   ① 가격 프로모션 3개: 11월 30일까지 90% → 12월 80% → 1월 70%  (관리자 › 가격 프로모션에서 고치거나 끌 수 있음. 광고 신청 화면의 최소 입찰가에 반영)
//   ② 공식 이벤트 1개: 이벤트 페이지(어썸코리안)에 진행 방법을 자세히 안내
//   ③ 전체창 팝업 배너 1개: PC용(가로)·휴대폰용(세로) 그림, 누르면 ②의 이벤트로 이동 (관리자 › 팝업 배너에서 끌 수 있음. 1월 31일에 저절로 끝남)
// 같은 제목이 이미 있으면 건너뛴다(여러 번 돌려도 안전). 되돌리지 않는다(데이터 보호).
return new class extends Migration
{
    private const TZ = 'America/New_York';
    private const EVENT_TITLE = '📣 광고 신청 한정 프로모션 — 광고비 최대 90% 할인';
    private const POPUP_TITLE = '광고 프로모션 전체창 (광고비 90% 할인)';

    private function et(string $s): Carbon
    {
        return Carbon::parse($s, self::TZ)->utc();
    }

    public function up(): void
    {
        $this->promotions();
        $eventId = $this->event();
        $this->popup($eventId);
    }

    /** ① 기간별 할인: 신청한 날 기준 (애틀랜타 시간) */
    private function promotions(): void
    {
        if (!Schema::hasTable('pricing_promotions')) return;
        $rows = [
            ['광고비 90% 할인 (11월 30일까지 신청)', 90, now(), $this->et('2026-11-30 23:59:59')],
            ['광고비 80% 할인 (12월 신청)', 80, $this->et('2026-12-01 00:00:00'), $this->et('2026-12-31 23:59:59')],
            ['광고비 70% 할인 (1월 신청)', 70, $this->et('2027-01-01 00:00:00'), $this->et('2027-01-31 23:59:59')],
        ];
        foreach ($rows as [$title, $pct, $from, $to]) {
            if (DB::table('pricing_promotions')->where('title', $title)->exists()) continue;
            DB::table('pricing_promotions')->insert([
                'title' => $title, 'discount_pct' => $pct,
                'applies_to_ads' => true, 'applies_to_packages' => false,   // 광고에만 (포인트 패키지 결제는 그대로)
                'starts_at' => $from, 'ends_at' => $to, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /** ② 이벤트 페이지 안내 글 */
    private function event(): int
    {
        $existing = DB::table('events')->where('title', self::EVENT_TITLE)->value('id');
        if ($existing) return (int) $existing;

        $content = <<<'TXT'
아직도 비싼 광고비 내고 계세요? 🤔
어썸코리안 광고를 지금 신청하시면, 신청 시기에 따라 광고비를 최대 90%까지 깎아 드려요.
일찍 신청할수록 더 많이 할인되는 한정 프로모션이에요!

📅 할인 일정 (신청한 날 기준 · 애틀랜타 시간)
- 11월 30일(월) 밤 12시까지 신청: 90% 할인
- 12월 1일 ~ 12월 31일 신청: 80% 할인
- 1월 1일 ~ 1월 31일 신청: 70% 할인
※ 할인율은 "광고를 신청한 날" 기준이에요. 날짜가 지나면 할인율이 내려가니 서두르세요. 2월 이후는 따로 안내드려요.

💡 어떤 할인인가요?
- 광고 신청 화면의 모든 광고 자리(이미지 광고·텍스트 광고)의 "최소 입찰가"가 정가보다 할인율만큼 낮아져요. 신청 화면에 "광고 할인 진행중"과 함께 정가가 취소선으로 같이 보여요.
- 광고는 한 달 단위로 입찰해서 자리를 배정해요(월간 경매). 같은 자리에 여러 분이 신청하면 입찰 금액이 높은 순서대로 배정돼요. 원하는 자리라면 최소 입찰가보다 조금 더 높게 넣으시면 유리해요.
- 광고비는 카드로만 결제할 수 있어요(포인트로는 신청 불가). 카드 결제에는 최소 결제 금액이 있어서, 입찰 금액이 그보다 낮으면 화면에서 올려 달라고 안내해 드려요.

📝 신청 방법
1. 어썸코리안에 로그인해요. (회원가입은 무료예요)
2. 이 글 맨 아래 [📣 광고 신청하러 가기] 버튼을 눌러 "광고 신청(월간 경매)" 화면으로 가요.
3. ① 광고가 나갈 페이지(홈·장터·부동산 등)를 고르고, ② 광고를 보여 줄 지역을 고르고, ③ 원하는 자리와 광고 종류(이미지 광고 / 텍스트 광고)를 골라요. 어느 자리가 어떻게 보이는지는 "광고 위치 확인하기"에서 미리 볼 수 있어요.
4. 광고 이미지(또는 문구)와 눌렀을 때 이동할 주소를 넣어요.
5. 입찰 금액을 확인해요. 할인이 적용된 최소 입찰가가 보이고, 그보다 높게 입찰할 수도 있어요.
6. 카드를 입력하고 [카드 확인하고 신청 완료]를 눌러요. 이때는 카드에 금액이 "보류"만 되고, 아직 청구되지 않아요.

✅ 신청한 뒤에는?
- 관리자가 광고 내용을 확인하고 승인하면 그때 카드에 청구되고 광고가 게시돼요. 광고는 한 달 단위로 나가요.
- 승인되기 전(카드 확인 대기·승인 대기)에는 "내 입찰 내역"에서 [신청 취소]를 누르면 청구 없이 취소할 수 있어요.
- 거절되면 청구되지 않아요. 승인되지 않은 카드 보류는 약 7일 안에 자동으로 풀려요.
- 신청 상태(카드 확인 대기 → 승인 대기 → 게시중)는 "내 입찰 내역"에서 언제든 볼 수 있어요.

❓ 자주 묻는 질문
Q. 90% 할인이면 정가의 10%만 내면 되나요?
A. 광고 자리마다 "최소 입찰가"가 정가의 10% 수준으로 내려가요. 실제 결제 금액은 입찰하신 금액이에요. (카드 최소 결제 금액 이상이어야 해요)
Q. 할인이 끝나기 전에 신청해야 하나요?
A. 네, 할인율은 신청한 날 기준이에요. 같은 자리라도 신청 날짜에 따라 최소 입찰가가 달라져요.
Q. 어떤 광고 자리가 있나요?
A. 신청 화면의 "광고 위치 확인하기"에서 홈과 각 페이지의 광고 자리를 미리 볼 수 있어요.
Q. 궁금한 점이 있어요.
A. 문의하기 메뉴로 편하게 물어봐 주세요.
TXT;

        return (int) DB::table('events')->insertGetId([
            'title' => self::EVENT_TITLE,
            'category' => 'community',
            'event_type' => 'awesomekorean',
            'is_pinned' => true,
            'content' => $content,
            'description' => '11월 30일까지 신청하면 광고비 90% 할인! 12월 80% · 1월 70% — 서두를수록 더 싸요. 신청 방법과 진행 과정을 자세히 알려 드려요.',
            'image_url' => '/images/banners/ad-promo-pc.webp',
            'banner_subtitle' => '11월 30일까지 90% · 12월 80% · 1월 70%',
            'banner_color' => '#EC3F78',
            'reward_points' => 0,
            'start_date' => '2026-10-21',
            'end_date' => '2027-01-31',
            'is_free' => true,
            'price' => 0,
            'organizer' => 'AwesomeKorean',
            'event_url' => '/ad-apply',
            'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** ③ 전체창 팝업 — 그림 전체를 누르면 이벤트로 이동 (✕·"오늘 하루 열지 않음"은 화면이 자동으로 붙인다) */
    private function popup(int $eventId): void
    {
        if (!Schema::hasTable('popup_banners') || !Schema::hasColumn('popup_banners', 'layout')) return;
        if (DB::table('popup_banners')->where('title', self::POPUP_TITLE)->exists()) return;

        $link = '/events?open=' . $eventId;
        $whole = [['type' => 'link', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 100, 'link' => $link, 'label' => '광고 프로모션 자세히 보기']];
        $extra = [
            'headline' => '광고비 90% 할인',
            'img_desktop' => '/images/banners/ad-promo-pc.webp',
            'img_mobile' => '/images/banners/ad-promo-m.webp',
            'hotspots_desktop' => $whole,
            'hotspots_mobile' => $whole,
            'cta_label' => '자세히 보기',
            'cta_link' => $link,
        ];
        DB::table('popup_banners')->insert([
            'title' => self::POPUP_TITLE, 'type' => 'text', 'layout' => 'fullscreen', 'content' => '',
            'extra' => json_encode($extra, JSON_UNESCAPED_UNICODE),
            'width' => 500, 'height' => 300, 'link_url' => null, 'display_mode' => 'once_per_day',
            'is_active' => true, 'start_at' => null, 'end_at' => $this->et('2027-01-31 23:59:59'), 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Cache::forget('popup_banners_public');
    }

    public function down(): void
    {
        // 데이터 보호: 지우지 않는다. 끄려면 관리자에서 비활성으로 바꾸면 된다.
    }
};
