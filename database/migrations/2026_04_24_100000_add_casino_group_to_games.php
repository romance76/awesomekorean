<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // group_slug 컬럼 추가 - 개별 게임을 상위 허브/대기실로 묶기 위함
        Schema::table('games', function (Blueprint $table) {
            if (!Schema::hasColumn('games', 'group_slug')) {
                $table->string('group_slug', 50)->nullable()->after('category');
                $table->index('group_slug');
            }
        });

        // 1) 카지노 허브 게임 등록 (메인 로비에서 유일한 도박 진입점)
        $casinoExists = DB::table('games')->where('slug', 'casino')->exists();
        if (!$casinoExists) {
            DB::table('games')->insert([
                'slug'        => 'casino',
                'name'        => '🎰 카지노 대기실',
                'description' => '포커 · 홀덤 · 고스톱 · 블랙잭 (베팅형)',
                'icon'        => '🎰',
                'category'    => 'card',
                'group_slug'  => null,
                'path'        => '/games/casino',
                'is_active'   => true,
                'sort_order'  => -1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        // 2) 기존 4개 도박 게임에 group_slug 할당 → 메인 로비에서 숨김 (카지노 대기실에서만 접근)
        DB::table('games')->whereIn('slug', ['poker','holdem','gostop','blackjack'])->update([
            'group_slug' => 'casino',
            'updated_at' => now(),
        ]);

        // 3) 메인 로비에 없던 게임들 보강
        $extras = [
            ['animals',   '동물 이름 퀴즈', '사진 보고 이름 맞추기', '🦌', 'education', '/games/animals'],
            ['idiom',     '사자성어 퀴즈',  '사자성어 학습',         '📜', 'education', '/games/idiom'],
            ['wordcard',  '단어카드',       '영단어 암기',           '📇', 'word',      '/games/wordcard'],
            ['stroop',    '스트룹',         '색깔 vs 글자 집중력',   '🎨', 'brain',     '/games/stroop'],
        ];
        foreach ($extras as $i => $g) {
            $exists = DB::table('games')->where('slug', $g[0])->exists();
            if (!$exists) {
                DB::table('games')->insert([
                    'slug'        => $g[0],
                    'name'        => $g[1],
                    'description' => $g[2],
                    'icon'        => $g[3],
                    'category'    => $g[4],
                    'group_slug'  => null,
                    'path'        => $g[5],
                    'is_active'   => true,
                    'sort_order'  => 100 + $i,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('games')->where('slug', 'casino')->delete();
        DB::table('games')->whereIn('slug', ['poker','holdem','gostop','blackjack'])->update([
            'group_slug' => null,
        ]);
        DB::table('games')->whereIn('slug', ['animals','idiom','wordcard','stroop'])->delete();

        Schema::table('games', function (Blueprint $table) {
            if (Schema::hasColumn('games', 'group_slug')) {
                $table->dropIndex(['group_slug']);
                $table->dropColumn('group_slug');
            }
        });
    }
};
