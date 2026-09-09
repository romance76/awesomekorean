<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task 2 — 게시글/댓글 작성 포인트 정책 변경.
 * - 글 작성 무제한 허용, 글+댓글 합산 하루 N회까지만 포인트 지급 (신규 키 content_earn_daily_max = 3)
 * - post_write 값 5 → 3 (댓글과 동일하게 통일)
 * - post_write_daily_max / comment_write_daily_max 는 더 이상 코드에서 참조하지 않음 (행은 그대로 두어도 무해)
 *
 * UPDATE 문은 존재하는 행에만 적용되므로 재실행해도 안전하고, updateOrInsert 는 중복 키 에러 없이 안전.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('point_settings')) return;

        // 신규 키: 게시글+댓글 합산 하루 포인트 지급 횟수 상한
        DB::table('point_settings')->updateOrInsert(
            ['key' => 'content_earn_daily_max'],
            [
                'category' => 'earn',
                'label' => '글+댓글 합산 일일 포인트 지급 상한',
                'value' => '3',
                'description' => '게시글/댓글 작성 자체는 무제한, 하루 이 횟수(합산)까지만 포인트 지급',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        // 기존 post_write 값 5 → 3 (댓글 작성과 동일하게 통일)
        DB::table('point_settings')
            ->where('key', 'post_write')
            ->update([
                'value' => '3',
                'description' => '하루 최대 3회(글+댓글 합산)까지 지급',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('point_settings')) {
            DB::table('point_settings')->where('key', 'content_earn_daily_max')->delete();
            DB::table('point_settings')->where('key', 'post_write')->update(['value' => '5', 'updated_at' => now()]);
        }
    }
};
