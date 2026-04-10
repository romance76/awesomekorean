<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Board;
use App\Models\Post;
use App\Models\Comment;
use App\Models\JobPost;
use App\Models\MarketItem;
use App\Models\RealEstateListing;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\NewsCategory;
use App\Models\News;
use App\Models\RecipeCategory;
use App\Models\RecipePost;
use App\Models\Event;
use App\Models\Business;
use App\Models\BusinessReview;
use App\Models\QaCategory;
use App\Models\QaPost;
use App\Models\QaAnswer;
use App\Models\Short;
use App\Models\MusicCategory;
use App\Models\MusicTrack;
use App\Models\GroupBuy;
use App\Models\GameSetting;
use App\Models\SiteSetting;
use App\Models\ShoppingStore;
use App\Models\ShoppingDeal;

class DatabaseSeeder extends Seeder
{
    private $userIds = [];
    private $cities = [
        ['city'=>'Los Angeles','state'=>'CA','zip'=>'90010','lat'=>34.0522,'lng'=>-118.2437],
        ['city'=>'New York','state'=>'NY','zip'=>'10001','lat'=>40.7128,'lng'=>-74.0060],
        ['city'=>'Chicago','state'=>'IL','zip'=>'60625','lat'=>41.8781,'lng'=>-87.6298],
        ['city'=>'Atlanta','state'=>'GA','zip'=>'30338','lat'=>33.7490,'lng'=>-84.3880],
        ['city'=>'Dallas','state'=>'TX','zip'=>'75006','lat'=>32.7767,'lng'=>-96.7970],
        ['city'=>'Houston','state'=>'TX','zip'=>'77036','lat'=>29.7604,'lng'=>-95.3698],
        ['city'=>'Seattle','state'=>'WA','zip'=>'98104','lat'=>47.6062,'lng'=>-122.3321],
        ['city'=>'San Francisco','state'=>'CA','zip'=>'94112','lat'=>37.7749,'lng'=>-122.4194],
        ['city'=>'Washington','state'=>'DC','zip'=>'20001','lat'=>38.9072,'lng'=>-77.0369],
        ['city'=>'Philadelphia','state'=>'PA','zip'=>'19107','lat'=>39.9526,'lng'=>-75.1652],
    ];

    public function run(): void
    {
        $this->seedUsers();
        $this->seedBoards();
        $this->seedPosts();
        $this->seedComments();
        $this->seedJobs();
        $this->seedMarket();
        $this->seedRealEstate();
        $this->seedClubs();
        $this->seedNews();
        $this->seedRecipes();
        $this->seedEvents();
        $this->seedBusinesses();
        $this->seedQA();
        $this->seedShorts();
        $this->seedMusic();
        $this->seedGroupBuys();
        $this->seedGameSettings();
        $this->seedSiteSettings();
        $this->seedShopping();
        $this->seedRegionalChatRooms();
    }

    private function rCity() { return $this->cities[array_rand($this->cities)]; }
    private function rUser() { return $this->userIds[array_rand($this->userIds)]; }
    private function rDate($days = 90) { return now()->subDays(rand(0, $days)); }
    private function rLatLng($c) { return ['lat' => $c['lat'] + rand(-100,100)/10000, 'lng' => $c['lng'] + rand(-100,100)/10000]; }

    // ════════════════════════════════════════
    // 1. USERS (201명)
    // ════════════════════════════════════════
    private function seedUsers()
    {
        $admin = User::create([
            'name'=>'관리자','nickname'=>'관리자','email'=>'admin@somekorean.com',
            'password'=>Hash::make('password'),'role'=>'admin','language'=>'ko','points'=>10000,
        ]);

        $lastNames = ['김','이','박','최','정','강','조','윤','장','임','한','오','서','신','권','황','안','송','류','전'];
        $firstNames = ['민수','지영','현우','서연','준호','하나','성민','유진','태현','수빈','영호','미영','정훈','소연','동현','은지','상현','지은','병철','혜진'];
        $engNames = ['James','Sarah','David','Jenny','Michael','Grace','Kevin','Ashley','Daniel','Amy'];

        for ($i = 0; $i < 200; $i++) {
            $c = $this->cities[$i % 10];
            $isEng = rand(1,4) === 1;
            $name = $isEng
                ? $engNames[array_rand($engNames)] . ' ' . $lastNames[array_rand($lastNames)]
                : $lastNames[array_rand($lastNames)] . $firstNames[array_rand($firstNames)];

            $user = User::create([
                'name' => $name, 'nickname' => $name,
                'email' => "user{$i}@test.com",
                'password' => Hash::make('password'),
                'city'=>$c['city'],'state'=>$c['state'],'zipcode'=>$c['zip'],
                'latitude'=>$c['lat']+rand(-100,100)/10000,
                'longitude'=>$c['lng']+rand(-100,100)/10000,
                'points'=>rand(0,3000),'language'=>rand(1,5)===1?'en':'ko',
                'bio' => ['미국 생활 10년차입니다','한인 커뮤니티 활동 중','맛집 탐방이 취미','IT 종사자','주부','유학생','자영업자','은퇴자'][rand(0,7)],
                'created_at' => now()->subDays(rand(0, 180)),
            ]);
            $this->userIds[] = $user->id;
        }
        $this->userIds[] = $admin->id;
        $this->command->info('✅ 201 users');
    }

    // ════════════════════════════════════════
    // 2. BOARDS (11개)
    // ════════════════════════════════════════
    private function seedBoards()
    {
        $boards = [
            ['name'=>'자유게시판','slug'=>'free','description'=>'자유롭게 이야기하세요'],
            ['name'=>'정보공유','slug'=>'info','description'=>'유용한 정보를 공유합니다'],
            ['name'=>'생활꿀팁','slug'=>'tips','description'=>'미국 생활 꿀팁 모음'],
            ['name'=>'맛집후기','slug'=>'food','description'=>'한인 맛집 리뷰'],
            ['name'=>'여행이야기','slug'=>'travel','description'=>'여행 후기와 정보'],
            ['name'=>'자녀교육','slug'=>'education','description'=>'자녀교육 정보 공유'],
            ['name'=>'이민생활','slug'=>'immigration','description'=>'이민/비자 관련 정보'],
            ['name'=>'건강정보','slug'=>'health','description'=>'건강과 의료 정보'],
            ['name'=>'유머','slug'=>'humor','description'=>'웃긴 이야기와 짤'],
            ['name'=>'고민상담','slug'=>'advice','description'=>'고민을 나눠요'],
            ['name'=>'홍보/광고','slug'=>'promotion','description'=>'업소 홍보와 광고'],
        ];
        foreach ($boards as $i => $b) Board::create(array_merge($b, ['sort_order' => $i]));
        $this->command->info('✅ 11 boards');
    }

    // ════════════════════════════════════════
    // 3. POSTS (300개 - 실제같은 한국어)
    // ════════════════════════════════════════
    private function seedPosts()
    {
        $titles = [
            '미국에서 한국 식재료 어디서 구입하시나요?','자녀 SAT 준비 학원 추천해주세요','영주권 인터뷰 후기입니다',
            '중고차 구매할 때 꼭 확인해야 할 것들','LA 한인타운 맛집 리스트 2026','시민권 시험 공부 방법',
            '세금 신고 TurboTax vs CPA 어떤게 나을까요','한인 의사 추천 부탁드립니다','자동차 보험 최저가 찾는 법',
            '미국 학교 시스템이 너무 달라서 혼란스럽네요','이사할 때 체크리스트 공유합니다','한국 택배 보내는 가장 저렴한 방법',
            '미국 생활 10년차가 알려주는 꿀팁 모음','요즘 환율이 미쳤네요 송금은 어디로?','한인 마트 세일 정보 모음',
            '아이 학교 급식이 너무 부실해요','직장에서 인종차별 당했을때 대처법','집 리모델링 DIY 후기',
            '한국 귀국 시 꼭 사올것 리스트','처음 미국 오시는 분들께 드리는 조언','HSA 계좌 활용하는 똑똑한 방법',
            '한인 교회 추천해주세요','아파트 vs 집 어떤게 좋을까요','미국에서 김치 담그기 도전',
            '운전면허 필기시험 꿀팁','401k 얼마나 넣고 계세요?','추석인데 미국에서 한식 만들기',
            'Costco 추천 물품 리스트','어린이 한글학교 정보','미국 병원비가 너무 비싸요',
        ];
        $contents = [
            "안녕하세요! 미국 생활하면서 궁금했던 점을 여쭤봅니다.\n\n주변 한인 분들께 여쭤봤는데 다들 의견이 달라서요. 경험 있으신 분들의 조언 부탁드립니다.\n\n미리 감사합니다!",
            "오늘 정보 하나 공유합니다.\n\n최근에 알게 된 건데 정말 유용해서 다른 분들도 알면 좋을 것 같아서 올려봅니다. 저도 처음에는 몰랐는데 주변에서 알려줘서 큰 도움이 됐어요.\n\n혹시 더 좋은 방법 아시면 댓글로 알려주세요!",
            "후기 남깁니다.\n\n처음에는 걱정이 많았는데 직접 해보니까 생각보다 어렵지 않더라고요. 다만 몇 가지 주의할 점이 있어서 공유합니다.\n\n1. 미리 서류 준비를 철저히 하세요\n2. 예약은 최소 2주 전에\n3. 영어가 부담되면 통역 서비스 이용하세요\n\n도움이 되셨으면 좋겠습니다.",
            "요즘 이게 화제더라고요.\n\n한인 커뮤니티에서 많이들 이야기하시는데, 저도 경험해보니 정말 그런 것 같아요. 특히 한인분들한테는 더 유용한 정보인 것 같습니다.\n\n궁금하신 점은 편하게 물어보세요!",
            "미국 생활 꿀팁 하나 알려드릴게요!\n\n많은 분들이 모르시는 건데, 이거 하나만 알아도 연간 수백 달러를 절약할 수 있습니다. 저도 처음에 미국 왔을 때 전혀 몰랐는데 한인 선배분이 알려주셔서 큰 도움이 됐어요.\n\n다들 미국 생활 화이팅입니다! 💪",
        ];

        $boardIds = Board::pluck('id')->toArray();
        for ($i = 0; $i < 300; $i++) {
            Post::create([
                'board_id' => $boardIds[array_rand($boardIds)],
                'user_id' => $this->rUser(),
                'title' => $titles[array_rand($titles)],
                'content' => $contents[array_rand($contents)],
                'view_count' => rand(10, 800),
                'like_count' => rand(0, 60),
                'comment_count' => rand(0, 25),
                'is_pinned' => rand(1,50) === 1,
                'created_at' => $this->rDate(90),
            ]);
        }
        $this->command->info('✅ 300 posts');
    }

    // ════════════════════════════════════════
    // 4. COMMENTS (500개)
    // ════════════════════════════════════════
    private function seedComments()
    {
        $texts = [
            '좋은 정보 감사합니다!','저도 같은 경험이 있어요','정말 도움이 되네요 감사합니다',
            '혹시 더 자세한 정보 있으신가요?','저도 추천합니다 진짜 좋아요','이거 몰랐는데 유용하네요',
            '공감합니다 ㅠㅠ','완전 동의해요','참고하겠습니다 감사합니다!',
            '우와 이런 정보가 있었군요','저는 조금 다른 경험인데요...','너무 유용한 글이에요 공유 감사합니다',
            '댓글 달고 갑니다!','궁금했던 건데 감사합니다','이 방법 저도 써봤는데 진짜 좋아요',
            '한인분들 다 화이팅이에요!','ㅋㅋㅋ 재밌네요','좋은 하루 되세요!',
        ];

        $postIds = Post::pluck('id')->toArray();
        $commentIds = [];

        for ($i = 0; $i < 500; $i++) {
            $isReply = rand(1,4) === 1 && count($commentIds) > 0;
            $comment = Comment::create([
                'commentable_type' => 'App\\Models\\Post',
                'commentable_id' => $postIds[array_rand($postIds)],
                'user_id' => $this->rUser(),
                'parent_id' => $isReply ? $commentIds[array_rand($commentIds)] : null,
                'content' => $texts[array_rand($texts)],
                'like_count' => rand(0, 15),
                'created_at' => $this->rDate(60),
            ]);
            $commentIds[] = $comment->id;
        }
        $this->command->info('✅ 500 comments');
    }

    // ════════════════════════════════════════
    // 5. JOBS (200개)
    // ════════════════════════════════════════
    private function seedJobs()
    {
        $data = [
            ['title'=>'한식당 주방보조 구합니다','company'=>'서울가든','cat'=>'restaurant','type'=>'full','min'=>16,'max'=>20],
            ['title'=>'스시 셰프 경력자 모집','company'=>'사쿠라 스시','cat'=>'restaurant','type'=>'full','min'=>20,'max'=>30],
            ['title'=>'카페 바리스타 파트타임','company'=>'카페 봄','cat'=>'restaurant','type'=>'part','min'=>15,'max'=>18],
            ['title'=>'네일 테크니션 급구','company'=>'K-Beauty Nail','cat'=>'beauty','type'=>'full','min'=>18,'max'=>25],
            ['title'=>'헤어 스타일리스트 모집','company'=>'코리안 뷰티','cat'=>'beauty','type'=>'full','min'=>20,'max'=>35],
            ['title'=>'배달 드라이버 모집','company'=>'K-Express','cat'=>'driving','type'=>'part','min'=>20,'max'=>30],
            ['title'=>'셔틀 운전기사 채용','company'=>'코리안 셔틀','cat'=>'driving','type'=>'full','min'=>18,'max'=>25],
            ['title'=>'풀스택 개발자 구인','company'=>'Hana Tech','cat'=>'it','type'=>'full','min'=>80000,'max'=>130000],
            ['title'=>'모바일 앱 개발자','company'=>'K-Digital','cat'=>'it','type'=>'contract','min'=>70000,'max'=>120000],
            ['title'=>'마트 캐셔 아르바이트','company'=>'한남체인','cat'=>'retail','type'=>'part','min'=>15,'max'=>17],
            ['title'=>'의류 매장 직원','company'=>'한인 플라자','cat'=>'retail','type'=>'full','min'=>16,'max'=>20],
            ['title'=>'한영 통번역 프리랜서','company'=>'서울 CPA','cat'=>'office','type'=>'contract','min'=>25,'max'=>40],
            ['title'=>'회계사 보조 구합니다','company'=>'Kim & Associates','cat'=>'office','type'=>'full','min'=>45000,'max'=>60000],
            ['title'=>'건설 인부 일용직','company'=>'Pacific 건설','cat'=>'construction','type'=>'part','min'=>20,'max'=>30],
            ['title'=>'치과 프론트 직원','company'=>'한인 치과','cat'=>'medical','type'=>'full','min'=>18,'max'=>22],
        ];

        for ($i = 0; $i < 200; $i++) {
            $d = $data[$i % count($data)];
            $c = $this->rCity();
            $salaryType = $d['min'] > 1000 ? 'yearly' : 'hourly';

            JobPost::create([
                'user_id'=>$this->rUser(),'title'=>$d['title'],'company'=>$d['company'],
                'content'=>"저희 {$d['company']}에서 함께 일할 분을 찾습니다.\n\n[근무 조건]\n- 근무 형태: ".['full'=>'풀타임','part'=>'파트타임','contract'=>'계약직'][$d['type']]."\n- 급여: \${$d['min']}~\${$d['max']}/{$salaryType}\n- 근무지: {$c['city']}, {$c['state']}\n\n[우대사항]\n- 경력자 우대\n- 한영 이중언어 가능자\n- 성실하고 책임감 있는 분\n\n관심 있으신 분은 연락주세요!",
                'category'=>$d['cat'],'type'=>$d['type'],
                'salary_min'=>$d['min'],'salary_max'=>$d['max'],'salary_type'=>$salaryType,
                'city'=>$c['city'],'state'=>$c['state'],'zipcode'=>$c['zip'],
                'lat'=>$c['lat']+rand(-100,100)/10000,'lng'=>$c['lng']+rand(-100,100)/10000,
                'contact_phone'=>'('.rand(200,999).') '.rand(100,999).'-'.rand(1000,9999),
                'contact_email'=>'hr@'.strtolower(str_replace(' ','',$d['company'])).'.com',
                'view_count'=>rand(20,400),'expires_at'=>now()->addDays(rand(7,60)),
                'created_at'=>$this->rDate(30),
            ]);
        }
        $this->command->info('✅ 200 jobs');
    }

    // ════════════════════════════════════════
    // 6. MARKET (200개)
    // ════════════════════════════════════════
    private function seedMarket()
    {
        $items = [
            ['t'=>'아이폰 15 Pro 256GB','cat'=>'electronics','p'=>800],
            ['t'=>'삼성 55인치 4K TV','cat'=>'electronics','p'=>400],
            ['t'=>'맥북 에어 M3','cat'=>'electronics','p'=>900],
            ['t'=>'닌텐도 스위치 OLED','cat'=>'electronics','p'=>250],
            ['t'=>'이케아 소파 (2인용)','cat'=>'furniture','p'=>300],
            ['t'=>'식탁 세트 (4인용)','cat'=>'furniture','p'=>200],
            ['t'=>'퀸 사이즈 매트리스','cat'=>'furniture','p'=>350],
            ['t'=>'유아 카시트 (거의 새것)','cat'=>'baby','p'=>80],
            ['t'=>'유모차 판매합니다','cat'=>'baby','p'=>120],
            ['t'=>'자전거 (하이브리드)','cat'=>'sports','p'=>200],
            ['t'=>'골프 클럽 세트','cat'=>'sports','p'=>500],
            ['t'=>'한국 전기밥솥','cat'=>'electronics','p'=>60],
            ['t'=>'에어프라이어 팝니다','cat'=>'electronics','p'=>40],
            ['t'=>'겨울 코트 (여성 M)','cat'=>'clothing','p'=>50],
            ['t'=>'명품 가방 정품','cat'=>'clothing','p'=>600],
        ];

        for ($i = 0; $i < 200; $i++) {
            $d = $items[$i % count($items)];
            $c = $this->rCity();
            MarketItem::create([
                'user_id'=>$this->rUser(),'title'=>$d['t'].' #'.rand(1,99),
                'content'=>"상태 좋습니다. 직거래 선호합니다.\n\n구매한지 ".rand(1,24)."개월 됐고, 사용감 거의 없습니다.\n거래 장소는 {$c['city']} 주변에서 가능합니다.\n\n문자 주시면 빠른 답변 드리겠습니다!",
                'price'=>$d['p'] * (rand(50,120)/100),
                'category'=>$d['cat'],'condition'=>['new','like_new','good','fair'][rand(0,3)],
                'status'=>rand(1,10)<=8?'active':(rand(0,1)?'reserved':'sold'),
                'city'=>$c['city'],'state'=>$c['state'],
                'lat'=>$c['lat']+rand(-100,100)/10000,'lng'=>$c['lng']+rand(-100,100)/10000,
                'view_count'=>rand(10,300),'is_negotiable'=>rand(0,1),
                'created_at'=>$this->rDate(60),
            ]);
        }
        $this->command->info('✅ 200 market items');
    }

    // ════════════════════════════════════════
    // 7. REAL ESTATE (150개)
    // ════════════════════════════════════════
    private function seedRealEstate()
    {
        $types = ['rent','rent','rent','sale','roommate'];
        $props = ['apt','house','condo','studio'];

        for ($i = 0; $i < 150; $i++) {
            $c = $this->rCity();
            $type = $types[array_rand($types)];
            $propType = $props[array_rand($props)];
            $beds = rand(0,4);
            $price = match($type) {
                'rent' => rand(800, 3500),
                'sale' => rand(200000, 800000),
                'roommate' => rand(500, 1500),
            };

            RealEstateListing::create([
                'user_id'=>$this->rUser(),'title'=>"{$c['city']} {$propType} ".($type==='sale'?'매매':'렌트'),
                'content'=>"위치: {$c['city']}, {$c['state']}\n방: {$beds}개 / 화장실: ".rand(1,3)."개\n면적: ".rand(400,2000)."sqft\n\n".($type==='rent'?"월세 \${$price}\n입주 가능일: ".now()->addDays(rand(7,60))->format('Y-m-d'):"매매가 \$".number_format($price))."\n\n한인 밀집 지역이며 대중교통 편리합니다.\n관심 있으시면 연락주세요.",
                'type'=>$type,'property_type'=>$propType,'price'=>$price,
                'deposit'=>$type==='rent'?$price:null,
                'city'=>$c['city'],'state'=>$c['state'],'zipcode'=>$c['zip'],
                'address'=>rand(100,9999).' '.['Main St','Oak Ave','Elm Dr','Park Blvd','1st Ave'][rand(0,4)],
                'lat'=>$c['lat']+rand(-100,100)/10000,'lng'=>$c['lng']+rand(-100,100)/10000,
                'bedrooms'=>$beds,'bathrooms'=>rand(1,3),'sqft'=>rand(400,2500),
                'view_count'=>rand(20,500),'contact_phone'=>'('.rand(200,999).') '.rand(100,999).'-'.rand(1000,9999),
                'created_at'=>$this->rDate(60),
            ]);
        }
        $this->command->info('✅ 150 real estate listings');
    }

    // ════════════════════════════════════════
    // 8. CLUBS (50개)
    // ════════════════════════════════════════
    private function seedClubs()
    {
        $clubs = [
            ['n'=>'LA 한인 등산 모임','cat'=>'sports','t'=>'local'],
            ['n'=>'뉴욕 한인 독서 클럽','cat'=>'books','t'=>'local'],
            ['n'=>'미국 한인 사진 동호회','cat'=>'photo','t'=>'online'],
            ['n'=>'시카고 한인 골프 모임','cat'=>'sports','t'=>'local'],
            ['n'=>'한인 엄마들의 육아 모임','cat'=>'parenting','t'=>'online'],
            ['n'=>'미국 한인 요리 동호회','cat'=>'cooking','t'=>'online'],
            ['n'=>'달라스 한인 테니스 클럽','cat'=>'sports','t'=>'local'],
            ['n'=>'한인 IT 개발자 모임','cat'=>'tech','t'=>'online'],
            ['n'=>'시애틀 한인 하이킹 그룹','cat'=>'sports','t'=>'local'],
            ['n'=>'한인 투자/재테크 스터디','cat'=>'finance','t'=>'online'],
        ];

        for ($i = 0; $i < 50; $i++) {
            $d = $clubs[$i % count($clubs)];
            $c = $this->rCity();
            $ownerId = $this->rUser();
            $memberCount = rand(5, 80);

            $club = Club::create([
                'user_id'=>$ownerId,'name'=>$d['n'].($i>=10?' '.($i-9):''),
                'description'=>$d['n'].'입니다. 함께 즐거운 활동하실 분들을 모집합니다!',
                'category'=>$d['cat'],'type'=>$d['t'],'zipcode'=>$c['zip'],
                'lat'=>$c['lat']+rand(-100,100)/10000, 'lng'=>$c['lng']+rand(-100,100)/10000,
                'member_count'=>$memberCount,
                'created_at'=>$this->rDate(120),
            ]);

            ClubMember::create(['club_id'=>$club->id,'user_id'=>$ownerId,'role'=>'admin','joined_at'=>$club->created_at]);
            for ($j = 0; $j < min($memberCount - 1, 10); $j++) {
                try {
                    ClubMember::create(['club_id'=>$club->id,'user_id'=>$this->rUser(),'role'=>'member','joined_at'=>$this->rDate(60)]);
                } catch (\Exception $e) {} // ignore dupes
            }
        }
        $this->command->info('✅ 50 clubs');
    }

    // ════════════════════════════════════════
    // 9. NEWS (9 카테고리 + 200 기사)
    // ════════════════════════════════════════
    private function seedNews()
    {
        $cats = ['이민/비자','경제/비즈니스','정치','사회','생활','문화/연예','스포츠','테크','커뮤니티'];
        $catIds = [];
        foreach ($cats as $i => $name) {
            $cat = NewsCategory::create(['name'=>$name,'slug'=>'news-'.($i+1)]);
            $catIds[] = $cat->id;
        }

        $headlines = [
            '이민/비자'=>['H1B 비자 신청 시즌 시작','영주권 대기 시간 단축 소식','시민권 시험 개정안 발표','DACA 갱신 절차 안내','취업비자 심사 강화'],
            '경제/비즈니스'=>['한인 자영업 지원금 확대','부동산 시장 전망 2026','금리 인하 예상 시기','한인 스타트업 투자 유치','세금 신고 마감 임박'],
            '정치'=>['한미 정상회담 성과','미국 중간선거 분석','이민법 개정안 논의','한인 정치 참여 확대','대선 후보 한인 정책'],
            '사회'=>['한인 혐오 범죄 대응','한인 학생 명문대 합격','한인 타운 안전 강화','코리안 아메리칸 역사의 달','한인 의료인 봉사 활동'],
            '생활'=>['한인 마트 신규 오픈','자동차 리콜 정보','건강보험 가입 시기','전기차 보조금 안내','여름 절약 팁 모음'],
            '문화/연예'=>['K-POP 미국 투어 일정','한국 영화 아카데미 수상','BTS 새 앨범 발매','한식 세계화 프로젝트','넷플릭스 한국 드라마 인기'],
            '스포츠'=>['MLB 한인 선수 활약','손흥민 EPL 골 기록','한인 골프 대회 소식','NBA 시즌 프리뷰','한인 마라톤 대회 개최'],
            '테크'=>['AI 기술 한인 스타트업','테크 기업 한인 채용','새로운 앱 서비스 출시','사이버 보안 주의보','메타 한국어 지원 확대'],
            '커뮤니티'=>['한인회 정기 총회','한인 축제 일정 공개','한글학교 봄학기 등록','한인 봉사단체 모집','동포 장학금 수여식'],
        ];

        for ($i = 0; $i < 200; $i++) {
            $catIdx = $i % 9;
            $catName = $cats[$catIdx];
            $titles = $headlines[$catName];

            News::create([
                'title' => $titles[array_rand($titles)] . ' (' . rand(1,99) . ')',
                'content' => "오늘 {$catName} 관련 소식을 전합니다.\n\n관계자에 따르면 최근 한인 커뮤니티에서 큰 관심을 받고 있는 이슈로, 많은 한인분들이 주목하고 있습니다.\n\n자세한 내용은 관련 기관에 문의하시기 바랍니다.",
                'summary' => "한인 커뮤니티 {$catName} 관련 최신 소식입니다.",
                'source' => ['코리아타임즈','한국일보','중앙일보 US','조선일보 US','연합뉴스'][rand(0,4)],
                'source_url' => 'https://example.com/news/' . rand(10000,99999),
                'category_id' => $catIds[$catIdx],
                'view_count' => rand(50, 2000),
                'published_at' => $this->rDate(30),
                'created_at' => $this->rDate(30),
            ]);
        }
        $this->command->info('✅ 9 news categories + 200 articles');
    }

    // ════════════════════════════════════════
    // 10. RECIPES (100개)
    // ════════════════════════════════════════
    private function seedRecipes()
    {
        $cats = ['한식','중식','일식','양식','분식','디저트','반찬','국/찌개','면/파스타','음료','건강식','간편식'];
        $catIds = [];
        foreach ($cats as $i => $name) $catIds[] = RecipeCategory::create(['name'=>$name,'slug'=>'recipe-'.($i+1),'sort_order'=>$i])->id;

        $recipes = [
            ['t'=>'김치찌개','tk'=>'김치찌개','d'=>'medium','c'=>0,'pt'=>10,'ct'=>20],
            ['t'=>'불고기','tk'=>'불고기','d'=>'easy','c'=>0,'pt'=>15,'ct'=>15],
            ['t'=>'비빔밥','tk'=>'비빔밥','d'=>'easy','c'=>0,'pt'=>20,'ct'=>10],
            ['t'=>'떡볶이','tk'=>'떡볶이','d'=>'easy','c'=>4,'pt'=>5,'ct'=>15],
            ['t'=>'잡채','tk'=>'잡채','d'=>'medium','c'=>0,'pt'=>20,'ct'=>20],
            ['t'=>'된장찌개','tk'=>'된장찌개','d'=>'easy','c'=>7,'pt'=>10,'ct'=>20],
            ['t'=>'감자탕','tk'=>'감자탕','d'=>'hard','c'=>7,'pt'=>20,'ct'=>60],
            ['t'=>'갈비찜','tk'=>'갈비찜','d'=>'hard','c'=>0,'pt'=>30,'ct'=>90],
            ['t'=>'파전','tk'=>'파전','d'=>'easy','c'=>6,'pt'=>10,'ct'=>15],
            ['t'=>'김밥','tk'=>'김밥','d'=>'medium','c'=>0,'pt'=>30,'ct'=>10],
        ];

        for ($i = 0; $i < 100; $i++) {
            $r = $recipes[$i % count($recipes)];
            RecipePost::create([
                'user_id'=>$this->rUser(),
                'title'=>$r['t'].' 만들기','title_ko'=>$r['tk'].' 만드는 법',
                'content'=>$r['t']." 레시피를 공유합니다!\n\n한국에서 먹던 그 맛을 미국에서도 재현할 수 있어요.",
                'content_ko'=>$r['tk']." 레시피입니다.\n\n재료만 있으면 누구나 쉽게 만들 수 있어요!",
                'ingredients'=>json_decode('["재료1","재료2","재료3","양념"]'),
                'steps'=>json_decode('["재료를 준비합니다","양념을 만듭니다","재료를 볶습니다","완성!"]'),
                'category_id'=>$r['c'] ? $catIds[$r['c']] : $catIds[array_rand($catIds)],
                'servings'=>rand(2,6),'prep_time'=>$r['pt'],'cook_time'=>$r['ct'],
                'difficulty'=>$r['d'],
                'view_count'=>rand(30,500),'like_count'=>rand(0,40),'comment_count'=>rand(0,15),
                'created_at'=>$this->rDate(90),
            ]);
        }
        $this->command->info('✅ 12 recipe categories + 100 recipes');
    }

    // ════════════════════════════════════════
    // 11. EVENTS (100개)
    // ════════════════════════════════════════
    private function seedEvents()
    {
        $events = [
            ['t'=>'한인 문화 축제','cat'=>'culture'],
            ['t'=>'한인 네트워킹 모임','cat'=>'networking'],
            ['t'=>'한글학교 발표회','cat'=>'education'],
            ['t'=>'한인 교회 바자회','cat'=>'community'],
            ['t'=>'K-POP 댄스 워크샵','cat'=>'culture'],
            ['t'=>'한인 골프 대회','cat'=>'sports'],
            ['t'=>'한식 쿠킹 클래스','cat'=>'food'],
            ['t'=>'한인 비즈니스 세미나','cat'=>'networking'],
            ['t'=>'어린이 한국어 캠프','cat'=>'education'],
            ['t'=>'한인 자선 음악회','cat'=>'culture'],
        ];

        for ($i = 0; $i < 100; $i++) {
            $d = $events[$i % count($events)];
            $c = $this->rCity();
            $startDate = now()->addDays(rand(-30, 90));

            Event::create([
                'title'=>$d['t'].' in '.$c['city'],'category'=>$d['cat'],
                'description'=>$d['t'].'에 여러분을 초대합니다!',
                'content'=>"일시: ".($startDate->format('Y년 m월 d일'))."\n장소: {$c['city']} 한인회관\n\n한인 여러분의 많은 참여 바랍니다.\n사전 등록하시면 무료 입장 가능합니다.",
                'organizer'=>['한인회','한인 상공회의소','한글학교','한인 교회','K-Culture Center'][rand(0,4)],
                'venue'=>['한인회관','커뮤니티 센터','교회 홀','호텔 컨퍼런스룸','공원'][rand(0,4)],
                'address'=>rand(100,9999).' '.['Main St','Broadway','Olympic Blvd'][rand(0,2)],
                'city'=>$c['city'],'state'=>$c['state'],'zipcode'=>$c['zip'],
                'lat'=>$c['lat']+rand(-100,100)/10000,'lng'=>$c['lng']+rand(-100,100)/10000,
                'start_date'=>$startDate,'end_date'=>$startDate->copy()->addHours(rand(2,8)),
                'price'=>rand(0,3)===0?0:rand(10,50),
                'view_count'=>rand(30,500),'attendee_count'=>rand(0,100),
                'created_at'=>$startDate->copy()->subDays(rand(7,30)),
            ]);
        }
        $this->command->info('✅ 100 events');
    }

    // ════════════════════════════════════════
    // 12. BUSINESSES (200개)
    // ════════════════════════════════════════
    private function seedBusinesses()
    {
        $biz = [
            ['n'=>'서울가든 Korean BBQ','cat'=>'restaurant','sub'=>'한식'],
            ['n'=>'카페 봄','cat'=>'restaurant','sub'=>'카페'],
            ['n'=>'한인 마트','cat'=>'grocery','sub'=>'마트'],
            ['n'=>'K-Beauty Nail Spa','cat'=>'beauty','sub'=>'네일'],
            ['n'=>'서울 헤어','cat'=>'beauty','sub'=>'미용실'],
            ['n'=>'한인 치과','cat'=>'medical','sub'=>'치과'],
            ['n'=>'Kim & Associates CPA','cat'=>'professional','sub'=>'회계'],
            ['n'=>'한인 법률 사무소','cat'=>'professional','sub'=>'법률'],
            ['n'=>'코리안 오토','cat'=>'auto','sub'=>'정비'],
            ['n'=>'한인 부동산','cat'=>'realestate','sub'=>'부동산'],
        ];

        for ($i = 0; $i < 200; $i++) {
            $d = $biz[$i % count($biz)];
            $c = $this->rCity();

            $business = Business::create([
                'name'=>$d['n'].' '.$c['city'],'category'=>$d['cat'],'subcategory'=>$d['sub'],
                'description'=>$c['city'].'에 위치한 '.$d['n'].'입니다. 한인 고객분들을 정성껏 모시겠습니다.',
                'phone'=>'('.rand(200,999).') '.rand(100,999).'-'.rand(1000,9999),
                'email'=>strtolower(str_replace([' ','&'],['',''],$d['n'])).'@email.com',
                'website'=>'https://www.'.strtolower(str_replace([' ','&','\''],['','',''],$d['n'])).'.com',
                'address'=>rand(100,9999).' '.['Main St','Olympic Blvd','Vermont Ave','Western Ave'][rand(0,3)],
                'city'=>$c['city'],'state'=>$c['state'],'zipcode'=>$c['zip'],
                'lat'=>$c['lat']+rand(-100,100)/10000,'lng'=>$c['lng']+rand(-100,100)/10000,
                'hours'=>json_decode('{"mon":"9:00-21:00","tue":"9:00-21:00","wed":"9:00-21:00","thu":"9:00-21:00","fri":"9:00-22:00","sat":"10:00-22:00","sun":"closed"}', true),
                'rating'=>rand(30,50)/10,'review_count'=>rand(0,50),
                'view_count'=>rand(50,1000),
                'created_at'=>$this->rDate(180),
            ]);

            // Add some reviews
            for ($j = 0; $j < rand(0, 5); $j++) {
                BusinessReview::create([
                    'business_id'=>$business->id,'user_id'=>$this->rUser(),
                    'rating'=>rand(3,5),
                    'content'=>['친절하고 좋아요!','맛있습니다 추천!','서비스가 좋습니다','가격도 합리적이에요','한인분이라 소통이 편해요','재방문 의사 100%'][rand(0,5)],
                    'created_at'=>$this->rDate(60),
                ]);
            }
        }
        $this->command->info('✅ 200 businesses + reviews');
    }

    // ════════════════════════════════════════
    // 13. Q&A (150개 + 답변)
    // ════════════════════════════════════════
    private function seedQA()
    {
        $cats = ['이민/비자','법률','세금/회계','부동산','자동차','의료/보험','교육','취업','생활정보','IT/기술','기타'];
        $catIds = [];
        foreach ($cats as $i => $name) $catIds[] = QaCategory::create(['name'=>$name,'slug'=>'qa-'.($i+1),'sort_order'=>$i])->id;

        $questions = [
            ['q'=>'H1B에서 영주권으로 전환하는 절차가 궁금합니다','c'=>0],
            ['q'=>'교통사고 합의금 적정 수준이 얼마인가요?','c'=>1],
            ['q'=>'프리랜서 세금 신고는 어떻게 하나요?','c'=>2],
            ['q'=>'렌트 계약 시 주의해야 할 조항들','c'=>3],
            ['q'=>'중고차 구입 시 레몬법 적용 가능한가요?','c'=>4],
            ['q'=>'건강보험 없이 병원 가는 방법이 있나요?','c'=>5],
            ['q'=>'미국 대학 입시 준비 타임라인','c'=>6],
            ['q'=>'IT 회사 취업 시 비자 스폰서 받는법','c'=>7],
            ['q'=>'SSN 없이 할 수 있는 것들 정리','c'=>8],
            ['q'=>'미국에서 한국 넷플릭스 보는 방법','c'=>9],
        ];

        $answers = [
            '제가 경험한 바로는 이렇습니다. 먼저 관련 서류를 준비하시고, 전문가와 상담하시는 것을 추천드립니다.',
            '저도 비슷한 상황이었는데요, 결론적으로 전문 변호사/회계사에게 상담받으시는 게 가장 확실합니다.',
            '이 부분은 상황에 따라 다를 수 있는데, 일반적으로는 이렇게 진행됩니다. 참고하세요!',
            '좋은 질문이네요! 저도 처음에 헷갈렸는데, 이렇게 하면 됩니다.',
        ];

        for ($i = 0; $i < 150; $i++) {
            $d = $questions[$i % count($questions)];
            $bounty = rand(0,4) === 0 ? rand(10, 100) : 0;

            $qa = QaPost::create([
                'user_id'=>$this->rUser(),'category_id'=>$catIds[$d['c']],
                'title'=>$d['q'].' ('.rand(1,99).')',
                'content'=>$d['q']."\n\n자세한 상황 설명드리겠습니다. 경험 있으신 분들의 조언 부탁드립니다.\n\n미리 감사합니다!",
                'bounty_points'=>$bounty,'view_count'=>rand(30,500),
                'is_resolved'=>rand(0,2)===0,
                'created_at'=>$this->rDate(60),
            ]);

            $ansCount = rand(1, 5);
            for ($j = 0; $j < $ansCount; $j++) {
                $ans = QaAnswer::create([
                    'qa_post_id'=>$qa->id,'user_id'=>$this->rUser(),
                    'content'=>$answers[array_rand($answers)],
                    'like_count'=>rand(0, 15),
                    'is_best'=>$j === 0 && $qa->is_resolved,
                    'created_at'=>$qa->created_at->addHours(rand(1, 72)),
                ]);
                if ($j === 0 && $qa->is_resolved) {
                    $qa->update(['best_answer_id'=>$ans->id, 'answer_count'=>$ansCount]);
                }
            }
            if (!$qa->is_resolved) $qa->update(['answer_count'=>$ansCount]);
        }
        $this->command->info('✅ 11 QA categories + 150 questions + answers');
    }

    // ════════════════════════════════════════
    // 14. SHORTS (100개)
    // ════════════════════════════════════════
    private function seedShorts()
    {
        $ytIds = ['dQw4w9WgXcQ','9bZkp7q19f0','kJQP7kiw5Fk','JGwWNGJdvx8','RgKAFK5djSk','fJ9rUzIMcZQ','OPf0YbXqDm0','hTWKbfoikeg','lp-EO5I60KA','60ItHLz5WEA'];
        $titles = ['한국 길거리 음식 먹방','K-POP 댄스 챌린지','한인타운 투어','미국 생활 브이로그','한식 레시피 숏츠','LA 맛집 탐방','한인 마트 쇼핑','미국 운전 팁','영어 회화 꿀팁','한국 문화 소개'];

        for ($i = 0; $i < 100; $i++) {
            $ytId = $ytIds[array_rand($ytIds)];
            Short::create([
                'user_id'=>$this->rUser(),
                'title'=>$titles[array_rand($titles)].' #'.rand(1,99),
                'video_url'=>"https://youtube.com/shorts/{$ytId}",
                'youtube_id'=>$ytId,
                'thumbnail_url'=>"https://img.youtube.com/vi/{$ytId}/hqdefault.jpg",
                'duration'=>rand(15,60),
                'view_count'=>rand(100,5000),'like_count'=>rand(0,200),'comment_count'=>rand(0,30),
                'created_at'=>$this->rDate(30),
            ]);
        }
        $this->command->info('✅ 100 shorts');
    }

    // ════════════════════════════════════════
    // 15. MUSIC (8카테고리 + 50트랙)
    // ════════════════════════════════════════
    private function seedMusic()
    {
        $categories = [
            ['name'=>'발라드','slug'=>'ballad'],['name'=>'트로트','slug'=>'trot'],
            ['name'=>'K-POP','slug'=>'kpop'],['name'=>'힙합','slug'=>'hiphop'],
            ['name'=>'R&B','slug'=>'rnb'],['name'=>'재즈','slug'=>'jazz'],
            ['name'=>'클래식','slug'=>'classic'],['name'=>'OST','slug'=>'ost'],
        ];

        $tracks = [
            'ballad'=>[['t'=>'보고 싶다','a'=>'김범수'],['t'=>'사랑했지만','a'=>'김광석'],['t'=>'거짓말','a'=>'빅뱅'],['t'=>'눈의 꽃','a'=>'박효신'],['t'=>'좋은 날','a'=>'아이유']],
            'trot'=>[['t'=>'잘가라','a'=>'임영웅'],['t'=>'사랑의 콜센타','a'=>'영탁'],['t'=>'나 트로트 가수다','a'=>'장윤정'],['t'=>'무조건','a'=>'박상철']],
            'kpop'=>[['t'=>'Dynamite','a'=>'BTS'],['t'=>'Pink Venom','a'=>'BLACKPINK'],['t'=>'Next Level','a'=>'aespa'],['t'=>'LOVE DIVE','a'=>'IVE'],['t'=>'Hype Boy','a'=>'NewJeans']],
            'hiphop'=>[['t'=>'어떻게 이별까지 사랑하겠어','a'=>'AKMU'],['t'=>'ANYWAY','a'=>'비와이'],['t'=>'꽃','a'=>'지코']],
            'rnb'=>[['t'=>'Rain','a'=>'태연'],['t'=>'밤편지','a'=>'아이유'],['t'=>'All of My Life','a'=>'박원']],
            'jazz'=>[['t'=>'Fly Me to the Moon','a'=>'Frank Sinatra'],['t'=>'What a Wonderful World','a'=>'Louis Armstrong']],
            'classic'=>[['t'=>'캐논 변주곡','a'=>'파헬벨'],['t'=>'엘리제를 위하여','a'=>'베토벤']],
            'ost'=>[['t'=>'My Love','a'=>'이승철'],['t'=>'Stay With Me','a'=>'찬열&펀치'],['t'=>'사랑이 아프다','a'=>'이하이']],
        ];

        $ytIds = ['dQw4w9WgXcQ','kJQP7kiw5Fk','JGwWNGJdvx8','RgKAFK5djSk','9bZkp7q19f0','fJ9rUzIMcZQ'];

        foreach ($categories as $i => $cat) {
            $mc = MusicCategory::create(array_merge($cat, ['sort_order'=>$i]));
            $slug = $cat['slug'];
            if (isset($tracks[$slug])) {
                foreach ($tracks[$slug] as $j => $track) {
                    MusicTrack::create([
                        'category_id'=>$mc->id,'title'=>$track['t'],'artist'=>$track['a'],
                        'youtube_url'=>'https://youtube.com/watch?v='.$ytIds[array_rand($ytIds)],
                        'youtube_id'=>$ytIds[array_rand($ytIds)],'duration'=>rand(180,300),'sort_order'=>$j,
                    ]);
                }
            }
        }
        $this->command->info('✅ 8 music categories + tracks');
    }

    // ════════════════════════════════════════
    // 16. GROUP BUYS (50개)
    // ════════════════════════════════════════
    private function seedGroupBuys()
    {
        $items = [
            ['t'=>'한국 김치 공동구매','op'=>50,'gp'=>35],
            ['t'=>'삼성 TV 공동구매','op'=>800,'gp'=>600],
            ['t'=>'한국 과자 대량 구매','op'=>30,'gp'=>20],
            ['t'=>'김밥 재료 공동구매','op'=>40,'gp'=>25],
            ['t'=>'한국 라면 박스','op'=>25,'gp'=>18],
        ];

        for ($i = 0; $i < 50; $i++) {
            $d = $items[$i % count($items)];
            $c = $this->rCity();
            GroupBuy::create([
                'user_id'=>$this->rUser(),'title'=>$d['t'].' ('.$c['city'].')',
                'content'=>$d['t']." 같이 하실 분 구합니다!\n\n개별 구매: \${$d['op']}\n공동구매: \${$d['gp']}\n\n참여 인원이 모이면 바로 진행합니다.",
                'original_price'=>$d['op'],'group_price'=>$d['gp'],
                'min_participants'=>rand(3,10),'max_participants'=>rand(10,30),
                'current_participants'=>rand(1,8),
                'city'=>$c['city'],'state'=>$c['state'],
                'lat'=>$c['lat']+rand(-100,100)/10000,'lng'=>$c['lng']+rand(-100,100)/10000,
                'status'=>['recruiting','recruiting','recruiting','confirmed','completed'][rand(0,4)],
                'deadline'=>now()->addDays(rand(3,30)),
                'created_at'=>$this->rDate(30),
            ]);
        }
        $this->command->info('✅ 50 group buys');
    }

    // ════════════════════════════════════════
    // 17. GAME SETTINGS
    // ════════════════════════════════════════
    private function seedGameSettings()
    {
        $settings = [
            ['game_type'=>'global','key'=>'daily_spin_enabled','value'=>'true'],
            ['game_type'=>'global','key'=>'daily_spin_min','value'=>'0'],
            ['game_type'=>'global','key'=>'daily_spin_max','value'=>'300'],
            ['game_type'=>'global','key'=>'point_per_game','value'=>'5'],
            ['game_type'=>'quiz','key'=>'questions_per_round','value'=>'10'],
            ['game_type'=>'quiz','key'=>'time_per_question','value'=>'30'],
            ['game_type'=>'gostop','key'=>'max_players','value'=>'3'],
            ['game_type'=>'poker','key'=>'min_bet','value'=>'10'],
            ['game_type'=>'poker','key'=>'max_bet','value'=>'1000'],
        ];
        foreach ($settings as $s) GameSetting::create($s);
        $this->command->info('✅ game settings');
    }

    // ════════════════════════════════════════
    // 18. SITE SETTINGS
    // ════════════════════════════════════════
    private function seedSiteSettings()
    {
        $settings = [
            ['key'=>'site_name','value'=>'SomeKorean','group'=>'general'],
            ['key'=>'site_description','value'=>'미국 한인 커뮤니티','group'=>'general'],
            ['key'=>'primary_color','value'=>'#F59E0B','group'=>'appearance'],
            ['key'=>'logo_url','value'=>'/images/logo_00.jpg','group'=>'appearance'],
            ['key'=>'contact_email','value'=>'info@somekorean.com','group'=>'general'],
        ];
        foreach ($settings as $s) SiteSetting::create($s);
        $this->command->info('✅ site settings');
    }

    // ════════════════════════════════════════
    // 19. SHOPPING (매장 + 딜)
    // ════════════════════════════════════════
    private function seedShopping()
    {
        $stores = [
            ['name'=>'H Mart','category'=>'grocery'],
            ['name'=>'한남체인','category'=>'grocery'],
            ['name'=>'한양마트','category'=>'grocery'],
            ['name'=>'Amazon','category'=>'online'],
            ['name'=>'Costco','category'=>'warehouse'],
            ['name'=>'Target','category'=>'retail'],
        ];
        $storeIds = [];
        foreach ($stores as $s) {
            $store = ShoppingStore::create($s);
            $storeIds[] = $store->id;
        }

        $deals = [
            ['t'=>'신라면 멀티팩 (5개입)','op'=>8.99,'sp'=>5.99,'dp'=>33],
            ['t'=>'CJ 비비고 만두 (대용량)','op'=>15.99,'sp'=>10.99,'dp'=>31],
            ['t'=>'오뚜기 진라면 (4+1)','op'=>7.49,'sp'=>4.99,'dp'=>33],
            ['t'=>'삼양 불닭볶음면 세트','op'=>12.99,'sp'=>8.99,'dp'=>31],
            ['t'=>'농심 새우깡 파티팩','op'=>6.99,'sp'=>3.99,'dp'=>43],
            ['t'=>'정관장 홍삼 에브리타임','op'=>45.00,'sp'=>32.99,'dp'=>27],
            ['t'=>'한국 김 선물세트','op'=>24.99,'sp'=>16.99,'dp'=>32],
            ['t'=>'CJ 햇반 즉석밥 (12개)','op'=>18.99,'sp'=>13.99,'dp'=>26],
            ['t'=>'오설록 제주 녹차 세트','op'=>28.00,'sp'=>19.99,'dp'=>29],
            ['t'=>'삼성 갤럭시 버즈3 프로','op'=>249.99,'sp'=>179.99,'dp'=>28],
            ['t'=>'LG 스타일러 의류관리기','op'=>1499.00,'sp'=>999.00,'dp'=>33],
            ['t'=>'쿠쿠 전기밥솥 CRP-P1009','op'=>299.99,'sp'=>219.99,'dp'=>27],
            ['t'=>'한국 라면 종합세트 (20개)','op'=>35.99,'sp'=>24.99,'dp'=>31],
            ['t'=>'비비고 왕교자 (대용량)','op'=>16.99,'sp'=>11.99,'dp'=>29],
            ['t'=>'테팔 프라이팬 세트','op'=>89.99,'sp'=>59.99,'dp'=>33],
        ];

        foreach ($deals as $d) {
            ShoppingDeal::create([
                'store_id' => $storeIds[array_rand($storeIds)],
                'title' => $d['t'],
                'description' => $d['t'] . ' 특가 세일!',
                'original_price' => $d['op'],
                'sale_price' => $d['sp'],
                'discount_percent' => $d['dp'],
                'url' => 'https://example.com/deal/' . rand(1000, 9999),
                'is_active' => true,
                'expires_at' => now()->addDays(rand(3, 30)),
            ]);
        }
        $this->command->info('✅ 6 stores + 15 shopping deals');
    }

    // ════════════════════════════════════════
    // 20. REGIONAL CHAT ROOMS (지역별 24시간 채팅방)
    // ════════════════════════════════════════
    private function seedRegionalChatRooms()
    {
        $rooms = [
            '🗽 뉴욕 한인 채팅방', '🏙️ 뉴저지 한인 채팅방', '🍑 아틀란타 한인 채팅방',
            '🌴 LA 한인 채팅방', '🌁 샌프란시스코 한인 채팅방', '🤠 달라스 한인 채팅방',
            '🚀 휴스턴 한인 채팅방', '☕ 시애틀 한인 채팅방', '🏛️ 워싱턴DC 한인 채팅방',
            '🔔 필라델피아 한인 채팅방', '🌬️ 시카고 한인 채팅방', '🎰 라스베가스 한인 채팅방',
            '🌺 호놀룰루 한인 채팅방', '🍊 어바인/OC 한인 채팅방', '💬 자유 수다방',
        ];

        foreach ($rooms as $name) {
            $room = \App\Models\ChatRoom::create([
                'name' => $name,
                'type' => 'public',
                'created_by' => 1, // admin
            ]);

            // 더미 메시지 추가
            $messages = [
                '안녕하세요! 여기 사시는 분들 반갑습니다 👋',
                '오늘 날씨가 정말 좋네요 ☀️',
                '이 동네 한식당 추천해주세요!',
                '주말에 같이 등산 가실 분?',
                '방금 이사 왔는데 한인 마트 어디 있나요?',
                '오늘 한인 행사 있다고 들었는데 아시는 분?',
                '좋은 한인 교회 추천 부탁드려요',
                '여기 치안은 어떤가요?',
            ];

            $userIds = \App\Models\User::pluck('id')->toArray();
            foreach (array_slice($messages, 0, rand(3, 8)) as $msg) {
                \App\Models\ChatMessage::create([
                    'chat_room_id' => $room->id,
                    'user_id' => $userIds[array_rand($userIds)],
                    'content' => $msg,
                    'type' => 'text',
                    'created_at' => now()->subMinutes(rand(1, 1440)),
                ]);
            }
        }
        $this->command->info('✅ 15 regional chat rooms + messages');
    }
}
