<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 10/8 에 올린 정보 글 11편에 대표 이미지를 붙인다.
// 작가·라이선스는 Openverse 에서 확인한 값(CC BY / CC BY-SA / 퍼블릭 도메인 마크)이고, 본문 맨 위에 출처를 함께 적는다.
// 제목이 같은 글만 대상으로 하고, 이미 대표 이미지가 있거나 못 찾으면 건드리지 않는다.
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('info_posts')) return;

        $rows = json_decode(<<<'JSON'
[
  {
    "title": "2026년 11월 1일 일광절약시간제 종료: 시계 1시간 되돌리는 법과 같이 챙길 것들",
    "url": "https://live.staticflickr.com/3199/3288666366_c569f7e8b0_b.jpg",
    "alt": "벽시계",
    "credit": "<p><em>사진: huzzah16, CC BY 2.0 (<a href=\"https://creativecommons.org/licenses/by/2.0/\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">라이선스</a>) · <a href=\"https://www.flickr.com/photos/31985339@N05/3288666366\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">원본 보기</a></em></p>"
  },
  {
    "title": "팁·초과근무 수당 소득공제(No Tax on Tips·Overtime) 2025~2028: 한인 근로자가 확인할 조건",
    "url": "https://live.staticflickr.com/4153/5025601209_3df1028ed6_b.jpg",
    "alt": "팁 통에 담긴 현금",
    "credit": "<p><em>사진: Dave Dugdale, CC BY-SA 2.0 (<a href=\"https://creativecommons.org/licenses/by-sa/2.0/\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">라이선스</a>) · <a href=\"https://www.flickr.com/photos/37387065@N05/5025601209\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">원본 보기</a></em></p>"
  },
  {
    "title": "주택 보험(Homeowners Insurance) 처음 가입할 때: HO-3 보장 범위와 조지아 바람·우박 공제액 주의점",
    "url": "https://live.staticflickr.com/8344/8193630365_577b617f34_b.jpg",
    "alt": "미국 주택가의 단독주택",
    "credit": "<p><em>사진: pasa47, CC BY 2.0 (<a href=\"https://creativecommons.org/licenses/by/2.0/\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">라이선스</a>) · <a href=\"https://www.flickr.com/photos/53301297@N00/8193630365\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">원본 보기</a></em></p>"
  },
  {
    "title": "조지아 HOPE 장학금: GPA 3.0, 신청 방법(GAfutures), 한인 학생이 확인할 자격 조건",
    "url": "https://live.staticflickr.com/2912/14035836847_d74d6942f8_b.jpg",
    "alt": "학위 가운을 입은 대학 졸업생들",
    "credit": "<p><em>사진: COD Newsroom, CC BY 2.0 (<a href=\"https://creativecommons.org/licenses/by/2.0/\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">라이선스</a>) · <a href=\"https://www.flickr.com/photos/41431665@N07/14035836847\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">원본 보기</a></em></p>"
  },
  {
    "title": "조지아 렌트 보증금(Security Deposit) 돌려받는 법: 30일 규정, 입주·퇴거 점검, 분쟁 대처",
    "url": "https://live.staticflickr.com/8378/8459895510_f7993ec708_b.jpg",
    "alt": "가구가 없는 빈 방의 마루와 계단",
    "credit": "<p><em>사진: brightcd, CC BY 2.0 (<a href=\"https://creativecommons.org/licenses/by/2.0/\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">라이선스</a>) · <a href=\"https://www.flickr.com/photos/96716542@N00/8459895510\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">원본 보기</a></em></p>"
  },
  {
    "title": "조지아 Peach Pass 이용 가이드: 익스프레스 레인 요금, 계정 만들기, 벌금 피하는 법",
    "url": "https://live.staticflickr.com/2327/2049159278_e83707c04c_b.jpg",
    "alt": "애틀랜타 고속도로 야경",
    "credit": "<p><em>사진: james.rintamaki, CC BY-SA 2.0 (<a href=\"https://creativecommons.org/licenses/by-sa/2.0/\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">라이선스</a>) · <a href=\"https://www.flickr.com/photos/16434424@N05/2049159278\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">원본 보기</a></em></p>"
  },
  {
    "title": "2025년 새 시민권 시험(Civics Test): 128문제 중 20문제, 12개 맞아야 합격",
    "url": "https://live.staticflickr.com/2935/14687804116_c553cd4dc4_b.jpg",
    "alt": "푸른 하늘의 미국 국기",
    "credit": "<p><em>사진: JeepersMedia, CC BY 2.0 (<a href=\"https://creativecommons.org/licenses/by/2.0/\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">라이선스</a>) · <a href=\"https://www.flickr.com/photos/39160147@N03/14687804116\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">원본 보기</a></em></p>"
  },
  {
    "title": "FDIC 예금 보호 한도 $250,000: 은행·소유 형태별로 계산하는 법과 보호되지 않는 상품",
    "url": "https://live.staticflickr.com/2278/2501885975_bbf9e858b3_b.jpg",
    "alt": "오래된 은행 건물",
    "credit": "<p><em>사진: davef3138, CC BY-SA 2.0 (<a href=\"https://creativecommons.org/licenses/by-sa/2.0/\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">라이선스</a>) · <a href=\"https://www.flickr.com/photos/26670433@N07/2501885975\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">원본 보기</a></em></p>"
  },
  {
    "title": "인터넷 요금제 비교할 때 쓰는 FCC 브로드밴드 라벨 읽는 법과 2026년 9월 규정 변경",
    "url": "https://live.staticflickr.com/8740/16199008854_2824d6e4c8_b.jpg",
    "alt": "와이파이 공유기",
    "credit": "<p><em>사진: s_pixels, CC BY 2.0 (<a href=\"https://creativecommons.org/licenses/by/2.0/\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">라이선스</a>) · <a href=\"https://www.flickr.com/photos/82701576@N03/16199008854\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">원본 보기</a></em></p>"
  },
  {
    "title": "소규모 사업자 보험 기초: 일반배상책임(GL)과 BOP, 무엇을 보장하고 무엇은 안 되나",
    "url": "https://live.staticflickr.com/5084/5276348140_da708cf2a1_b.jpg",
    "alt": "작은 가게 앞 매장 전경",
    "credit": "<p><em>사진: Mike GL, CC BY 2.0 (<a href=\"https://creativecommons.org/licenses/by/2.0/\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">라이선스</a>) · <a href=\"https://www.flickr.com/photos/49481211@N08/5276348140\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">원본 보기</a></em></p>"
  },
  {
    "title": "IRS·이민국 사칭 사기 구별법: 전화·문자 받았을 때 대처와 신고 방법",
    "url": "https://live.staticflickr.com/65535/55457118369_89ac17d821_b.jpg",
    "alt": "스팸 전화가 걸려 온 스마트폰 화면",
    "credit": "<p><em>사진: Alachua County, 퍼블릭 도메인 마크 1.0 (<a href=\"https://creativecommons.org/publicdomain/mark/1.0/\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">라이선스</a>) · <a href=\"https://www.flickr.com/photos/66143513@N03/55457118369\" rel=\"noopener noreferrer nofollow ugc\" target=\"_blank\">원본 보기</a></em></p>"
  }
]
JSON, true);

        foreach ($rows as $r) {
            $post = DB::table('info_posts')->where('title', $r['title'])->first();
            if (!$post || !empty($post->cover_image_url)) continue;

            $body = (string) $post->body;
            if (!str_starts_with(ltrim($body), '<img')) {
                $img = '<img src="' . $r['url'] . '" alt="' . htmlspecialchars($r['alt'], ENT_QUOTES, 'UTF-8') . '">';
                $body = $img . "\n" . $r['credit'] . "\n" . $body;
            }

            DB::table('info_posts')->where('id', $post->id)->update([
                'cover_image_url' => $r['url'],
                'body' => $body,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // 콘텐츠 보강이라 되돌리지 않음
    }
};
