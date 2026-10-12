<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 내돈내산 카테고리 정리(비슷한 것은 '/' 로 묶음) — 기존 상품의 옛 이름을 새 이름으로 옮긴다.
// 새 목록은 resources/js/utils/shoppingCategories.js. 지우는 것 없음, 이름만 바꿈.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('amazon_products') || !Schema::hasColumn('amazon_products', 'category')) return;
        $t = fn () => DB::table('amazon_products');

        // 1) 제목으로 확실히 알 수 있는 것 먼저 (비타민·영양제, 정원·공구)
        $t()->where(function ($q) {
            foreach (['%vitamin%', '%VIT D%', '%calcium%', '%supplement%', '%probiotic%', '%omega%', '%multivitamin%'] as $w) $q->orWhere('title', 'LIKE', $w);
        })->whereNotIn('category', ['주방/조리도구', '식품/간식/음료'])->update(['category' => '건강/비타민/영양제']);

        $t()->whereIn('category', ['Home', '가전/전자제품'])->where(function ($q) {
            foreach (['%shed%', '%edging%', '%garden%', '%garage%', '%lawn%', '%generator%', '%drill%', '%tool%'] as $w) $q->orWhere('title', 'LIKE', $w);
        })->update(['category' => '정원/공구/DIY']);

        // 2) 나머지는 이름표대로
        $map = [
            '주방용품' => '주방/조리도구',
            '한국요리 관련 제품' => '한국식품/한식재료',
            '식품' => '식품/간식/음료',
            '생활용품' => '생활용품/청소/세제',
            '가전/전자제품' => '가전/전자/컴퓨터',
            '육아/교육' => '육아/아기용품',
            'Beauty/K-Beauty' => '뷰티/K-Beauty/퍼스널케어',
            'Home' => '가구/인테리어/침구',
            '계절상품' => '계절/명절/선물',
            '선물' => '계절/명절/선물',
        ];
        foreach ($map as $old => $new) $t()->where('category', $old)->update(['category' => $new]);
    }

    public function down(): void
    {
        // 되돌리기는 하지 않음(옛 이름은 화면 목록에서 빠졌음). 필요하면 관리자 화면에서 상품별로 고침.
    }
};
