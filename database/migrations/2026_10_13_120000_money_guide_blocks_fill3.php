<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 머니 가이드 내용 채우기 (3차) — database/data/money_guides/*.json 중 아직 blocks 가 비어 있는 가이드에만 채운다. 관리자가 고친 건 덮어쓰지 않고 공개 여부도 그대로.
return new class extends Migration
{
    public function up(): void
    {
        foreach (glob(database_path('data/money_guides/*.json')) ?: [] as $file) {
            $d = json_decode(file_get_contents($file), true);
            if (!is_array($d) || empty($d['slug']) || empty($d['blocks'])) continue;
            $g = DB::table('money_guides')->where('slug', $d['slug'])->first();
            if (!$g || $g->blocks) continue;
            $upd = ['blocks' => json_encode($d['blocks'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'updated_at' => now()];
            if (!empty($d['summary']) && !$g->summary) $upd['summary'] = $d['summary'];
            DB::table('money_guides')->where('id', $g->id)->update($upd);
        }
    }

    public function down(): void
    {
        // 데이터 보호: 칸을 지우지 않는다.
    }
};
