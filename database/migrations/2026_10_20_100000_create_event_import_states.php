<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 외부 지역 행사(Ticketmaster) 자동 가져오기 — 주(State)별 켜기/끄기와 마지막 실행 결과.
 * 한인이 많이 사는 30개 주를 기본으로 넣어 둔다(관리자 화면에서 주마다 끄고 켤 수 있음).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_import_states', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('name', 40);
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('last_fetched')->default(0);
            $table->unsignedInteger('last_created')->default(0);
            $table->unsignedInteger('last_updated')->default(0);
            $table->string('last_error', 300)->nullable();
            $table->timestamps();
        });

        // 한인 인구가 많은 순(대략)
        $states = [
            'CA' => '캘리포니아', 'NY' => '뉴욕', 'TX' => '텍사스', 'NJ' => '뉴저지', 'WA' => '워싱턴',
            'IL' => '일리노이', 'VA' => '버지니아', 'GA' => '조지아', 'FL' => '플로리다', 'MD' => '메릴랜드',
            'PA' => '펜실베이니아', 'MA' => '매사추세츠', 'NC' => '노스캐롤라이나', 'HI' => '하와이', 'AZ' => '애리조나',
            'CO' => '콜로라도', 'NV' => '네바다', 'OR' => '오리건', 'MI' => '미시간', 'OH' => '오하이오',
            'MN' => '미네소타', 'CT' => '코네티컷', 'TN' => '테네시', 'SC' => '사우스캐롤라이나', 'AL' => '앨라배마',
            'UT' => '유타', 'IN' => '인디애나', 'MO' => '미주리', 'OK' => '오클라호마', 'KS' => '캔자스',
        ];
        $now = now();
        $i = 0;
        foreach ($states as $code => $name) {
            DB::table('event_import_states')->insert([
                'code' => $code, 'name' => $name, 'enabled' => true, 'sort_order' => ++$i,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // 가져온 행사를 (출처, 출처 번호)로 빨리 찾기 위한 색인
        Schema::table('events', function (Blueprint $table) {
            $table->index(['source', 'source_id'], 'events_source_sourceid_idx');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_source_sourceid_idx');
        });
        Schema::dropIfExists('event_import_states');
    }
};
