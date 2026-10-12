<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 내돈내산 목록에 보이던 관리자 테스트 글("ASDd") 숨김 — 지우지 않고 status 만 바꾼다
return new class extends Migration
{
    public function up(): void
    {
        DB::table('amazon_products')->where('title', 'ASDd')->where('our_description', 'like', 'ASDdWF%')
            ->update(['status' => 'hidden', 'is_active' => false]);
    }

    public function down(): void {}
};
