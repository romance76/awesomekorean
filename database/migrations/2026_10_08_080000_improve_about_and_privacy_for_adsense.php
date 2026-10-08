<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 애드센스 심사 준비:
//  1) 소개(About) 페이지가 238자짜리 기본 문구뿐이라, 운영 목적·서비스·정보 글 작성 기준·정정 요청 방법을 담아 보강.
//     관리자가 이미 직접 고쳐 둔 경우(기본 문구보다 길면)에는 건드리지 않는다.
//  2) 개인정보처리방침 11항의 "Google 광고 설정 페이지"에 실제 링크(adssettings.google.com)와 aboutads.info 를 추가.
//     이미 링크가 있으면 건드리지 않는다.
return new class extends Migration
{
    public function up(): void
    {
        $about = DB::table('site_settings')->where('key', 'about_page')->value('value');
        if ($about === null || mb_strlen(strip_tags((string) $about)) < 400) {
            $html = <<<'HTML'
<h2>AwesomeKorean 소개</h2>
<p>AwesomeKorean은 미국에 사는 한인들이 생활에 필요한 정보와 사람을 한곳에서 만날 수 있도록 만든 커뮤니티 플랫폼입니다. 이민·정착 초기에 겪는 막막함을 줄이고, 이웃 한인끼리 서로 돕는 공간이 되는 것이 목표입니다.</p>

<h4>제공하는 서비스</h4>
<ul>
<li><b>커뮤니티·Q&amp;A</b> — 생활 속 궁금증을 묻고 경험을 나누는 게시판</li>
<li><b>구인구직·중고장터·부동산·공동구매</b> — 지역 한인 사이의 거래와 소식</li>
<li><b>한인 업소록·NEW 소식</b> — 우리 동네 한인 업소와 신장개업·폐업정리 소식</li>
<li><b>정보</b> — 영주권, 세금, 운전면허, 신용점수, 의료보험처럼 미국 생활에서 자주 필요한 주제를 한국어로 정리한 글</li>
<li><b>뉴스·레시피·이벤트·게임·음악</b> — 일상에서 즐길 수 있는 콘텐츠</li>
</ul>

<h4>정보 글은 이렇게 만듭니다</h4>
<ul>
<li>미국 연방·주 정부와 공공기관의 공식 안내를 기준으로 주제를 고르고, 가능한 한 출처 링크를 함께 적습니다.</li>
<li>글은 AI 도구의 도움을 받아 초안을 만들고 있으며, 한인 독자가 실제로 궁금해하는 질문 중심으로 정리합니다.</li>
<li>법률·세금·이민·금융·의료에 관한 글은 일반적인 참고 자료이며 전문가 상담을 대체하지 않습니다. 제도와 금액은 수시로 바뀌므로 중요한 결정 전에는 해당 기관의 최신 공지를 꼭 확인해 주세요.</li>
<li>오류를 발견하시면 알려 주세요. 확인 후 글을 고치거나 내립니다.</li>
</ul>

<h4>광고와 수익</h4>
<p>서비스를 계속 운영하기 위해 NEW 전단 광고, 제휴 링크, 그리고 Google AdSense 같은 광고를 게재할 수 있습니다. 광고는 광고로 알아볼 수 있게 표시하며, 광고 때문에 글의 내용이 달라지지 않습니다.</p>

<h4>문의</h4>
<p>제안, 정정 요청, 광고 문의는 <a href="/contact">문의하기</a> 페이지 또는 <a href="mailto:admin@awesomekorean.com">admin@awesomekorean.com</a> 으로 보내 주세요.</p>
HTML;
            DB::table('site_settings')->updateOrInsert(['key' => 'about_page'], ['value' => $html]);
        }

        $privacy = DB::table('site_settings')->where('key', 'privacy_page')->value('value');
        if (is_string($privacy) && !str_contains($privacy, 'adssettings.google.com')) {
            $old = '이용자는 Google 광고 설정 페이지에서 맞춤 광고를 비활성화할 수 있습니다.';
            $new = '이용자는 <a href="https://adssettings.google.com" target="_blank" rel="noopener noreferrer">Google 광고 설정 페이지</a>에서 맞춤 광고를 비활성화할 수 있으며, '
                 . '<a href="https://www.aboutads.info" target="_blank" rel="noopener noreferrer">www.aboutads.info</a> 에서 제3자 공급업체의 맞춤 광고용 쿠키 사용을 거부할 수도 있습니다.';
            if (str_contains($privacy, $old)) {
                DB::table('site_settings')->where('key', 'privacy_page')->update(['value' => str_replace($old, $new, $privacy)]);
            }
        }
    }

    public function down(): void
    {
        // 사이트 문구 변경이라 되돌리지 않음
    }
};
