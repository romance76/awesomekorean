<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 정식 오픈 전에 개발용 시더(SeedClubs)로 만들었던 테스트 동호회를 지운다.
 * 이름이 시더 목록과 정확히 일치하는 동호회만 지우므로, 회원이 직접 만든 동호회는 건드리지 않는다.
 * 게시글/게시판/멤버/북마크도 함께 정리한다.
 */
return new class extends Migration
{
    private const NAMES = [
        '아틀란타 등산 모임', 'LA 한인 골프 클럽', '뉴욕 독서 모임 "책갈피"', '한인 요리 연구회', '시카고 테니스 동호회',
        '사진 동호회 "찰칵"', '달라스 볼링 모임', '한인 러닝 크루 ATL', '게임 동호회 "파티원 모집"', '휴스턴 한인 낚시 동호회',
        '워싱턴 DC 영화 감상 모임', '애난데일 한인 자전거 클럽', '한인 뜨개질 모임 "따뜻한 손"', '필라델피아 한인 축구 모임',
        '시애틀 한인 하이킹 클럽', '한인 투자 스터디 그룹', '귀넷 한인 육아 모임', '한인 음악 밴드 "코리안 웨이브"',
        '덴버 한인 스키/보드 클럽', '한인 보드게임 모임',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('clubs')) return;

        $ids = DB::table('clubs')->whereIn('name', self::NAMES)->pluck('id')->all();
        if (!$ids) return;

        DB::transaction(function () use ($ids) {
            foreach (['club_posts', 'club_boards', 'club_members'] as $t) {
                if (Schema::hasTable($t)) DB::table($t)->whereIn('club_id', $ids)->delete();
            }
            if (Schema::hasTable('bookmarks')) {
                DB::table('bookmarks')->where('bookmarkable_type', 'App\\Models\\Club')->whereIn('bookmarkable_id', $ids)->delete();
            }
            DB::table('clubs')->whereIn('id', $ids)->delete();
        });
    }

    public function down(): void
    {
        // 지운 테스트 데이터는 되돌리지 않는다.
    }
};
