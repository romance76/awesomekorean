<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Event;
use App\Models\User;

class SeedEvents extends Seeder
{
    public function run(): void
    {
        Event::query()->delete();
        $users = User::pluck('id')->toArray();
        $now = now();

        $events = [
            // ═══ 문화 ═══
            ['title'=>'제21회 조지아 한인 문화축제','category'=>'festival','organizer'=>'아틀란타 한인회','venue'=>'Blackburn Park','city'=>'Atlanta','state'=>'GA','start_date'=>$now->copy()->addDays(30),'end_date'=>$now->copy()->addDays(30)->addHours(8),'price'=>0,'content'=>"올해로 21회를 맞이하는 조지아 한인 문화축제!\n\n한복 패션쇼, K-POP 커버댄스, 사물놀이, 한식 부스 등\n온 가족이 함께 즐길 수 있는 축제입니다.\n\n주차 무료, 입장 무료\n오전 11시~오후 7시\n\n자원봉사자도 모집 중입니다."],
            ['title'=>'한인 미술 전시회 "우리의 이야기"','category'=>'concert','organizer'=>'Korean American Artists Association','venue'=>'Mason Fine Art Gallery','city'=>'Atlanta','state'=>'GA','start_date'=>$now->copy()->addDays(14),'end_date'=>$now->copy()->addDays(28),'price'=>0,'content'=>"재미 한인 작가 15명의 작품 전시회\n\n회화, 조각, 사진, 설치미술 등 다양한 장르\n오프닝 리셉션: 첫째 날 오후 6시~9시 (와인 제공)\n\n화~토 11AM~6PM, 월/일 휴관\n입장 무료"],
            ['title'=>'추석 대잔치 — 한인 마트 특별 이벤트','category'=>'festival','organizer'=>'H-Mart Duluth','venue'=>'H-Mart Duluth','city'=>'Duluth','state'=>'GA','start_date'=>$now->copy()->addDays(45),'price'=>0,'content'=>"추석을 맞아 H-Mart에서 특별 이벤트를 진행합니다!\n\n- 송편 만들기 체험 (선착순 50명)\n- $50 이상 구매 시 복주머니 증정\n- 한복 입고 오시면 10% 할인\n- 떡, 과일, 한과 특별 세일"],

            // ═══ 교육 ═══
            ['title'=>'미국 세금 신고 세미나 (한국어)','category'=>'education','organizer'=>'Kim CPA Group','venue'=>'Korean Community Center','city'=>'Suwanee','state'=>'GA','start_date'=>$now->copy()->addDays(20),'end_date'=>$now->copy()->addDays(20)->addHours(3),'price'=>0,'content'=>"한인을 위한 세금 신고 무료 세미나\n\n내용:\n- 개인 세금 신고 기본\n- 자영업자 세금 절약 팁\n- 한국 소득 미국 신고 방법\n- 부동산 투자 세금 처리\n\n사전 등록 필수 (50석 한정)\n한국어 진행, Q&A 시간 포함"],
            ['title'=>'SAT 만점 전략 학부모 설명회','category'=>'education','organizer'=>'아이비리그 학원','venue'=>'Gwinnett Public Library','city'=>'Duluth','state'=>'GA','start_date'=>$now->copy()->addDays(10),'end_date'=>$now->copy()->addDays(10)->addHours(2),'price'=>0,'content'=>"SAT 1550+ 달성 학생들의 공부법 공유\n\n- SAT 시험 최신 트렌드\n- 영역별 고득점 전략\n- 대학 입시 타임라인\n- 장학금 정보\n\n학부모+학생 함께 참석 가능\n무료, 사전 등록 권장"],
            ['title'=>'부동산 투자 입문 강좌','category'=>'education','organizer'=>'Korean Realty Group','venue'=>'Zoom Online','city'=>'','state'=>'','start_date'=>$now->copy()->addDays(7),'end_date'=>$now->copy()->addDays(7)->addHours(2),'price'=>20,'content'=>"미국 부동산 투자 첫걸음\n\n- 첫 집 구매 vs 투자용\n- 모기지 종류 및 자격 요건\n- 조지아 부동산 시장 현황\n- 세금 혜택 활용법\n\nZoom 온라인 진행\n참가비 $20 (자료집 포함)"],

            // ═══ 네트워킹 ═══
            ['title'=>'한인 창업가 네트워킹 밋업','category'=>'community','organizer'=>'K-Startup Atlanta','venue'=>'WeWork Buckhead','city'=>'Atlanta','state'=>'GA','start_date'=>$now->copy()->addDays(5),'end_date'=>$now->copy()->addDays(5)->addHours(3),'price'=>10,'content'=>"아틀란타 한인 창업가/예비 창업자 네트워킹\n\n라이트닝 토크 3명 (각 10분)\n자유 네트워킹 + 음료 제공\n\n참가비 $10 (음료+간식 포함)\n명함 준비해오세요!\n\n매월 첫째 목요일 정기 모임"],
            ['title'=>'한인 여성 프로페셔널 모임','category'=>'community','organizer'=>'Korean Women in Business','venue'=>'Caffé Bene','city'=>'Duluth','state'=>'GA','start_date'=>$now->copy()->addDays(12),'price'=>0,'content'=>"한인 여성 직장인/사업가 정기 모임\n\n이번 주제: 워라밸과 커리어 성장\n\n편안한 분위기에서 커피 한잔하며\n서로의 경험을 나눠요\n\n매월 둘째 토요일 오전 10시\n참가비 무료 (음료 개인 부담)"],

            // ═══ 스포츠 ═══
            ['title'=>'한인 골프 토너먼트 2026','category'=>'sports','organizer'=>'아틀란타 한인 골프회','venue'=>'Trophy Club of Atlanta','city'=>'Alpharetta','state'=>'GA','start_date'=>$now->copy()->addDays(35),'price'=>120,'content'=>"제15회 아틀란타 한인 골프 대회\n\n4인 스크램블 방식\n참가비 $120 (그린피+카트+점심+상품)\n\n시상:\n- 우승팀: $500 상품권\n- 롱기스트: $100\n- 니어핀: $100\n\n선착순 80명 마감\n비가 와도 진행합니다 (우천시 일정 변경 없음)"],
            ['title'=>'한인 배드민턴 동호회 오픈 매치','category'=>'sports','organizer'=>'Atlanta K-Badminton','venue'=>'Peachtree Badminton Center','city'=>'Chamblee','state'=>'GA','start_date'=>$now->copy()->addDays(3),'end_date'=>$now->copy()->addDays(3)->addHours(3),'price'=>5,'content'=>"매주 토요일 오전 배드민턴 오픈 매치\n\n초보~중급 환영\n라켓 없어도 빌려드려요\n참가비 $5 (코트비 분담)\n\n9AM~12PM, 복식 위주\n운동 후 점심 같이 드실 분 환영"],

            // ═══ 음식 ═══
            ['title'=>'한인 푸드트럭 페스티벌','category'=>'festival','organizer'=>'Korean Food Truck Association','venue'=>'Centennial Olympic Park','city'=>'Atlanta','state'=>'GA','start_date'=>$now->copy()->addDays(25),'price'=>0,'content'=>"한식 푸드트럭 10대가 한자리에!\n\n떡볶이, 치킨, 비빔밥, 김밥, 붕어빵, 호떡 등\nK-POP DJ 공연\n포토존 운영\n\n입장 무료, 음식은 현장 구매\n오전 11시~오후 8시\n\n현금/카드 모두 가능"],
            ['title'=>'한국 전통주 시음회','category'=>'education','organizer'=>'Korean Wine & Spirits','venue'=>'The Painted Pin','city'=>'Atlanta','state'=>'GA','start_date'=>$now->copy()->addDays(18),'price'=>35,'content'=>"막걸리, 소주, 청주 등 한국 전통주 시음\n\n7종 시음 + 안주 페어링\n전문가 해설 (한국어+영어)\n\n참가비 $35 (시음+안주 포함)\n21세 이상만 참가 가능\n선착순 30명"],

            // ═══ 종교 ═══
            ['title'=>'부활절 연합 예배 & 에그헌트','category'=>'community','organizer'=>'아틀란타 한인교회 연합','venue'=>'새소망교회','city'=>'Suwanee','state'=>'GA','start_date'=>$now->copy()->addDays(60),'price'=>0,'content'=>"아틀란타 한인교회 연합 부활절 예배\n\n오전 10시 연합예배\n오후 12시 점심 (도시락 준비)\n오후 1시 어린이 에그헌트\n\n누구나 환영합니다!\n주차 충분"],

            // ═══ 비즈니스 ═══
            ['title'=>'아마존 FBA 셀러 스터디그룹','category'=>'education','organizer'=>'K-Commerce Club','venue'=>'Starbucks Reserve','city'=>'Duluth','state'=>'GA','start_date'=>$now->copy()->addDays(8),'price'=>0,'content'=>"아마존 FBA로 부업/창업 관심 있으신 분\n\n이번 주 주제: 상품 소싱 & 리서치\n- Jungle Scout 활용법\n- 중국 공장 직접 소싱\n- FBA 수수료 계산\n\n매주 수요일 저녁 7시\n참가 무료, 커피 개인 부담"],

            // ═══ 과거 이벤트 (지난 것들) ═══
            ['title'=>'2025 한인의 밤 갈라','category'=>'community','organizer'=>'아틀란타 한인회','venue'=>'Hyatt Regency Atlanta','city'=>'Atlanta','state'=>'GA','start_date'=>$now->copy()->subDays(30),'end_date'=>$now->copy()->subDays(30)->addHours(5),'price'=>80,'content'=>"2025 한인의 밤 갈라 디너\n\n한인 유공자 시상식\n디너 코스 + 와인\n라이브 밴드 공연\n경매 행사\n\n정장 착용 (Black Tie Optional)"],
            ['title'=>'김치 만들기 워크숍','category'=>'education','organizer'=>'Korean Cooking Club','venue'=>'Sur La Table','city'=>'Buckhead','state'=>'GA','start_date'=>$now->copy()->subDays(15),'price'=>45,'content'=>"전통 김치 만들기 체험\n\n배추김치 + 깍두기\n재료 모두 제공\n만든 김치 가져가실 수 있어요\n\n인원: 15명 한정\n참가비 $45"],
            ['title'=>'한인 마라톤 10K 대회','category'=>'sports','organizer'=>'Atlanta Korean Running Club','venue'=>'Piedmont Park','city'=>'Atlanta','state'=>'GA','start_date'=>$now->copy()->subDays(45),'price'=>30,'content'=>"제3회 한인 마라톤 10K\n\n코스: Piedmont Park 순환\n참가비 $30 (기념 티셔츠 포함)\n완주 메달 제공"],

            // ═══ 다른 도시 이벤트 ═══
            ['title'=>'LA 한인 영화제 2026','category'=>'concert','organizer'=>'Korean American Film Festival','venue'=>'CGV Cinemas LA','city'=>'Los Angeles','state'=>'CA','start_date'=>$now->copy()->addDays(40),'end_date'=>$now->copy()->addDays(44),'price'=>15,'content'=>"제8회 LA 한인 영화제\n\n한국 독립영화 12편 상영\n감독과의 대화 세션\n개막식 레드카펫\n\n일일권 $15, 전체 패스 $50\n학생 할인 50%"],
            ['title'=>'뉴욕 한인 비빔밥 페스티벌','category'=>'festival','organizer'=>'Korean Food Foundation','venue'=>'Madison Square Park','city'=>'New York','state'=>'NY','start_date'=>$now->copy()->addDays(50),'price'=>0,'content'=>"맨하탄 한복판에서 비빔밥 축제!\n\n무료 비빔밥 1000인분\n한식 쿠킹 시연\nK-POP 공연\n\n입장 무료\n오전 11시~오후 5시"],
            ['title'=>'시카고 한인 송년회','category'=>'community','organizer'=>'시카고 한인회','venue'=>'Palmer House Hilton','city'=>'Chicago','state'=>'IL','start_date'=>$now->copy()->addDays(55),'price'=>60,'content'=>"2026 시카고 한인 송년회\n\n디너 뷔페 + 공연\n경품 추첨 (에어팟, 기프트카드 등)\n한해를 마무리하는 자리\n\n참가비 $60/인\n테이블 예약 가능 (10인석 $550)"],
            ['title'=>'달라스 한인 건강박람회','category'=>'community','organizer'=>'Korean Medical Association','venue'=>'Korean Community Center','city'=>'Carrollton','state'=>'TX','start_date'=>$now->copy()->addDays(22),'price'=>0,'content'=>"한인 무료 건강검진 박람회\n\n- 혈압/혈당 측정\n- 시력 검사\n- 독감 예방접종\n- 암 검진 상담\n- 한의원 침/뜸 체험\n\n한국어 통역 제공\n오전 9시~오후 3시\n사전 등록 불필요"],
            ['title'=>'시애틀 한인 가을 하이킹','category'=>'sports','organizer'=>'Seattle Korean Hikers','venue'=>'Mt. Rainier National Park','city'=>'Seattle','state'=>'WA','start_date'=>$now->copy()->addDays(15),'price'=>10,'content'=>"가을 단풍 하이킹\n\nParadise Trail (중급, 약 8km)\n소요시간: 4~5시간\n\n준비물: 등산화, 물, 도시락, 우비\n참가비 $10 (교통비 분담)\n시애틀 출발 카풀 모집 중\n\n오전 7시 집합, 오후 5시 귀환 예정"],
            ['title'=>'휴스턴 한인 볼링 대회','category'=>'sports','organizer'=>'Houston Korean Sports Club','venue'=>'Main Event Entertainment','city'=>'Houston','state'=>'TX','start_date'=>$now->copy()->addDays(9),'price'=>25,'content'=>"가족 볼링 대회\n\n3인 1팀, 2게임\n참가비 $25/팀 (슈즈 포함)\n\n시상: 1등 $200, 2등 $100, 3등 $50\n어린이 부문 별도 시상\n가족 단위 참가 환영!"],
        ];

        foreach ($events as $e) {
            $e['user_id'] = $users[array_rand($users)];
            $e['view_count'] = rand(10, 500);
            $e['attendee_count'] = rand(0, 50);
            $e['is_active'] = true;
            $e['is_free'] = !isset($e['price']) || $e['price'] == 0;
            if (!isset($e['end_date'])) $e['end_date'] = null;
            Event::create($e);
        }

        $this->command->info('Created ' . count($events) . ' realistic events');
    }
}
