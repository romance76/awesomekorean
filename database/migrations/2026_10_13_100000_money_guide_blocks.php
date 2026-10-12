<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 머니 가이드를 '글자만 쭉'에서 히어로·요약·도표·그래프·계산기·링크·다운로드 블록으로.
// - money_guides.blocks (JSON, 비어 있으면 예전처럼 content 를 보여 줌)
// - database/data/money_guides/*.json 의 내용을 비어 있는 가이드에만 채움 (관리자가 고친 건 덮어쓰지 않음, 공개 여부도 그대로)
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('money_guides', 'blocks')) {
            Schema::table('money_guides', function (Blueprint $t) {
                $t->json('blocks')->nullable()->after('content');
            });
        }
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
