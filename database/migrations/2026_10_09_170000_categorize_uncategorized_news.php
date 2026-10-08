<?php

use App\Models\NewsCategory;
use App\Support\NewsCategorizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** 분류가 없던 외부 언론사 기사에 분류를 붙인다 (목록 태그가 전부 "뉴스"로만 보이던 문제). 여러 번 실행해도 안전. */
return new class extends Migration {
    public function up(): void
    {
        if (NewsCategory::count() === 0) return;
        DB::table('news')->whereNull('category_id')->orderBy('id')->chunkById(300, function ($rows) {
            foreach ($rows as $r) {
                $id = NewsCategorizer::categoryId((string) $r->title, (string) $r->summary);
                if ($id) DB::table('news')->where('id', $r->id)->update(['category_id' => $id]);
            }
        });
    }

    public function down(): void {}
};
