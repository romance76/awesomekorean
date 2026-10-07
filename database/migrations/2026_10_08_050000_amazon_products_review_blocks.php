<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 회원 리뷰 본문을 "글 + 사진" 블록 목록으로 저장 (글 사이사이에 직접 찍은 사진을 넣기 위함)
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('amazon_products', 'review_blocks')) {
            Schema::table('amazon_products', function (Blueprint $t) {
                $t->json('review_blocks')->nullable()->after('our_description');
            });
        }

        if (!DB::table('point_settings')->where('key', 'shopping_review_min_photos')->exists()) {
            DB::table('point_settings')->insert([
                'category' => 'spend', 'key' => 'shopping_review_min_photos', 'label' => '내돈내산 리뷰: 직접 찍은 사진 최소 장수', 'value' => '2',
                'description' => '리뷰 글 사이에 넣어야 하는 직접 찍은 사진의 최소 장수.', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('amazon_products', 'review_blocks')) {
            Schema::table('amazon_products', function (Blueprint $t) { $t->dropColumn('review_blocks'); });
        }
    }
};
