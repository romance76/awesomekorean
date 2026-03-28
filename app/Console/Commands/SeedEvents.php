<?php

namespace App\Console\Commands;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SeedEvents extends Command
{
    protected $signature = 'events:seed {--count=55 : Number of events to create}';
    protected $description = 'Seed realistic Korean-American community events for the next 3 months';

    public function handle()
    {
        $count = (int) $this->option('count');
        $now = Carbon::now();
        $endDate = $now->copy()->addMonths(3);

        $events = $this->getEventTemplates();

        $created = 0;
        while ($created < $count) {
            $template = $events[$created % count($events)];

            $eventDate = $now->copy()->addDays(rand(1, 90))->setHour(rand(9, 20))->setMinute(rand(0, 1) * 30)->setSecond(0);

            $location = $template['locations'][array_rand($template['locations'])];

            Event::create([
                'user_id'        => rand(1, 137),
                'title'          => $template['title'],
                'description'    => $template['description'],
                'location'       => $location['address'],
                'region'         => $location['region'],
                'category'       => $template['category'],
                'max_attendees'  => $template['max_attendees'],
                'attendee_count' => rand(0, (int) ($template['max_attendees'] * 0.6)),
                'price'          => $template['price'],
                'image'          => null,
                'event_date'     => $eventDate,
                'is_online'      => $template['is_online'] ?? false,
                'status'         => 'published',
            ]);

            $created++;
        }

        $this->info("{$created}개의 커뮤니티 이벤트가 생성되었습니다.");
        return 0;
    }

    private function getEventTemplates(): array
    {
        $laLocations = [
            ['address' => '3250 Wilshire Blvd, Los Angeles, CA 90010', 'region' => 'LA'],
            ['address' => '621 S Western Ave, Los Angeles, CA 90005', 'region' => 'LA'],
            ['address' => '928 S Western Ave #200, Los Angeles, CA 90006', 'region' => 'LA'],
        ];

        $atlantaLocations = [
            ['address' => '5150 Buford Hwy, Doraville, GA 30340', 'region' => 'Atlanta'],
            ['address' => '6035 Peachtree Rd, Doraville, GA 30360', 'region' => 'Atlanta'],
        ];

        $nyLocations = [
            ['address' => '149-06 41st Ave, Flushing, NY 11355', 'region' => 'NY'],
            ['address' => '136-80 39th Ave, Flushing, NY 11354', 'region' => 'NY'],
        ];

        $chicagoLocations = [
            ['address' => '4300 N California Ave, Chicago, IL 60618', 'region' => 'Chicago'],
        ];

        $seattleLocations = [
            ['address' => '2801 Beacon Ave S, Seattle, WA 98144', 'region' => 'Seattle'],
        ];

        $dallasLocations = [
            ['address' => '2625 Old Denton Rd, Carrollton, TX 75007', 'region' => 'Dallas'],
        ];

        $houstonLocations = [
            ['address' => '9896 Bellaire Blvd, Houston, TX 77036', 'region' => 'Houston'],
        ];

        $sfLocations = [
            ['address' => '1360 Post St, San Francisco, CA 94109', 'region' => 'SF'],
        ];

        $allLocations = array_merge(
            $laLocations, $atlantaLocations, $nyLocations, $chicagoLocations,
            $seattleLocations, $dallasLocations, $houstonLocations, $sfLocations
        );

        $onlineLocation = [
            ['address' => 'Zoom 온라인', 'region' => 'Online'],
        ];

        return [
            // ===== 모임 (meetup) =====
            [
                'title'          => '한인 엄마들의 모임',
                'description'    => '한인 엄마들이 모여 육아 경험을 나누고 서로 응원하는 정기 모임입니다. 아이들과 함께 참석 가능하며, 간단한 다과가 준비됩니다.',
                'category'       => 'meetup',
                'max_attendees'  => 30,
                'price'          => 0,
                'locations'      => $laLocations,
            ],
            [
                'title'          => '한인 시니어 친목 모임',
                'description'    => '시니어 한인분들을 위한 친목 모임입니다. 함께 차를 마시며 이야기 나누고 건강 정보를 공유합니다. 매주 정기적으로 진행됩니다.',
                'category'       => 'meetup',
                'max_attendees'  => 40,
                'price'          => 0,
                'locations'      => $atlantaLocations,
            ],
            [
                'title'          => '한인 청년 네트워킹 모임',
                'description'    => '20-30대 한인 청년들이 모여 진로, 직장생활, 미국 정착 경험을 나누는 모임입니다. 새로운 인맥을 만들 수 있는 좋은 기회입니다.',
                'category'       => 'meetup',
                'max_attendees'  => 50,
                'price'          => 5.00,
                'locations'      => $nyLocations,
            ],
            [
                'title'          => '한인 독서 모임',
                'description'    => '매월 한 권의 책을 선정하여 함께 읽고 토론하는 독서 모임입니다. 한국어와 영어 도서 모두 포함되며, 누구나 참여 가능합니다.',
                'category'       => 'meetup',
                'max_attendees'  => 20,
                'price'          => 0,
                'locations'      => $sfLocations,
            ],
            [
                'title'          => '한인 등산 모임',
                'description'    => '주말마다 함께 등산하며 건강도 챙기고 친목도 다지는 모임입니다. 초보자부터 경험자까지 모두 환영합니다. 등산 후 점심 식사도 함께합니다.',
                'category'       => 'meetup',
                'max_attendees'  => 25,
                'price'          => 0,
                'locations'      => $seattleLocations,
            ],
            [
                'title'          => '한인 사진 동호회 출사',
                'description'    => '사진을 좋아하는 한인분들이 함께 출사를 나가는 모임입니다. 풍경, 인물, 거리 사진 등 다양한 주제로 촬영하며 서로의 작품을 공유합니다.',
                'category'       => 'meetup',
                'max_attendees'  => 15,
                'price'          => 0,
                'locations'      => $laLocations,
            ],
            [
                'title'          => '한인 싱글 소셜 모임',
                'description'    => '미혼 한인들을 위한 소셜 이벤트입니다. 편안한 분위기에서 새로운 사람들을 만나고 대화를 나눌 수 있습니다. 가벼운 음료와 핑거푸드가 제공됩니다.',
                'category'       => 'meetup',
                'max_attendees'  => 40,
                'price'          => 15.00,
                'locations'      => $chicagoLocations,
            ],
            [
                'title'          => '한인 와인 동호회',
                'description'    => '와인을 좋아하는 한인분들이 모여 다양한 와인을 시음하고 와인에 대한 지식을 나누는 모임입니다. 매회 다른 테마로 진행됩니다.',
                'category'       => 'meetup',
                'max_attendees'  => 20,
                'price'          => 25.00,
                'locations'      => $dallasLocations,
            ],

            // ===== 음식 (food) =====
            [
                'title'          => '한식 축제 - Korean Food Festival',
                'description'    => '한인 커뮤니티가 함께하는 대규모 한식 축제입니다. 비빔밥, 떡볶이, 김치전 등 다양한 한국 음식을 즐길 수 있으며, 한식 요리 시연도 진행됩니다.',
                'category'       => 'food',
                'max_attendees'  => 500,
                'price'          => 10.00,
                'locations'      => $laLocations,
            ],
            [
                'title'          => '김치 담그기 워크숍',
                'description'    => '전통 방식으로 김치를 직접 담가보는 체험 워크숍입니다. 재료와 도구가 모두 제공되며, 만든 김치는 집으로 가져갈 수 있습니다.',
                'category'       => 'food',
                'max_attendees'  => 25,
                'price'          => 35.00,
                'locations'      => $atlantaLocations,
            ],
            [
                'title'          => '한식 요리 교실 - 불고기와 잡채',
                'description'    => '한국의 대표 요리 불고기와 잡채를 함께 만들어봅니다. 전문 셰프의 지도 하에 진행되며, 요리 후 함께 식사합니다.',
                'category'       => 'food',
                'max_attendees'  => 20,
                'price'          => 40.00,
                'locations'      => $nyLocations,
            ],
            [
                'title'          => '떡 만들기 체험',
                'description'    => '한국 전통 떡을 직접 만들어보는 체험 행사입니다. 송편, 인절미 등 다양한 떡을 만들어보고 시식할 수 있습니다. 어린이 동반 환영합니다.',
                'category'       => 'food',
                'max_attendees'  => 30,
                'price'          => 20.00,
                'locations'      => $houstonLocations,
            ],
            [
                'title'          => '한인 BBQ 파티',
                'description'    => '한인 커뮤니티 야외 바비큐 파티입니다. 삼겹살, 갈비 등 한국식 바비큐와 함께 즐거운 시간을 보내세요. 가족 단위 참석을 환영합니다.',
                'category'       => 'food',
                'max_attendees'  => 100,
                'price'          => 15.00,
                'locations'      => $dallasLocations,
            ],
            [
                'title'          => '한식 디저트 클래스 - 호떡과 붕어빵',
                'description'    => '한국의 인기 길거리 디저트 호떡과 붕어빵을 직접 만들어보는 클래스입니다. 초보자도 쉽게 따라할 수 있으며, 모든 재료가 제공됩니다.',
                'category'       => 'food',
                'max_attendees'  => 15,
                'price'          => 30.00,
                'locations'      => $sfLocations,
            ],
            [
                'title'          => '전통 한식 상차림 체험',
                'description'    => '한국 전통 상차림을 배우고 직접 체험하는 문화 행사입니다. 반찬 만들기부터 상차림 예절까지 배울 수 있습니다.',
                'category'       => 'food',
                'max_attendees'  => 20,
                'price'          => 25.00,
                'locations'      => $chicagoLocations,
            ],

            // ===== 문화 (culture) =====
            [
                'title'          => 'K-POP 콘서트 - 커뮤니티 나이트',
                'description'    => '한인 커뮤니티를 위한 K-POP 콘서트입니다. 지역 K-POP 댄스팀의 공연과 함께 인기 K-POP 노래를 즐길 수 있는 특별한 밤입니다.',
                'category'       => 'culture',
                'max_attendees'  => 300,
                'price'          => 20.00,
                'locations'      => $laLocations,
            ],
            [
                'title'          => '한국 영화 상영회 - Korean Film Night',
                'description'    => '매월 진행되는 한국 영화 상영회입니다. 최신 한국 영화를 영어 자막과 함께 감상하고, 영화 후 토론 시간이 있습니다.',
                'category'       => 'culture',
                'max_attendees'  => 80,
                'price'          => 8.00,
                'locations'      => $nyLocations,
            ],
            [
                'title'          => '한국 전통 무용 공연',
                'description'    => '부채춤, 탈춤 등 한국의 아름다운 전통 무용을 감상할 수 있는 공연입니다. 공연 후 체험 시간도 마련되어 있습니다.',
                'category'       => 'culture',
                'max_attendees'  => 150,
                'price'          => 15.00,
                'locations'      => $atlantaLocations,
            ],
            [
                'title'          => '한글 서예 체험 워크숍',
                'description'    => '한글의 아름다움을 서예로 표현해보는 워크숍입니다. 붓과 먹을 사용하여 한글 서예를 배우며, 작품을 완성하여 가져갈 수 있습니다.',
                'category'       => 'culture',
                'max_attendees'  => 20,
                'price'          => 25.00,
                'locations'      => $seattleLocations,
            ],
            [
                'title'          => '한복 체험 & 사진 촬영회',
                'description'    => '아름다운 한복을 입어보고 전문 사진사가 촬영해주는 특별한 이벤트입니다. 다양한 한복이 준비되어 있으며, 촬영된 사진은 디지털로 제공됩니다.',
                'category'       => 'culture',
                'max_attendees'  => 40,
                'price'          => 30.00,
                'locations'      => $houstonLocations,
            ],
            [
                'title'          => 'K-POP 댄스 워크숍',
                'description'    => '인기 K-POP 안무를 배워보는 댄스 워크숍입니다. 초보자도 쉽게 따라할 수 있도록 단계별로 진행됩니다. 운동복과 실내화를 준비해주세요.',
                'category'       => 'culture',
                'max_attendees'  => 30,
                'price'          => 15.00,
                'locations'      => $sfLocations,
            ],
            [
                'title'          => '사물놀이 체험 워크숍',
                'description'    => '한국 전통 타악기 사물놀이를 배워보는 체험 프로그램입니다. 꽹과리, 장구, 북, 징의 기본 연주법을 배우고 합주를 경험해봅니다.',
                'category'       => 'culture',
                'max_attendees'  => 20,
                'price'          => 10.00,
                'locations'      => $chicagoLocations,
            ],
            [
                'title'          => '한인 미술 전시회',
                'description'    => '한인 작가들의 작품을 감상할 수 있는 미술 전시회입니다. 회화, 조각, 설치미술 등 다양한 장르의 작품이 전시되며, 작가와의 대화 시간도 있습니다.',
                'category'       => 'culture',
                'max_attendees'  => 100,
                'price'          => 0,
                'locations'      => $laLocations,
            ],

            // ===== 스포츠 (sports) =====
            [
                'title'          => '한인 골프 토너먼트',
                'description'    => '한인 커뮤니티 연례 골프 대회입니다. 초보자부터 숙련자까지 모두 참가 가능하며, 식사와 상품이 포함되어 있습니다.',
                'category'       => 'sports',
                'max_attendees'  => 80,
                'price'          => 120.00,
                'locations'      => $laLocations,
            ],
            [
                'title'          => '한인 축구 리그',
                'description'    => '한인 축구 동호회 리그전입니다. 매주 주말 경기가 진행되며, 팀 등록과 개인 참가 모두 가능합니다. 실력에 관계없이 즐겁게 참여하세요.',
                'category'       => 'sports',
                'max_attendees'  => 60,
                'price'          => 30.00,
                'locations'      => $atlantaLocations,
            ],
            [
                'title'          => '한인 배드민턴 대회',
                'description'    => '한인 배드민턴 동호회 주최 대회입니다. 단식, 복식, 혼합복식 종목으로 진행되며, 참가 신청 시 실력 수준을 선택해주세요.',
                'category'       => 'sports',
                'max_attendees'  => 40,
                'price'          => 20.00,
                'locations'      => $nyLocations,
            ],
            [
                'title'          => '한인 볼링 대회',
                'description'    => '한인 커뮤니티 볼링 대회입니다. 팀별 또는 개인전으로 진행되며, 볼링 후 뒤풀이도 함께합니다. 가족 참여를 환영합니다.',
                'category'       => 'sports',
                'max_attendees'  => 50,
                'price'          => 25.00,
                'locations'      => $dallasLocations,
            ],
            [
                'title'          => '한인 마라톤 훈련 모임',
                'description'    => '마라톤을 준비하는 한인들의 훈련 모임입니다. 5K부터 풀마라톤까지 목표에 맞는 훈련 프로그램이 제공됩니다. 매주 토요일 아침 진행됩니다.',
                'category'       => 'sports',
                'max_attendees'  => 30,
                'price'          => 0,
                'locations'      => $seattleLocations,
            ],
            [
                'title'          => '한인 테니스 클리닉',
                'description'    => '테니스를 배우고 싶은 한인분들을 위한 클리닉입니다. 전문 코치의 지도 하에 기초부터 배울 수 있으며, 라켓 대여가 가능합니다.',
                'category'       => 'sports',
                'max_attendees'  => 16,
                'price'          => 35.00,
                'locations'      => $houstonLocations,
            ],
            [
                'title'          => '한인 탁구 대회',
                'description'    => '한인 탁구 동호회 주최 정기 대회입니다. A, B, C 등급별로 나누어 진행되며, 초보자 레슨도 병행됩니다.',
                'category'       => 'sports',
                'max_attendees'  => 32,
                'price'          => 15.00,
                'locations'      => $chicagoLocations,
            ],
            [
                'title'          => '한인 요가 클래스',
                'description'    => '한국어로 진행되는 요가 클래스입니다. 스트레스 해소와 건강 증진을 위한 프로그램으로, 요가 매트만 준비하시면 됩니다.',
                'category'       => 'sports',
                'max_attendees'  => 25,
                'price'          => 10.00,
                'locations'      => $sfLocations,
            ],

            // ===== 교육 (education) =====
            [
                'title'          => 'SAT 집중 대비반 설명회',
                'description'    => 'SAT 시험을 준비하는 학생과 학부모를 위한 설명회입니다. 효과적인 학습 전략과 시험 팁을 공유하며, 무료 모의고사 기회도 제공됩니다.',
                'category'       => 'education',
                'max_attendees'  => 100,
                'price'          => 0,
                'locations'      => $laLocations,
            ],
            [
                'title'          => '한국어 교실 - 초급반',
                'description'    => '한국어를 처음 배우는 분들을 위한 초급 한국어 교실입니다. 한글 읽기와 기본 회화를 중심으로 8주 과정으로 진행됩니다.',
                'category'       => 'education',
                'max_attendees'  => 20,
                'price'          => 50.00,
                'locations'      => $atlantaLocations,
            ],
            [
                'title'          => '미국 대학 입시 세미나',
                'description'    => '한인 학생과 학부모를 위한 대학 입시 전략 세미나입니다. 아이비리그 합격생 패널 토론과 입시 컨설턴트의 조언을 들을 수 있습니다.',
                'category'       => 'education',
                'max_attendees'  => 150,
                'price'          => 0,
                'locations'      => $nyLocations,
            ],
            [
                'title'          => '한인 코딩 교실 - 어린이/청소년',
                'description'    => '초등학생과 중학생을 대상으로 한 코딩 교실입니다. 스크래치와 파이썬 기초를 한국어로 배울 수 있으며, 노트북을 지참해주세요.',
                'category'       => 'education',
                'max_attendees'  => 20,
                'price'          => 30.00,
                'locations'      => $seattleLocations,
            ],
            [
                'title'          => '이민법 세미나 - 비자와 영주권',
                'description'    => '한인 이민 변호사가 진행하는 이민법 세미나입니다. H1B, L1 비자 및 영주권 신청 절차에 대해 자세히 설명하고 Q&A 시간이 있습니다.',
                'category'       => 'education',
                'max_attendees'  => 80,
                'price'          => 0,
                'locations'      => $dallasLocations,
            ],
            [
                'title'          => '한인 부동산 투자 세미나',
                'description'    => '미국 부동산 투자에 관심 있는 한인분들을 위한 세미나입니다. 부동산 시장 동향, 투자 전략, 세금 관련 정보를 한국어로 설명해드립니다.',
                'category'       => 'education',
                'max_attendees'  => 60,
                'price'          => 10.00,
                'locations'      => $houstonLocations,
            ],
            [
                'title'          => '영어 회화 스터디 그룹',
                'description'    => '한인분들을 위한 영어 회화 스터디 그룹입니다. 일상 회화부터 비즈니스 영어까지 레벨별로 진행되며, 원어민 튜터가 함께합니다.',
                'category'       => 'education',
                'max_attendees'  => 15,
                'price'          => 10.00,
                'is_online'      => true,
                'locations'      => $onlineLocation,
            ],
            [
                'title'          => '한인 재정 플래닝 워크숍',
                'description'    => '은퇴 준비, 절세 전략, 자녀 학자금 저축 등 한인 가정에 필요한 재정 계획을 전문가와 함께 배우는 워크숍입니다.',
                'category'       => 'education',
                'max_attendees'  => 40,
                'price'          => 0,
                'locations'      => $sfLocations,
            ],

            // ===== 비즈니스 (business) =====
            [
                'title'          => '한인 비즈니스 네트워킹',
                'description'    => '한인 사업가, 전문직 종사자들의 네트워킹 이벤트입니다. 명함 교환과 함께 비즈니스 기회를 모색하고, 경험을 나눌 수 있는 자리입니다.',
                'category'       => 'business',
                'max_attendees'  => 80,
                'price'          => 20.00,
                'locations'      => $laLocations,
            ],
            [
                'title'          => '한인 창업 세미나',
                'description'    => '미국에서 창업을 계획 중인 한인분들을 위한 세미나입니다. 사업 등록, 세금, 자금 조달 등 실질적인 정보를 한국어로 안내해드립니다.',
                'category'       => 'business',
                'max_attendees'  => 60,
                'price'          => 0,
                'locations'      => $atlantaLocations,
            ],
            [
                'title'          => '한인 IT 전문가 밋업',
                'description'    => 'IT 업계에 종사하는 한인 전문가들의 정기 밋업입니다. 최신 기술 트렌드를 공유하고, 커리어 개발에 대해 논의합니다.',
                'category'       => 'business',
                'max_attendees'  => 50,
                'price'          => 10.00,
                'locations'      => $sfLocations,
            ],
            [
                'title'          => '한인 여성 리더십 포럼',
                'description'    => '한인 여성 리더들이 모여 경험과 통찰을 나누는 포럼입니다. 직장에서의 리더십, 워라밸, 커리어 전환 등 다양한 주제를 다룹니다.',
                'category'       => 'business',
                'max_attendees'  => 70,
                'price'          => 15.00,
                'locations'      => $nyLocations,
            ],
            [
                'title'          => '한인 소상공인 지원 워크숍',
                'description'    => '한인 소상공인을 위한 정부 지원 프로그램과 대출 신청 방법을 안내하는 워크숍입니다. SBA 대출, 보조금 등 실질적인 정보를 제공합니다.',
                'category'       => 'business',
                'max_attendees'  => 40,
                'price'          => 0,
                'locations'      => $dallasLocations,
            ],
            [
                'title'          => '한인 의료 전문가 컨퍼런스',
                'description'    => '한인 의사, 치과의사, 약사 등 의료 전문가들의 연례 컨퍼런스입니다. 최신 의료 정보 공유와 네트워킹의 기회가 마련되어 있습니다.',
                'category'       => 'business',
                'max_attendees'  => 100,
                'price'          => 50.00,
                'locations'      => $houstonLocations,
            ],
            [
                'title'          => '한인 부동산 에이전트 밋업',
                'description'    => '한인 부동산 에이전트들의 정보 교류 및 네트워킹 모임입니다. 지역별 시장 동향과 마케팅 전략을 공유합니다.',
                'category'       => 'business',
                'max_attendees'  => 30,
                'price'          => 10.00,
                'locations'      => $chicagoLocations,
            ],
            [
                'title'          => '한인 이커머스 창업 워크숍',
                'description'    => '아마존, 쇼피파이 등 온라인 플랫폼에서 비즈니스를 시작하려는 분들을 위한 실전 워크숍입니다. 상품 소싱부터 마케팅까지 단계별로 안내합니다.',
                'category'       => 'business',
                'max_attendees'  => 35,
                'price'          => 25.00,
                'is_online'      => true,
                'locations'      => $onlineLocation,
            ],

            // ===== 일반 (general) =====
            [
                'title'          => '한인 커뮤니티 봄맞이 축제',
                'description'    => '한인 커뮤니티의 봄맞이 대축제입니다. 공연, 음식, 게임 등 다양한 프로그램이 준비되어 있으며, 온 가족이 함께 즐길 수 있습니다.',
                'category'       => 'general',
                'max_attendees'  => 500,
                'price'          => 0,
                'locations'      => $laLocations,
            ],
            [
                'title'          => '한인회 정기 총회',
                'description'    => '지역 한인회 정기 총회입니다. 지난 분기 활동 보고와 향후 계획을 논의하며, 커뮤니티 현안에 대해 함께 이야기합니다.',
                'category'       => 'general',
                'max_attendees'  => 200,
                'price'          => 0,
                'locations'      => $allLocations,
            ],
            [
                'title'          => '한인 자선 바자회',
                'description'    => '한인 커뮤니티를 위한 자선 바자회입니다. 수익금은 지역 한인 장학금과 어려운 이웃 돕기에 사용됩니다. 물품 기부도 환영합니다.',
                'category'       => 'general',
                'max_attendees'  => 200,
                'price'          => 0,
                'locations'      => $nyLocations,
            ],
            [
                'title'          => '한인 교회 부활절 연합 예배',
                'description'    => '지역 한인 교회들이 함께하는 부활절 연합 예배입니다. 예배 후 친교와 함께 음식이 준비되어 있습니다.',
                'category'       => 'general',
                'max_attendees'  => 300,
                'price'          => 0,
                'locations'      => $atlantaLocations,
            ],
            [
                'title'          => '한인 헌혈 캠페인',
                'description'    => '한인 커뮤니티 헌혈 캠페인입니다. 건강한 성인이면 누구나 참여 가능하며, 헌혈 후 간식과 음료가 제공됩니다. 사전 예약을 권장합니다.',
                'category'       => 'general',
                'max_attendees'  => 100,
                'price'          => 0,
                'locations'      => $seattleLocations,
            ],
        ];
    }
}
