<?php

use App\Support\PointRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 내돈내산 리뷰: 직접 찍은 사진 최소 장수 제한 해제 (Kay 2026-10-12). 관리자 설정에서 다시 올릴 수 있다.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('point_settings')->where('key', 'shopping_review_min_photos')->update(['value' => '0']);
        PointRules::flush();
    }

    public function down(): void {}
};
