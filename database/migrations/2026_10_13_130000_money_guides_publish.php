<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 머니 가이드 41편 공개 (Kay 지시 2026-10-12). database/data/money_guides/*.json 에 있는 가이드 중
// 내용(blocks)이 채워진 것만 공개로 바꾼다. 관리자 › MONEY 관리에서 언제든 다시 비공개로 돌릴 수 있다.
return new class extends Migration
{
    public function up(): void
    {
        $slugs = [];
        foreach (glob(database_path('data/money_guides/*.json')) ?: [] as $file) {
            $d = json_decode(file_get_contents($file), true);
            if (is_array($d) && !empty($d['slug'])) $slugs[] = $d['slug'];
        }
        if (!$slugs) return;
        DB::table('money_guides')->whereIn('slug', $slugs)->whereNotNull('blocks')
            ->update(['is_published' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // 데이터 보호: 되돌리지 않는다 (관리자 화면에서 비공개로 바꿀 수 있음).
    }
};
