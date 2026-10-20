<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Visit California 는 행사 페이지에 구조화된 행사 정보(JSON-LD)가 없어 지금은 읽을 수 없다(후보 0건).
 * 매주 페이지 160개를 헛되이 열지 않도록 꺼 두고, 나중에 읽는 방법을 추가하면 관리자 화면에서 다시 켠다.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('event_feed_sources')->where('key', 'ca_visit_california')->update([
            'enabled' => false, 'last_error' => '행사 페이지에 JSON-LD 가 없어 지금은 읽을 수 없어요(꺼 둠)', 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('event_feed_sources')->where('key', 'ca_visit_california')->update(['enabled' => true, 'last_error' => null]);
    }
};
