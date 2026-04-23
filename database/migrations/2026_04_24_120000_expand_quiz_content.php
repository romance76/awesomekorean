<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        // ═══════════════════════════════════════════════════
        // 1) 국기 — flagcdn.com 실사진으로 교체 + 40+ 국가
        // ═══════════════════════════════════════════════════
        DB::table('quiz_questions')->where('game_slug', 'flag')->delete();

        $flags = [
            // Level 1 — 친숙한 국가 10개
            [1, '대한민국',   'kr'],
            [1, '미국',       'us'],
            [1, '일본',       'jp'],
            [1, '중국',       'cn'],
            [1, '프랑스',     'fr'],
            [1, '영국',       'gb'],
            [1, '독일',       'de'],
            [1, '이탈리아',   'it'],
            [1, '캐나다',     'ca'],
            [1, '호주',       'au'],

            // Level 2 — 주요 국가 15개
            [2, '브라질',     'br'],
            [2, '멕시코',     'mx'],
            [2, '스페인',     'es'],
            [2, '러시아',     'ru'],
            [2, '인도',       'in'],
            [2, '베트남',     'vn'],
            [2, '태국',       'th'],
            [2, '네덜란드',   'nl'],
            [2, '스위스',     'ch'],
            [2, '스웨덴',     'se'],
            [2, '노르웨이',   'no'],
            [2, '핀란드',     'fi'],
            [2, '덴마크',     'dk'],
            [2, '폴란드',     'pl'],
            [2, '포르투갈',   'pt'],

            // Level 3 — 중급 국가 15개
            [3, '아르헨티나', 'ar'],
            [3, '튀르키예',   'tr'],
            [3, '이집트',     'eg'],
            [3, '남아프리카', 'za'],
            [3, '사우디아라비아', 'sa'],
            [3, '아일랜드',   'ie'],
            [3, '그리스',     'gr'],
            [3, '오스트리아', 'at'],
            [3, '체코',       'cz'],
            [3, '우크라이나', 'ua'],
            [3, '필리핀',     'ph'],
            [3, '인도네시아', 'id'],
            [3, '말레이시아', 'my'],
            [3, '싱가포르',   'sg'],
            [3, '뉴질랜드',   'nz'],

            // Level 4 — 고급 15개
            [4, '이스라엘',   'il'],
            [4, '이란',       'ir'],
            [4, '몽골',       'mn'],
            [4, '카자흐스탄', 'kz'],
            [4, '쿠바',       'cu'],
            [4, '칠레',       'cl'],
            [4, '페루',       'pe'],
            [4, '콜롬비아',   'co'],
            [4, '케냐',       'ke'],
            [4, '나이지리아', 'ng'],
            [4, '에티오피아', 'et'],
            [4, '모로코',     'ma'],
            [4, '아랍에미리트', 'ae'],
            [4, '헝가리',     'hu'],
            [4, '크로아티아', 'hr'],

            // Level 5 — 마스터 10개
            [5, '부탄',       'bt'],
            [5, '네팔',       'np'],
            [5, '방글라데시', 'bd'],
            [5, '파키스탄',   'pk'],
            [5, '스리랑카',   'lk'],
            [5, '조지아',     'ge'],
            [5, '에스토니아', 'ee'],
            [5, '라트비아',   'lv'],
            [5, '리투아니아', 'lt'],
            [5, '아이슬란드', 'is'],
        ];
        $rows = [];
        foreach ($flags as $i => $f) {
            $rows[] = [
                'game_slug'  => 'flag',
                'level'      => $f[0],
                'answer'     => $f[1],
                'image_url'  => "https://flagcdn.com/w320/{$f[2]}.png",
                'is_active'  => true,
                'sort_order' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('quiz_questions')->insert($rows);

        // ═══════════════════════════════════════════════════
        // 2) 사자성어 30+
        // ═══════════════════════════════════════════════════
        DB::table('quiz_questions')->where('game_slug', 'idiom')->delete();

        // 각 항목: [level, 정답(사자성어), 힌트(뜻)]
        $idioms = [
            [1, '일석이조', '한 가지 일로 두 가지 이익을 얻음'],
            [1, '고진감래', '고생 끝에 즐거움이 옴'],
            [1, '금상첨화', '좋은 일에 또 좋은 일이 더해짐'],
            [1, '작심삼일', '결심이 사흘을 못 감'],
            [1, '다다익선', '많으면 많을수록 좋음'],
            [1, '유비무환', '준비가 있으면 근심이 없음'],
            [1, '일거양득', '한 가지 행동으로 두 이익을 얻음'],
            [1, '대기만성', '큰 그릇은 늦게 만들어짐'],

            [2, '새옹지마', '인생의 길흉화복은 알 수 없음'],
            [2, '우공이산', '쉬지 않고 노력하면 큰 일을 이룸'],
            [2, '형설지공', '어려움 속에서 학문을 이루는 노력'],
            [2, '타산지석', '남의 잘못을 거울삼아 자신을 경계함'],
            [2, '감언이설', '귀에 달콤한 말로 꾀는 것'],
            [2, '각골난망', '은혜가 뼈에 새겨져 잊을 수 없음'],
            [2, '견물생심', '물건을 보면 갖고 싶은 마음이 생김'],
            [2, '동상이몽', '같은 자리에 있으면서 다른 생각을 함'],

            [3, '우이독경', '쇠귀에 경 읽기 — 아무리 말해도 못 알아들음'],
            [3, '표리부동', '겉과 속이 다름'],
            [3, '사면초가', '사방이 적으로 둘러싸여 고립된 상태'],
            [3, '조삼모사', '잔꾀로 남을 속이는 것'],
            [3, '오리무중', '안개 속에 있어 갈피를 못 잡음'],
            [3, '청출어람', '제자가 스승보다 나음'],
            [3, '권선징악', '선을 권하고 악을 벌함'],
            [3, '고립무원', '홀로 떨어져 도움이 없음'],

            [4, '백척간두', '매우 위태로운 상황'],
            [4, '결초보은', '죽어서도 은혜를 갚음'],
            [4, '자승자박', '자기 줄에 자기가 묶임 — 자업자득'],
            [4, '교각살우', '작은 일을 고치려다 큰 일을 그르침'],
            [4, '과유불급', '지나친 것은 모자람과 같다'],
            [4, '각주구검', '시대 변화에 맞지 않는 융통성 없는 행동'],

            [5, '수어지교', '물과 물고기처럼 떼어놓을 수 없는 사이'],
            [5, '토사구팽', '쓸모없어지면 버림받음'],
            [5, '지록위마', '사슴을 말이라 우기듯 옳지 않은 것을 강요함'],
            [5, '괄목상대', '눈을 비비고 상대를 봄 — 몰라보게 발전함'],
        ];
        $rows = [];
        foreach ($idioms as $i => $it) {
            $rows[] = [
                'game_slug'  => 'idiom',
                'level'      => $it[0],
                'answer'     => $it[1],
                'hint'       => $it[2],
                'is_active'  => true,
                'sort_order' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('quiz_questions')->insert($rows);

        // ═══════════════════════════════════════════════════
        // 3) 단어카드 — 한영 매칭 60+, 실사진 URL
        //    이미지는 Unsplash Source (키워드 기반 고정된 사진)
        // ═══════════════════════════════════════════════════
        DB::table('quiz_questions')->where('game_slug', 'wordcard')->delete();

        // 각 항목: [level, 한국어, 이모지hex(fallback), Unsplash 쿼리]
        $words = [
            // Level 1 — 과일/음식 기초 15
            [1, '사과',    '1f34e',  'apple-fruit'],
            [1, '바나나',  '1f34c',  'banana'],
            [1, '포도',    '1f347',  'grapes'],
            [1, '딸기',    '1f353',  'strawberry'],
            [1, '수박',    '1f349',  'watermelon'],
            [1, '오렌지',  '1f34a',  'orange-fruit'],
            [1, '빵',      '1f35e',  'bread'],
            [1, '우유',    '1f95b',  'milk'],
            [1, '치즈',    '1f9c0',  'cheese'],
            [1, '햄버거',  '1f354',  'hamburger'],
            [1, '피자',    '1f355',  'pizza'],
            [1, '케이크',  '1f370',  'cake'],
            [1, '도넛',    '1f369',  'donut'],
            [1, '아이스크림','1f368','ice-cream'],
            [1, '쿠키',    '1f36a',  'cookie'],

            // Level 2 — 동물 15
            [2, '강아지',  '1f436',  'dog'],
            [2, '고양이',  '1f431',  'cat'],
            [2, '토끼',    '1f430',  'rabbit'],
            [2, '말',      '1f40e',  'horse'],
            [2, '소',      '1f42e',  'cow'],
            [2, '양',      '1f411',  'sheep'],
            [2, '닭',      '1f414',  'chicken'],
            [2, '오리',    '1f986',  'duck'],
            [2, '물고기',  '1f41f',  'fish'],
            [2, '거북이',  '1f422',  'turtle'],
            [2, '나비',    '1f98b',  'butterfly'],
            [2, '꿀벌',    '1f41d',  'bee'],
            [2, '곰',      '1f43b',  'bear'],
            [2, '사자',    '1f981',  'lion'],
            [2, '호랑이',  '1f42f',  'tiger'],

            // Level 3 — 일상 사물 15
            [3, '신발',    '1f45f',  'shoes'],
            [3, '가방',    '1f45c',  'backpack'],
            [3, '모자',    '1f3a9',  'hat'],
            [3, '우산',    '2602',   'umbrella'],
            [3, '시계',    '231a',   'wristwatch'],
            [3, '안경',    '1f453',  'eyeglasses'],
            [3, '열쇠',    '1f511',  'key'],
            [3, '책',      '1f4d6',  'book'],
            [3, '연필',    '270f',   'pencil'],
            [3, '가위',    '2702',   'scissors'],
            [3, '자동차',  '1f697',  'car'],
            [3, '자전거',  '1f6b2',  'bicycle'],
            [3, '비행기',  '2708',   'airplane'],
            [3, '기차',    '1f686',  'train'],
            [3, '배',      '26f5',   'sailboat'],

            // Level 4 — 자연/날씨 10
            [4, '해',      '2600',   'sun'],
            [4, '달',      '1f319',  'moon'],
            [4, '구름',    '2601',   'cloud'],
            [4, '비',      '1f327',  'rain'],
            [4, '눈',      '2744',   'snowflake'],
            [4, '번개',    '26a1',   'lightning'],
            [4, '나무',    '1f333',  'tree'],
            [4, '꽃',      '1f337',  'flower'],
            [4, '바다',    '1f30a',  'ocean-wave'],
            [4, '산',      '26f0',   'mountain'],

            // Level 5 — 집/생활 10
            [5, '집',      '1f3e0',  'house-home'],
            [5, '의자',    '1fa91',  'chair'],
            [5, '침대',    '1f6cf',  'bed-bedroom'],
            [5, '전화기',  '1f4f1',  'mobile-phone'],
            [5, '컴퓨터',  '1f4bb',  'computer-laptop'],
            [5, '카메라',  '1f4f7',  'camera'],
            [5, '선물',    '1f381',  'gift-present'],
            [5, '풍선',    '1f388',  'balloon'],
            [5, '촛불',    '1f56f',  'candle'],
            [5, '별',      '2b50',   'star'],
        ];
        $rows = [];
        foreach ($words as $i => $w) {
            $rows[] = [
                'game_slug'  => 'wordcard',
                'level'      => $w[0],
                'answer'     => $w[1],
                'emoji_hex'  => $w[2],
                // Unsplash Source 는 랜덤이라 seed 를 안쓰면 매번 다르므로 이모지 fallback 을 주 이미지로.
                // 관리자에서 image_url 추가 업로드 가능.
                'image_url'  => null,
                'is_active'  => true,
                'sort_order' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('quiz_questions')->insert($rows);

        // ═══════════════════════════════════════════════════
        // 4) 동물 — 실사진 URL 업데이트 (Wikimedia Commons)
        //    fallback 으로 emoji_hex 는 유지
        // ═══════════════════════════════════════════════════
        $animalPhotos = [
            '강아지'   => 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=400&h=400&fit=crop',
            '고양이'   => 'https://images.unsplash.com/photo-1533738363-b7f9aef128ce?w=400&h=400&fit=crop',
            '토끼'     => 'https://images.unsplash.com/photo-1585110396000-c9ffd4e4b308?w=400&h=400&fit=crop',
            '곰'       => 'https://images.unsplash.com/photo-1530595467537-0b5996c41f2d?w=400&h=400&fit=crop',
            '사자'     => 'https://images.unsplash.com/photo-1546182990-dffeafbe841d?w=400&h=400&fit=crop',
            '코끼리'   => 'https://images.unsplash.com/photo-1551969014-7d2c4cddf0b6?w=400&h=400&fit=crop',
            '원숭이'   => 'https://images.unsplash.com/photo-1540573133985-87b6da6d54a9?w=400&h=400&fit=crop',
            '돼지'     => 'https://images.unsplash.com/photo-1593179449458-e0d43d512551?w=400&h=400&fit=crop',
            '여우'     => 'https://images.unsplash.com/photo-1474511320723-9a56873867b5?w=400&h=400&fit=crop',
            '개구리'   => 'https://images.unsplash.com/photo-1552410260-0fd9b577afa6?w=400&h=400&fit=crop',
            '나비'     => 'https://images.unsplash.com/photo-1559736530-a02ff7f33fe8?w=400&h=400&fit=crop',
            '펭귄'     => 'https://images.unsplash.com/photo-1551986782-d0169b3f8fa7?w=400&h=400&fit=crop',
            '기린'     => 'https://images.unsplash.com/photo-1547721064-da6cfb341d50?w=400&h=400&fit=crop',
            '악어'     => 'https://images.unsplash.com/photo-1610058851378-a94a4cec6e0a?w=400&h=400&fit=crop',
            '호랑이'   => 'https://images.unsplash.com/photo-1615032951053-af2c040ced92?w=400&h=400&fit=crop',
            '상어'     => 'https://images.unsplash.com/photo-1559157036-d1e903d5fbec?w=400&h=400&fit=crop',
            '돌고래'   => 'https://images.unsplash.com/photo-1607153333879-c174d265f1d2?w=400&h=400&fit=crop',
            '문어'     => 'https://images.unsplash.com/photo-1583249598754-b7a2f59651fb?w=400&h=400&fit=crop',
            '얼룩말'   => 'https://images.unsplash.com/photo-1526095179574-86e545346ae6?w=400&h=400&fit=crop',
            '고슴도치' => 'https://images.unsplash.com/photo-1511655082169-e3c2afa4e2b0?w=400&h=400&fit=crop',
        ];
        foreach ($animalPhotos as $name => $url) {
            DB::table('quiz_questions')
                ->where('game_slug', 'animals')
                ->where('answer', $name)
                ->update(['image_url' => $url, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('quiz_questions')
            ->whereIn('game_slug', ['flag','idiom','wordcard'])
            ->delete();
        DB::table('quiz_questions')
            ->where('game_slug', 'animals')
            ->update(['image_url' => null]);
    }
};
