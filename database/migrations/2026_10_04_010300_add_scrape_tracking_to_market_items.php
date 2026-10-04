<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 중고장터 더미 데이터를 eBay Browse API에서 가져온 실제 매물로 대체하기 위한 준비.
// real_estate_listings에 적용했던 것과 동일한 패턴: user_id를 nullable로 바꾸고
// (shorts 테이블과 동일), source/external_id로 긁어온 것과 회원이 올린 것을 구분한다.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_items', function ($table) {
            $table->dropForeign(['user_id']);
        });

        DB::statement('ALTER TABLE market_items MODIFY user_id BIGINT UNSIGNED NULL');

        Schema::table('market_items', function ($table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->string('source', 20)->default('user')->after('user_id'); // user, scraped
            $table->string('external_source', 30)->nullable()->after('source'); // ex) ebay
            $table->string('external_id', 100)->nullable()->after('external_source');
            $table->timestamp('scraped_at')->nullable()->after('external_id');
            $table->unique(['external_source', 'external_id']);
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('market_items', function ($table) {
            $table->dropUnique(['external_source', 'external_id']);
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'external_source', 'external_id', 'scraped_at']);
            $table->dropForeign(['user_id']);
        });

        DB::table('market_items')->whereNull('user_id')->delete();

        DB::statement('ALTER TABLE market_items MODIFY user_id BIGINT UNSIGNED NOT NULL');

        Schema::table('market_items', function ($table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
