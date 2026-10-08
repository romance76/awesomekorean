<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 뉴스 제목/요약/본문에 &apos; 같은 HTML 코드가 그대로 저장돼 화면에 보이던 문제 정리.
 * (수집기가 &apos; 를 풀지 못했음 — 수집기도 함께 고침.) 여러 번 실행해도 안전.
 */
return new class extends Migration {
    public function up(): void
    {
        $dec = fn (?string $v) => $v === null ? null : html_entity_decode(html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        DB::table('news')
            ->where(function ($q) {
                foreach (['title', 'summary', 'content'] as $col) {
                    $q->orWhere($col, 'like', '%&%;%');
                }
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($dec) {
                foreach ($rows as $r) {
                    $new = ['title' => $dec($r->title), 'summary' => $dec($r->summary), 'content' => $dec($r->content)];
                    $changed = array_filter($new, fn ($v, $k) => $v !== $r->$k, ARRAY_FILTER_USE_BOTH);
                    if ($changed) DB::table('news')->where('id', $r->id)->update($changed);
                }
            });
    }

    public function down(): void
    {
        // 되돌릴 수 없음 (원래 값을 보관하지 않음)
    }
};
