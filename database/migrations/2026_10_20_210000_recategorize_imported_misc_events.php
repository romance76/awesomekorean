<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 이미 가져온 Ticketmaster "기타 행사" 분류(Disney On Ice·Harlem Globetrotters 같은 순회 쇼)를 '콘서트·공연'으로 옮긴다.
 * 진짜 축제·페어·박람회(장르: 페어·축제 / 지역 행사 / 박람회·전시)는 '지역 축제·행사' 그대로 두고,
 * 강연·세미나는 '교육·체험'으로 보낸다. 본문 첫 줄 "분류: 기타 행사 · …" 기준.
 */
return new class extends Migration
{
    public function up(): void
    {
        $misc = fn () => DB::table('events')->where('source', 'ticketmaster')->where('content', 'like', '%분류: 기타 행사%');

        // 쇼·가족 공연 등은 콘서트·공연
        $misc()->update(['category' => 'concert']);
        // 진짜 축제·페어·박람회는 지역 축제·행사
        $misc()->where(function ($q) {
            foreach (['%페어·축제%', '%지역 행사%', '%박람회·전시%'] as $like) $q->orWhere('content', 'like', "%분류: 기타 행사 · {$like}");
        })->update(['category' => 'festival']);
        // 강연·세미나는 교육·체험
        $misc()->where('content', 'like', '%Lecture/Seminar%')->update(['category' => 'education']);
        // 제목에 축제·페어·박람회·퍼레이드가 있으면 축제(스포츠 제외)
        DB::table('events')->where('source', 'ticketmaster')->where('category', '!=', 'sports')->where(function ($q) {
            foreach (['%festival%', '% fair %', '% fair', '% expo %', '% expo', '%parade%'] as $like) $q->orWhere('title', 'like', $like);
        })->update(['category' => 'festival']);

        // VIP·호스피탈리티·스위트 상품은 행사가 아니므로 정리(참가자가 없는 것만)
        DB::table('events')->where('source', 'ticketmaster')->where('attendee_count', 0)->where(function ($q) {
            foreach (['%suites -%', '% vip %', '%hospitality%'] as $like) $q->orWhere('title', 'like', $like);
        })->delete();
    }

    public function down(): void
    {
        // 되돌리지 않음(분류를 바로잡는 데이터 보정)
    }
};
