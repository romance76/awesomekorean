<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// "민박"이 렌트(type=rent) 하위 카테고리와 룸메이트(type=roommate) 상단 메뉴에
// 중복으로 존재해 혼란스럽다는 피드백으로, 민박을 룸메이트 쪽 하나로 통합한다
// (프런트엔드 카테고리 목록은 별도 커밋에서 정리). 기존에 type=rent로 올라간
// 민박 매물도 새 분류를 따르도록 함께 옮겨준다.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('real_estate_listings')
            ->where('type', 'rent')
            ->where('property_type', 'minbak')
            ->update(['type' => 'roommate']);
    }

    public function down(): void
    {
        DB::table('real_estate_listings')
            ->where('type', 'roommate')
            ->where('property_type', 'minbak')
            ->update(['type' => 'rent']);
    }
};
