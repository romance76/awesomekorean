<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 머니 가이드 41편 내용 갱신 (2026-10-13): 2026년 숫자(HOPE·Pell·Medicare·세금 공제 등)를 본문에 직접 넣고,
// 길고 되풀이되던 안내문을 짧게 바꿨다. 기준은 database/data/money_guides/*.json.
// 관리자가 화면에서 고친 가이드가 있어도 이번 한 번은 JSON 내용으로 덮어쓴다(공개 여부·요약 글은 그대로).
return new class extends Migration
{
    public function up(): void
    {
        foreach (glob(database_path('data/money_guides/*.json')) ?: [] as $file) {
            $d = json_decode(file_get_contents($file), true);
            if (!is_array($d) || empty($d['slug']) || empty($d['blocks'])) continue;
            DB::table('money_guides')->where('slug', $d['slug'])->update([
                'blocks' => json_encode($d['blocks'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // 데이터 보호: 되돌리지 않는다.
    }
};
