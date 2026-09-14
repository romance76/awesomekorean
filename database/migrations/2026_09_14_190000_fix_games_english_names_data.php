<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 이전 마이그레이션(add_english_names_to_games)이 '2048' 같은 연관배열 키의
// PHP 자동 int 캐스팅 버그로 memory 하나만 채우고 중단됐을 가능성에 대비해,
// 동일한 데이터를 안전한 튜플 방식으로 다시 채워 넣는 안전망 마이그레이션.
// 이미 값이 채워져 있어도 같은 값으로 덮어쓸 뿐이라 재실행해도 안전함.
return new class extends Migration {
    public function up(): void
    {
        $translations = [
            ['memory', 'Memory', 'Card Matching'],
            ['2048', '2048', 'Number Puzzle'],
            ['omok', 'Omok', '5-in-a-Row'],
            ['puzzle', 'Puzzle', 'Piece Matching'],
            ['bingo', 'Bingo', 'Bingo Game'],
            ['speedcalc', 'Quick Math', 'Mental Math Training'],
            ['seniormemory', 'Senior Memory', 'Memory Training'],
            ['stroop', 'Stroop', 'Color vs Word Focus'],
            ['snake', 'Snake', 'Grow the Snake'],
            ['towerdefense', 'Tower Defense', 'Math Castle Defense'],
            ['slots', 'Slot Machine', 'Lucky Slots'],
            ['stocksim', 'Stock Sim', 'Mock Investing'],
            ['wordle', 'Wordle', '5-Letter Word'],
            ['wordchain', 'Word Chain', 'Word Chain Game'],
            ['wordblank', 'Fill in the Blank', 'Complete the Word'],
            ['spelling', 'Spelling', 'English Words'],
            ['typing', 'Typing', 'Typing Practice'],
            ['wordcard', 'Word Cards', 'English Vocabulary'],
            ['hangul', 'Hangul', 'Learn Hangul'],
            ['counting', 'Counting', 'Early Math'],
            ['colors', 'Colors', 'Learn Colors'],
            ['shapes', 'Shapes', 'Learn Shapes'],
            ['satwords', 'SAT Words', 'SAT Prep'],
            ['proverb', 'Proverb Quiz', 'Korean Proverbs'],
            ['flag', 'Flag Quiz', 'World Flags'],
            ['uslife', 'US Life', 'Citizenship Quiz'],
            ['animals', 'Animal Quiz', 'Name That Animal'],
            ['idiom', 'Idiom Quiz', 'Four-Character Idioms'],
            ['casino', '🎰 Casino Lounge', 'Poker · Hold\'em · Go-Stop · Blackjack'],
        ];

        foreach ($translations as [$slug, $nameEn, $descEn]) {
            DB::table('games')->where('slug', $slug)->update([
                'name_en'        => $nameEn,
                'description_en' => $descEn,
            ]);
        }
    }

    public function down(): void
    {
        // 데이터 채우기 전용이라 되돌릴 필요 없음
    }
};
