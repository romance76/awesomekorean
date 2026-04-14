<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubBoard;
use App\Models\ClubPost;
use App\Models\User;

class SeedClubs extends Seeder
{
    public function run(): void
    {
        ClubPost::query()->delete();
        ClubBoard::query()->delete();
        ClubMember::query()->delete();
        Club::query()->delete();

        $users = User::pluck('id')->toArray();
        $now = now();

        $clubs = [
            ['name'=>'아틀란타 등산 모임','category'=>'등산','type'=>'local','description'=>"매주 토요일 아침 조지아 트레일을 함께 걸어요!\n\n초보자부터 경험자까지 모두 환영합니다.\n석서니사이드 트레일, 스톤마운틴, 블러드마운틴 등\n계절별 다양한 코스를 탐험합니다.",'rules'=>"1. 출발 10분 전 집합\n2. 쓰레기는 반드시 되가져가기\n3. 안전 장비 필수 (물, 간식, 모자)\n4. 반려견 동반 시 사전 공지",'zipcode'=>'30338','lat'=>33.749,'lng'=>-84.388,'max_members'=>50],
            ['name'=>'LA 한인 골프 클럽','category'=>'골프','type'=>'local','description'=>"LA 지역 한인 골프 동호회입니다.\n\n매월 정기 라운딩 + 분기별 미니 토너먼트\n핸디캡 무관, 골프를 사랑하는 분이면 OK!\n라운딩 후 식사 모임도 있어요.",'rules'=>"1. 정기 라운딩 노쇼 3회 시 경고\n2. 그린피/카트비 본인 부담\n3. 에티켓 필수 (슬로우 플레이 주의)\n4. 회비 월 $20",'zipcode'=>'90010','lat'=>34.052,'lng'=>-118.244,'max_members'=>40],
            ['name'=>'뉴욕 독서 모임 "책갈피"','category'=>'독서','type'=>'local','description'=>"한달에 한 권, 함께 읽고 이야기하는 모임\n\n한국 문학, 영미 문학, 자기계발 등 다양한 장르\n매월 마지막 토요일 플러싱 카페에서 모임\n읽고 싶은 책을 추천하고 투표로 결정합니다.",'rules'=>"1. 모임 전 책 완독 필수\n2. 스포일러 주의\n3. 서로 다른 의견 존중\n4. 연 회비 없음 (카페 음료 개인 부담)",'zipcode'=>'11354','lat'=>40.765,'lng'=>-73.833,'max_members'=>20],
            ['name'=>'한인 요리 연구회','category'=>'요리','type'=>'online','description'=>"한식, 양식, 일식, 중식 다양한 레시피를 공유하고\n함께 만들어보는 온라인 요리 동호회\n\n매주 수요일 저녁 Zoom으로 쿡어롱\n레시피 게시판에서 자유롭게 공유",'rules'=>"1. 쿡어롱 참여 시 재료 미리 준비\n2. 레시피 공유 시 사진 첨부\n3. 상업적 홍보 금지\n4. 즐겁게!",'lat'=>0,'lng'=>0,'max_members'=>0],
            ['name'=>'시카고 테니스 동호회','category'=>'테니스','type'=>'local','description'=>"시카고 한인 테니스 동호회입니다.\n\n매주 일요일 오전 9시 링컨파크 코트\n레벨별 매칭 (초급/중급/상급)\n연 2회 클럽 내부 토너먼트",'rules'=>"1. 코트비 분담 ($5/인)\n2. 라켓 본인 지참\n3. 4주 연속 불참 시 휴면 처리\n4. 부상 시 본인 책임",'zipcode'=>'60625','lat'=>41.878,'lng'=>-87.630,'max_members'=>30],
            ['name'=>'사진 동호회 "찰칵"','category'=>'사진','type'=>'online','description'=>"사진 찍는 것을 좋아하는 사람들의 모임\n\n매월 테마 촬영 챌린지\n서로 사진 피드백 + 후보정 팁 공유\n출사 모임 (지역별 오프라인)",'rules'=>"1. 타인 사진 비하/비방 금지\n2. 출사 시 개인 장비 관리\n3. 초상권 존중\n4. 상업적 이용 시 사전 동의",'lat'=>0,'lng'=>0,'max_members'=>0],
            ['name'=>'달라스 볼링 모임','category'=>'볼링','type'=>'local','description'=>"달라스 한인 볼링 동호회\n\n매주 금요일 저녁 7시 AMF Richardson Lanes\n초보도 환영! 볼링 치면서 친목 다져요\n팀 리그전 참가 중",'rules'=>"1. 볼링비 본인 부담 ($15~20)\n2. 깨끗한 볼링슈즈 착용\n3. 리그전 참가 시 출석 필수\n4. 뒤풀이 참여 환영",'zipcode'=>'75006','lat'=>32.777,'lng'=>-96.797,'max_members'=>24],
            ['name'=>'한인 러닝 크루 ATL','category'=>'운동','type'=>'local','description'=>"아틀란타 한인 러닝 크루!\n\n매주 화/목 저녁 6시 벨트라인 러닝\n5K~10K 다양한 페이스 그룹\n매월 1회 공식 대회 참가",'rules'=>"1. 안전 조끼/헤드램프 (야간)\n2. 페이스 무관, 본인 속도로\n3. 완주 못해도 OK\n4. 러닝 후 치맥 필수 (선택사항ㅋ)",'zipcode'=>'30338','lat'=>33.752,'lng'=>-84.365,'max_members'=>100],
            ['name'=>'게임 동호회 "파티원 모집"','category'=>'게임','type'=>'online','description'=>"PC/콘솔/모바일 게임 함께 할 동료 모집!\n\n발로란트, 롤, 배그, 오버워치, 포켓몬 등\n디스코드 서버에서 항시 파티 모집\n매주 토요일 밤 정기 게임 모임",'rules'=>"1. 욕설/인신공격 금지\n2. 핵/치트 사용 시 영구 추방\n3. 초보 배려\n4. 즐겁게 게임하자!",'lat'=>0,'lng'=>0,'max_members'=>0],
            ['name'=>'휴스턴 한인 낚시 동호회','category'=>'낚시','type'=>'local','description'=>"텍사스 걸프코스트에서 낚시하는 모임\n\n바다낚시/민물낚시 모두\n보트 공동 렌탈 (비용 분담)\n잡은 물고기로 회/매운탕 파티!",'rules'=>"1. 낚시 면허(License) 필수\n2. 보트비 동일 분담\n3. 쓰레기 투기 금지\n4. 안전 조끼 착용",'zipcode'=>'77036','lat'=>29.760,'lng'=>-95.370,'max_members'=>20],
            ['name'=>'워싱턴 DC 영화 감상 모임','category'=>'영화','type'=>'local','description'=>"한국 영화/미드/드라마를 함께 보고 이야기하는 모임\n\n매월 2회 정기 상영회 (한인 커뮤니티센터)\n최신 한국 영화 + 클래식 명작\n시청 후 토론 시간",'rules'=>"1. 상영 중 핸드폰 무음\n2. 스포일러 주의\n3. 음식 반입 OK (냄새 안 나는 것)\n4. 추천 영화 항상 환영",'zipcode'=>'20001','lat'=>38.907,'lng'=>-77.037,'max_members'=>30],
            ['name'=>'애난데일 한인 자전거 클럽','category'=>'운동','type'=>'local','description'=>"버지니아 한인 자전거 라이딩 클럽\n\n매주 일요일 아침 W&OD 트레일 라이딩\n20~50km 코스 (레벨별)\n커피숍 중간 휴식 포함",'rules'=>"1. 헬멧 필수\n2. 정비 상태 확인 후 참가\n3. 그룹 라이딩 규칙 준수\n4. 펑크 수리킷 지참",'zipcode'=>'22003','lat'=>38.830,'lng'=>-77.196,'max_members'=>25],
            ['name'=>'한인 뜨개질 모임 "따뜻한 손"','category'=>'기타','type'=>'online','description'=>"뜨개질/코바늘 하시는 분들 모여요!\n\n패턴 공유, 실 추천, 완성작 자랑\n초보자 튜토리얼 제공\n연말에 기부용 목도리 함께 만들기",'rules'=>"1. 상업적 패턴 무단 배포 금지\n2. 서로의 작품에 따뜻한 피드백\n3. 판매글은 장터 게시판에만",'lat'=>0,'lng'=>0,'max_members'=>0],
            ['name'=>'필라델피아 한인 축구 모임','category'=>'운동','type'=>'local','description'=>"필리 한인 축구 동호회\n\n매주 토요일 오전 10시 FDR Park\n11인제 풀경기\n레벨 무관, 축구화만 있으면 OK",'rules'=>"1. 축구화 필수 (운동화 불가)\n2. 양 팀 유니폼 (흰/검정) 준비\n3. 물/간식 본인 지참\n4. 거친 플레이 경고",'zipcode'=>'19107','lat'=>39.953,'lng'=>-75.165,'max_members'=>30],
            ['name'=>'시애틀 한인 하이킹 클럽','category'=>'등산','type'=>'local','description'=>"시애틀 주변 트레일 탐험!\n\n레이니어, 올림픽, 캐스케이드 산맥\n초급~상급 다양한 코스\n사계절 하이킹 (겨울엔 스노슈)",'rules'=>"1. 10 Essentials 필수 (물, 지도, 랜턴 등)\n2. 곰 스프레이 지참 (원격지)\n3. Leave No Trace 원칙\n4. 출발 전 날씨 확인",'zipcode'=>'98104','lat'=>47.606,'lng'=>-122.332,'max_members'=>40],
            ['name'=>'한인 투자 스터디 그룹','category'=>'기타','type'=>'online','description'=>"주식, 부동산, 암호화폐 투자 정보 공유\n\n매주 일요일 저녁 Zoom 스터디\n포트폴리오 리뷰, 시장 분석\n투자 서적 함께 읽기",'rules'=>"1. 투자 추천은 개인 의견 (책임 X)\n2. 특정 종목 매수/매도 강요 금지\n3. 사기/폰지 홍보 즉시 추방\n4. 정보 공유만, 투자는 자기 책임",'lat'=>0,'lng'=>0,'max_members'=>50],
            ['name'=>'귀넷 한인 육아 모임','category'=>'기타','type'=>'local','description'=>"귀넷 카운티 한인 엄마/아빠 모임\n\n플레이데이트, 정보 공유, 육아 고민 상담\n학교/학원 정보, 유아 프로그램 추천\n계절별 가족 이벤트",'rules'=>"1. 다른 양육 방식 존중\n2. 아이 사진 외부 공유 금지\n3. 상업적 홍보 자제\n4. 서로 도우며 육아하자!",'zipcode'=>'30096','lat'=>34.003,'lng'=>-84.145,'max_members'=>80],
            ['name'=>'한인 음악 밴드 "코리안 웨이브"','category'=>'음악','type'=>'local','description'=>"LA 한인 밴드! 보컬/기타/베이스/드럼/키보드\n\n매주 수요일 저녁 합주\n한국 가요 + 팝/록 커버\n한인 행사 공연 참여",'rules'=>"1. 합주실 비용 분담\n2. 악기 본인 지참\n3. 정기 공연 참여 필수\n4. 신곡 제안 환영",'zipcode'=>'90010','lat'=>34.058,'lng'=>-118.300,'max_members'=>10],
            ['name'=>'덴버 한인 스키/보드 클럽','category'=>'운동','type'=>'local','description'=>"콜로라도 스키 시즌 같이 즐겨요!\n\nVail, Breckenridge, Keystone 등\n시즌 패스 공동 구매\n초보 레슨 지원",'rules'=>"1. 안전 장비 필수 (헬멧)\n2. 카풀비 분담\n3. 리프트 대기 시 끼어들기 X\n4. 부상 시 본인 보험 처리",'zipcode'=>'80012','lat'=>39.739,'lng'=>-104.990,'max_members'=>30],
            ['name'=>'한인 보드게임 모임','category'=>'게임','type'=>'local','description'=>"아틀란타 보드게임 동호회\n\n카탄, 아줄, 스플렌더, 마피아 등\n매주 금요일 저녁 7시\n다양한 게임 보유, 새 게임 환영",'rules'=>"1. 게임 규칙 숙지 후 참여\n2. 음식/음료 OK (테이블 조심)\n3. 승부 욕심 적당히\n4. 게임 후 정리 함께",'zipcode'=>'30096','lat'=>34.006,'lng'=>-84.142,'max_members'=>16],
        ];

        $boardNames = ['자유 게시판','공지사항','모임 후기','사진 갤러리','질문/답변'];

        foreach ($clubs as $c) {
            $ownerId = $users[array_rand($users)];
            $c['user_id'] = $ownerId;
            $c['is_active'] = true;
            $c['is_public'] = true;
            $c['member_count'] = rand(5, 40);
            $club = Club::create($c);

            // 오너 멤버 추가
            ClubMember::create(['club_id'=>$club->id,'user_id'=>$ownerId,'role'=>'admin','grade'=>'owner','joined_at'=>$now->copy()->subDays(rand(30,180))]);

            // 랜덤 멤버 추가
            $memberIds = collect($users)->reject(fn($id) => $id === $ownerId)->shuffle()->take(min($club->member_count - 1, 15));
            foreach ($memberIds as $mid) {
                ClubMember::create(['club_id'=>$club->id,'user_id'=>$mid,'role'=>'member','grade'=>rand(1,10)>8?'admin':'member','joined_at'=>$now->copy()->subDays(rand(1,90))]);
            }

            // 게시판 2~3개
            $bCount = rand(2, 3);
            $selectedBoards = array_slice($boardNames, 0, $bCount);
            foreach ($selectedBoards as $i => $bName) {
                ClubBoard::create(['club_id'=>$club->id,'name'=>$bName,'sort_order'=>$i,'only_admin_post'=>$bName==='공지사항']);
            }

            // 게시글 3~8개
            $boards = ClubBoard::where('club_id', $club->id)->pluck('id')->toArray();
            $pCount = rand(3, 8);
            for ($p = 0; $p < $pCount; $p++) {
                ClubPost::create([
                    'club_id' => $club->id,
                    'board_id' => $boards[array_rand($boards)],
                    'user_id' => $memberIds->isNotEmpty() ? $memberIds->random() : $ownerId,
                    'title' => $this->randomPostTitle($c['category']),
                    'content' => $this->randomPostContent($c['category']),
                    'created_at' => $now->copy()->subDays(rand(0, 30))->subHours(rand(0, 23)),
                ]);
            }
        }

        $this->command->info('Created ' . count($clubs) . ' clubs with boards and posts');
    }

    private function randomPostTitle($cat): string
    {
        $titles = [
            '등산' => ['이번 주 토요일 참가 가능하신 분?','스톤마운틴 트레일 후기','등산화 추천 부탁드려요','다음 달 코스 투표!','오늘 풍경 사진 공유합니다'],
            '골프' => ['이번 라운딩 스코어 공유','드라이버 슬라이스 교정 팁','다음 달 토너먼트 일정','골프 연습장 추천','중고 아이언 세트 팝니다'],
            '독서' => ['이번 달 책 투표','읽고 나서 느낀 점','추천 도서 목록','모임 일정 변경 안내','서점 할인 정보'],
            '요리' => ['김치찌개 레시피 공유','에어프라이어 요리 추천','이번 주 쿡어롱 메뉴','재료 어디서 사세요?','만든 요리 자랑!'],
            '테니스' => ['이번 주 코트 예약 완료','포핸드 그립 질문','라켓 추천 부탁해요','일요일 참가 인원 확인','대회 결과 공유'],
        ];
        $list = $titles[$cat] ?? ['오늘 모임 후기','다음 일정 공지','자유 토론','질문있어요','사진 공유합니다'];
        return $list[array_rand($list)];
    }

    private function randomPostContent($cat): string
    {
        $contents = [
            "안녕하세요! 이번 주 모임 참가하실 분 댓글 달아주세요.\n날씨 좋으면 야외에서 할 예정입니다.",
            "오늘 모임 정말 즐거웠어요! 다음에도 또 만나요~\n사진은 갤러리에 올렸습니다.",
            "혹시 장비 추천해주실 수 있나요?\n초보라서 뭘 사야 할지 모르겠어요...",
            "다음 달 일정 투표 올립니다.\n편한 날짜에 투표해주세요!",
            "새로 가입했습니다! 잘 부탁드려요.\n관련 경험은 별로 없지만 열심히 배우겠습니다.",
            "오늘 참석하지 못해서 아쉽네요.\n다음에는 꼭 참가할게요!",
            "공지입니다. 장소가 변경되었습니다.\n자세한 내용은 공지사항 확인해주세요.",
        ];
        return $contents[array_rand($contents)];
    }
}
