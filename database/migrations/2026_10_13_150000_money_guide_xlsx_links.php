<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 머니 가이드의 내려받기 파일을 CSV → 엑셀(.xlsx) 로 교체 (2026-10-13).
// CSV 는 한글이 깨지고 수식이 어긋난 곳이 있어, 수식이 검증된 xlsx 로 바꿨다. 가이드 본문(blocks)의 링크 글자만 바꾸고 나머지는 그대로 둔다.
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('money_guides')->whereNotNull('blocks')->get(['id', 'blocks']);
        foreach ($rows as $r) {
            $new = preg_replace('#((?:\\\\)?/downloads(?:\\\\)?/money(?:\\\\)?/[\w-]+)\.csv#', '$1.xlsx', $r->blocks);
            if ($new === null || $new === $r->blocks) continue;
            // 같은 항목의 표시 글자(CSV → 엑셀)도 함께: 파일이 xlsx 인 항목 바로 뒤의 kind 만 바꾼다
            $new = preg_replace('#(\.xlsx"\s*,\s*"kind"\s*:\s*)"CSV"#', '$1"엑셀"', $new) ?? $new;
            DB::table('money_guides')->where('id', $r->id)->update(['blocks' => $new, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // 데이터 보호: 되돌리지 않는다.
    }
};
