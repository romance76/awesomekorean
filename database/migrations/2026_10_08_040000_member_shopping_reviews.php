<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 내돈내산 리뷰 — 회원이 자기 Amazon Associates 태그로 직접 쓰는 리뷰.
 * 기존 amazon_products 를 그대로 쓰되 작성자/상태/통계를 붙인다 (관리자 상품은 user_id = NULL, status = published).
 *  - 같은 상품(ASIN)에 여러 회원이 각자 리뷰를 쓸 수 있어 asin 단독 unique 를 (asin, user_id) 로 바꾼다.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('amazon_tag', 40)->nullable()->after('email');   // Amazon Associates 스토어 ID (예: abc-20)
        });

        Schema::table('amazon_products', function (Blueprint $table) {
            $table->dropUnique('amazon_products_asin_unique');
            $table->unsignedBigInteger('user_id')->nullable()->after('id')->index();
            $table->string('status', 12)->default('published')->after('is_active');   // published | pending | hidden | rejected
            $table->unsignedTinyInteger('rating')->nullable()->after('status');
            $table->string('affiliate_tag', 40)->nullable()->after('affiliate_url');    // 이 링크에 쓴 작성자 태그
            $table->unsignedInteger('view_count')->default(0)->after('clicks');
            $table->unsignedInteger('comment_count')->default(0)->after('view_count');
            $table->string('admin_note', 255)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unique(['asin', 'user_id'], 'amazon_products_asin_user_unique');
            $table->index(['status', 'is_active', 'published_at'], 'amazon_products_status_idx');
        });
        DB::table('amazon_products')->whereNull('published_at')->update(['published_at' => DB::raw('created_at')]);

        Schema::create('amazon_product_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('amazon_product_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('viewed_at')->useCurrent();
            $table->index(['amazon_product_id', 'viewed_at']);
        });

        $settings = [
            ['shopping_review_first_approval', '내돈내산 리뷰: 첫 리뷰는 관리자 승인 후 공개 (1=예, 0=아니오)', '1', '회원이 처음 쓰는 리뷰만 승인 대기. 한 번 승인된 회원의 이후 리뷰는 바로 공개돼요.'],
            ['shopping_review_autohide_reports', '내돈내산 리뷰: 신고 몇 명이면 자동으로 내림', '3', '서로 다른 회원이 이만큼 신고하면 리뷰가 임시로 내려가고 관리자 확인을 기다려요. 0=자동 안 내림'],
            ['shopping_review_min_chars', '내돈내산 리뷰: 최소 글자 수', '60', '너무 짧은 리뷰를 막아요.'],
            ['shopping_hot_count', '쇼핑: 이번주 HOT 표시 개수(최대)', '5', '최근 7일 점수(조회 + 클릭×3 + 댓글×5)가 높은 순으로 HOT 아이콘이 붙는 상품 수'],
            ['shopping_hot_min_score', '쇼핑: HOT 최소 점수', '15', '이 점수 이상이어야 HOT 이 붙어요.'],
        ];
        foreach ($settings as [$key, $label, $value, $desc]) {
            if (!DB::table('point_settings')->where('key', $key)->exists()) {
                DB::table('point_settings')->insert([
                    'category' => 'spend', 'key' => $key, 'label' => $label, 'value' => $value,
                    'description' => $desc, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_product_views');
        Schema::table('amazon_products', function (Blueprint $table) {
            $table->dropUnique('amazon_products_asin_user_unique');
            $table->dropIndex('amazon_products_status_idx');
            $table->dropColumn(['user_id', 'status', 'rating', 'affiliate_tag', 'view_count', 'comment_count', 'admin_note', 'published_at']);
        });
        Schema::table('users', function (Blueprint $table) { $table->dropColumn('amazon_tag'); });
    }
};
