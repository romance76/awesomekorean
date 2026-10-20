<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 이미 가져온 Ticketmaster 행사의 카테고리를 행사 자신의 분류로 바로잡는다.
 * ("기타" 후보 묶음으로 받은 스포츠 경기가 '지역 축제·행사'로 들어갔던 문제)
 * 가져올 때 본문 첫 줄에 "분류: 스포츠 · 농구 · College" 처럼 한글 분류를 적어 두었으므로 그걸 기준으로 한다.
 * 우선순위는 코드(EventImporter::categoryFor)와 같다: 스포츠 > 축제(제목) > 음악·공연 > 기타 행사.
 */
return new class extends Migration
{
    public function up(): void
    {
        $base = fn () => DB::table('events')->where('source', 'ticketmaster');

        // 1) 분류(대분류)로 기본값
        $base()->where('content', 'like', '%분류: 음악%')->update(['category' => 'concert']);
        $base()->where('content', 'like', '%분류: 공연·예술%')->update(['category' => 'concert']);
        $base()->where('content', 'like', '%분류: 영화%')->update(['category' => 'concert']);
        $base()->where('content', 'like', '%분류: 기타 행사%')->update(['category' => 'festival']);

        // 2) 제목에 축제·페어·박람회·퍼레이드가 있으면 축제
        $base()->where(function ($q) {
            foreach (['%festival%', '% fair %', '% fair', '% expo %', '% expo', '%parade%'] as $like) $q->orWhere('title', 'like', $like);
        })->update(['category' => 'festival']);

        // 3) 스포츠는 마지막에(가장 우선): 대분류가 스포츠이거나 장르가 경기 종목
        $base()->where(function ($q) {
            $q->where('content', 'like', '%분류: 스포츠%');
            foreach (['농구', '야구', '풋볼', '하키', '축구', '레슬링', '모터스포츠', '골프', '테니스', '아이스 스케이팅'] as $g) {
                $q->orWhere('content', 'like', "%분류: %· {$g}%");
            }
        })->update(['category' => 'sports']);
    }

    public function down(): void
    {
        // 되돌리지 않음(분류를 바로잡는 데이터 보정)
    }
};
