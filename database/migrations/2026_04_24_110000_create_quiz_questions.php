<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->string('game_slug', 50)->index();      // animals / flag / idiom / proverb / satwords / uslife …
            $table->integer('level')->default(1);           // 1~3 레벨 난이도
            $table->string('answer', 100);                  // 정답 텍스트 (선택지 리스트에 자동 섞임)
            $table->text('wrong_answers')->nullable();      // ||| 구분 오답 (없으면 같은 레벨의 다른 문제 정답들로 자동 구성)
            $table->string('image_url', 500)->nullable();   // 이미지 URL (Noto emoji 등)
            $table->string('emoji_hex', 20)->nullable();    // Noto 이모지 hex ex) 1f981
            $table->string('hint', 200)->nullable();        // 힌트/설명
            $table->string('sound', 100)->nullable();       // 이미지 퀴즈의 음성 표현 (강아지=멍멍)
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index(['game_slug', 'level', 'is_active']);
        });

        // 동물 퀴즈 시드 (GameAnimals.vue 에 하드코딩된 데이터를 DB 로 이전)
        $animals = [
            [1, '강아지',  '1f436', '멍멍!'],
            [1, '고양이',  '1f431', '야옹~'],
            [1, '토끼',    '1f430', '(폴짝)'],
            [1, '곰',      '1f43b', '으르렁'],
            [1, '사자',    '1f981', '어흥!'],
            [1, '코끼리',  '1f418', '(나팔 소리)'],
            [1, '원숭이',  '1f412', '끼끼~'],
            [1, '돼지',    '1f437', '꿀꿀~'],
            [2, '여우',    '1f98a', null],
            [2, '개구리',  '1f438', '개굴개굴'],
            [2, '나비',    '1f98b', null],
            [2, '펭귄',    '1f427', null],
            [2, '기린',    '1f992', null],
            [2, '악어',    '1f40a', null],
            [3, '호랑이',  '1f42f', '어흥!'],
            [3, '상어',    '1f988', null],
            [3, '돌고래',  '1f42c', '끼이익~'],
            [3, '문어',    '1f419', null],
            [3, '얼룩말',  '1f993', null],
            [3, '고슴도치', '1f994', null],
        ];
        $rows = [];
        foreach ($animals as $i => $a) {
            $rows[] = [
                'game_slug'  => 'animals',
                'level'      => $a[0],
                'answer'     => $a[1],
                'emoji_hex'  => $a[2],
                'sound'      => $a[3],
                'is_active'  => true,
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('quiz_questions')->insert($rows);

        // 국기 퀴즈 시드 (일부)
        $flags = [
            [1, '대한민국',  '1f1f0-1f1f7'],
            [1, '미국',      '1f1fa-1f1f8'],
            [1, '일본',      '1f1ef-1f1f5'],
            [1, '중국',      '1f1e8-1f1f3'],
            [1, '프랑스',    '1f1eb-1f1f7'],
            [1, '영국',      '1f1ec-1f1e7'],
            [1, '독일',      '1f1e9-1f1ea'],
            [1, '이탈리아',  '1f1ee-1f1f9'],
            [2, '캐나다',    '1f1e8-1f1e6'],
            [2, '호주',      '1f1e6-1f1fa'],
            [2, '브라질',    '1f1e7-1f1f7'],
            [2, '멕시코',    '1f1f2-1f1fd'],
            [2, '스페인',    '1f1ea-1f1f8'],
            [2, '러시아',    '1f1f7-1f1fa'],
            [2, '인도',      '1f1ee-1f1f3'],
            [2, '베트남',    '1f1fb-1f1f3'],
            [3, '태국',      '1f1f9-1f1ed'],
            [3, '네덜란드',  '1f1f3-1f1f1'],
            [3, '아르헨티나','1f1e6-1f1f7'],
            [3, '튀르키예',  '1f1f9-1f1f7'],
        ];
        $flagRows = [];
        foreach ($flags as $i => $f) {
            $flagRows[] = [
                'game_slug'  => 'flag',
                'level'      => $f[0],
                'answer'     => $f[1],
                'emoji_hex'  => $f[2],
                'is_active'  => true,
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('quiz_questions')->insert($flagRows);
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
