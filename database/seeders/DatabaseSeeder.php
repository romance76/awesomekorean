<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Board;
use App\Models\Post;
use App\Models\JobPost;
use App\Models\MarketItem;
use App\Models\QaCategory;
use App\Models\QaPost;
use App\Models\RecipeCategory;
use App\Models\NewsCategory;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Admin User ───
        User::create([
            'name' => '관리자', 'nickname' => '관리자', 'email' => 'admin@somekorean.com',
            'password' => Hash::make('password'), 'role' => 'admin', 'language' => 'ko', 'points' => 10000,
        ]);

        // ─── 200 Fake Users ───
        $lastNames = ['김','이','박','최','정','강','조','윤','장','임','한','오','서','신','권','황','안','송','류','전'];
        $firstNames = ['민수','지영','현우','서연','준호','하나','성민','유진','태현','수빈','영호','미영','정훈','소연','동현','은지','상현','지은','병철','혜진'];
        $cities = [
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

        for ($i = 0; $i < 200; $i++) {
            $c = $cities[$i % 10];
            User::create([
                'name' => $lastNames[array_rand($lastNames)] . $firstNames[array_rand($firstNames)],
                'nickname' => $lastNames[array_rand($lastNames)] . $firstNames[array_rand($firstNames)],
                'email' => "user{$i}@test.com",
                'password' => Hash::make('password'),
                'city' => $c['city'], 'state' => $c['state'], 'zipcode' => $c['zip'],
                'latitude' => $c['lat'] + (rand(-100,100)/10000),
                'longitude' => $c['lng'] + (rand(-100,100)/10000),
                'points' => rand(0, 3000), 'language' => rand(1,5) === 1 ? 'en' : 'ko',
            ]);
        }
        $this->command->info('201 users created');

        // ─── Boards ───
        $boards = [
            ['name'=>'자유게시판','slug'=>'free'], ['name'=>'정보공유','slug'=>'info'],
            ['name'=>'생활꿀팁','slug'=>'tips'], ['name'=>'맛집후기','slug'=>'food'],
            ['name'=>'여행이야기','slug'=>'travel'], ['name'=>'자녀교육','slug'=>'education'],
            ['name'=>'이민생활','slug'=>'immigration'], ['name'=>'건강정보','slug'=>'health'],
            ['name'=>'유머','slug'=>'humor'], ['name'=>'고민상담','slug'=>'advice'],
            ['name'=>'홍보/광고','slug'=>'promotion'],
        ];
        foreach ($boards as $i => $b) Board::create(array_merge($b, ['sort_order' => $i, 'description' => $b['name'] . ' 게시판']));
        $this->command->info('11 boards created');

        // ─── Posts (300) ───
        $titles = ['자녀 SAT 준비 어떻게 하시나요?','중고차 구매 시 주의사항','LA 한인타운 맛집 리스트','영주권 인터뷰 후기','미국 생활 팁 모음','한인 마트 세일 정보','자동차 보험 비교','시민권 시험 준비','한인 병원 추천','미국 세금 신고 방법','학교 선택 고민','이사 후기','한식 레시피 공유','운전면허 취득 방법','비자 연장 경험'];
        $contents = ['안녕하세요! 정보 공유합니다. 궁금하신 점은 댓글로 남겨주세요.','최근에 알게 된 유용한 정보인데요, 다른 분들도 알면 좋을 것 같아서 올립니다.','경험을 공유합니다. 같은 상황인 분들에게 도움이 되었으면 합니다.'];
        $boardIds = Board::pluck('id')->toArray();
        $userIds = User::pluck('id')->toArray();

        for ($i = 0; $i < 300; $i++) {
            Post::create([
                'board_id' => $boardIds[array_rand($boardIds)],
                'user_id' => $userIds[array_rand($userIds)],
                'title' => $titles[array_rand($titles)] . ' ' . rand(1,99),
                'content' => $contents[array_rand($contents)] . "\n\n" . $contents[array_rand($contents)],
                'view_count' => rand(10, 500),
                'like_count' => rand(0, 50),
                'comment_count' => rand(0, 30),
                'created_at' => now()->subDays(rand(0, 90)),
            ]);
        }
        $this->command->info('300 posts created');

        // ─── Jobs (100) ───
        $jobTitles = ['제과점 베이커 모집','모바일 앱 개발자','네일 테크니션','배달 드라이버','마트 캐셔','한영 통번역','회계사 보조','레스토랑 서버','헤어 스타일리스트','사무직 직원'];
        $companies = ['카페 봄','Hana Tech','K-Beauty','한인 플라자','Pacific Moving','서울 CPA','코리안 셔틀','한남체인'];
        $jobCats = ['restaurant','it','beauty','driving','retail','office','construction','medical','education','etc'];

        for ($i = 0; $i < 100; $i++) {
            $c = $cities[array_rand($cities)];
            JobPost::create([
                'user_id' => $userIds[array_rand($userIds)],
                'title' => $jobTitles[array_rand($jobTitles)],
                'company' => $companies[array_rand($companies)],
                'content' => '구인합니다. 자세한 내용은 연락주세요.',
                'category' => $jobCats[array_rand($jobCats)],
                'type' => ['full','part','contract'][rand(0,2)],
                'salary_min' => rand(15, 30),
                'salary_max' => rand(25, 45),
                'salary_type' => 'hourly',
                'city' => $c['city'], 'state' => $c['state'], 'zipcode' => $c['zip'],
                'lat' => $c['lat'] + rand(-100,100)/10000,
                'lng' => $c['lng'] + rand(-100,100)/10000,
                'contact_phone' => '(' . rand(200,999) . ') ' . rand(100,999) . '-' . rand(1000,9999),
                'view_count' => rand(10, 300),
                'expires_at' => now()->addDays(rand(7, 60)),
                'created_at' => now()->subDays(rand(0, 30)),
            ]);
        }
        $this->command->info('100 jobs created');

        // ─── Market Items (100) ───
        $marketTitles = ['아이폰 15 Pro 판매','삼성 TV 55인치','이케아 소파','유아 카시트','자전거 판매','맥북 에어','닌텐도 스위치','한국 식기 세트'];
        $marketCats = ['electronics','furniture','clothing','auto','baby','sports','books','etc'];

        for ($i = 0; $i < 100; $i++) {
            $c = $cities[array_rand($cities)];
            MarketItem::create([
                'user_id' => $userIds[array_rand($userIds)],
                'title' => $marketTitles[array_rand($marketTitles)] . ' #' . rand(1,99),
                'content' => '상태 좋습니다. 직거래 가능합니다.',
                'price' => rand(5, 2000),
                'category' => $marketCats[array_rand($marketCats)],
                'condition' => ['new','like_new','good','fair'][rand(0,3)],
                'status' => rand(1,10) <= 8 ? 'active' : (rand(0,1) ? 'reserved' : 'sold'),
                'city' => $c['city'], 'state' => $c['state'],
                'lat' => $c['lat'] + rand(-100,100)/10000,
                'lng' => $c['lng'] + rand(-100,100)/10000,
                'view_count' => rand(10, 200),
                'created_at' => now()->subDays(rand(0, 60)),
            ]);
        }
        $this->command->info('100 market items created');

        // ─── QA Categories ───
        foreach (['이민/비자','법률','세금/회계','부동산','자동차','의료/보험','교육','취업','생활정보','IT/기술','기타'] as $i => $name) {
            QaCategory::create(['name' => $name, 'slug' => 'qa-' . ($i+1), 'sort_order' => $i]);
        }

        // ─── Recipe Categories ───
        foreach (['한식','중식','일식','양식','분식','디저트','반찬','국/찌개','면/파스타','음료','건강식','간편식'] as $i => $name) {
            RecipeCategory::create(['name' => $name, 'slug' => 'recipe-' . ($i+1), 'sort_order' => $i]);
        }

        // ─── News Categories (9 main) ───
        $newsCats = ['이민/비자','경제/비즈니스','정치','사회','생활','문화/연예','스포츠','테크','커뮤니티'];
        foreach ($newsCats as $i => $name) {
            NewsCategory::create(['name' => $name, 'slug' => 'news-' . ($i+1)]);
        }

        // ─── Site Settings ───
        SiteSetting::create(['key' => 'site_name', 'value' => 'SomeKorean', 'group' => 'general']);
        SiteSetting::create(['key' => 'site_description', 'value' => '미국 한인 커뮤니티', 'group' => 'general']);
        SiteSetting::create(['key' => 'primary_color', 'value' => '#F59E0B', 'group' => 'appearance']);

        $this->command->info('Seeding complete!');
    }
}
