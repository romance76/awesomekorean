<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JobPost;
use App\Models\User;

class SeedJobs extends Seeder
{
    public function run(): void
    {
        // 기존 job_posts 전부 삭제
        JobPost::query()->delete();

        $users = User::pluck('id')->toArray();
        $now = now();

        $jobs = $this->getJobData();

        foreach ($jobs as $i => $j) {
            JobPost::create(array_merge($j, [
                'user_id' => $users[array_rand($users)],
                'is_active' => true,
                'view_count' => rand(5, 890),
                'created_at' => $now->copy()->subDays(rand(0, 45))->subHours(rand(0, 23)),
                'updated_at' => $now->copy()->subDays(rand(0, 10)),
            ]));
        }

        $this->command->info('Created ' . count($jobs) . ' realistic job posts');
    }

    private function getJobData(): array
    {
        // ────────── 도시별 좌표 ──────────
        $cities = [
            'LA' => ['city'=>'Los Angeles','state'=>'CA','zip'=>'90010','lat'=>34.0622,'lng'=>-118.3090],
            'LA2' => ['city'=>'Koreatown','state'=>'CA','zip'=>'90005','lat'=>34.0578,'lng'=>-118.3003],
            'Fullerton' => ['city'=>'Fullerton','state'=>'CA','zip'=>'92831','lat'=>33.8703,'lng'=>-117.9242],
            'Irvine' => ['city'=>'Irvine','state'=>'CA','zip'=>'92618','lat'=>33.6846,'lng'=>-117.8265],
            'NY' => ['city'=>'New York','state'=>'NY','zip'=>'10001','lat'=>40.7484,'lng'=>-73.9967],
            'Flushing' => ['city'=>'Flushing','state'=>'NY','zip'=>'11354','lat'=>40.7654,'lng'=>-73.8328],
            'FortLee' => ['city'=>'Fort Lee','state'=>'NJ','zip'=>'07024','lat'=>40.8509,'lng'=>-73.9712],
            'Palisades' => ['city'=>'Palisades Park','state'=>'NJ','zip'=>'07650','lat'=>40.8482,'lng'=>-73.9979],
            'Atlanta' => ['city'=>'Atlanta','state'=>'GA','zip'=>'30338','lat'=>33.7490,'lng'=>-84.3880],
            'Duluth' => ['city'=>'Duluth','state'=>'GA','zip'=>'30096','lat'=>34.0029,'lng'=>-84.1446],
            'Suwanee' => ['city'=>'Suwanee','state'=>'GA','zip'=>'30024','lat'=>34.0515,'lng'=>-84.0713],
            'Chicago' => ['city'=>'Chicago','state'=>'IL','zip'=>'60625','lat'=>41.8781,'lng'=>-87.6298],
            'Dallas' => ['city'=>'Dallas','state'=>'TX','zip'=>'75006','lat'=>32.7767,'lng'=>-96.7970],
            'Carrollton' => ['city'=>'Carrollton','state'=>'TX','zip'=>'75007','lat'=>32.9537,'lng'=>-96.8903],
            'Houston' => ['city'=>'Houston','state'=>'TX','zip'=>'77036','lat'=>29.7004,'lng'=>-95.5614],
            'Seattle' => ['city'=>'Seattle','state'=>'WA','zip'=>'98104','lat'=>47.6062,'lng'=>-122.3321],
            'Federal Way' => ['city'=>'Federal Way','state'=>'WA','zip'=>'98003','lat'=>47.3223,'lng'=>-122.3126],
            'SF' => ['city'=>'San Francisco','state'=>'CA','zip'=>'94112','lat'=>37.7749,'lng'=>-122.4194],
            'DC' => ['city'=>'Washington','state'=>'DC','zip'=>'20001','lat'=>38.9072,'lng'=>-77.0369],
            'Annandale' => ['city'=>'Annandale','state'=>'VA','zip'=>'22003','lat'=>38.8304,'lng'=>-77.1961],
            'Philly' => ['city'=>'Philadelphia','state'=>'PA','zip'=>'19107','lat'=>39.9526,'lng'=>-75.1652],
            'Denver' => ['city'=>'Denver','state'=>'CO','zip'=>'80012','lat'=>39.7392,'lng'=>-104.9903],
            'LasVegas' => ['city'=>'Las Vegas','state'=>'NV','zip'=>'89109','lat'=>36.1699,'lng'=>-115.1398],
            'Honolulu' => ['city'=>'Honolulu','state'=>'HI','zip'=>'96814','lat'=>21.3069,'lng'=>-157.8583],
        ];

        $data = [];

        // ═══════════ 요식업 (restaurant) — 35개 ═══════════
        $data[] = $this->h('restaurant','full','한식당 홀서빙 구합니다','서울정 Korean BBQ',$cities['LA'],17,22,'hourly','(213) 387-5912','hiring@seoulfood.com',"저희 서울정 LA 코리아타운점에서 홀서빙 직원을 구합니다.\n\n주 5일 근무, 오후 4시~밤 11시\n주말 필수 (금/토 포함)\n\n경력 1년 이상이면 좋지만 성실하신 분이면 교육해드립니다.\n팁 별도, 식사 제공.\n영어 기본 회화 가능하신 분.\n\n관심 있으시면 전화 또는 매장 방문해주세요.");
        $data[] = $this->h('restaurant','full','일식 스시 셰프 모집 (경력 3년↑)','오마카세 by Jun',$cities['NY'],28,42,'hourly','(917) 553-0182','jun@omakasenyc.com',"맨하탄 미드타운 오마카세 레스토랑에서 스시 셰프를 모십니다.\n\n자격요건:\n- 일식 조리 경력 3년 이상 필수\n- 사시미, 니기리, 마키 전문\n- Food Handler Certificate 소지자\n- 저녁 근무 가능 (오후 3시~자정)\n\n연봉 협의 가능, 경력에 따라 우대.\n주 5일, 건강보험 제공.\n\n포트폴리오 또는 경력 사항 이메일로 보내주세요.");
        $data[] = $this->h('restaurant','part','카페 바리스타 주말 파트 (학생 환영)','달콤 카페',$cities['Duluth'],14,17,'hourly','(678) 999-3204',null,"둘루스 H-Mart 근처 달콤 카페에서 주말 바리스타 구합니다.\n\n토/일 오전 8시~오후 3시 (협의 가능)\n에스프레소 머신 경험 있으면 좋아요.\n학생도 환영합니다!\n\n간단한 음료 제조 + 디저트 포장\n밝은 성격이면 플러스\n\n카카오톡으로 연락주셔도 됩니다 (ID: dalkom_cafe)");
        $data[] = $this->h('restaurant','full','중국집 주방장 모집','만리장성',$cities['Flushing'],25,35,'hourly','(718) 886-3301',null,"플러싱 한인타운 만리장성에서 주방장을 찾습니다.\n\n짜장면/짬뽕/탕수육 등 한식중화 전문이신 분\n점심+저녁 피크타임 컨트롤 가능하신 분\n주 6일 (월~토), 일요일 휴무\n\n월급 $6,500~$8,000 (경력에 따라)\n숙소 제공 가능\n\n경력 5년 이상 우대합니다.\n전화 문의 환영.");
        $data[] = $this->h('restaurant','part','분식집 카운터+서빙 알바','신전떡볶이 어바인점',$cities['Irvine'],15,17,'hourly','(949) 301-8827',null,"어바인 신전떡볶이에서 카운터+서빙 파트타임 구합니다.\n\n오후 11시~저녁 8시 사이 5시간\n주 3~4일 (요일 협의)\n\n떡볶이, 튀김 등 간단한 조리 보조도 있어요.\n한국어+영어 가능하면 좋습니다.\n학생 OK, 비자 무관\n\n오셔서 면접 보셔도 됩니다.\n주소: 14120 Culver Dr, Irvine");
        $data[] = $this->h('restaurant','full','BBQ치킨 매장 매니저','BBQ Chicken',$cities['Atlanta'],18,23,'hourly','(404) 320-9912',null,"아틀란타 뷰포드하이웨이 BBQ치킨에서 매장 매니저를 구합니다.\n\n업무: 주문관리, 직원 스케줄, 재고 관리, 매출 보고\n주 5일 근무, 오전 10시~오후 8시\n영어 필수 (고객 응대)\n\n식당/패스트푸드 매니저 경험 우대\n의료보험 제공, 식사 무료\n\n이력서 이메일로 보내주세요.");
        $data[] = $this->h('restaurant','full','베이커리 제빵사 채용','파리바게뜨 시카고점',$cities['Chicago'],19,25,'hourly','(773) 248-5521','hr@parisbaguettechicago.com',"시카고 리버노스 파리바게뜨에서 제빵사를 모집합니다.\n\n새벽 근무 가능하신 분 (오전 4시 출근)\n빵/페이스트리 제조 경력 2년 이상\n한국식 빵류 (크림빵, 단팥빵 등) 경험 우대\n\nFood Handler License 필수\n주 5일, 주말 포함 로테이션\n\n건강보험/치과보험 제공\n면접 시 실기 테스트 있습니다.");
        $data[] = $this->h('restaurant','part','주말 디시워셔 급구','아리랑가든',$cities['Houston'],14,16,'hourly','(832) 661-2299',null,"휴스턴 한인타운 아리랑가든에서 주말 디시워셔 급구합니다.\n\n금/토/일 저녁 5시~11시\n시급 $14~$16 + 팁 쉐어\n식사 제공\n\n경험 없어도 됩니다. 체력 좋으신 분 환영.\n바로 시작 가능한 분 연락주세요.");
        $data[] = $this->h('restaurant','full','순대국밥집 주방 구합니다','원조할매순대국',$cities['Carrollton'],16,20,'hourly','(972) 484-7733',null,"캐롤턴 원조할매순대국에서 주방 보조 구합니다.\n\n아침 9시~오후 6시\n순대, 곱창 손질 + 국물 관리\n위생관리 철저하신 분\n\n주 6일 (일요일 휴무)\n식사 제공, 팁 있음\n\n여성분 환영합니다.\n경력자 급여 협의 가능.");
        $data[] = $this->h('restaurant','full','한인 프차 매장 직원 구함','굽네치킨 애난데일',$cities['Annandale'],16,20,'hourly','(571) 392-8815',null,"애난데일 굽네치킨에서 풀타임 직원 구합니다.\n\n치킨 조리 + 홀서빙 겸직\n점심~저녁 풀타임 (오전 11시~밤 10시 교대)\n\n치킨 조리 경험 있으면 좋지만 없어도 교육합니다.\n영어 기본 가능하면 OK\n한국 분식 좋아하시는 분 환영\n\n전화 또는 매장 방문 면접");
        $data[] = $this->h('restaurant','full','삼겹살집 서빙+그릴 담당','삼다도 LA점',$cities['LA2'],18,22,'hourly','(213) 480-1190',null,"코리아타운 삼다도에서 서빙+그릴 관리 직원 구합니다.\n\n삼겹살/갈비 그릴 세팅 + 테이블 관리\n저녁 타임 4시~12시 (바 마감까지)\n주 5일, 팁 $80~$150/일\n\n체력 좋으신 분, 서비스 마인드 있는 분\n경력 우대하지만 신입도 환영\n\n한국어 필수, 영어 가능하면 플러스");
        $data[] = $this->h('restaurant','contract','케이터링 셰프 (이벤트)','K-Party Catering',$cities['SF'],30,50,'hourly','(415) 920-3381','kpartycatering@gmail.com',"샌프란시스코 한인 케이터링 업체에서 이벤트 셰프를 구합니다.\n\n주로 주말 행사 (결혼식, 돌잔치, 기업행사)\n한식 50인분 이상 대량 조리 경험 필수\n차량 소유 필수 (장비/재료 운반)\n\n건당 $300~$500 (행사 규모에 따라)\n월 평균 6~10건\n\n관심 있으신 분 이메일로 경력 보내주세요.");
        $data[] = $this->h('restaurant','part','포장마차 스타일 야식집 알바','야식대장 시애틀',$cities['Seattle'],16,19,'hourly','(206) 432-7751',null,"시애틀 야식대장에서 저녁 알바 구합니다.\n\n오후 6시~새벽 2시 (주 3~4일)\n떡볶이, 오뎅, 순대, 어묵 등 분식류 조리보조\n야식 메뉴 좋아하시는 분 우대 ㅎㅎ\n\n시급 + 야간 수당 + 식사\n대학생 환영, 바로 시작 가능");

        // ═══════════ IT — 20개 ═══════════
        $data[] = $this->h('it','full','React/Node 풀스택 개발자','Hana Tech Solutions',$cities['LA'],95000,140000,'yearly','(213) 402-8831','careers@hanatechsol.com',"LA 한인 IT 스타트업 Hana Tech에서 풀스택 개발자를 모집합니다.\n\n필수 기술:\n- React 또는 Vue.js 2년+\n- Node.js / Express 백엔드\n- PostgreSQL 또는 MySQL\n- Git, CI/CD 경험\n\n우대:\n- TypeScript, AWS\n- 한국어+영어 bilingual\n\n연봉 $95K~$140K + RSU\n의료/치과/비전 보험, 401K 매칭\nRemote 주 2일 가능\n\n이력서+포트폴리오 이메일로 보내주세요.");
        $data[] = $this->h('it','full','iOS/Android 모바일 개발자','KoreanApp Studio',$cities['SF'],100000,150000,'yearly',null,'jobs@koreanapp.io',"한인 커뮤니티 앱 전문 스튜디오에서 모바일 개발자를 찾습니다.\n\n- Swift/Kotlin 네이티브 또는 React Native/Flutter\n- REST API 연동 경험\n- App Store/Play Store 배포 경험\n- UI/UX 감각 있는 분\n\n현재 3개 앱 운영 중, 신규 프로젝트 투입\n풀리모트 가능 (월 1회 SF 오피스 미팅)\n\n비자 스폰서 불가, OPT/H1B 소지자 환영");
        $data[] = $this->h('it','contract','워드프레스 웹사이트 제작 (프리랜서)','미주한인상공회의소',$cities['DC'],40,60,'hourly','(202) 555-1234','webmaster@kacc.org',"한인상공회의소 공식 웹사이트 리뉴얼 프로젝트\n\n- 워드프레스 커스텀 테마 제작\n- 회원 디렉토리 + 이벤트 캘린더\n- 한/영 이중언어\n- 반응형 디자인 필수\n\n예산: $5,000~$8,000\n기간: 6~8주\n\n포트폴리오 보내주시면 미팅 잡겠습니다.");
        $data[] = $this->h('it','full','한국어 가능 테크 서포트','Samsung SDS America',$cities['Dallas'],55000,75000,'yearly','(972) 761-4500','recruit@samsungsds.com',"삼성SDS 아메리카 달라스 오피스에서 테크 서포트 직원을 채용합니다.\n\n업무:\n- 한국 본사 직원 IT 지원 (VPN, 이메일, 프린터 등)\n- 사무실 네트워크/장비 관리\n- 헬프데스크 티켓 처리\n\n자격:\n- 한국어 비즈니스 레벨 필수\n- CompTIA A+ 또는 동등 경력\n- Windows/Mac 양쪽 경험\n\n복리후생 풀패키지, 한식 구내식당");
        $data[] = $this->h('it','part','SNS 마케팅 + 그래픽 디자인','K-Beauty Online',$cities['LA2'],20,30,'hourly','(323) 850-1827',null,"코리아타운 뷰티 이커머스에서 SNS 담당 파트타임 구합니다.\n\n인스타그램/틱톡 콘텐츠 제작 + 포스팅\nCanva/Photoshop으로 배너/썸네일 디자인\n주 20~25시간, 재택 가능\n\n한국 뷰티 트렌드에 관심 많은 분\nSNS 팔로워 1만+ 운영 경험 있으면 우대\n\n포트폴리오(인스타 계정 등) 보내주세요.");
        $data[] = $this->h('it','full','데이터 엔지니어 (한영 가능)','LG CNS America',$cities['Philly'],90000,130000,'yearly',null,'hr.us@lgcns.com',"LG CNS 미동부 거점 필라델피아 오피스\n\n- Python/Spark 데이터 파이프라인 구축\n- AWS (S3, Glue, Redshift) 경험\n- SQL 고급\n- 한국 본사와 커뮤니케이션 (한국어 필수)\n\n경력 3년+, 비자 스폰서 가능\n연봉 협의, 사이닝 보너스 있음\n\n이력서+LinkedIn 프로필 보내주세요.");
        $data[] = $this->h('it','full','QA 테스터 (게임)','NCSoft West',$cities['Irvine'],50000,70000,'yearly','(949) 389-1200','qa-recruit@ncwest.com',"엔씨소프트 웨스트 어바인 오피스에서 QA 테스터를 모집합니다.\n\n- 한국어 현지화 QA (번역 검수)\n- 버그 리포팅 (JIRA)\n- 게임 플레이 테스트\n- 야간/주말 빌드 테스트 가능\n\n게임 좋아하시는 분, 꼼꼼하신 분\n한국어 네이티브 + 영어 가능 필수\n\n보험/401K/게임 무료 이용");
        $data[] = $this->h('it','contract','쇼핑몰 솔루션 개발 (카페24/고도몰)','한인쇼핑넷',$cities['FortLee'],50,80,'hourly','(201) 944-2288',null,"포트리 한인쇼핑넷에서 쇼핑몰 개발자를 구합니다.\n\n카페24 또는 고도몰 스킨 커스텀 경험 필수\n- 결제 모듈 연동 (Stripe, PayPal)\n- 한국 배송 API 연동\n- 상품 대량등록 자동화\n\n프로젝트 단위 계약 (3~6개월)\n리모트 OK, 주 1회 미팅\n\n경력 + 포트폴리오 필수");
        $data[] = $this->h('it','full','ERP 컨설턴트 (SAP/Oracle)','Hyundai Motor America',$cities['Irvine'],85000,120000,'yearly',null,'careers@hyundai-us.com',"현대자동차 미국본부 어바인에서 ERP 컨설턴트를 찾습니다.\n\n- SAP MM/SD 또는 Oracle EBS 3년+\n- 한국 본사 프로세스 이해\n- 영어+한국어 비즈니스 레벨\n- 미국 딜러십 시스템 경험 우대\n\n출장 있음 (미국 내 20%)\n비자 스폰서 가능\n연봉+보너스+차량 할인");
        $data[] = $this->h('it','part','유튜브 영상편집 알바','먹방TV',$cities['Atlanta'],18,25,'hourly','(770) 331-8820',null,"한인 유튜버 먹방TV에서 영상편집 알바 구합니다.\n\nPremiere Pro 또는 Final Cut Pro 가능\n자막 작업 (한글+영어)\n주 2~3개 영상, 각 15~20분 분량\n썸네일 제작도 가능하면 좋아요\n\n재택근무, 주 15~20시간\n유튜브/틱톡 숏폼 편집 경험 우대\n\n샘플 작업물 보내주세요.");

        // ═══════════ 미용 (beauty) — 20개 ═══════════
        $data[] = $this->h('beauty','full','네일 테크니션 급구 (Atlanta)','K-Nails & Spa',$cities['Duluth'],20,28,'hourly','(678) 476-3310',null,"둘루스 K-Nails & Spa에서 네일 테크니션을 급히 구합니다.\n\n조건:\n- Georgia State Nail Tech License 필수\n- 젤네일, 아크릴, 페디큐어 가능\n- 주 5일, 화~토 근무\n\n시급 + 팁 (평균 일 $200~$300)\n건강보험 제공, 점심 제공\n\n바로 시작 가능한 분 연락주세요.\n매장 주소: Pleasant Hill Rd, Duluth");
        $data[] = $this->h('beauty','full','헤어 디자이너 (경력 우대)','이가자헤어비스 뉴욕',$cities['Flushing'],2500,5000,'monthly','(718) 939-1100','hr@leegajanyc.com',"플러싱 이가자헤어비스에서 헤어 디자이너를 모십니다.\n\n커트/펌/염색 모두 가능하신 분\n한국 미용사 자격증 + NY State License\n고객 관리 능력 좋으신 분\n\n보장 월급 + 인센티브 (매출의 40~50%)\n교육 프로그램 제공\n워크인 고객 많은 매장\n\n포트폴리오 (인스타그램 등) 있으면 보내주세요.");
        $data[] = $this->h('beauty','part','속눈썹 연장 전문가 (주 3일)','Lash Room LA',$cities['LA2'],25,35,'hourly','(213) 700-5543',null,"코리아타운 Lash Room에서 속눈썹 전문가 파트타임 구합니다.\n\n클래식/볼륨 래쉬 시술 경험 1년+\nCA Esthetician License 필수\n주 3일 (수/금/토)\n\n시급 + 팁 (하루 $150~250 가능)\n재료 지급, 편안한 분위기\n\n인스타 작업 사진 보내주시면 연락드려요.");
        $data[] = $this->h('beauty','full','피부관리사 채용','Seoul Skin Clinic',$cities['Annandale'],22,30,'hourly','(703) 256-8890',null,"애난데일 서울스킨클리닉에서 에스테티션을 모집합니다.\n\n- VA Esthetician License 필수\n- 한국식 피부관리 경험 우대 (아쿠아필, 산소필링 등)\n- 고객 상담 + 시술 + 사후관리\n\n주 5일 (화~토)\n기본급 + 인센티브\n최신 장비 보유, 교육 지원\n\n한국어+영어 모두 가능하면 좋습니다.");
        $data[] = $this->h('beauty','full','남성 전문 바버 (Barber)','K-Barber Shop',$cities['Dallas'],20,30,'hourly','(214) 905-2217',null,"달라스 캐롤턴 K-Barber Shop에서 바버를 구합니다.\n\n남성 커트 전문 (페이드, 테이퍼, 가위커트)\nTexas Barber License 필수\n한인+미국인 고객 반반\n\n의자 렌탈 또는 커미션 선택 가능\n주 5일, 일/월 휴무\n주차 무료, 깨끗한 시설\n\n관심 있으시면 매장 방문 또는 전화주세요.");
        $data[] = $this->h('beauty','full','왁싱 + 태닝 전문 직원','Glow Studio',$cities['Chicago'],19,26,'hourly','(773) 328-6612',null,"시카고 글로우스튜디오에서 왁싱/태닝 전문 직원을 구합니다.\n\n브라질리언 왁싱 경험 필수\n스프레이 태닝 가능하면 우대\nIL Esthetician License\n\n주 5일, 유연한 스케줄\n시급 + 팁 + 제품 할인\n\n편안한 분위기에서 일하실 분 환영합니다.");
        $data[] = $this->h('beauty','full','네일샵 매니저 경력직','Crystal Nails',$cities['Philly'],3500,4500,'monthly','(215) 330-7741',null,"필라델피아 Crystal Nails에서 매니저를 찾습니다.\n\n네일샵 운영 경력 3년 이상\n직원 스케줄 관리, 재고/발주\n고객 불만 처리 + VIP 관리\n영어 필수 (직원 관리 + 고객 소통)\n\n월급 + 보너스 (매출 목표 달성 시)\n주 5일, 건강보험 제공\n\n이력서 보내주세요.");
        $data[] = $this->h('beauty','part','메이크업 아티스트 (웨딩)','K-Wedding Studio',$cities['LA'],200,400,'hourly','(213) 388-2255','booking@kweddingla.com',"LA 한인 웨딩스튜디오에서 메이크업 아티스트를 구합니다.\n\n주말 웨딩 시즌 (3~10월) 집중\n한국식 웨딩 메이크업 + 헤어 세팅\n현장 출장 가능 (차량 소유 필수)\n\n건당 $200~$400 (촬영+본식)\n월 평균 8~12건\n\n포트폴리오 필수, 인스타 DM으로도 가능합니다.");
        $data[] = $this->h('beauty','full','네일 테크니션 (풀타임)','Luxury Nails & Spa',$cities['Houston'],18,25,'hourly','(713) 977-5582',null,"휴스턴 Luxury Nails에서 네일 테크니션 구합니다.\n\n젤, 딥파우더, 아크릴 모두 가능하신 분\nTX Nail Tech License\n주 5일 (월~금 또는 화~토 선택)\n\n워크인 많은 매장, 팁 좋음\n시급 + 팁 (월 $4000~$6000 가능)\n\n경험 많으신 분 우대합니다.");
        $data[] = $this->h('beauty','full','두피관리 + 탈모 클리닉 직원','모근재생센터',$cities['FortLee'],25,35,'hourly','(201) 363-8891',null,"포트리 모근재생센터에서 두피관리 전문 직원을 구합니다.\n\n두피 스케일링, LED 치료, 탈모 상담\nNJ Cosmetology License\n관련 교육 이수자 또는 경력자 우대\n\n한인 고객 90%, 한국어 필수\n주 5일, 점심 제공\n기본급 + 시술 인센티브\n\n관심 있으시면 전화주세요.");

        // ═══════════ 운전 (driving) — 15개 ═══════════
        $data[] = $this->h('driving','full','택배 드라이버 (아마존 DSP)','K-Logistics',$cities['Atlanta'],19,23,'hourly','(770) 680-3321',null,"아틀란타 K-Logistics 아마존 DSP에서 택배 드라이버를 구합니다.\n\n- 밴 제공 (Mercedes Sprinter)\n- 하루 130~180개 배달\n- 오전 8시 출발, 보통 오후 5~6시 끝\n\n조건:\n- 유효한 Georgia 운전면허\n- 21세 이상\n- 영어 기본 (네비 읽을 정도)\n- 범죄기록 없는 분\n\n주 4~5일, 건강보험 제공\n한인 디스패처 있어서 편합니다.");
        $data[] = $this->h('driving','part','우버/리프트 + 한인 공항 셔틀','코리안 라이드',$cities['Suwanee'],25,40,'hourly','(404) 551-8870',null,"수와니/둘루스 지역 한인 공항 셔틀 드라이버 구합니다.\n\n하츠필드-잭슨 공항 ↔ 귀넷 카운티\n본인 차량 사용 (SUV 또는 미니밴)\n\n한인 고객 위주, 한국어 필수\n건당 $40~$60 (공항까지)\n예약제, 스케줄 자유\n\n우버/리프트 병행 가능\n카톡 ID: koreanride_atl");
        $data[] = $this->h('driving','full','CDL 트럭 드라이버 (OTR)','한미물류',$cities['Dallas'],1200,1800,'monthly','(972) 331-7742',null,"한미물류에서 CDL Class A 장거리 드라이버를 모집합니다.\n\nOTR (Over the Road) 주 5~6일\n텍사스 ↔ 캘리포니아 ↔ 조지아 루트\n\n조건:\n- CDL Class A + 1년 경력\n- 영어 기본 (DOT 검사 대응)\n- 깨끗한 MVR\n\n주급 $1,200~$1,800 (마일리지 기반)\n건강보험, 2주 근무 후 3일 휴가\n\n한국인 디스패처 상주");
        $data[] = $this->h('driving','full','식재료 배달 드라이버','한아름마트',$cities['Palisades'],18,22,'hourly','(201) 944-0505',null,"팰리세이즈파크 한아름마트에서 배달 드라이버를 구합니다.\n\n- 냉장 트럭 운전 (회사 차량 제공)\n- 뉴저지/뉴욕 한인 식당 배달\n- 오전 6시~오후 2시\n\n주 5일, 체력 필요 (식재료 상하차)\n깨끗한 운전기록 필수\n한국어 가능하면 좋음 (한인 식당 소통)\n\n면접 시 운전면허 사본 지참");
        $data[] = $this->h('driving','part','학원 셔틀 기사 (오후)','눈높이 학원',$cities['Duluth'],15,18,'hourly','(678) 985-3345',null,"둘루스 눈높이 학원에서 오후 셔틀 기사를 구합니다.\n\n오후 2시~6시 (학교→학원→집 셔틀)\n15인승 밴 운전 (차량 제공)\n\n조건:\n- GA 운전면허 2년+\n- 아이들 좋아하시는 분\n- 귀넷 카운티 지리 아시는 분\n\n주 5일 (월~금), 주말 휴무\n학기 중만 근무 (방학 때 쉼)");
        $data[] = $this->h('driving','full','이삿짐 트럭 드라이버+무버','OK이사',$cities['LA'],20,28,'hourly','(213) 385-8800',null,"LA OK이사에서 드라이버+무버를 모집합니다.\n\n26ft 트럭 운전 + 가구/짐 운반\nDOT Medical Card 필수\n체력 좋으신 분 (매일 짐 운반)\n\n시급 $20~$28 + 팁 (고객에게 직접 받음)\n주 5~6일, 바쁜 시즌 오버타임 가능\n한인 이사 전문, 한국어 필수\n\n바로 시작 가능한 분 우대");

        // ═══════════ 판매 (retail) — 15개 ═══════════
        $data[] = $this->h('retail','part','H-Mart 캐셔 알바','H-Mart',$cities['Duluth'],14,16,'hourly','(678) 584-4400',null,"둘루스 H-Mart에서 캐셔 알바를 구합니다.\n\n주말 포함 주 3~4일\n오후 2시~밤 10시 (클로징)\n\nPOS 시스템 사용, 계산, 봉지 담기\n한국어+영어 기본 가능\n서서 일하는 거라 체력 필요\n\n학생 환영, 플렉시블 스케줄\n직원 할인 10%\n\n매장 방문 면접 (이력서 지참)");
        $data[] = $this->h('retail','full','한인마트 정육 코너 직원','갤러리아마트',$cities['Annandale'],18,24,'hourly','(703) 941-8860',null,"애난데일 갤러리아마트 정육 코너에서 직원을 구합니다.\n\n소/돼지/닭 정형 + 양념육 제조\n정육 경력 1년 이상 우대\n위생관리 철저하신 분\n\n주 6일 (일요일 휴무)\n오전 7시~오후 4시\n식사 제공, 직원 할인\n\n관심 있으시면 매장 정육 코너로 문의주세요.");
        $data[] = $this->h('retail','full','화장품 매장 뷰티 어드바이저','Amore Pacific',$cities['LA'],17,22,'hourly','(213) 480-0808','jobs@amorepacificusa.com',"아모레퍼시픽 LA 매장에서 뷰티 어드바이저를 모집합니다.\n\n설화수, 라네즈 등 브랜드 판매+상담\n고객 피부 타입 분석 + 제품 추천\n매출 목표 달성\n\n한국 화장품 지식 있으면 우대\n영어 필수 (다양한 고객층)\n기본급 + 커미션 + 제품 샘플\n\n뷰티/화장품 매장 경험 1년+ 우대");
        $data[] = $this->h('retail','part','꽃집 알바 (주말)','서울플라워',$cities['Flushing'],15,18,'hourly','(718) 461-5530',null,"플러싱 서울플라워에서 주말 알바를 구합니다.\n\n토/일 오전 8시~오후 5시\n꽃 진열, 꽃다발 포장 보조, 배달 정리\n어머니날/졸업시즌 특히 바빠요\n\n꽃 좋아하시는 분, 꼼꼼하신 분\n차량 있으면 배달도 가능 (추가 수당)\n\n매장 방문 면접 OK");
        $data[] = $this->h('retail','full','안경점 직원 (한영 가능)','Korea Optical',$cities['Chicago'],16,20,'hourly','(773) 478-2291',null,"시카고 한인 안경점에서 직원을 구합니다.\n\n- 안경 피팅, 렌즈 주문, 프레임 조정\n- 보험 클레임 처리 (교육 제공)\n- 고객 상담 (한국어+영어)\n\n안경점 경험 없어도 교육합니다.\n주 5일, 일/월 휴무\n직원 안경 무료\n\n꼼꼼하고 친절한 분 환영합니다.");
        $data[] = $this->h('retail','full','셀폰 매장 판매직','K-Mobile',$cities['Houston'],16,20,'hourly','(713) 773-2828',null,"휴스턴 K-Mobile에서 셀폰 판매 직원을 구합니다.\n\nT-Mobile/AT&T 신규가입/기변 상담\n한인 고객 대상 (한국어 필수)\n인터넷/TV 번들 세일즈 포함\n\n기본시급 + 커미션 (잘하면 월 $5000+)\n주 6일, 일요일 휴무\n\n세일즈 경험 우대, 열정 있는 분 환영");
        $data[] = $this->h('retail','part','반찬가게 포장+판매','엄마손반찬',$cities['Federal Way'],15,17,'hourly','(253) 217-8830',null,"페더럴웨이 엄마손반찬에서 파트타임 구합니다.\n\n오전 반찬 포장 + 오후 판매\n주 3~4일, 시간 협의\n\n반찬 종류 많아서 정리정돈 잘하시는 분\n한국어 필수 (한인 고객 100%)\n시식 + 점심 제공\n\n아주머니들도 많이 일하시는 편한 분위기예요.");

        // ═══════════ 사무직 (office) — 15개 ═══════════
        $data[] = $this->h('office','full','한영 통번역 (법률사무소)','Kim & Park Law',$cities['NY'],55000,75000,'yearly','(212) 302-7700','hr@kimparklaw.com',"맨하탄 김앤박 법률사무소에서 한영 통번역 직원을 구합니다.\n\n- 법률 문서 번역 (이민, 부동산, 사업)\n- 법정 통역 동행\n- 고객 상담 통역\n\n한국어 네이티브 + 영어 Fluent 필수\n법률 번역 경력 1년+ 우대\n통번역 자격증 소지자 우대\n\n주 5일, 의료보험 제공\n연봉 협의 가능");
        $data[] = $this->h('office','full','회계사무소 Bookkeeper','Seoul Accounting',$cities['Carrollton'],45000,60000,'yearly','(972) 416-8832',null,"캐롤턴 서울회계법인에서 북키퍼를 찾습니다.\n\n- QuickBooks 필수\n- A/P, A/R, Bank Reconciliation\n- 한인 소규모 비즈니스 담당\n- 한국어+영어 가능\n\n회계/경영 전공 또는 관련 경력 2년+\n주 5일 (월~금) 9AM~6PM\n\nCPA 시험 준비 중이시면 지원 가능\n연봉 + 보너스 + 보험");
        $data[] = $this->h('office','contract','부동산 에이전트 (신규 환영)','Korean Realty Group',$cities['Duluth'],null,null,null,'(678) 777-9901','join@koreanrealtyga.com',"귀넷 카운티 한인 부동산에서 에이전트를 모집합니다.\n\nGA Real Estate License 소지자 (또는 취득 예정)\n한인 매수/매도 고객 다수\n신규 환영, 멘토링 제공\n\n100% 커미션, 오피스 피 $200/월\n마케팅 지원 (명함, 온라인 광고)\n\n부동산에 관심 있으신 분 연락주세요.\n파트타임 시작도 가능합니다.");
        $data[] = $this->h('office','full','치과 프론트 데스크','한소망치과',$cities['Fullerton'],20,25,'hourly','(714) 525-3344',null,"풀러턴 한소망치과에서 프론트 데스크 직원을 구합니다.\n\n- 예약 관리, 환자 접수, 보험 확인\n- Dentrix 또는 Eaglesoft 사용 (교육 가능)\n- 한국어+영어 가능 필수\n\n치과/의료 프론트 경험 우대\n주 5일 (월~금), 토요일 격주\n건강보험, 치과치료 무료\n\n밝은 성격이면 좋겠습니다!");
        $data[] = $this->h('office','full','보험 에이전트 (State Farm)','David Kim - State Farm',$cities['Atlanta'],40000,80000,'yearly','(678) 691-2215',null,"아틀란타 데이비드김 State Farm에서 보험 에이전트를 구합니다.\n\n자동차/주택/생명보험 상담 + 판매\nGA Insurance License (취득 지원)\n한인 고객 네트워크 활용\n\n기본급 $40K + 커미션 (잘하면 $80K+)\n오피스 제공, 마케팅 지원\n보험 경험 없어도 교육합니다\n\n세일즈에 자신 있는 분 환영!");
        $data[] = $this->h('office','part','한국어 학원 사무보조','한글학교',$cities['Denver'],15,18,'hourly','(720) 301-5578',null,"덴버 한글학교에서 사무보조를 구합니다.\n\n토요일 오전 9시~오후 2시\n학생 출석 관리, 교재 정리, 학부모 소통\n행사 준비 보조\n\n대학생 환영, 교육에 관심 있는 분\n한국어 필수\n\n봉사시간 인정 가능 (학생인 경우)");

        // ═══════════ 건설 (construction) — 10개 ═══════════
        $data[] = $this->h('construction','full','인테리어 목수 (경력)','한빛건설',$cities['LA'],25,38,'hourly','(213) 388-5561',null,"LA 한빛건설에서 인테리어 목수를 구합니다.\n\n캐비닛, 몰딩, 바닥재 시공\n상업용 인테리어 경력 3년+\n본인 공구 소유 (기본 공구)\n\n주 5~6일, 현장에 따라 유동적\n시급 $25~$38 (경력에 따라)\n오버타임 1.5배\n\n차량 있으면 좋음 (현장 이동)\n관심 있으시면 전화주세요.");
        $data[] = $this->h('construction','part','페인트공 보조 (신입 가능)','코리안 페인팅',$cities['Atlanta'],16,22,'hourly','(404) 788-3312',null,"아틀란타 코리안 페인팅에서 페인트 보조를 구합니다.\n\n실내/외 페인트 작업 보조\n테이핑, 프라이머, 롤러/스프레이\n\n경험 없어도 교육합니다.\n체력 좋으신 분, 높은 곳 무서워하지 않는 분\n주 5일, 현장 위치에 따라 출발지 다름\n\n차량 있으면 우대 (교통비 지원)");
        $data[] = $this->h('construction','full','전기 기사 (Licensed Electrician)','JK Electric',$cities['Chicago'],30,45,'hourly','(773) 814-2290',null,"시카고 JK Electric에서 전기 기사를 구합니다.\n\nIllinois Licensed Electrician\n상업용/주거용 배선, 패널 교체\n신축 + 리모델링 모두\n\n시급 $30~$45 + 오버타임\n회사 차량 제공 (집에서 현장 직행)\n건강보험, 연금\n\n경력 5년+ 우대\n영어 기본 (인스펙터 대응)");
        $data[] = $this->h('construction','full','타일 시공 기사','우리타일',$cities['Houston'],22,35,'hourly','(832) 523-6614',null,"휴스턴 우리타일에서 타일 기사를 구합니다.\n\n욕실/주방/바닥 타일 시공\n대리석, 포세린, 모자이크 경험\n레벨링 + 방수 작업 가능\n\n주 5~6일, 시급 + 프로젝트 보너스\n한인 건설현장 + 미국 현장 모두\n\n경력 3년+ 필수\n본인 공구 소유 우대");
        $data[] = $this->h('construction','full','HVAC 테크니션','코리안 에어컨',$cities['Dallas'],25,40,'hourly','(214) 628-3317',null,"달라스 코리안 에어컨에서 HVAC 기사를 찾습니다.\n\nEPA 608 Certification 필수\n에어컨 설치/수리/유지보수\n주거용 위주 (한인 고객 많음)\n\n시급 $25~$40 (경력에 따라)\n회사 트럭 제공, 공구 지급\n텍사스 여름에 바빠요 (오버타임 많음)\n\n관심 있으시면 전화주세요.");

        // ═══════════ 의료 (medical) — 10개 ═══════════
        $data[] = $this->h('medical','full','치과 어시스턴트 (한국어 가능)','미소치과',$cities['LA2'],20,26,'hourly','(213) 380-1100',null,"코리아타운 미소치과에서 치과 어시스턴트를 구합니다.\n\nDA Certificate 또는 OJT 경력\n엑스레이 촬영, 석션, 기구 준비\n한국어 환자 소통 가능\n\n주 5일 (월~금), 토요일 격주\n건강보험+치과 무료\n점심 제공\n\n밝고 친절하신 분 환영합니다.");
        $data[] = $this->h('medical','full','한방 침술사 (Acupuncturist)','동의한방',$cities['Annandale'],60000,90000,'yearly','(703) 750-2280',null,"애난데일 동의한방에서 침술사를 모집합니다.\n\nVA Acupuncture License 필수\n침, 뜸, 부항, 한약 처방\n한인+미국인 환자 모두\n\n보험 빌링 경험 있으면 우대\n주 5일, 점심시간 2시간\n쾌적한 진료실, 주차 무료\n\n이력서와 License 사본 보내주세요.");
        $data[] = $this->h('medical','part','요양보호사 (Caregiver)','효도 홈케어',$cities['Flushing'],17,22,'hourly','(718) 888-3345',null,"플러싱 효도홈케어에서 요양보호사를 구합니다.\n\nHome Health Aide (HHA) Certificate\n한인 어르신 돌봄 (식사, 목욕, 외출 동행)\n퀸즈/맨하탄 지역\n\n주 3~5일, 시간 선택 가능\n야간 케이스도 있음 (수당 추가)\n한국어 필수\n\n따뜻한 마음씨 가진 분 환영합니다.");
        $data[] = $this->h('medical','full','물리치료 보조 (PTA)','서울 재활센터',$cities['Chicago'],50000,65000,'yearly','(773) 478-9901',null,"시카고 서울재활센터에서 PTA를 모집합니다.\n\nIllinois PTA License 필수\n정형외과/스포츠 재활 환자 담당\n한인 어르신 환자도 많습니다\n\n주 5일, 건강보험/치과/비전\n계속 교육 (CE) 지원\n\n한국어+영어 가능하면 우대\n새 졸업자도 환영합니다.");
        $data[] = $this->h('medical','full','약국 테크니션','한약국',$cities['Palisades'],18,23,'hourly','(201) 461-6600',null,"팰리세이즈파크 한약국에서 약국 테크니션을 구합니다.\n\nNJ Pharmacy Tech License\n처방전 입력, 약 조제 보조, 보험 처리\n한인 환자 다수 (한국어 필수)\n\n주 5일, 주말 교대\nCVS/Walgreens 경력 있으면 우대\n건강보험 제공\n\n약사 선생님이 잘 가르쳐주십니다.");

        // ═══════════ 교육 (education) — 10개 ═══════════
        $data[] = $this->h('education','part','수학 과외 선생님 (중고등)','매쓰플러스 학원',$cities['Duluth'],25,40,'hourly','(678) 205-7732',null,"둘루스 매쓰플러스에서 수학 과외 선생님을 구합니다.\n\nAlgebra ~ AP Calculus 수업 가능\n주 3~4일, 오후 4시~8시\n소그룹 수업 (3~5명)\n\n이공계 전공 대학생/졸업생\nSAT Math 750+ 또는 동등 실력\n한국어+영어 수업 가능\n\n시급 + 학생 수 보너스\n여름방학 집중반도 있습니다.");
        $data[] = $this->h('education','part','피아노 레슨 강사','서울음악원',$cities['Irvine'],35,50,'hourly','(949) 733-2215',null,"어바인 서울음악원에서 피아노 강사를 모십니다.\n\n초급~중급 학생 레슨\n주 2~4일, 시간 협의\n\n음대 졸업 또는 관련 전공\n아이들 지도 경험 있으면 우대\n리사이틀 준비 도움\n\n레슨실 제공, 악기 사용 가능\n학생 수에 따라 수입 조절 가능");
        $data[] = $this->h('education','full','영어/ESL 강사 (성인)','한인 커뮤니티센터',$cities['Atlanta'],18,25,'hourly','(770) 416-0050',null,"아틀란타 한인커뮤니티센터에서 ESL 강사를 구합니다.\n\n한인 성인 대상 영어 회화+문법 수업\n초급~중급 클래스\n주 3일 (월/수/금) 오전 10시~12시\n\nTESOL/CELTA 자격증 또는 교육 경력\n한국어 가능하면 좋음 (설명 시)\n\n교재 제공, 소규모 클래스 (10~15명)\n보람 있는 일입니다!");
        $data[] = $this->h('education','part','태권도 사범','대한태권도장',$cities['Carrollton'],20,30,'hourly','(469) 850-3318',null,"캐롤턴 대한태권도장에서 사범을 구합니다.\n\n국기원 3단 이상\n아동+성인 클래스 지도\n영어 수업 가능 (미국 학생 많음)\n\n주 4~5일, 오후 3시~8시\n주말 시합 인솔 (월 1~2회)\n\n태권도 교육에 열정 있는 분\n비자 서류 도움 가능 (상담)");
        $data[] = $this->h('education','part','SAT/ACT 영어 강사','아이비리그 학원',$cities['FortLee'],40,60,'hourly','(201) 585-2290',null,"포트리 아이비리그학원에서 SAT 영어 강사를 찾습니다.\n\nSAT Reading/Writing 전문\n자체 커리큘럼 있음 (교재 제공)\n주 2~3일, 저녁 6시~9시\n\nSAT 1500+ 또는 동등 실력\n아이비리그/명문대 출신 우대\n한국어+영어 가능\n\n시급 $40~$60 (경력에 따라)\n여름 집중반 별도 수당");

        // ═══════════ 기타 (etc) — 10개 ═══════════
        $data[] = $this->h('etc','part','반려동물 시터 (주말)','멍냥시터',$cities['Seattle'],18,25,'hourly','(206) 420-8831',null,"시애틀 멍냥시터에서 주말 펫시터를 구합니다.\n\n견주 집 방문 또는 본인 집에서 돌봄\n산책, 밥주기, 놀아주기\n\n동물 좋아하시는 분 필수!\n차량 있으면 좋음\n한인 고객 위주\n\n건당 $25~$40 (시간/크기에 따라)\n리뷰 좋으면 단골 많아져요.");
        $data[] = $this->h('etc','full','세탁소 직원 (풀타임)','클린마스터',$cities['Philly'],15,18,'hourly','(215) 574-3321',null,"필라델피아 클린마스터 세탁소에서 직원을 구합니다.\n\n세탁물 분류, 프레싱, 포장\n드라이클리닝 기계 조작 (교육 제공)\n\n주 6일 (월~토), 오전 7시~오후 4시\n경험 없어도 됩니다\n성실하고 꼼꼼하신 분\n\n식사 제공, 교통비 보조\n한국어만 가능해도 OK");
        $data[] = $this->h('etc','part','이벤트 스탭 (주말)','K-Festival Events',$cities['LasVegas'],15,20,'hourly','(702) 331-8890',null,"라스베가스 한인 이벤트 회사에서 주말 스탭을 구합니다.\n\n한인 행사, 콘서트, 페스티벌 현장 지원\n부스 설치/해체, 안내, 티켓 확인\n\n체력 좋으신 분, 밝은 성격\n행사마다 다름 (보통 6~10시간)\n\n일당 $120~$200\n재미있는 일이에요! 가수 공연도 봐요 ㅎㅎ");
        $data[] = $this->h('etc','full','교회 사무간사','새소망교회',$cities['Suwanee'],35000,45000,'yearly','(770) 831-1120',null,"수와니 새소망교회에서 사무간사를 구합니다.\n\n교회 행정 전반 (주보, 회계, 일정 관리)\n주일학교 지원\n성도 관리 시스템 운영\n\n한국어 필수, 기독교인\n워드/엑셀/파워포인트 가능\n주 5일 + 주일 근무\n\n사역에 헌신적이신 분 환영합니다.\n월~금 9AM~5PM, 주일은 별도 스케줄");
        $data[] = $this->h('etc','part','사진/영상 작가 (프리랜서)','K-Studio Photography',$cities['NY'],50,100,'hourly','(917) 770-3345','hello@kstudionyc.com',"뉴욕 K-Studio에서 프리랜서 포토그래퍼를 구합니다.\n\n한인 웨딩/돌잔치/프로필 촬영\n본인 장비 소유 (풀프레임 + 렌즈)\nLightroom/Photoshop 후보정\n\n건당 $400~$800 (촬영+편집)\n주말 위주, 스케줄 자유\n\n포트폴리오 필수!\n인스타그램이나 웹사이트 링크 보내주세요.");
        $data[] = $this->h('etc','full','자동차 정비사 (경력)','한인오토',$cities['Houston'],22,35,'hourly','(713) 270-5510',null,"휴스턴 한인오토에서 정비사를 구합니다.\n\nASE Certification 우대\n엔진, 브레이크, 전기, A/C 전반\n한국차 (현대/기아) + 일본차 위주\n\n주 5~6일, 시급 + 잡당 보너스\n리프트 4대, 깨끗한 작업환경\n공구 일부 제공\n\n경력 3년+ 우대\n한국어+영어 기본 가능");

        // ═══════════ 구직 (seeking) — 20개 ═══════════
        $data[] = $this->s('restaurant','full','주방 경력 8년, 한식/일식 모두 가능합니다',$cities['LA'],20,28,'hourly','(213) 500-8821',null,"한식당 8년 경력의 주방장입니다.\n\n한식 (찌개, 구이, 반찬류 100종+)\n일식 (스시, 사시미, 라멘)\n중화 (짜장, 짬뽕, 볶음류)\n\nFood Manager Certificate 소지\n현재 LA 거주, 즉시 출근 가능\n\n주 5~6일 근무 희망\n급여 협의, 성실하게 일하겠습니다.");
        $data[] = $this->s('beauty','full','네일 테크니션 10년차, 아틀란타 지역',$cities['Suwanee'],22,30,'hourly','(678) 820-1133',null,"네일 경력 10년차 테크니션입니다.\n\nGA State License 소지\n젤, 아크릴, 딥파우더, 아트네일 전문\n스파 페디큐어, 왁싱도 가능\n\n현재 수와니 거주\n둘루스/스와니/존스크릭 지역 선호\n풀타임 희망, 즉시 가능\n\n이전 매장 인스타: @nailby_jin");
        $data[] = $this->s('it','full','백엔드 개발자 5년 경력 (Python/Java)',$cities['SF'],110000,150000,'yearly',null,'devjung@gmail.com',"백엔드 개발자 5년차입니다.\n\n기술 스택:\n- Python (Django, FastAPI)\n- Java (Spring Boot)\n- AWS (EC2, RDS, Lambda, S3)\n- Docker, Kubernetes\n- PostgreSQL, Redis\n\n현재 H1B 소지, 트랜스퍼 가능\nSF Bay Area 또는 리모트 희망\n\nGitHub: github.com/devjung\n이력서 요청 시 이메일 주세요.");
        $data[] = $this->s('office','full','경력 5년 회계사, CPA 시험 준비 중',$cities['Dallas'],50000,65000,'yearly','(214) 339-2280',null,"회계사무소 5년 경력입니다.\n\nQuickBooks, Xero 능숙\n한인 소규모 비즈니스 세금 신고 경험\nPayroll, Sales Tax, 1099 처리\n\n달라스/캐롤턴 지역 선호\nCPA 시험 준비 중 (2과목 합격)\n주 5일 풀타임 희망\n\n성실하게 일하겠습니다. 면접 환영합니다.");
        $data[] = $this->s('driving','full','CDL Class A 3년 경력, 무사고',$cities['Houston'],1400,1800,'monthly','(832) 512-9901',null,"CDL Class A 드라이버 3년차입니다.\n\n53ft 트레일러 경험\n냉장/드라이밴 모두 가능\n깨끗한 MVR, 무사고\n\n텍사스~캘리포니아 OTR 선호\n주 5일 근무 희망\n\n바로 시작 가능합니다.\n전화 또는 문자 주세요.");
        $data[] = $this->s('construction','full','인테리어 목수 15년 경력',$cities['LA'],30,45,'hourly','(323) 997-4412',null,"인테리어 목수 15년 경력입니다.\n\n캐비닛 제작/설치\n몰딩, 트림, 문짝\n바닥재 (하드우드, LVP)\n타일도 어느 정도 가능\n\n본인 공구 + 트럭 있음\nLA/OC 지역\n\n소규모 리모델링 하청도 받습니다.\n사진 보내드릴 수 있습니다.");
        $data[] = $this->s('retail','part','대학생, 주말 알바 구합니다 (어디든)',$cities['Duluth'],14,18,'hourly','(470) 331-2215',null,"Georgia Tech 3학년 학생입니다.\n\n토/일 풀타임 가능\n평일은 오후 5시 이후 가능\n\nH-Mart, 식당, 카페 등 어디든 OK\n한국어+영어 유창\n성실합니다!\n\n둘루스/스와니/존스크릭 지역\n차량 있어요.");
        $data[] = $this->s('education','part','영어/한국어 과외 가능 (대학원생)',$cities['Chicago'],30,45,'hourly',null,'tutor.kim.chi@gmail.com',"시카고대 교육학 석사과정 중입니다.\n\nSAT Reading/Writing 지도 가능\n한국어 교육 (외국인 대상) 경험\n초등~고등 영어/국어 과외\n\n시카고 북부 또는 온라인 수업 가능\n주 3~4일, 오후 시간대\n\n학생 성적 향상 사례 있습니다.\n편하게 연락주세요!");
        $data[] = $this->s('medical','full','간호사 RN, 한국+미국 경력',$cities['Flushing'],75000,95000,'yearly','(718) 753-2280',null,"한국에서 간호사 5년 + 미국 RN 2년 경력입니다.\n\nNY State RN License\nBLS/ACLS Certification\n병원 내과 병동 + 외래 경험\n\n한인 요양원 또는 한인 내과 선호\n퀸즈/맨하탄 지역\n풀타임, 야간도 가능\n\n이력서+자격증 사본 보내드립니다.");
        $data[] = $this->s('etc','full','영상/사진 촬영 + 편집 프리랜서',$cities['NY'],40,80,'hourly','(646) 820-3317','portfolio@jhvideo.com',"뉴욕 기반 영상/사진 프리랜서입니다.\n\n웨딩, 돌잔치, 기업 행사 촬영\n유튜브/인스타 콘텐츠 제작\nDrone FAA Part 107 소지\n\n장비: Sony A7IV, DJI Air 3, Gimbal\nPremiere Pro, After Effects, Lightroom\n\n포트폴리오: jhvideo.com\n건당 또는 월 계약 모두 가능합니다.");

        // 추가 구직 10개
        $data[] = $this->s('restaurant','part','카페 바리스타 경력 2년, 주말 가능',$cities['Seattle'],16,20,'hourly','(206) 819-3321',null,"카페 바리스타 2년 경험입니다.\n\n라떼아트 가능, 에스프레소 머신 (La Marzocca, Breville)\nFood Handler Card 있음\n\n시애틀 지역, 주말+평일 오후 가능\n밝은 성격, 서비스 좋다는 리뷰 많이 받았어요\n\n커피 진심으로 좋아합니다 ☕");
        $data[] = $this->s('beauty','full','헤어 디자이너 경력 7년',$cities['LA2'],3000,5000,'monthly','(323) 900-1127',null,"서울에서 7년 + LA에서 2년 헤어 디자이너입니다.\n\nCA Cosmetology License\n커트, 펌, 염색, 매직, 클리닉 전문\n남성/여성 모두 가능\n\n코리아타운 또는 OC 지역 선호\n보장급 또는 커미션 둘 다 OK\n고객 200명+ 보유\n\n인스타: @hair_minjae");
        $data[] = $this->s('office','full','경력 3년 사무직, 엑셀/회계 가능',$cities['Annandale'],40000,55000,'yearly','(571) 485-3321',null,"한국에서 무역회사 3년 근무했습니다.\n\n엑셀 고급 (피벗, VLOOKUP, 매크로)\n기본 회계 (전표 처리, 매출 정리)\n한영 통번역 가능\n무역서류 (L/C, B/L, Invoice)\n\nVA/DC 지역 선호\n비자 문제 없습니다 (영주권자)\n\n성실하고 꼼꼼합니다.");
        $data[] = $this->s('construction','full','플러머 경력 8년',$cities['Dallas'],28,40,'hourly','(469) 770-8821',null,"배관 경력 8년차입니다.\n\n주거용/상업용 배관 설치 및 수리\n온수기 교체, 하수관 청소\nTexas Plumbing License\n\n본인 트럭 + 공구 보유\n달라스/포트워스 지역\n하청 또는 직접 수주 모두 가능\n\n전화 주시면 바로 견적 나갑니다.");
        $data[] = $this->s('medical','part','물리치료사 PT, 파트타임 찾습니다',$cities['Denver'],40,55,'hourly','(720) 581-3345',null,"물리치료사 4년 경력입니다.\n\nColorado PT License\n정형외과, 스포츠 재활 전문\n침술/건침 자격 있음\n\n주 2~3일 파트타임 희망\n덴버 메트로 지역\n\n현재 다른 곳에서 주 3일 근무 중\n추가 근무처를 찾고 있습니다.");
        $data[] = $this->s('driving','part','운전 경력 20년, 셔틀/배달 어디든',$cities['Palisades'],18,25,'hourly','(201) 850-4412',null,"운전 경력 20년 무사고입니다.\n\nNJ/NY 지역 지리 완벽\n공항 셔틀, 식재료 배달, 이삿짐 보조\n15인승 밴, 26ft 트럭 모두 가능\nClean MVR, DOT Medical Card\n\n파트타임 또는 프리랜서\n시간 유동적으로 맞출 수 있습니다.");
        $data[] = $this->s('it','contract','UI/UX 디자이너 프리랜서',$cities['LA'],50,80,'hourly',null,'uxdesign.seo@gmail.com',"UI/UX 디자이너 4년 경력입니다.\n\nFigma, Sketch, Adobe XD\n웹/앱 디자인 + 프로토타입\n사용자 리서치, 와이어프레임\n\n한인 스타트업/이커머스 경험 다수\n리모트 선호, 프로젝트 단위 계약\n\n포트폴리오: behance.net/seoux\n편하게 이메일 주세요.");
        $data[] = $this->s('etc','part','번역가, 한영/영한 전문',$cities['DC'],30,50,'hourly','(202) 821-7741',null,"한영/영한 전문 번역가입니다.\n\nATA Certified Translator\n법률, 의료, 기술 문서 전문\n통역도 가능 (법정, 비즈니스 미팅)\n\nDC/VA/MD 지역 현장 통역\n원격 번역도 수시로 받습니다\n\n워드당 $0.12~$0.18 (분야에 따라)\n긴급 건도 가능합니다.");
        $data[] = $this->s('restaurant','full','제빵사 경력 6년, 한국식+유럽식',$cities['Fullerton'],20,28,'hourly','(714) 307-2281',null,"제빵 경력 6년입니다.\n\n파리바게뜨에서 3년 + 개인 베이커리 3년\n식빵, 크로와상, 케이크, 마카롱\n한국식 빵류 (소보로, 크림빵, 단팥빵)\n\nCA Food Handler Certificate\n새벽 출근 OK (4~5시)\n\nOC/LA 지역 베이커리 찾습니다.\n작업 사진 보내드릴 수 있습니다.");
        $data[] = $this->s('education','full','유아 교육 전공, 어린이집/유치원 교사',$cities['Atlanta'],35000,45000,'yearly','(770) 831-5512',null,"유아교육학 전공, 어린이집 3년 경력입니다.\n\nGA Early Childhood Education Certificate\nCPR/First Aid 자격\n한국어+영어 이중언어 수업 가능\n\n아틀란타/귀넷 지역 한인 어린이집/유치원\n풀타임 희망\n\n아이들을 진심으로 사랑합니다.\n추천서 제출 가능합니다.");

        return $data;
    }

    // 구인 헬퍼
    private function h(string $cat, string $type, string $title, string $company, array $city, ?int $salMin, ?int $salMax, ?string $salType, ?string $phone, ?string $email, string $content): array
    {
        return [
            'post_type' => 'hiring',
            'category' => $cat,
            'type' => $type,
            'title' => $title,
            'company' => $company,
            'content' => $content,
            'salary_min' => $salMin,
            'salary_max' => $salMax,
            'salary_type' => $salType,
            'city' => $city['city'],
            'state' => $city['state'],
            'zipcode' => $city['zip'],
            'lat' => $city['lat'] + (rand(-50, 50) / 10000), // 약간의 좌표 분산
            'lng' => $city['lng'] + (rand(-50, 50) / 10000),
            'contact_phone' => $phone,
            'contact_email' => $email,
        ];
    }

    // 구직 헬퍼
    private function s(string $cat, string $type, string $title, array $city, ?int $salMin, ?int $salMax, ?string $salType, ?string $phone, ?string $email, string $content): array
    {
        return [
            'post_type' => 'seeking',
            'category' => $cat,
            'type' => $type,
            'title' => $title,
            'company' => null,
            'content' => $content,
            'salary_min' => $salMin,
            'salary_max' => $salMax,
            'salary_type' => $salType,
            'city' => $city['city'],
            'state' => $city['state'],
            'zipcode' => $city['zip'],
            'lat' => $city['lat'] + (rand(-50, 50) / 10000),
            'lng' => $city['lng'] + (rand(-50, 50) / 10000),
            'contact_phone' => $phone,
            'contact_email' => $email,
        ];
    }
}
