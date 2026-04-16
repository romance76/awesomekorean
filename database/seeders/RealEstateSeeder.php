<?php

namespace Database\Seeders;

use App\Models\RealEstateListing;
use App\Models\User;
use Illuminate\Database\Seeder;

class RealEstateSeeder extends Seeder
{
    public function run(): void
    {
        $userIds = User::where('is_banned', false)->pluck('id')->toArray();
        if (empty($userIds)) return;

        $cities = [
            ['city' => 'Atlanta', 'state' => 'GA', 'zip' => '30301', 'lat' => 33.749, 'lng' => -84.388],
            ['city' => 'Duluth', 'state' => 'GA', 'zip' => '30096', 'lat' => 34.006, 'lng' => -84.148],
            ['city' => 'Suwanee', 'state' => 'GA', 'zip' => '30024', 'lat' => 34.043, 'lng' => -84.026],
            ['city' => 'Los Angeles', 'state' => 'CA', 'zip' => '90001', 'lat' => 33.941, 'lng' => -118.248],
            ['city' => 'Koreatown', 'state' => 'CA', 'zip' => '90006', 'lat' => 34.058, 'lng' => -118.302],
            ['city' => 'Irvine', 'state' => 'CA', 'zip' => '92618', 'lat' => 33.684, 'lng' => -117.827],
            ['city' => 'Fort Lee', 'state' => 'NJ', 'zip' => '07024', 'lat' => 40.851, 'lng' => -73.973],
            ['city' => 'Flushing', 'state' => 'NY', 'zip' => '11354', 'lat' => 40.765, 'lng' => -73.833],
            ['city' => 'Carrollton', 'state' => 'TX', 'zip' => '75007', 'lat' => 32.958, 'lng' => -96.889],
            ['city' => 'Seattle', 'state' => 'WA', 'zip' => '98101', 'lat' => 47.606, 'lng' => -122.332],
        ];

        $rentItems = [
            ['type'=>'rent','property_type'=>'studio','title'=>'아틀란타 스튜디오 렌트','bedrooms'=>0,'bathrooms'=>1,'sqft'=>450,'price'=>850],
            ['type'=>'rent','property_type'=>'1br','title'=>'Duluth 1BR 아파트 렌트','bedrooms'=>1,'bathrooms'=>1,'sqft'=>650,'price'=>1100],
            ['type'=>'rent','property_type'=>'2br','title'=>'Suwanee 2BR 타운홈 렌트','bedrooms'=>2,'bathrooms'=>2,'sqft'=>1200,'price'=>1800],
            ['type'=>'rent','property_type'=>'3br_plus','title'=>'Atlanta 3BR 하우스 렌트','bedrooms'=>3,'bathrooms'=>2,'sqft'=>1800,'price'=>2500],
            ['type'=>'rent','property_type'=>'3br_plus','title'=>'LA Koreatown 4BR 펜트하우스','bedrooms'=>4,'bathrooms'=>3,'sqft'=>2500,'price'=>5500],
            ['type'=>'rent','property_type'=>'studio','title'=>'Irvine 스튜디오 (가구 포함)','bedrooms'=>0,'bathrooms'=>1,'sqft'=>400,'price'=>1600],
            ['type'=>'rent','property_type'=>'1br','title'=>'Flushing 1BR 렌트 (역세권)','bedrooms'=>1,'bathrooms'=>1,'sqft'=>550,'price'=>2200],
            ['type'=>'rent','property_type'=>'2br','title'=>'Fort Lee 2BR 한인밀집지역','bedrooms'=>2,'bathrooms'=>1,'sqft'=>900,'price'=>2800],
            ['type'=>'rent','property_type'=>'roommate','title'=>'Atlanta 룸메이트 구함 (여성)','bedrooms'=>1,'bathrooms'=>1,'sqft'=>300,'price'=>650],
            ['type'=>'rent','property_type'=>'roommate','title'=>'LA 한인타운 룸메이트 남성','bedrooms'=>1,'bathrooms'=>1,'sqft'=>250,'price'=>800],
            ['type'=>'rent','property_type'=>'minbak','title'=>'Duluth 단기 민박 (월단위)','bedrooms'=>1,'bathrooms'=>1,'sqft'=>400,'price'=>1200],
            ['type'=>'rent','property_type'=>'office_rent','title'=>'Atlanta 오피스 렌트 500sqft','bedrooms'=>0,'bathrooms'=>1,'sqft'=>500,'price'=>1500],
            ['type'=>'rent','property_type'=>'store_rent','title'=>'Koreatown 상가 렌트 (1층)','bedrooms'=>0,'bathrooms'=>1,'sqft'=>800,'price'=>3500],
            ['type'=>'rent','property_type'=>'retail_rent','title'=>'Dallas 소매 공간 렌트','bedrooms'=>0,'bathrooms'=>1,'sqft'=>1200,'price'=>2200],
            ['type'=>'rent','property_type'=>'etc_home','title'=>'Seattle 하우스 지하 렌트','bedrooms'=>1,'bathrooms'=>1,'sqft'=>600,'price'=>900],
            ['type'=>'rent','property_type'=>'1br','title'=>'Carrollton 1BR 신축 아파트','bedrooms'=>1,'bathrooms'=>1,'sqft'=>700,'price'=>1350],
            ['type'=>'rent','property_type'=>'2br','title'=>'Atlanta Midtown 2BR 럭셔리','bedrooms'=>2,'bathrooms'=>2,'sqft'=>1100,'price'=>3200],
            ['type'=>'rent','property_type'=>'studio','title'=>'Fort Lee 스튜디오 즉시입주','bedrooms'=>0,'bathrooms'=>1,'sqft'=>380,'price'=>1800],
            ['type'=>'rent','property_type'=>'3br_plus','title'=>'Suwanee 학군좋은 3BR 하우스','bedrooms'=>3,'bathrooms'=>2,'sqft'=>2000,'price'=>2200],
            ['type'=>'rent','property_type'=>'building_rent','title'=>'Atlanta 소규모 건물 렌트','bedrooms'=>0,'bathrooms'=>2,'sqft'=>3000,'price'=>8000],
        ];

        $saleItems = [
            ['type'=>'sale','property_type'=>'studio','title'=>'Atlanta condo 매매','bedrooms'=>0,'bathrooms'=>1,'sqft'=>500,'price'=>185000],
            ['type'=>'sale','property_type'=>'1br','title'=>'Duluth 1BR 콘도 매매','bedrooms'=>1,'bathrooms'=>1,'sqft'=>750,'price'=>220000],
            ['type'=>'sale','property_type'=>'2br','title'=>'Suwanee 2BR 타운홈 매매','bedrooms'=>2,'bathrooms'=>2,'sqft'=>1400,'price'=>350000],
            ['type'=>'sale','property_type'=>'3br_plus','title'=>'Atlanta 4BR 하우스 매매','bedrooms'=>4,'bathrooms'=>3,'sqft'=>2500,'price'=>525000],
            ['type'=>'sale','property_type'=>'3br_plus','title'=>'LA 3BR 하우스 (한인타운 근처)','bedrooms'=>3,'bathrooms'=>2,'sqft'=>1800,'price'=>890000],
            ['type'=>'sale','property_type'=>'studio','title'=>'Irvine 스튜디오 콘도 매매','bedrooms'=>0,'bathrooms'=>1,'sqft'=>450,'price'=>420000],
            ['type'=>'sale','property_type'=>'2br','title'=>'Flushing 2BR 콘도 (신축)','bedrooms'=>2,'bathrooms'=>2,'sqft'=>1000,'price'=>680000],
            ['type'=>'sale','property_type'=>'1br','title'=>'Fort Lee 1BR 리버뷰 콘도','bedrooms'=>1,'bathrooms'=>1,'sqft'=>700,'price'=>450000],
            ['type'=>'sale','property_type'=>'office_sale','title'=>'Atlanta 오피스 빌딩 매매','bedrooms'=>0,'bathrooms'=>4,'sqft'=>5000,'price'=>1200000],
            ['type'=>'sale','property_type'=>'store_sale','title'=>'Koreatown 상가 매매 (1층)','bedrooms'=>0,'bathrooms'=>2,'sqft'=>1500,'price'=>950000],
            ['type'=>'sale','property_type'=>'building','title'=>'Dallas 소규모 빌딩 매매','bedrooms'=>0,'bathrooms'=>6,'sqft'=>8000,'price'=>2500000],
            ['type'=>'sale','property_type'=>'retail_sale','title'=>'Seattle 소매 공간 매매','bedrooms'=>0,'bathrooms'=>1,'sqft'=>1200,'price'=>680000],
            ['type'=>'sale','property_type'=>'etc_home','title'=>'Carrollton 듀플렉스 매매','bedrooms'=>4,'bathrooms'=>3,'sqft'=>2200,'price'=>380000],
            ['type'=>'sale','property_type'=>'3br_plus','title'=>'Suwanee 학군좋은 5BR 하우스','bedrooms'=>5,'bathrooms'=>4,'sqft'=>3500,'price'=>720000],
            ['type'=>'sale','property_type'=>'etc_commercial','title'=>'Atlanta 토지 매매 (0.5에이커)','bedrooms'=>0,'bathrooms'=>0,'sqft'=>21780,'price'=>250000],
        ];

        $all = array_merge($rentItems, $saleItems);
        $descriptions = [
            '깨끗하고 밝은 유닛입니다. 주차 포함. 빨래 건물 내 있음.',
            '한인마트, 한식당 5분 거리. 학군 우수. 즉시 입주 가능.',
            '조용한 주거지역. 공원 도보 가능. 반려동물 협의.',
            '리모델링 완료. 새 주방, 새 화장실. 크레딧 체크 필요.',
            '교통 편리 (고속도로 5분). 쇼핑몰 근처. 커뮤니티 풀 포함.',
            '안전한 게이티드 커뮤니티. 피트니스 센터. 관리비 포함.',
            '전문 관리, 24시간 유지보수 서비스. 빠른 대응.',
            '넓은 발코니, 뷰 좋음. 층간 소음 적음. 엘리베이터.',
        ];

        foreach ($all as $item) {
            $loc = $cities[array_rand($cities)];
            RealEstateListing::create(array_merge($item, [
                'user_id' => $userIds[array_rand($userIds)],
                'content' => $descriptions[array_rand($descriptions)] . "\n\n" . $descriptions[array_rand($descriptions)],
                'address' => rand(100, 9999) . ' ' . ['Main St', 'Peachtree Rd', 'Olympic Blvd', 'Broadway', 'Market St'][array_rand(['Main St', 'Peachtree Rd', 'Olympic Blvd', 'Broadway', 'Market St'])],
                'city' => $loc['city'],
                'state' => $loc['state'],
                'zipcode' => $loc['zip'],
                'lat' => $loc['lat'] + (rand(-100, 100) / 10000),
                'lng' => $loc['lng'] + (rand(-100, 100) / 10000),
                'is_active' => true,
                'contact_phone' => '470-' . rand(100,999) . '-' . rand(1000,9999),
                'contact_email' => 'realestate' . rand(1,50) . '@example.com',
            ]));
        }

        $this->command->info('Real estate seeded: ' . count($all) . ' listings');
    }
}
