<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('name_en', 100)->nullable()->after('name');
            $table->string('description_en', 200)->nullable()->after('description');
        });

        $translations = [
            'memory'       => ['Memory', 'Card Matching'],
            '2048'         => ['2048', 'Number Puzzle'],
            'omok'         => ['Omok', '5-in-a-Row'],
            'puzzle'       => ['Puzzle', 'Piece Matching'],
            'bingo'        => ['Bingo', 'Bingo Game'],
            'speedcalc'    => ['Quick Math', 'Mental Math Training'],
            'seniormemory' => ['Senior Memory', 'Memory Training'],
            'stroop'       => ['Stroop', 'Color vs Word Focus'],
            'snake'        => ['Snake', 'Grow the Snake'],
            'towerdefense' => ['Tower Defense', 'Math Castle Defense'],
            'slots'        => ['Slot Machine', 'Lucky Slots'],
            'stocksim'     => ['Stock Sim', 'Mock Investing'],
            'wordle'       => ['Wordle', '5-Letter Word'],
            'wordchain'    => ['Word Chain', 'Word Chain Game'],
            'wordblank'    => ['Fill in the Blank', 'Complete the Word'],
            'spelling'     => ['Spelling', 'English Words'],
            'typing'       => ['Typing', 'Typing Practice'],
            'wordcard'     => ['Word Cards', 'English Vocabulary'],
            'hangul'       => ['Hangul', 'Learn Hangul'],
            'counting'     => ['Counting', 'Early Math'],
            'colors'       => ['Colors', 'Learn Colors'],
            'shapes'       => ['Shapes', 'Learn Shapes'],
            'satwords'     => ['SAT Words', 'SAT Prep'],
            'proverb'      => ['Proverb Quiz', 'Korean Proverbs'],
            'flag'         => ['Flag Quiz', 'World Flags'],
            'uslife'       => ['US Life', 'Citizenship Quiz'],
            'animals'      => ['Animal Quiz', 'Name That Animal'],
            'idiom'        => ['Idiom Quiz', 'Four-Character Idioms'],
            'casino'       => ['🎰 Casino Lounge', 'Poker · Hold\'em · Go-Stop · Blackjack'],
        ];

        foreach ($translations as $slug => [$nameEn, $descEn]) {
            DB::table('games')->where('slug', $slug)->update([
                'name_en'        => $nameEn,
                'description_en' => $descEn,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'description_en']);
        });
    }
};
