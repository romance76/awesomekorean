<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 팝업 배너에 "전체창" 형식을 추가한다 (2026-10-17, 소프트 오픈 안내용).
//  - popup_banners.layout : modal(기존 가운데 팝업, 기본) | fullscreen(화면 전체를 덮는 안내)
//  - popup_banners.extra  : 전체창 문구(JSON) — 작은 글씨·큰 제목·부제·둘러보기 목록·안내문·버튼
// 소프트 오픈 안내 전체창 배너를 켜진 상태로 하나 만든다(사용자 요청 2026-10-17). 끄려면 관리자 › 팝업 배너에서 활성 체크를 해제하면 된다.
// 이미 칸이 있거나 같은 제목의 배너가 있으면 건너뜀(여러 번 돌려도 안전).
return new class extends Migration
{
    private const TITLE = '소프트 오픈 전체창 안내';

    public function up(): void
    {
        if (!Schema::hasTable('popup_banners')) return;

        Schema::table('popup_banners', function (Blueprint $table) {
            if (!Schema::hasColumn('popup_banners', 'layout')) $table->string('layout', 12)->default('modal')->after('type');
            if (!Schema::hasColumn('popup_banners', 'extra')) $table->json('extra')->nullable()->after('content');
        });

        if (DB::table('popup_banners')->where('title', self::TITLE)->exists()) return;

        $extra = [
            'eyebrow' => 'SOFT OPEN',
            'headline' => '어썸코리안이 문을 열었어요!',
            'subtitle' => '미국 한인 이웃과 나누고, 찾고, 함께 아끼는 커뮤니티예요. 지금은 소프트 오픈 기간이라 오픈 기념 혜택을 드려요.',
            'show_open_event' => true,
            'features_title' => '이렇게 써 보세요',
            'features_text' => "🛒|중고장터|/market|이웃과 사고팔아요
🤝|공동구매|/groupbuy|함께 사면 더 싸요
💼|구인구직|/jobs|한인 일자리를 찾아요
🏠|부동산|/realestate|렌트·매매 매물
🍜|업소록|/directory|맛집·병원·가게
💬|커뮤니티|/community|이웃과 이야기해요
❓|Q&A|/qa|궁금한 건 물어보세요
🎁|이벤트|/events|경품과 혜택
💰|머니|/money|알뜰 정보와 혜택",
            'img_desktop' => '/images/banners/soft-open-pc.jpg',
            'img_mobile' => '/images/banners/soft-open-m.jpg',
            'hotspots_desktop' => json_decode('[{"type":"close","x":95.51,"y":2.02,"w":3.23,"h":5.74},{"type":"cta","x":5.26,"y":86.61,"w":36.84,"h":6.8},{"type":"check","x":16.93,"y":94.47,"w":13.4,"h":3.08},{"type":"link","x":3.83,"y":59.51,"w":12.92,"h":7.44,"link":"/market","label":"중고장터"},{"type":"link","x":17.34,"y":59.51,"w":13.04,"h":7.44,"link":"/groupbuy","label":"공동구매"},{"type":"link","x":30.98,"y":59.51,"w":13.16,"h":7.44,"link":"/jobs","label":"구인구직"},{"type":"link","x":3.83,"y":68.01,"w":12.92,"h":7.65,"link":"/realestate","label":"부동산"},{"type":"link","x":17.34,"y":68.01,"w":13.04,"h":7.65,"link":"/directory","label":"업소록"},{"type":"link","x":30.98,"y":68.01,"w":13.16,"h":7.65,"link":"/community","label":"커뮤니티"},{"type":"link","x":3.83,"y":76.73,"w":12.92,"h":7.97,"link":"/qa","label":"Q&A"},{"type":"link","x":17.34,"y":76.73,"w":13.04,"h":7.97,"link":"/events","label":"이벤트"},{"type":"link","x":30.98,"y":76.73,"w":13.16,"h":7.97,"link":"/money","label":"머니"}]', true),
            'hotspots_mobile' => json_decode('[{"type":"cta","x":20.96,"y":89,"w":57.87,"h":7.18},{"type":"link","x":13.62,"y":50.54,"w":22.77,"h":11.24,"link":"/market","label":"중고장터"},{"type":"link","x":38.51,"y":50.54,"w":22.98,"h":11.24,"link":"/groupbuy","label":"공동구매"},{"type":"link","x":63.62,"y":50.54,"w":22.77,"h":11.24,"link":"/jobs","label":"구인구직"},{"type":"link","x":13.62,"y":63.1,"w":22.77,"h":11.36,"link":"/realestate","label":"부동산"},{"type":"link","x":38.51,"y":63.1,"w":22.98,"h":11.36,"link":"/directory","label":"업소록"},{"type":"link","x":63.62,"y":63.1,"w":22.77,"h":11.36,"link":"/community","label":"커뮤니티"},{"type":"link","x":13.62,"y":75.78,"w":22.77,"h":11.36,"link":"/qa","label":"Q&A"},{"type":"link","x":38.51,"y":75.78,"w":22.98,"h":11.36,"link":"/events","label":"이벤트"},{"type":"link","x":63.62,"y":75.78,"w":22.77,"h":11.36,"link":"/money","label":"머니"}]', true),
            'note' => '소프트 오픈 기간이라 부족한 점이 있을 수 있어요. 불편한 점이나 바라는 점은 문의하기로 알려 주세요. 함께 만들어 가요!',
            'cta_label' => '둘러보기',
            'cta_link' => '',
        ];
        DB::table('popup_banners')->insert([
            'title' => self::TITLE, 'type' => 'text', 'layout' => 'fullscreen', 'content' => '', 'extra' => json_encode($extra, JSON_UNESCAPED_UNICODE),
            'width' => 500, 'height' => 300, 'link_url' => null, 'display_mode' => 'once_per_day',
            'is_active' => true, 'start_at' => null, 'end_at' => null, 'sort_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Cache::forget('popup_banners_public');
    }

    public function down(): void
    {
        // 데이터 보호: 칸과 배너를 지우지 않는다.
    }
};
