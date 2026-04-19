<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OfficialEventSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            [
                'title' => '🐛 버그를 잡아라! 오류 제보 이벤트',
                'category' => 'community',
                'event_type' => 'awesomekorean',
                'is_pinned' => true,
                'content' => "사이트를 이용하다가 이상한 점을 발견하셨나요?\n에러가 나거나, 클릭이 안 되거나, 화면이 이상하게 보이는 부분을\n캡처해서 올려주시면 포인트를 드립니다!\n\n참여 방법:\n1. 문제가 생긴 화면을 캡처\n2. 이 게시물에 댓글로 사진 + 설명 등록\n3. 확인 후 포인트 지급\n\n보상:\n- 처음 제보한 버그: 500 포인트\n- 이미 제보된 버그 추가 확인: 100 포인트\n- 중요 버그 (결제/로그인 등): 최대 2,000 포인트",
                'banner_subtitle' => '오류 제보하고 포인트 받기',
                'banner_color' => '#E8534A',
                'reward_points' => 500,
                'start_date' => '2025-05-01',
                'end_date' => '2025-05-31',
                'is_free' => true,
                'price' => 0,
                'organizer' => 'AwesomeKorean',
                'is_active' => true,
            ],
            [
                'title' => '🏠 부동산 리스팅 2배 포인트 이벤트',
                'category' => 'community',
                'event_type' => 'awesomekorean',
                'is_pinned' => true,
                'content' => "오픈 기념! 부동산 리스팅 등록 시 포인트 2배!\n\n이벤트 혜택:\n- 리스팅 등록 횟수 제한 없음 (기간 내 무제한)\n- 리스팅 1건 등록 시 기본 50포인트 → 100포인트 지급\n- 매매/렌트/상업용 모두 적용\n\n참여 대상:\n- 부동산 에이전트\n- 집주인 직접 등록 (FSBO)\n- 렌트 매물 올리는 일반 회원 누구든지\n\n※ 부동산 에이전트로 활동하고 싶으신 분들도 환영합니다!\n   프로필 등록 후 바로 리스팅 시작하세요.",
                'banner_subtitle' => '리스팅 무제한 + 포인트 2배 혜택',
                'banner_color' => '#3B82F6',
                'reward_points' => 100,
                'start_date' => '2025-05-01',
                'end_date' => '2025-05-31',
                'is_free' => true,
                'price' => 0,
                'organizer' => 'AwesomeKorean',
                'event_url' => '/realestate/write',
                'is_active' => true,
            ],
            [
                'title' => '🎵 사이트 즐기면서 노래도 들으세요!',
                'category' => 'community',
                'event_type' => 'awesomekorean',
                'is_pinned' => true,
                'content' => "썸코리안에서 커뮤니티 활동하면서\n좋아하는 음악도 함께 즐기세요!\n\n음악듣기 사용 방법:\n1. 상단 메뉴에서 [음악듣기] 클릭\n2. 장르 / 아티스트 검색\n3. 플레이리스트 저장해서 언제든지 재생\n4. 다른 페이지 이동해도 음악은 계속 재생됩니다 🎶\n\n이런 분들께 추천:\n- 게시판 글 읽으면서 음악 듣고 싶은 분\n- 레시피 보면서 요리할 때 음악 틀고 싶은 분\n- 채팅하면서 신나는 음악 배경으로 깔고 싶은 분",
                'banner_subtitle' => '사이트 이용 중 음악 계속 재생',
                'banner_color' => '#8B5CF6',
                'reward_points' => 0,
                'start_date' => '2025-05-01',
                'end_date' => null,
                'is_free' => true,
                'price' => 0,
                'organizer' => 'AwesomeKorean',
                'event_url' => '/music',
                'is_active' => true,
            ],
            [
                'title' => '💬 24시간 오픈 채팅방 와서 놀다 가세요!',
                'category' => 'community',
                'event_type' => 'awesomekorean',
                'is_pinned' => true,
                'content' => "언제든지 들어와서 이야기 나눠요!\n혼자 있기 심심할 때, 궁금한 거 있을 때,\n그냥 수다 떨고 싶을 때 언제든지 환영합니다 😊\n\n오픈 채팅방 특징:\n- 24시간 365일 항상 열려 있어요\n- 로그인만 하면 바로 참여 가능\n- 한국어 / 영어 모두 OK\n- 미국 생활 정보, 일상 이야기, 무엇이든 OK\n- 채팅 참여만 해도 매일 포인트 지급!\n\n채팅 참여 포인트:\n- 첫 입장: 50 포인트\n- 매일 채팅 1회 이상: 10 포인트",
                'banner_subtitle' => '24시간 언제든지 환영합니다',
                'banner_color' => '#10B981',
                'reward_points' => 50,
                'start_date' => '2025-05-01',
                'end_date' => null,
                'is_free' => true,
                'price' => 0,
                'organizer' => 'AwesomeKorean',
                'event_url' => '/chat',
                'is_active' => true,
            ],
        ];

        $eventIds = [];
        foreach ($events as $e) {
            $eventIds[] = DB::table('events')->insertGetId(array_merge($e, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // 히어로 배너 4개
        $banners = [
            ['title' => '🐛 버그를 잡아라!', 'subtitle' => '오류 제보하고 포인트 받기', 'bg_color' => '#E8534A', 'text_color' => '#FFFFFF', 'link_type' => 'event', 'event_id' => $eventIds[0], 'sort_order' => 1],
            ['title' => '🏠 부동산 포인트 2배!', 'subtitle' => '리스팅 무제한 + 포인트 2배 혜택', 'bg_color' => '#3B82F6', 'text_color' => '#FFFFFF', 'link_type' => 'event', 'event_id' => $eventIds[1], 'sort_order' => 2],
            ['title' => '🎵 음악 들으면서 놀아요!', 'subtitle' => '사이트 이용 중 음악 계속 재생', 'bg_color' => '#8B5CF6', 'text_color' => '#FFFFFF', 'link_type' => 'event', 'event_id' => $eventIds[2], 'sort_order' => 3],
            ['title' => '💬 오픈 채팅방 와서 놀아요!', 'subtitle' => '24시간 언제든지 환영합니다', 'bg_color' => '#10B981', 'text_color' => '#FFFFFF', 'link_type' => 'event', 'event_id' => $eventIds[3], 'sort_order' => 4],
        ];

        foreach ($banners as $b) {
            DB::table('hero_banners')->insert(array_merge($b, [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
