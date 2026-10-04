<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 부동산 매물을 외부 API(RealtyAPI)에서 매일 긁어와 채우기 위한 준비.
// user_id는 지금까지 NOT NULL이라 실제 회원이 올린 척 끼워 넣을 수밖에 없었는데,
// 긁어온 매물은 회원 소유가 아니므로 Shorts 테이블과 동일하게 user_id를
// nullable로 바꾸고, source/external_id로 긁어온 것과 회원이 올린 것을 구분한다.
// 30일 지난 scraped 매물만 자동 삭제하고 회원이 올린 매물(source=user)은 절대 건드리지 않는다.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('real_estate_listings', function ($table) {
            $table->dropForeign(['user_id']);
        });

        DB::statement('ALTER TABLE real_estate_listings MODIFY user_id BIGINT UNSIGNED NULL');

        Schema::table('real_estate_listings', function ($table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->string('source', 20)->default('user')->after('user_id'); // user, scraped
            $table->string('external_source', 30)->nullable()->after('source'); // ex) realtyapi
            $table->string('external_id', 100)->nullable()->after('external_source');
            $table->timestamp('scraped_at')->nullable()->after('external_id');
            $table->unique(['external_source', 'external_id']);
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('real_estate_listings', function ($table) {
            $table->dropUnique(['external_source', 'external_id']);
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'external_source', 'external_id', 'scraped_at']);
            $table->dropForeign(['user_id']);
        });

        DB::table('real_estate_listings')->whereNull('user_id')->delete();

        DB::statement('ALTER TABLE real_estate_listings MODIFY user_id BIGINT UNSIGNED NOT NULL');

        Schema::table('real_estate_listings', function ($table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
